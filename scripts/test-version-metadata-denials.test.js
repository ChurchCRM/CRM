const assert = require("node:assert/strict");
const { readFileSync } = require("node:fs");
const { resolve } = require("node:path");
const test = require("node:test");

const root = resolve(__dirname, "..");
const apache = readFileSync(resolve(root, "src/.htaccess"), "utf8");
const apacheImage = readFileSync(resolve(root, "docker/apache/default.conf"), "utf8");
const nginx = readFileSync(resolve(root, "docker/examples/nginx/default.conf"), "utf8");
const caddy = readFileSync(resolve(root, "docker/examples/frankenphp/Caddyfile"), "utf8");

const privatePaths = [
  "/composer.json",
  "/composer.lock",
  "/package.json",
  "/package-lock.json",
  "/mysql/upgrade.json",
  "/vendor/composer/installed.json",
];
const publicPaths = ["/index.php", "/robots.txt", "/Images/ChurchCRM.png"];

test("Apache FilesMatch denies the metadata filenames without affecting public assets", () => {
  const match = apache.match(/<FilesMatch "([^"]+)">\s*Require all denied\s*<\/FilesMatch>/);
  assert.ok(match, "expected explicit Apache 2.4 denial");
  const protectedBasename = new RegExp(match[1]);
  for (const path of privatePaths) {
    assert.equal(protectedBasename.test(path.split("/").at(-1)), true, path);
  }
  for (const path of publicPaths) {
    assert.equal(protectedBasename.test(path.split("/").at(-1)), false, path);
  }
});

test("the Apache image config denies the metadata filenames without relying on .htaccess", () => {
  const match = apacheImage.match(/<FilesMatch "(\^\(composer[^"]+)">\s*Require all denied\s*<\/FilesMatch>/);
  assert.ok(match, "expected explicit denial in docker/apache/default.conf");
  const protectedBasename = new RegExp(match[1]);
  for (const path of privatePaths) {
    assert.equal(protectedBasename.test(path.split("/").at(-1)), true, path);
  }
  for (const path of publicPaths) {
    assert.equal(protectedBasename.test(path.split("/").at(-1)), false, path);
  }
});

const nestedPrivatePaths = [...privatePaths, "/vendor/azuyalabs/yasumi/composer.json"];
const METADATA_NGINX = /location ~ (\/\(\?:composer[^\s]+\$) \{ return 404; \}/;

test("nginx denies metadata basenames at any depth, root and subdirectory", () => {
  const rootMatch = nginx.match(new RegExp(`^    ${METADATA_NGINX.source}`, "m"));
  const subMatch = nginx.match(new RegExp(`^#     ${METADATA_NGINX.source}`, "m"));
  assert.ok(rootMatch && subMatch);
  for (const match of [rootMatch, subMatch]) {
    const pattern = new RegExp(match[1]);
    for (const path of nestedPrivatePaths) assert.equal(pattern.test(path), true, path);
    for (const path of [...publicPaths, "/Images/composer.png"]) assert.equal(pattern.test(path), false, path);
  }
});

test("FrankenPHP matchers deny metadata basenames at any depth, root and subdirectory", () => {
  const rootMatcher = caddy.match(/^\s*@metadata path_regexp metadata (.+)$/m);
  const subdirMatcher = caddy.match(/^#\s+@metadata path_regexp metadata (.+)$/m);
  assert.ok(rootMatcher && subdirMatcher);
  for (const match of [rootMatcher, subdirMatcher]) {
    const pattern = new RegExp(match[1].trim());
    for (const path of nestedPrivatePaths) assert.equal(pattern.test(path), true, path);
    for (const path of [...publicPaths, "/Images/composer.png"]) assert.equal(pattern.test(path), false, path);
  }
  // Caddy sorts directive types: a top-level `respond @metadata` runs too late
  // after catch-all `handle`. Denials must be part of the handle group.
  const rootDenial = "    handle @metadata {\n        respond 404\n    }";
  const subdirDenial = "#     handle @metadata {\n#         respond 404\n#     }";
  assert.ok(caddy.includes(rootDenial), "root denial must be a handle block");
  assert.ok(caddy.includes(subdirDenial), "subdirectory denial must be a handle block");
  const rootFallback = caddy.indexOf("\n    handle {");
  const subdirFallback = caddy.indexOf("\n#     handle /churchcrm/* {");
  assert.ok(rootFallback > caddy.indexOf(rootDenial), "deny before fallback");
  assert.ok(subdirFallback > caddy.indexOf(subdirDenial), "subdir deny before fallback");
  assert.ok(!caddy.includes("respond @metadata 404"), "no ineffective top-level respond");
});

const xdebugInstall = /for i in 1 2 3; do pecl install xdebug[^\n]*/;

test("Xdebug install retries and fails the build after three failed attempts", () => {
  const { spawnSync } = require("node:child_process");
  const { mkdtempSync, writeFileSync, chmodSync, readFileSync: read, rmSync } = require("node:fs");
  const { tmpdir } = require("node:os");
  const { join } = require("node:path");
  const dockerfiles = ["docker/Dockerfile.churchcrm-apache-php8", "docker/Dockerfile.churchcrm-fpm-php8"];
  const dir = mkdtempSync(join(tmpdir(), "churchcrm-xdebug-"));
  const stub = (name, body) => {
    writeFileSync(join(dir, name), `#!/bin/sh\n${body}\n`);
    chmodSync(join(dir, name), 0o755);
  };
  try {
    for (const file of dockerfiles) {
      const line = read(resolve(root, file), "utf8").match(xdebugInstall);
      assert.ok(line, `${file} must use the retry loop`);
      const command = line[0].replace("sleep 10", "sleep 0");
      const run = (pecl) => {
        stub("pecl", pecl);
        stub("docker-php-ext-enable", `echo enabled >> "${dir}/enabled"`);
        stub("count-reset", `: > "${dir}/calls"`);
        writeFileSync(join(dir, "calls"), "");
        rmSync(join(dir, "enabled"), { force: true });
        return spawnSync("sh", ["-c", command], { env: { ...process.env, PATH: `${dir}:${process.env.PATH}` }, encoding: "utf8" });
      };
      const calls = () => read(join(dir, "calls"), "utf8").trim().split("\n").filter(Boolean).length;

      const alwaysFails = run(`echo x >> "${dir}/calls"; exit 1`);
      assert.notEqual(alwaysFails.status, 0, file);
      assert.equal(calls(), 3, `${file} must try exactly three times`);

      const flaky = run(`echo x >> "${dir}/calls"; [ "$(wc -l < "${dir}/calls")" -ge 2 ]`);
      assert.equal(flaky.status, 0, `${file}: ${flaky.stderr}`);
      assert.equal(calls(), 2, file);
      assert.ok(read(join(dir, "enabled"), "utf8").includes("enabled"), file);
    }
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

// When Caddy is installed, exercise effective handle precedence rather than only
// source order. The CI suite still checks source structure when Caddy is absent.
test("Caddy metadata denial wins over a catch-all fallback", async (t) => {
  const { spawnSync, spawn } = require("node:child_process");
  const { mkdtempSync, writeFileSync, rmSync } = require("node:fs");
  const { tmpdir } = require("node:os");
  const { join } = require("node:path");
  const net = require("node:net");

  const available = spawnSync("caddy", ["version"], { encoding: "utf8" });
  if (available.error || available.status !== 0) {
    t.skip("Caddy is not installed; static assertions remain enforced");
    return;
  }

  const port = await new Promise((resolvePort, rejectPort) => {
    const listener = net.createServer();
    listener.once("error", rejectPort);
    listener.listen(0, "127.0.0.1", () => {
      const chosen = listener.address().port;
      listener.close(() => resolvePort(chosen));
    });
  });

  const dir = mkdtempSync(join(tmpdir(), "churchcrm-caddy-routes-"));
  const rootMatcher = caddy.match(/^\s*@metadata path_regexp metadata (.+)$/m)?.[0].trim();
  const subMatcher = caddy.match(/^#\s+@metadata path_regexp metadata (.+)$/m)?.[0].replace(/^#\s*/, "");
  assert.ok(rootMatcher);
  assert.ok(subMatcher);
  const config = join(dir, "Caddyfile");

  try {
    for (const matcher of [rootMatcher, subMatcher]) {
      writeFileSync(config,
        `:${port} {
          bind 127.0.0.1
          ${matcher}
          handle @metadata {
            respond 404
          }
          handle {
            respond "fallback" 200
          }
        }
`);
      const validity = spawnSync("caddy", ["validate", "--config", config, "--adapter", "caddyfile"], { encoding: "utf8" });
      assert.equal(validity.status, 0, validity.stderr);
      const proc = spawn("caddy", ["run", "--config", config, "--adapter", "caddyfile"], {
        stdio: "ignore",
      });
      try {
        let ready = false;
        for (let attempt = 0; attempt < 45; attempt += 1) {
          if (proc.exitCode !== null) break;
          try {
            const response = await fetch(`http://127.0.0.1:${port}/public.txt`);
            ready = response.status === 200;
            if (ready) break;
          } catch {}
          await new Promise((done) => setTimeout(done, 100));
        }
        assert.ok(ready, "Caddy test server must be ready");
        // Exercise only fixed expected paths; never construct a network URL from
        // text read from the Caddyfile. The matcher itself is checked above.
        const pathPrefix = matcher === subMatcher ? "/churchcrm" : "";
        for (const path of nestedPrivatePaths) {
          const target = `${pathPrefix}${path}`;
          const response = await fetch(`http://127.0.0.1:${port}${target}`);
          assert.equal(response.status, 404, target);
        }
        const publicResponse = await fetch(`http://127.0.0.1:${port}/public.txt`);
        assert.equal(publicResponse.status, 200);
      } finally {
        proc.kill("SIGTERM");
        await new Promise((done) => {
          if (proc.exitCode !== null) done();
          else proc.once("exit", done);
        });
      }
    }
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
