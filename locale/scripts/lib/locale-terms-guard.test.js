const { test } = require('node:test');
const assert = require('node:assert/strict');

const { extractLiterals, literalsInLine, catalogTerms, guardTerms, renderGuardReport, MARKER } = require('./locale-terms-guard');

test('extracts literals from gettext, ngettext and t() calls', () => {
    assert.deepEqual(literalsInLine(`<?= gettext('Save Changes') ?>`), ['Save Changes']);
    assert.deepEqual(literalsInLine('label: gettext("Charity Legal Name"),'), ['Charity Legal Name']);
    assert.deepEqual(
        literalsInLine(`sprintf(ngettext('%1$d gift, %2$s', '%1$d gifts, %2$s', $n), $n, $total)`),
        ['%1$d gift, %2$s', '%1$d gifts, %2$s']);
    assert.deepEqual(literalsInLine(`signatureStatus.textContent = data.ok ? t("Signature on file") : t("No signature on file");`),
        ['Signature on file', 'No signature on file']);
});

test('unescapes literals the way the catalog stores them', () => {
    assert.deepEqual(literalsInLine(String.raw`<?= gettext('You\'re up to date.') ?>`), ["You're up to date."]);
    assert.deepEqual(literalsInLine(String.raw`gettext('The charity\'s registered address.')`), ["The charity's registered address."]);
    assert.deepEqual(literalsInLine('gettext("First\\nSecond")'), ['First\nSecond']);
});

test('does not mistake the tail of ngettext/gettext for a t() call', () => {
    assert.deepEqual(literalsInLine(`gettext('Only Once')`), ['Only Once']);
    assert.deepEqual(literalsInLine(`$x = format('not a term');`), []);
});

test('parses a unified diff into added and removed literals with locations', () => {
    const diff = [
        'diff --git a/src/finance/views/statements.php b/src/finance/views/statements.php',
        '--- a/src/finance/views/statements.php',
        '+++ b/src/finance/views/statements.php',
        '@@ -10,0 +11,2 @@',
        "+<?= gettext('Brand New Label') ?>",
        "+<?= gettext('Another Label') ?>",
        '@@ -20,1 +21,0 @@',
        "-<?= gettext('Old Label') ?>",
    ].join('\n');
    const { added, removed } = extractLiterals(diff);
    assert.deepEqual(added.get('Brand New Label'), [{ file: 'src/finance/views/statements.php', line: 11 }]);
    assert.deepEqual(added.get('Another Label'), [{ file: 'src/finance/views/statements.php', line: 12 }]);
    assert.deepEqual(removed.get('Old Label'), [{ file: 'src/finance/views/statements.php' }]);
});

test('catalog terms include plural msgids', () => {
    const po = 'msgid ""\nmsgstr ""\n\nmsgid "Save"\nmsgstr ""\n\nmsgid "%d gift"\nmsgid_plural "%d gifts"\nmsgstr[0] ""\n';
    assert.deepEqual([...catalogTerms(po)].sort(), ['%d gift', '%d gifts', 'Save']);
});

const guard = (added, removed, catalog) => guardTerms({
    added: new Map(Object.entries(added).map(([t, loc]) => [t, loc ?? []])),
    removed: new Map(Object.keys(removed ?? {}).map(t => [t, []])),
    catalog: new Set(catalog),
});
const whys = (flagged, term) => (flagged.find(f => f.term === term)?.why ?? []).join('; ');

test('flags a casing pair added by the same PR, in both directions', () => {
    const flagged = guard({ 'Charity legal name': [], 'Charity Legal Name': [] }, {}, ['Save']);
    assert.match(whys(flagged, 'Charity legal name'), /also added in this PR/);
    assert.match(whys(flagged, 'Charity Legal Name'), /also added in this PR/);
});

test('flags a new term that near-duplicates a catalog term', () => {
    const flagged = guard({ 'save changes.': [] }, {}, ['Save Changes']);
    assert.match(whys(flagged, 'save changes.'), /near-duplicate of existing “Save Changes”/);
});

test('flags code-like and over-long new terms, passes clean ones', () => {
    const flagged = guard({ '$row->name': [], 'sPersonName': [], 'A perfectly ordinary new label': [], ['x'.repeat(250)]: [] }, {}, ['Save']);
    assert.match(whys(flagged, '$row->name'), /code/);
    assert.match(whys(flagged, 'sPersonName'), /code/);
    assert.match(whys(flagged, 'x'.repeat(250)), /long/);
    assert.equal(whys(flagged, 'A perfectly ordinary new label'), '');
});

test('positional placeholders are not code', () => {
    const flagged = guard({ '%1$d gift totaling %2$s': [], '%s donated %s': [] }, {}, ['Save']);
    assert.equal(whys(flagged, '%1$d gift totaling %2$s'), '');
    assert.equal(whys(flagged, '%s donated %s'), '');
});

test('terms already in the catalog are not the guard’s business', () => {
    const flagged = guard({ 'Save Changes': [], 'save changes.': [] }, {}, ['Save Changes']);
    assert.equal(whys(flagged, 'Save Changes'), '');
    assert.match(whys(flagged, 'save changes.'), /near-duplicate/);
});

test('flags a rewording of a catalog term the same PR removes', () => {
    const flagged = guard(
        { '3. Generate Tax Statements': [] },
        { 'Generate Tax Statements': [] },
        ['Generate Tax Statements']);
    assert.match(whys(flagged, '3. Generate Tax Statements'), /similar to “Generate Tax Statements”, which this PR removes/);
});

test('a removed string that was never in the catalog raises no rename flag', () => {
    const flagged = guard({ 'Generate Tax Statements': [] }, { 'Old Internal Label': [] }, ['Save']);
    assert.equal(whys(flagged, 'Generate Tax Statements'), '');
});

test('report carries the marker, the count and the label escape hatch', () => {
    const report = renderGuardReport([{ term: 'Bad Term', why: ['looks like code or a variable name'], locations: [{ file: 'src/a.php', line: 3 }] }]);
    assert.ok(report.startsWith(MARKER));
    assert.match(report, /1 new term to look at/);
    assert.match(report, /locale-terms-ok/);
    assert.match(renderGuardReport([]), /No questionable new terms/);
});
