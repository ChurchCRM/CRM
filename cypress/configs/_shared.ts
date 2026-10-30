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
// A connection failure (DB down, wrong port or credentials) deliberately
// THROWS rather than being returned as data: it is an infrastructure fault
// that must fail the run loudly, not something a spec should assert on.
// Only driver errors from the statement itself are returned by the tasks.
//
// The port must match the stack the specs run against: 3306 for the
// default and ci-root stacks, DATABASE_SUBDIR_PORT (3307) for ci-subdir —
// the workflows export DATABASE_PORT accordingly, and an isolated local
// stack on another port must export it too.
async function openDbConnection(multipleStatements: boolean) {
  const mysql = require('mysql2/promise');
  return mysql.createConnection({
    host: process.env.DATABASE_HOST || '127.0.0.1',
    port: Number(process.env.DATABASE_PORT || 3306),
    user: process.env.MYSQL_USER || 'churchcrm',
    password: process.env.MYSQL_PASSWORD || 'changeme',
    database: process.env.MYSQL_DATABASE || 'churchcrm',
    multipleStatements,
    dateStrings: true
  });
}

function driverError(err: any) {
  return {
    code: err.code || null,
    errno: err.errno || null,
    sqlState: err.sqlState || null,
    message: err.message || String(err)
  };
}

async function closeDbConnection(connection: any) {
  // A rejected await inside finally would replace the value returned
  // above, so never let end() reject; destroy() cannot.
  try {
    await connection.end();
  } catch {
    connection.destroy();
  }
}

/**
 * Who may use the database tasks: cypress/db-access.json, the same list
 * `npm run lint` checks (#10377). A spec runs SQL only while the list approves
 * it or keeps it on the baseline; a fixture in cypress/support/ names its own
 * approved file. Only an approved schema spec changes the schema or runs an
 * upgrade block. A refusal throws, so it fails the test instead of reading as
 * a driver error a spec could assert on.
 */
const SCHEMA_CHANGE = /^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME|GRANT|REVOKE)\b|^\s*SET\s+GLOBAL\b/i;

let currentSpec: string | null = null;

/** Records which spec is running, so a task knows who called it. */
export function trackCurrentSpec(on: (event: 'before:spec', handler: (spec: { relative: string }) => void) => void) {
  on('before:spec', (spec) => {
    currentSpec = spec.relative.split('\\').join('/');
  });
}

function databaseAccess() {
  const fs = require('fs');
  const path = require('path');
  return JSON.parse(fs.readFileSync(path.resolve(__dirname, '../db-access.json'), 'utf8'));
}

function refuseUnlisted({ spec, fixture, sql, upgradeBlock = false }: { spec?: string | null; fixture?: string | null; sql?: string; upgradeBlock?: boolean }) {
  const caller = currentSpec || spec || null;
  if (caller === null) {
    // `cypress open` without experimentalInteractiveRunEvents; the lint guard still applies.
    return;
  }
  const { approved = {}, baseline = {} } = databaseAccess();
  const changesSchema = upgradeBlock || (sql !== undefined && SCHEMA_CHANGE.test(sql));
  let problem: string | null = null;
  if (fixture) {
    if (!fixture.startsWith('cypress/support/') || approved[fixture]?.kind !== 'fixture') {
      problem = `${fixture} is not a support fixture approved in cypress/db-access.json`;
    } else if (changesSchema) {
      problem = `the support fixture ${fixture} may not change the schema`;
    }
  } else if (changesSchema) {
    if (approved[caller]?.kind !== 'schema') {
      problem = `${caller} is not an approved schema spec, so it may not change the schema or run an upgrade block`;
    }
  } else if (!approved[caller] && !(baseline[caller] > 0)) {
    problem = `cypress/db-access.json neither approves ${caller} nor keeps it on the baseline`;
  }
  if (problem !== null) {
    throw new Error(`Database access refused (#10377): ${problem}. See .agents/skills/churchcrm/cypress-testing.md "Database access".`);
  }
}

export const dbTasks = {
  async 'passwordReset:race'({ url, password }: { url: string; password: string }) {
    const sessions = await Promise.all([0, 1].map(async () => {
      const response = await fetch(url);
      const html = await response.text();
      const csrf = html.match(/name="csrf_token" value="([^"]+)"/);
      const cookie = response.headers.getSetCookie().map((value) => value.split(';')[0]).join('; ');
      if (!csrf || !cookie) {
        const snippet = html.replace(/\s+/g, ' ').slice(0, 200);
        throw new Error(
          `Password reset confirmation did not establish a CSRF session\n` +
          `  url: ${url}\n` +
          `  status: ${response.status}\n` +
          `  body: ${snippet || '(empty)'}`,
        );
      }
      return { cookie, csrf: csrf[1] };
    }));
    return Promise.all(sessions.map(async ({ cookie, csrf }) => {
      const response = await fetch(url, {
        method: 'POST',
        headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: csrf, password, passwordConfirmation: password }),
      });
      if (!response.ok) {
        throw new Error(`Password reset POST returned HTTP ${response.status} for ${url}`);
      }
      return response.text();
    }));
  },
  async 'db:query'({ sql, params, spec = null, fixture = null }: { sql: string; params?: unknown[]; spec?: string | null; fixture?: string | null }) {
    refuseUnlisted({ spec, fixture, sql });
    const connection = await openDbConnection(false);
    try {
      const [rows] = await connection.query(sql, params || []);
      return { rows, error: null };
    } catch (err: any) {
      return { rows: null, error: driverError(err) };
    } finally {
      await closeDbConnection(connection);
    }
  },

  /**
   * Runs one block of a shipped upgrade script, the text between the lines
   * `-- BEGIN: <block>` and `-- END: <block>`, so a migration spec tests the
   * SQL an upgrade actually runs rather than a copy of it.
   */
  async 'db:upgradeBlock'({ script, block }: { script: string; block: string }) {
    refuseUnlisted({ upgradeBlock: true });
    const fs = require('fs');
    const path = require('path');

    // Cypress starts with cwd = cypress/configs, not the repo root.
    let root = process.cwd();
    while (!fs.existsSync(path.join(root, 'src', 'mysql', 'upgrade')) && path.dirname(root) !== root) {
      root = path.dirname(root);
    }
    const text = fs.readFileSync(path.join(root, 'src', 'mysql', 'upgrade', path.basename(script)), 'utf8');
    const begin = text.indexOf(`-- BEGIN: ${block}`);
    const end = text.indexOf(`-- END: ${block}`, begin);
    if (begin < 0 || end < 0) {
      throw new Error(`db:upgradeBlock: no block "${block}" in ${script}`);
    }

    const connection = await openDbConnection(true);
    try {
      await connection.query(text.slice(begin, end));
      return { error: null };
    } catch (err: any) {
      return { error: driverError(err) };
    } finally {
      await closeDbConnection(connection);
    }
  }
};

/**
 * Read every src/**\/.htaccess on the runner.
 *
 * The CI app directory is bind-mounted into the container, so the files the
 * setup wizard writes are the same files on disk here. The task only reads
 * `.htaccess` under `src/` (vendor excluded) and returns the RewriteBase
 * lines. Used by the new-system install spec (#10563).
 */
export const htaccessTasks = {
  'htaccess:scan'() {
    const fs = require('fs');
    const path = require('path');

    // Cypress starts with cwd = cypress/configs, not the repo root.
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

/**
 * Node-side Mailpit tasks.
 *
 * The `test` docker profile runs Mailpit (`docker/docker-compose.yaml`), so a
 * spec can assert what was actually *delivered* — subject, body, headers — and
 * not merely that the application believed it sent something. #9710 needs that:
 * "the drain sent it" and "the message carries a Reply-To pointing at the
 * coordinator" are different claims, and only the second one proves N10/CR6.
 *
 * These are node tasks rather than `cy.request()` for one reason: Mailpit is
 * OPTIONAL. The `ci-root` / `ci-subdir` profiles bring up no mail server at all,
 * and `cy.request()` fails the test outright on a refused connection — there is
 * no `failOnStatusCode` for ECONNREFUSED. A task can catch that and RETURN
 * `{ok: false}`, which lets a spec skip delivery assertions where there is no
 * mail server instead of failing on infrastructure that was never promised.
 * Same rationale as `db:query` returning driver errors instead of throwing.
 *
 * MAILPIT_URL wins; otherwise MAILSERVER_GUI_PORT (which an isolated local
 * stack sets alongside DATABASE_PORT) on localhost; otherwise Mailpit's 8025.
 */
export function mailpitBaseUrl(): string {
  if (process.env.MAILPIT_URL) {
    return process.env.MAILPIT_URL.replace(/\/+$/, '');
  }

  return `http://127.0.0.1:${process.env.MAILSERVER_GUI_PORT || 8025}`;
}

/**
 * Gives specs that call Mailpit with cy.request() the same URL as the mail
 * tasks, read with Cypress.mailpitUrl(). Every config that registers
 * mailTasks calls this.
 */
export function exposeMailpitUrl(config: any) {
  config.expose = { ...config.expose, mailpitUrl: mailpitBaseUrl() };
  return config;
}

async function mailpitFetch(path: string, init?: Record<string, unknown>) {
  // A short timeout keeps "no mail server here" from costing the spec a minute.
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

/**
 * Lists the PHP files under src/ that attach a role-auth middleware to a route,
 * a group or an app, so gate-coverage.spec.js can fail when a gate ships without
 * a gate row or an explicit pending entry. The middleware classes themselves
 * (src/ChurchCRM) and vendor code are not gates.
 */
export const gateCoverageTasks = {
  'gates:wrapperFiles'() {
    const fs = require('fs');
    const path = require('path');

    // Cypress starts with cwd = cypress/configs, not the repo root.
    let root = process.cwd();
    for (let i = 0; i < 6 && !fs.existsSync(path.join(root, 'src', 'api', 'index.php')); i++) {
      const parent = path.dirname(root);
      if (parent === root) {
        break;
      }
      root = parent;
    }
    const src = path.join(root, 'src');
    if (!fs.existsSync(path.join(src, 'api', 'index.php'))) {
      throw new Error('gates:wrapperFiles could not find src/ from ' + process.cwd());
    }

    const pattern = /->add\(\s*(?:new\s+)?\w+RoleAuthMiddleware|roleMiddleware['"]\s*=>\s*\w+RoleAuthMiddleware::class/;
    const found: string[] = [];
    const walk = (dir: string) => {
      for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        const rel = path.relative(root, full).split(path.sep).join('/');
        if (entry.isDirectory()) {
          if (entry.name !== 'vendor' && entry.name !== 'node_modules' && rel !== 'src/ChurchCRM') {
            walk(full);
          }
        } else if (entry.name.endsWith('.php') && pattern.test(fs.readFileSync(full, 'utf8'))) {
          found.push(rel);
        }
      }
    };
    walk(src);

    return found.sort();
  }
};

/**
 * Fake plugin registry for upgrade specs. The Docker webserver reads
 * CHURCHCRM_PLUGIN_FIXTURES (the repo is mounted at /home/ChurchCRM); a
 * registry.json in that directory replaces the remote registry (one approved
 * version per write, like the real registry), and zips for
 * https://fixtures.invalid/<name>.zip are served from it.
 */
export const pluginFixtureTasks = {
  async 'pluginFixtures:write'({ id, version, badSha = false, source }: { id: string; version: string; badSha?: boolean; source?: string }) {
    const archiver = require('archiver');
    const crypto = require('crypto');
    const fs = require('fs');
    const path = require('path');
    const dir = path.resolve(__dirname, '../fixtures/plugin-registry');
    fs.mkdirSync(dir, { recursive: true });

    const plugins = [];
    {
      const zipName = `${id}-${version}.zip`;
      const manifest = {
        id,
        name: 'Upgrade Fixture',
        description: 'Cypress fixture plugin for upgrade tests.',
        version,
        type: 'community',
        minimumCRMVersion: '7.0.0',
        mainClass: 'ChurchCRM\\Plugins\\UpgradeFixture\\UpgradeFixturePlugin',
        dependencies: [],
        settingsUrl: null,
        settings: [],
        menuItems: [],
        hooks: []
      };
      const plugin = `<?php\n\nnamespace ChurchCRM\\Plugins\\UpgradeFixture;\n\nuse ChurchCRM\\Plugin\\AbstractPlugin;\n\nclass UpgradeFixturePlugin extends AbstractPlugin\n{\n    public function getId(): string\n    {\n        return '${id}';\n    }\n\n    public function getName(): string\n    {\n        return 'Upgrade Fixture';\n    }\n\n    public function getDescription(): string\n    {\n        return 'Cypress fixture plugin.';\n    }\n\n    public function boot(): void\n    {\n    }\n}\n`;
      // `source` names a plugin under cypress/fixtures/plugins/ to ship instead of the generated one.
      const sourceDir = source ? path.resolve(__dirname, '../fixtures/plugins', source) : null;
      const files: Array<[string, string | Buffer]> = [];
      if (sourceDir) {
        const sourceManifest = JSON.parse(fs.readFileSync(path.join(sourceDir, 'plugin.json'), 'utf8'));
        Object.assign(manifest, sourceManifest, { id, version });
        const walk = (rel: string): void => {
          for (const entry of fs.readdirSync(path.join(sourceDir, rel), { withFileTypes: true })) {
            const child = rel === '' ? entry.name : `${rel}/${entry.name}`;
            if (entry.isDirectory()) {
              walk(child);
            } else if (child !== 'plugin.json') {
              files.push([child, fs.readFileSync(path.join(sourceDir, child))]);
            }
          }
        };
        walk('');
      } else {
        files.push(['src/UpgradeFixturePlugin.php', plugin]);
      }
      const zipPath = path.join(dir, zipName);
      await new Promise<void>((resolve, reject) => {
        const out = fs.createWriteStream(zipPath);
        const zip = archiver('zip');
        out.on('close', () => resolve());
        zip.on('error', reject);
        zip.pipe(out);
        zip.append(JSON.stringify(manifest, null, 2), { name: `${id}/plugin.json`, date: new Date(0) });
        for (const [name, content] of files) {
          zip.append(content, { name: `${id}/${name}`, date: new Date(0) });
        }
        zip.finalize();
      });
      plugins.push({
        id,
        name: manifest.name,
        version,
        downloadUrl: `https://fixtures.invalid/${zipName}`,
        sha256: badSha ? '0'.repeat(64) : crypto.createHash('sha256').update(fs.readFileSync(zipPath)).digest('hex'),
        risk: 'low',
        riskSummary: 'Test fixture.',
        permissions: []
      });
    }
    fs.writeFileSync(path.join(dir, 'registry.json'), JSON.stringify({ plugins }, null, 2));
    return plugins;
  },

  /** Is an upgrade backup still parked for this plugin? */
  'pluginFixtures:backupExists'(id: string) {
    const fs = require('fs');
    const path = require('path');
    return fs.existsSync(path.resolve(__dirname, '../../src/plugins/community/.upgrade-backup', id));
  },

  /** Simulate a crash mid-swap: the live copy is already parked, the new one never arrived. */
  'pluginFixtures:interruptUpgrade'(id: string) {
    const fs = require('fs');
    const path = require('path');
    const community = path.resolve(__dirname, '../../src/plugins/community');
    fs.mkdirSync(path.join(community, '.upgrade-backup'), { recursive: true });
    fs.renameSync(path.join(community, id), path.join(community, '.upgrade-backup', id));
    return null;
  },

  'pluginFixtures:clear'() {
    const fs = require('fs');
    const path = require('path');
    fs.rmSync(path.resolve(__dirname, '../fixtures/plugin-registry'), { recursive: true, force: true });
    return null;
  }
};

export const mailTasks = {
  /** Is a Mailpit instance reachable? Never throws — the answer is the point. */
  async 'mail:available'() {
    const result = await mailpitFetch('/api/v1/messages?limit=1');

    return { available: result.ok, url: mailpitBaseUrl(), error: result.error };
  },

  /** Delete every stored message, so a spec can assert on what IT produced. */
  async 'mail:clear'() {
    return await mailpitFetch('/api/v1/messages', { method: 'DELETE' });
  },

  /** Message summaries, newest first. `{ok, body: {messages: [...]}}`. */
  async 'mail:list'({ limit }: { limit?: number } = {}) {
    return await mailpitFetch(`/api/v1/messages?limit=${limit || 100}`);
  },

  /** One message in full — Text, HTML and the ReplyTo header among them. */
  async 'mail:get'({ id }: { id: string }) {
    return await mailpitFetch(`/api/v1/message/${encodeURIComponent(id)}`);
  },

  /** Makes the SMTP server refuse every recipient, or accept them again (Mailpit chaos, MP_ENABLE_CHAOS). */
  async 'mail:refuseRecipients'({ refuse }: { refuse: boolean }) {
    return await mailpitFetch('/api/v1/chaos', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        Sender: { ErrorCode: 451, Probability: 0 },
        Recipient: { ErrorCode: 451, Probability: refuse ? 100 : 0 },
        Authentication: { ErrorCode: 535, Probability: 0 }
      })
    });
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
  const tasks: Record<string, any> = { ...dbTasks, ...mailTasks, ...htaccessTasks, ...pluginFixtureTasks, ...gateCoverageTasks };

  // Register download verification tasks if available
  try {
    const { verifyDownloadTasks } = require('cy-verify-downloads');
    Object.assign(tasks, verifyDownloadTasks);
  } catch (err) {
    // optional dependency may be missing in some environments
  }

  on('task', tasks);
  exposeMailpitUrl(config);

  // Common browser launch options
  on('before:browser:launch', (browser: any, launchOptions: any) => {
    if (browser.name === 'chrome') {
      launchOptions.args.push('--disable-dev-shm-usage');
    }
    return launchOptions;
  });

  return config;
}
