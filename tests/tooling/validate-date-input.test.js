const test = require('node:test');
const assert = require('node:assert/strict');
const { findViolations } = require('../../scripts/lint/validate-date-input');

test('flags strtotime and createFromFormat on request input', () => {
    for (const source of [
        "$ts = strtotime($_POST['Date']);",
        "$d = DateTime::createFromFormat('m/d/Y', $request->getQueryParams()['start']);",
        "$d = strtotime($_GET['from']);",
    ]) {
        assert.equal(findViolations('src/SomePage.php', source).length, 1, source);
    }
});

test('flags the removed filterDate helper', () => {
    assert.equal(findViolations('src/SomePage.php', "$d = InputUtils::filterDate($_POST['Date']);").length, 1);
});

test('flags an ISO date printed into a date-picker field', () => {
    const source = `<input class="form-control date-picker" name="D" value="<?= date('Y-m-d') ?>">`;
    assert.equal(findViolations('src/SomePage.php', source).length, 1);
});

test('allows the DateTimeUtils entry points and unrelated date code', () => {
    for (const source of [
        "$d = DateTimeUtils::normalizeDate($_POST['Date']);",
        `<input class="form-control date-picker" name="D" value="<?= DateTimeUtils::formatForDatePicker(date('Y-m-d')) ?>">`,
        "$ts = strtotime($row['created']);",
        `<input type="date" name="D" value="<?= date('Y-m-d') ?>">`,
    ]) {
        assert.deepEqual(findViolations('src/SomePage.php', source), [], source);
    }
});

test('does not flag DateTimeUtils itself', () => {
    assert.deepEqual(findViolations('src/ChurchCRM/Utils/DateTimeUtils.php', "strtotime($_POST['x'])"), []);
});

test('flags strtotime on a request date held in a variable', () => {
    const source = "$startDate = $input['startDate'] ?? '';\nif (strtotime($startDate) === false) {}";
    assert.equal(findViolations('src/SomePage.php', source).length, 1);
});

test('allows a request date held in a variable once DateTimeUtils reads it', () => {
    const source = "$startDate = $input['startDate'] ?? '';\n$ts = strtotime(DateTimeUtils::parseIsoDate($startDate));";
    assert.deepEqual(findViolations('src/SomePage.php', source), []);
});
