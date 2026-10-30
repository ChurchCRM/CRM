const assert = require("node:assert/strict");
const { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } = require("node:fs");
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
  const matches = [...source.matchAll(/<FilesMatch "([^"]+)">\s*Require all denied\s*<\/FilesMatch>/g)];
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
  assert.ok(dockerApache.indexOf('<FilesMatch "^\\.ht">') < dockerApache.indexOf('<FilesMatch "^(composer'), "add image denial after dotfile block");
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
  const rootRule = nginx.match(/^\s*location ~ (\/\(\?:[^\n]+) \{ return 404; \}$/m);
  const subdirRule = nginx.match(/^#\s+location ~ (\/\(\?:[^\n]+) \{ return 404; \}$/m);
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
  const rootMatcher = caddy.match(/^\s*@metadata path_regexp metadata (.+)$/m);
  const subdirMatcher = caddy.match(/^#\s+@metadata path_regexp metadata (.+)$/m);
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
  const rootDenial = "    handle @metadata {\n        respond 404\n    }";
  const subdirDenial = "#     handle @metadata {\n#         respond 404\n#     }";
  assert.ok(caddy.includes(rootDenial), "root denial must be a handle");
  assert.ok(caddy.includes(subdirDenial), "subdir denial must be a handle");
  assert.ok(caddy.indexOf(rootDenial) < caddy.indexOf("\n    handle {"), "deny before root fallback");
  assert.ok(caddy.indexOf(subdirDenial) < caddy.indexOf("\n#     handle /churchcrm/* {"), "deny before subdir fallback");
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

// A ^~ application prefix bypasses regex locations at the server level.
// The deny must be nested in every ^~ block as well as at server level.
test("nginx protects metadata inside every ^~ application prefix", () => {
  const rule = nginx.split("\n").find((line) => line.startsWith("    location ~ /(?:composer"));
  assert.ok(rule, "server-level metadata rule missing");
  const rootPrefixes = [...nginx.matchAll(/^    location \^~ (\/[^\s{]+) \{$/gm)].map((m) => m[1]);
  assert.deepEqual(rootPrefixes, [
    "/session", "/api", "/v2", "/admin", "/finance", "/kiosk", "/plugins",
    "/external", "/setup", "/people", "/groups", "/event", "/fundraiser",
    "/ministries", "/portal", "/Images/",
  ]);
  for (const prefix of rootPrefixes) {
    const entry = "    location ^~ " + prefix + " {\n";
    const section = nginx.slice(nginx.indexOf(entry) + entry.length);
    assert.ok(section.startsWith(
      "        # Also block nested metadata: ^~ bypasses server-level regex locations.\n" +
      "        " + rule.trim() + "\n"
    ), prefix + " must contain an immediate nested deny");
  }
  const subPrefixes = [...nginx.matchAll(/^#     location \^~ (\/[^\s{]+) \{$/gm)].map((m) => m[1]);
  assert.deepEqual(subPrefixes, ["/churchcrm/Images/", "/churchcrm/session", "/churchcrm/plugins"]);
  for (const prefix of subPrefixes) {
    const entry = "#     location ^~ " + prefix + " {\n";
    const section = nginx.slice(nginx.indexOf(entry) + entry.length);
    assert.ok(section.startsWith(
      "#         # ^~ also bypasses the server-level metadata rule.\n" +
      "#         " + rule.trim() + "\n"
    ), prefix + " must contain an immediate nested deny");
  }
});

test("live nginx rejects a plugin manifest despite ^~ routing", async (t) => {
  const available = spawnSync("nginx", ["-v"], { encoding: "utf8" });
  if (available.error || available.status !== 0) {
    t.skip("nginx missing; static tests still enforce protection");
    return;
  }
  const rule = nginx.split("\n").find((line) => line.startsWith("    location ~ /(?:composer"));
  assert.ok(rule);
  const prefix = "    location ^~ /plugins {\n";
  const start = nginx.indexOf(prefix);
  assert.ok(start >= 0);
  const nested = nginx.slice(start + prefix.length).split("\n").find((line) => line.trim() === rule.trim());
  assert.ok(nested, "guard missing in the actual plugin block");
  const directory = mkdtempSync(join(tmpdir(), "churchcrm-nginx-guard-"));
  const site = join(directory, "site");
  const fixtures = [
    "plugins/community/demo/composer.json",
    "plugins/community/demo/package-lock.json",
    "plugins/community/demo/icon.png",
    "vendor/demo/composer.json",
    "Images/composer.png",
  ];
  for (const file of fixtures) {
    const filename = join(site, file);
    mkdirSync(join(filename, ".."), { recursive: true });
    writeFileSync(filename, "synthetic test fixture");
  }
  for (const dir of ["body", "proxy", "fastcgi", "uwsgi", "scgi"]) mkdirSync(join(directory, dir));
  const port = await new Promise((resolvePort, reject) => {
    const listener = net.createServer();
    listener.once("error", reject);
    listener.listen(0, "127.0.0.1", () => {
      const selected = listener.address().port;
      listener.close(() => resolvePort(selected));
    });
  });
  const config = join(directory, "nginx.conf");
  writeFileSync(config, [
    "worker_processes 1;",
    "pid " + directory + "/nginx.pid;",
    "error_log " + directory + "/error.log warn;",
    "events { worker_connections 64; }",
    "http {",
    "  access_log off;",
    ...["body","proxy","fastcgi","uwsgi","scgi"].map((n) => "  " + (n==="body"?"client_body":n) + "_temp_path " + directory + "/" + n + ";"),
    "  server {",
    "    listen 127.0.0.1:" + port + ";",
    "    server_name localhost;",
    "    root " + site + ";",
    "    " + rule.trim(),
    "    location ^~ /plugins {",
    "      " + nested.trim(),
    "      try_files $uri =404;",
    "    }",
    "    location / { try_files $uri =404; }",
    "  }",
    "}",
  ].join("\n") + "\n");
  let proc;
  try {
    const validation = spawnSync("nginx", ["-t", "-c", config, "-p", directory], { encoding: "utf8" });
    assert.equal(validation.status, 0, validation.stderr);
    proc = spawn("nginx", ["-c", config, "-p", directory, "-g", "daemon off;"], { stdio: "ignore" });
    let ready = false;
    for (let i = 0; i < 40; i++) {
      try {
        const response = await fetch("http://127.0.0.1:" + port + "/plugins/community/demo/icon.png");
        ready = response.status === 200;
        if (ready) break;
      } catch {}
      await new Promise((done) => setTimeout(done, 100));
    }
    assert.ok(ready, "temporary nginx failed to start");
    for (const path of [
      "/plugins/community/demo/composer.json",
      "/plugins/community/demo/package-lock.json",
      "/vendor/demo/composer.json",
    ]) {
      const response = await fetch("http://127.0.0.1:" + port + path);
      assert.equal(response.status, 404, path);
    }
    for (const path of ["/plugins/community/demo/icon.png", "/Images/composer.png"]) {
      const response = await fetch("http://127.0.0.1:" + port + path);
      assert.equal(response.status, 200, path);
    }
  } finally {
    if (proc) {
      proc.kill("SIGTERM");
      await new Promise((done) => {
        if (proc.exitCode !== null) done();
        else proc.once("exit", done);
      });
    }
    rmSync(directory, { recursive: true, force: true });
  }
});
