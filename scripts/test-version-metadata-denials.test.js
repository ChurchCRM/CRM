const assert = require("node:assert/strict");
const { readFileSync } = require("node:fs");
const { resolve } = require("node:path");
const test = require("node:test");

const root = resolve(__dirname, "..");
const apache = readFileSync(resolve(root, "src/.htaccess"), "utf8");
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

test("nginx protects each exact metadata path at root and subdirectory", () => {
  for (const path of privatePaths) {
    assert.ok(nginx.includes(`    location = ${path} { return 404; }`), path);
    assert.ok(nginx.includes(`#     location = /churchcrm${path} { return 404; }`), path);
  }
  assert.equal(nginx.includes("location = /index.php { return 404; }"), false);
});

test("FrankenPHP matchers deny root and subdirectory metadata", () => {
  const rootMatcher = caddy.match(/^\s*@metadata path (.+)$/m);
  const subdirMatcher = caddy.match(/^#\s+@metadata path (.+)$/m);
  assert.ok(rootMatcher);
  assert.ok(subdirMatcher);
  const rootPaths = rootMatcher[1].trim().split(/\s+/);
  const subdirPaths = subdirMatcher[1].trim().split(/\s+/);
  assert.deepEqual(rootPaths, privatePaths);
  assert.deepEqual(subdirPaths, privatePaths.map((path) => `/churchcrm${path}`));
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
  for (const path of publicPaths) {
    assert.ok(!rootPaths.includes(path));
    assert.ok(!subdirPaths.includes(`/churchcrm${path}`));
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
  const rootMatcher = caddy.match(/^\s*@metadata path (.+)$/m)?.[0].trim();
  const subMatcher = caddy.match(/^#\s+@metadata path (.+)$/m)?.[0].replace(/^#\s*/, "");
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
        for (const path of privatePaths) {
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
