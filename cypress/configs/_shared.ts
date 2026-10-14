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
      try {
        await connection.end();
      } catch {
        connection.destroy();
      }
    }
  }
};

/**
 * Read every src/**\/.htaccess on the runner.
 *
 * Cypress starts with cwd = cypress/configs, so walk up until src/.htaccess
 * is found. The CI app directory is bind-mounted, so these are the files the
 * setup wizard wrote. Vendor is skipped. Used by the new-system install spec.
 */
export const htaccessTasks = {
  'htaccess:scan'() {
    const fs = require('fs');
    const path = require('path');

    let root = process.cwd();
    for (let i = 0; i < 6; i++) {
      if (fs.existsSync(path.join(root, 'src', '.htaccess'))) {
        break;
      }
      const parent = path.dirname(root);
      if (parent === root) {
        break;
      }
      root = parent;
    }

    const src = path.join(root, 'src');
    if (!fs.existsSync(src)) {
      throw new Error('htaccess:scan could not find src/ from ' + process.cwd());
    }

    const found: { rel: string; hasRewriteEngine: boolean; rewriteBases: string[] }[] = [];

    const walk = (dir: string) => {
      for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.name === 'vendor' || entry.name === 'node_modules') {
          continue;
        }
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          walk(full);
          continue;
        }
        if (entry.name !== '.htaccess') {
          continue;
        }
        const rel = path.relative(root, full).split(path.sep).join('/');
        if (!rel.startsWith('src/') || rel.split('/').includes('..')) {
          continue;
        }
        const content = fs.readFileSync(full, 'utf8');
        const rewriteBases: string[] = [];
        const re = /^\s*RewriteBase\s+(\S+)\s*$/gm;
        let match: RegExpExecArray | null;
        while ((match = re.exec(content)) !== null) {
          rewriteBases.push(match[1]);
        }
        found.push({
          rel,
          hasRewriteEngine: /RewriteEngine\s+On/i.test(content),
          rewriteBases,
        });
      }
    };

    walk(src);
    found.sort((a, b) => a.rel.localeCompare(b.rel));
    return found;
  }
};

function mailpitBaseUrl(): string {
  if (process.env.MAILPIT_URL) {
    return process.env.MAILPIT_URL.replace(/\/+$/, '');
  }

  return `http://127.0.0.1:${process.env.MAILSERVER_GUI_PORT || 8025}`;
}

async function mailpitFetch(path: string, init?: Record<string, unknown>) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 5000);

  try {
    const response = await fetch(`${mailpitBaseUrl()}${path}`, {
      ...(init || {}),
      signal: controller.signal
    } as any);

    if (!response.ok) {
      return { ok: false, error: `HTTP ${response.status}`, body: null };
    }

    const text = await response.text();
    return { ok: true, error: null, body: text === '' ? null : JSON.parse(text) };
  } catch (err: any) {
    return { ok: false, error: err?.message || String(err), body: null };
  } finally {
    clearTimeout(timer);
  }
}

export const mailTasks = {
  async 'mail:available'() {
    const result = await mailpitFetch('/api/v1/messages?limit=1');

    return { available: result.ok, url: mailpitBaseUrl(), error: result.error };
  },

  async 'mail:clear'() {
    return await mailpitFetch('/api/v1/messages', { method: 'DELETE' });
  },

  async 'mail:list'({ limit }: { limit?: number } = {}) {
    return await mailpitFetch(`/api/v1/messages?limit=${limit || 100}`);
  },

  async 'mail:get'({ id }: { id: string }) {
    return await mailpitFetch(`/api/v1/message/${encodeURIComponent(id)}`);
  }
};

export function setupCommonNodeEvents(on: any, config: any) {
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

  const tasks: Record<string, any> = { ...dbTasks, ...mailTasks, ...htaccessTasks };

  try {
    const { verifyDownloadTasks } = require('cy-verify-downloads');
    Object.assign(tasks, verifyDownloadTasks);
  } catch (err) {
    // optional dependency may be missing in some environments
  }

  on('task', tasks);

  on('before:browser:launch', (browser: any, launchOptions: any) => {
    if (browser.name === 'chrome') {
      launchOptions.args.push('--disable-dev-shm-usage');
    }
    return launchOptions;
  });

  return config;
}
