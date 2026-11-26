const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');
const { findAdminRoleGates } = require('../../scripts/lint/validate-admin-api-prefix');

test('finds AdminRoleAuthMiddleware outside comments', () => {
    const source = `<?php
// AdminRoleAuthMiddleware must not be named only in a comment
/* AdminRoleAuthMiddleware */
$group->get('/x', 'handler')->add(AdminRoleAuthMiddleware::class);
`;
    assert.deepEqual(
        findAdminRoleGates(source).map((hit) => hit.line),
        [4],
    );
});

test('allows a route file that uses another role', () => {
    assert.deepEqual(findAdminRoleGates("<?php\n$group->add(FinanceRoleAuthMiddleware::class);\n"), []);
});

test('fails the CLI against a route that gates the /api app with admin role', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'admin-api-prefix-'));
    try {
        const file = path.join(dir, 'bad.php');
        fs.writeFileSync(file, "<?php\n$app->get('/secret', 'handler')->add(AdminRoleAuthMiddleware::class);\n");
        const result = spawnSync(process.execPath, ['scripts/lint/validate-admin-api-prefix.js', file], {
            encoding: 'utf8',
        });
        assert.equal(result.status, 1);
        assert.match(result.stderr, /bad\.php:2:/);
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
});
