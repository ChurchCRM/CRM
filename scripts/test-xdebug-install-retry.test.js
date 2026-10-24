const assert = require("node:assert/strict");
const { resolve } = require("node:path");
const test = require("node:test");

const root = resolve(__dirname, "..");

const xdebugInstall = /for i in 1 2 3; do pecl install xdebug[^\n]*/;

test("Xdebug install retries and fails the build after three failed attempts", () => {
  const { spawnSync } = require("node:child_process");
  const { mkdtempSync, writeFileSync, chmodSync, readFileSync, rmSync } = require("node:fs");
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
      const line = readFileSync(resolve(root, file), "utf8").match(xdebugInstall);
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
      const calls = () => readFileSync(join(dir, "calls"), "utf8").trim().split("\n").filter(Boolean).length;

      const alwaysFails = run(`echo x >> "${dir}/calls"; exit 1`);
      assert.notEqual(alwaysFails.status, 0, file);
      assert.equal(calls(), 3, `${file} must try exactly three times`);

      const flaky = run(`echo x >> "${dir}/calls"; [ "$(wc -l < "${dir}/calls")" -ge 2 ]`);
      assert.equal(flaky.status, 0, `${file}: ${flaky.stderr}`);
      assert.equal(calls(), 2, file);
      assert.ok(readFileSync(join(dir, "enabled"), "utf8").includes("enabled"), file);
    }
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

