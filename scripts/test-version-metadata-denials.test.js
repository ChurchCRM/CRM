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
  assert.ok(caddy.indexOf("respond @metadata 404") < caddy.indexOf("handle /session/*"));
  assert.ok(caddy.includes("#     respond @metadata 404"));
  for (const path of publicPaths) {
    assert.ok(!rootPaths.includes(path));
    assert.ok(!subdirPaths.includes(`/churchcrm${path}`));
  }
});
