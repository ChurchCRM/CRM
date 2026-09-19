/**
 * Test-database drift guard (#9769).
 *
 * Registers a read-only `rowCounts` Cypress task. `cypress/support/e2e.js`
 * calls it once before and once after every spec file and fails the spec file
 * if it left rows behind, or removed rows it did not create.
 *
 * The counts are read straight from MySQL rather than through the HTTP API
 * because there is no endpoint that reports a row count for note_nte,
 * event_attend or calendar_events. mysql2 is already a devDependency
 * (locale/scripts/locale-build-db.js uses it) and every compose profile
 * publishes the database port on the host, so the Cypress node process can
 * reach it.
 *
 * Which port that is depends on the compose profile:
 *
 *   docker-compose.yaml  (npm run docker:test:*)   DATABASE_PORT          3306
 *   docker-compose.parallel.yaml --profile ci-root DATABASE_PORT          3306
 *   docker-compose.parallel.yaml --profile ci-subdir DATABASE_SUBDIR_PORT 3307
 *
 * so every CI job sets ROW_GUARD_DB_PORT to the port its database is actually
 * published on (see .github/workflows/build-test-package.yml and
 * build-test-nightly.yml). Locally DATABASE_PORT is honoured as a fallback
 * because that is the variable the compose files themselves read.
 *
 * The guard is fail-hard by default: a snapshot that cannot be taken fails the
 * spec with a connection error naming host, port and database, because a guard
 * that silently switches itself off proves nothing. Two environment variables
 * change that:
 *
 *   ROW_GUARD_DISABLED=1  explicit local opt-out — the guard is not armed at
 *                         all (printed once when the config loads)
 *   ROW_GUARD_REQUIRED=1  set by every CI job — makes the opt-out ineffective
 *                         so a CI run can never accidentally run unguarded
 *
 * What the guard can and cannot see: it compares COUNT(*) per table, so it
 * catches growth (a leaked row) and shrinkage (a seeded row deleted), but not
 * a leaked row that replaces a deleted seeded row in the same spec — the count
 * is unchanged. That gap is covered from the other side: the cy.cleanup*()
 * helpers in cypress/support/api-commands.js re-read every id they delete and
 * fail unless the record is gone, and specs only hand them ids they created.
 */

const fs = require('fs');
const path = require('path');

/**
 * Tables a spec must leave exactly as it found them. These are the tables
 * #9769 observed drifting; each one is cheap to COUNT(*).
 *
 * note_nte is counted but its growth is only reported — see SOFT_TABLES.
 */
export const GUARDED_TABLES = [
  'events_event',
  'note_nte',
  'event_attend',
  'calendar_events',
  'person_per',
  'family_fam',
];

/**
 * Tables whose *growth* is reported but never fails a spec. Shrinkage still
 * fails: nothing in the application removes audit rows, so a lower count can
 * only mean a spec deleted seeded notes (or a seeded person or family, whose
 * notes go with them).
 *
 * note_nte is an append-only audit log that the *application* writes to on
 * almost every person or family write it is asked to perform: editing a person
 * adds an "Updated" timeline note, uploading a photo adds one, checking someone
 * in or out adds one, and even DELETE /api/note/{id} adds a `delete-note` row
 * in place of the note it removed. None of that is test litter and no endpoint
 * removes it, so a spec cannot restore the count without deleting the person
 * the note hangs off. Failing on it would mean an allowance in most of the
 * suite, which is noise rather than a signal.
 */
export const SOFT_TABLES = ['note_nte'];

const isTruthy = (value: string | undefined): boolean =>
  value !== undefined && ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());

/** Parse docker/.env for the compose database credentials. */
function readDockerEnv(projectRoot: string): Record<string, string> {
  const envPath = path.join(projectRoot, 'docker', '.env');
  const parsed: Record<string, string> = {};
  try {
    for (const line of fs.readFileSync(envPath, 'utf8').split('\n')) {
      const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
      if (match) {
        parsed[match[1]] = match[2];
      }
    }
  } catch (err) {
    // No docker/.env (e.g. a non-docker checkout) — fall back to env vars.
  }
  return parsed;
}

export function registerRowCountGuard(on: any, config: any) {
  const projectRoot = config?.projectRoot || process.cwd();
  const dockerEnv = readDockerEnv(projectRoot);

  const required = isTruthy(process.env.ROW_GUARD_REQUIRED);
  const disabled = isTruthy(process.env.ROW_GUARD_DISABLED);

  const connectionOptions = {
    host: process.env.ROW_GUARD_DB_HOST || '127.0.0.1',
    port: Number(process.env.ROW_GUARD_DB_PORT || process.env.DATABASE_PORT || 3306),
    user: process.env.MYSQL_USER || dockerEnv.MYSQL_USER || 'churchcrm',
    password: process.env.MYSQL_PASSWORD || dockerEnv.MYSQL_PASSWORD || 'changeme',
    database: process.env.MYSQL_DATABASE || dockerEnv.MYSQL_DATABASE || 'churchcrm',
    connectTimeout: 5000,
  };
  const target = `${connectionOptions.host}:${connectionOptions.port}/${connectionOptions.database}`;

  const hint =
    'The guard reads COUNT(*) over the published MySQL port. Set ROW_GUARD_DB_PORT ' +
    '(and ROW_GUARD_DB_HOST if the database is not on 127.0.0.1) to the port this ' +
    "run's database is published on; a local run may set ROW_GUARD_DISABLED=1 to " +
    'switch the guard off (CI sets ROW_GUARD_REQUIRED=1, which ignores the opt-out).';

  let pool: any = null;
  let poolError: string | null = null;

  const getPool = () => {
    if (pool === null && poolError === null) {
      try {
        // Required lazily so a checkout without node_modules/mysql2 still loads
        // the config; the task then reports the problem instead.
        const mysql = require('mysql2/promise');
        pool = mysql.createPool({ ...connectionOptions, connectionLimit: 1 });
      } catch (err: any) {
        poolError = `mysql2 is not installed (${err.message})`;
      }
    }
    return pool;
  };

  on('task', {
    /**
     * SELECT COUNT(*) for each guarded table.
     *
     * @returns {{available: true, counts: Record<string, number>, softTables: string[]}}
     *        | {{available: false, reason: string, hint: string}}
     */
    async rowCounts() {
      const activePool = getPool();
      if (!activePool) {
        return { available: false, reason: poolError, hint };
      }

      try {
        const counts: Record<string, number> = {};
        for (const table of GUARDED_TABLES) {
          const [rows] = await activePool.query(`SELECT COUNT(*) AS c FROM \`${table}\``);
          counts[table] = Number(rows[0].c);
        }
        return { available: true, counts, softTables: SOFT_TABLES };
      } catch (err: any) {
        // Not sticky: the pool reconnects on the next call, so a database that
        // comes back is picked up again by the next spec's opening snapshot.
        return {
          available: false,
          reason: `cannot read row counts from ${target} (${err.message})`,
          hint,
        };
      }
    },

    /**
     * Print a guard message to the terminal. Cypress.log() only reaches the
     * command log, which nobody sees in a headless CI run; this is what makes
     * the soft-table report visible in the job output.
     */
    rowGuardLog(message: string) {
      console.log(`[row-count-guard] ${message}`);
      return null;
    },
  });

  // Tell the support file whether the guard is armed. Configs that do not call
  // registerRowCountGuard() at all (new-system, locale, upgrade) leave it
  // switched off, which is what we want for suites that deliberately reseed or
  // import demo data.
  let armed = true;
  if (disabled && required) {
    console.warn(
      '[row-count-guard] ROW_GUARD_DISABLED is ignored because ROW_GUARD_REQUIRED is set — the guard stays armed.',
    );
  } else if (disabled) {
    armed = false;
    console.warn('[row-count-guard] ROW_GUARD_DISABLED is set — the test-database drift guard (#9769) is OFF for this run.');
  }
  config.env = { ...config.env, rowCountGuard: armed };

  return config;
}
