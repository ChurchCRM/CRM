const assert = require("node:assert/strict");
const { mkdtempSync, readFileSync, rmSync, writeFileSync } = require("node:fs");
const { join, resolve } = require("node:path");
const { spawn, spawnSync } = require("node:child_process");
const { tmpdir } = require("node:os");
const net = require("node:net");
const test = require("node:test");

const root = resolve(__dirname, "..");
const apache = readFileSync(resolve(root, "src/.htaccess"), "utf8");
const dockerApache = readFileSync(resolve(root, "docker/apache/default.conf"), "utf8");
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
const nestedPaths = [
  "/vendor/azuyalabs/yasumi/composer.json",
  "/vendor/azuyalabs/yasumi/composer.lock",
  "/vendor/azuyalabs/yasumi/package.json",
  "/vendor/azuyalabs/yasumi/package-lock.json",
  "/vendor/azuyalabs/yasumi/upgrade.json",
  "/vendor/azuyalabs/yasumi/installed.json",
];
const publicPaths = [
  "/index.php", "/robots.txt", "/Images/ChurchCRM.png",
  "/Images/composer.png", "/vendor/docs/composer.json.txt",
  "/vendor/docs/package-lock.json.backup",
];

function protectedBasenameMatcher(source, label) {
  const matches = [...source.matchAll(/<FilesMatch "([^"]+)">\\s*Require all denied\\s*<\\/FilesMatch>/g)];
  const match = matches.find((entry) => {
    const regex = new RegExp(entry[1]);
    return regex.test("composer.json") && regex.test("installed.json");
  });
  assert.ok(match, label + " must deny metadata filenames");
  return new RegExp(match[1]);
}

test("Apache .htaccess and Docker image deny nested metadata by filename", () => {
  const srcMatcher = protectedBasenameMatcher(apache, "src/.htaccess");
  const dockerMatcher = protectedBasenameMatcher(dockerApache, "docker/apache/default.conf");
  assert.equal(srcMatcher.source, dockerMatcher.source, "image and .htaccess rules must agree");
  assert.ok(dockerApache.indexOf('<FilesMatch "^\\\\.ht">') < dockerApache.indexOf('<FilesMatch "^(composer'), "add image denial after dotfile block");
  for (const path of [...privatePaths, ...nestedPaths]) {
    const name = path.split("/").at(-1);
    assert.ok(srcMatcher.test(name), path);
    assert.ok(dockerMatcher.test(name), "Docker: " + path);
  }
  for (const path of publicPaths) {
    assert.equal(srcMatcher.test(path.split("/").at(-1)), false, path);
  }
});

test("nginx regex denies nested metadata names and preserves public assets", () => {
  const rootRule = nginx.match(/^\\s*location ~ (\\/\\(\\?:[^\\n]+) \\{ return 404; \\}$/m);
  const subdirRule = nginx.match(/^#\\s+location ~ (\\/\\(\\?:[^\\n]+) \\{ return 404; \\}$/m);
  assert.ok(rootRule, "root regex location missing");
  assert.ok(subdirRule, "subdirectory example regex missing");
  assert.equal(subdirRule[1], rootRule[1], "both installations must deny the same basenames");
  const denied = new RegExp(rootRule[1]);
  for (const path of [...privatePaths, ...nestedPaths]) {
    assert.ok(denied.test(path), path);
    assert.ok(denied.test("/churchcrm" + path), "subdir: " + path);
  }
  for (const path of publicPaths) {
    assert.equal(denied.test(path), false, path);
    assert.equal(denied.test("/churchcrm" + path), false, "subdir: " + path);
  }
});

test("Caddy path_regexp protects nested metadata before fallback", () => {
  const rootMatcher = caddy.match(/^\\s*@metadata path_regexp metadata (.+)$/m);
  const subdirMatcher = caddy.match(/^#\\s+@metadata path_regexp metadata (.+)$/m);
  assert.ok(rootMatcher, "root path_regexp missing");
  assert.ok(subdirMatcher, "subdir path_regexp missing");
  assert.equal(subdirMatcher[1], rootMatcher[1], "path patterns must agree");
  const denied = new RegExp(rootMatcher[1]);
  for (const path of [...privatePaths, ...nestedPaths]) {
    assert.ok(denied.test(path), path);
    assert.ok(denied.test("/churchcrm" + path), "subdir: " + path);
  }
  for (const path of publicPaths) {
    assert.equal(denied.test(path), false, path);
  }
  // Caddy's directive ordering puts a plain respond behind the catch-all
  // handle; this specific denial must belong to the handle group.
  const rootDenial = "    handle @metadata {\\n        respond 404\\n    }";
  const subdirDenial = "#     handle @metadata {\\n#         respond 404\\n#     }";
  assert.ok(caddy.includes(rootDenial), "root denial must be a handle");
  assert.ok(caddy.includes(subdirDenial), "subdir denial must be a handle");
  assert.ok(caddy.indexOf(rootDenial) < caddy.indexOf("\\n    handle {"), "deny before root fallback");
  assert.ok(caddy.indexOf(subdirDenial) < caddy.indexOf("\\n#     handle /churchcrm/* {"), "deny before subdir fallback");
});

// When Caddy is installed, exercise effective handle precedence rather than only
// source order. The CI suite still checks source structure when Caddy is absent.
test("Caddy metadata denial wins over a catch-all fallback", async (t) => {
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
        for (const path of [...privatePaths, ...nestedPaths]) {
          const target = `${pathPrefix}${path}`;
          const response = await fetch(`http://127.0.0.1:${port}${target}`);
          assert.equal(response.status, 404, target);
        }
        for (const path of publicPaths) {
          const publicResponse = await fetch(`http://127.0.0.1:${port}${pathPrefix}${path}`);
          assert.equal(publicResponse.status, 200, path);
        }
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
