// Shared node event setup used by multiple Cypress configs

/**
 * Node-side database tasks.
 *
 * Cypress runs specs in a browser, so a spec cannot open a MySQL socket itself.
 * Schema-level guarantees — UNIQUE keys, foreign keys and their ON DELETE
 * rules, enum domains — have no HTTP surface to assert against, so #9705 (the
 * Volunteer v2 schema) needs a way to talk to the database directly.
 *
 * `mysql2` is already a devDependency (it is pulled in by the tooling), so this
 * adds no dependency. The task deliberately **returns** driver errors instead of
 * throwing: a rejected task fails the whole test, and asserting that a write is
 * rejected (ER_DUP_ENTRY, ER_NO_REFERENCED_ROW_2, ...) is the entire point.
 *
 * Connection defaults mirror docker/.env, which CI exports as real environment
 * variables; DATABASE_PORT lets an isolated stack publish MySQL on another port.
 */
export const dbTasks = {
  async 'db:query'({ sql, params }: { sql: string; params?: unknown[] }) {
    const mysql = require('mysql2/promise');

    // A connection failure (DB down, wrong port or credentials) deliberately
    // THROWS rather than being returned as data: it is an infrastructure fault
    // that must fail the run loudly, not something a spec should assert on.
    // Only driver errors from the statement itself are returned below.
    //
    // The port must match the stack the specs run against: 3306 for the
    // default and ci-root stacks, DATABASE_SUBDIR_PORT (3307) for ci-subdir —
    // the workflows export DATABASE_PORT accordingly, and an isolated local
    // stack on another port must export it too.
    const port = Number(process.env.DATABASE_PORT || 3306);
    const connection = await mysql.createConnection({
      host: process.env.DATABASE_HOST || '127.0.0.1',
      port,
      user: process.env.MYSQL_USER || 'churchcrm',
      password: process.env.MYSQL_PASSWORD || 'changeme',
      database: process.env.MYSQL_DATABASE || 'churchcrm',
      multipleStatements: false,
      dateStrings: true
    });

    try {
      const [rows] = await connection.query(sql, params || []);
      return { rows, error: null };
    } catch (err: any) {
      return {
        rows: null,
        error: {
          code: err.code || null,
          errno: err.errno || null,
          sqlState: err.sqlState || null,
          message: err.message || String(err)
        }
      };
    } finally {
      // A rejected await inside finally would replace the value returned
      // above, so never let end() reject; destroy() cannot.
      try {
        await connection.end();
      } catch {
        connection.destroy();
      }
    }
  }
};

export function setupCommonNodeEvents(on: any, config: any) {
  // cypress-terminal-report logs printer for CI debugging
  try {
    const installLogsPrinter = require('cypress-terminal-report/src/installLogsPrinter');
    installLogsPrinter(on, {
      outputRoot: 'cypress/logs',
      outputTarget: {
        'cypress-terminal-report.txt': 'txt',
        'cypress-terminal-report.json': 'json'
      },
      printLogsToConsole: 'onFail',
      printLogsToFile: 'always'
    });
  } catch (err) {
    // ignore optional logging integration errors in local environments
  }

  // Every task must be registered in a single on('task', ...) call — a second
  // registration replaces the first rather than merging with it.
  const tasks: Record<string, any> = { ...dbTasks };

  // Register download verification tasks if available
  try {
    const { verifyDownloadTasks } = require('cy-verify-downloads');
    Object.assign(tasks, verifyDownloadTasks);
  } catch (err) {
    // optional dependency may be missing in some environments
  }

  on('task', tasks);

  // Common browser launch options
  on('before:browser:launch', (browser: any, launchOptions: any) => {
    if (browser.name === 'chrome') {
      launchOptions.args.push('--disable-dev-shm-usage');
    }
    return launchOptions;
  });

  return config;
}
