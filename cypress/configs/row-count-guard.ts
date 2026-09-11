/**
 * Test-database drift guard (#9769).
 *
 * Registers a read-only `rowCounts` Cypress task. `cypress/support/e2e.js`
 * calls it once before and once after every spec file and fails the run if a
 * spec left rows behind.
 *
 * The counts are read straight from MySQL rather than through the HTTP API
 * because there is no endpoint that reports a row count for note_nte,
 * event_attend or calendar_events. mysql2 is already a devDependency
 * (locale/scripts/locale-build-db.js uses it) and every compose profile
 * publishes the database port on the host, so the Cypress node process can
 * reach it.
 *
 * The guard is fail-soft about *connecting*: if the database cannot be
 * reached it disables itself with a single warning instead of failing specs.
 * That keeps it inert in environments where the port is not published or is a
 * different one (the CI subdir jobs publish 3307 — set ROW_GUARD_DB_PORT to
 * enable it there). It is fail-hard about *drift*: once connected, any
 * unexplained growth fails the spec file that caused it.
 */

const fs = require('fs');
const path = require('path');

/**
 * Tables a spec must leave exactly as it found them. These are the tables
 * #9769 observed drifting; each one is cheap to COUNT(*).
 *
 * note_nte is counted but only reported, never failed — see SOFT_TABLES.
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
 * Tables whose growth is reported but never fails a spec.
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

  const connectionOptions = {
    host: process.env.ROW_GUARD_DB_HOST || '127.0.0.1',
    port: Number(process.env.ROW_GUARD_DB_PORT || process.env.DATABASE_PORT || 3306),
    user: process.env.MYSQL_USER || dockerEnv.MYSQL_USER || 'churchcrm',
    password: process.env.MYSQL_PASSWORD || dockerEnv.MYSQL_PASSWORD || 'changeme',
    database: process.env.MYSQL_DATABASE || dockerEnv.MYSQL_DATABASE || 'churchcrm',
    connectTimeout: 5000,
  };

  // null = not tried yet, false = tried and unavailable (stay disabled).
  let pool: any = null;
  let disabledReason: string | null = null;

  const getPool = () => {
    if (pool === null && disabledReason === null) {
      try {
        // Required lazily so a checkout without node_modules/mysql2 still runs.
        const mysql = require('mysql2/promise');
        pool = mysql.createPool({ ...connectionOptions, connectionLimit: 1 });
      } catch (err: any) {
        disabledReason = `mysql2 is not installed (${err.message})`;
      }
    }
    return pool;
  };

  on('task', {
    /**
     * SELECT COUNT(*) for each guarded table.
     *
     * @returns {{available: true, counts: Record<string, number>, softTables: string[]}}
     *        | {{available: false, reason: string}}
     */
    async rowCounts() {
      if (disabledReason !== null) {
        return { available: false, reason: disabledReason };
      }

      const activePool = getPool();
      if (!activePool) {
        return { available: false, reason: disabledReason };
      }

      try {
        const counts: Record<string, number> = {};
        for (const table of GUARDED_TABLES) {
          const [rows] = await activePool.query(`SELECT COUNT(*) AS c FROM \`${table}\``);
          counts[table] = Number(rows[0].c);
        }
        return { available: true, counts, softTables: SOFT_TABLES };
      } catch (err: any) {
        disabledReason =
          `cannot read row counts from ${connectionOptions.host}:${connectionOptions.port}` +
          `/${connectionOptions.database} (${err.message}) — set ROW_GUARD_DB_PORT to point at` +
          ' the database this run is using';
        pool = false;
        return { available: false, reason: disabledReason };
      }
    },
  });

  // Tell the support file the task exists. Configs that do not call this
  // (new-system, locale, upgrade) leave the guard switched off, which is what
  // we want for suites that deliberately reseed or import demo data.
  config.env = { ...config.env, rowCountGuard: true };

  return config;
}
