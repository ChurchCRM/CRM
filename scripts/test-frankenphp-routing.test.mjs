import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const root = fileURLToPath(new URL("../", import.meta.url));
const caddyfile = readFileSync(join(root, "docker/examples/frankenphp/Caddyfile"), "utf8");
const [normal, subdirComment] = caddyfile.split("# ── SUBDIRECTORY INSTALLATION");
assert.ok(subdirComment, "missing subdirectory example");

const example = subdirComment
  .split("\n")
  .filter((line) => line.startsWith("# "))
  .map((line) => line.slice(2))
  .join("\n");
const apps = ["portal", "people", "groups", "event", "ministries", "fundraiser"];

// Read the actual path matchers and try_files fallbacks instead of maintaining
// a duplicate routing table that could keep passing after the Caddyfile breaks.
function model(config, prefix) {
  const routes = new Map();
  for (const match of config.matchAll(/^\s*handle\s+(\/[\w/*-]+)\s*\{\s*try_files\s+(\{path\})\s+(\/[\w/.-]+)\s+php_server\s*\}/gm)) {
    const [, path, staticFirst, fallback] = match;
    assert.equal(staticFirst, "{path}");
    routes.set(path, fallback);
  }

  const cli = config.match(/^\s*@cli\s+path\s+(\/\S+)\s+(\/\S+)/m);
  const internal = config.match(/^\s*@internalPhp\s+path_regexp\s+internalphp\s+(\S+)/m);
  const portal = config.match(/^\s*@portalRoutes\s+path_regexp\s+portalroutes\s+(\S+)/m);
  const plugin = config.match(/^\s*@pluginPhp\s+path_regexp\s+pluginphp\s+(\S+)/m);
  assert.ok(cli && internal && portal && plugin, "missing security matchers");
  const blocked = [new RegExp(internal[1]), new RegExp(portal[1]), new RegExp(plugin[1])];
  assert.equal(cli[1], prefix + "/cli");
  assert.equal(cli[2], prefix + "/cli/*");

  return {
    routes,
    resolve(path, existingFiles = []) {
      if (path === cli[1] || path.startsWith(prefix + "/cli/") || blocked.some((re) => re.test(path))) {
        return { status: 404 };
      }
      const candidates = [...routes].filter(([key]) => path.startsWith(key.slice(0, -1)));
      // Simulate Caddy's most-specific handle path matcher.
      candidates.sort((a, b) => b[0].length - a[0].length);
      if (!candidates.length) return { fallback: prefix + "/index.php" };
      return { fallback: existingFiles.includes(path) ? path : candidates[0][1] };
    },
  };
}

for (const [name, config, prefix] of [
  ["site root", normal, ""],
  ["subdirectory", example, "/churchcrm"],
]) {
  test(name + ": routes every missing Slim application through its front controller", () => {
    const router = model(config, prefix);
    for (const app of apps) {
      assert.equal(router.routes.get(prefix + "/" + app + "/*"), prefix + "/" + app + "/index.php");
      assert.equal(router.resolve(prefix + "/" + app + "/example").fallback, prefix + "/" + app + "/index.php");
    }
    assert.equal(
      router.resolve(prefix + "/portal/theme/default/theme.css", [prefix + "/portal/theme/default/theme.css"]).fallback,
      prefix + "/portal/theme/default/theme.css",
      "real static assets must bypass the front controller",
    );
  });

  test(name + ": internal PHP, CLI, and bundled plugin sources are denied", () => {
    const router = model(config, prefix);
    const privatePaths = [
      "/cli",
      "/cli/cron.php",
      "/people/views/private.php",
      "/groups/views/internal.php",
      "/event/views/internal.php",
      "/ministries/views/internal.php",
      "/fundraiser/views/internal.php",
      "/admin/views/internal.php",
      "/finance/views/internal.php",
      "/v2/templates/internal.php",
      "/portal/routes/profile.php",
      "/plugins/community/plugin/secret.php",
    ];
    for (const path of privatePaths) {
      assert.deepEqual(router.resolve(prefix + path), { status: 404 }, "must block " + prefix + path);
    }
    assert.equal(router.resolve(prefix + "/portal/profile").fallback, prefix + "/portal/index.php");
  });
}

test("Caddy runs denial responses before app routing", () => {
  assert.match(normal, /^\s*order respond before handle$/m);
  for (const name of ["cli", "internalPhp", "portalRoutes", "pluginPhp"]) {
    assert.match(normal, new RegExp("respond @" + name + " 404"));
    assert.match(example, new RegExp("respond @" + name + " 404"));
  }
});
