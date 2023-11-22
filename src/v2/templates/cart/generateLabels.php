<?php

echo <<<'EOD'
<div class="card">
    <div class="card-header with-border">
        <h3 class="card-title"><?= gettext('Generate Labels') ?></h3>
    </div>
    <div class="card-body">
        <form method="get" action="Reports/PDFLabel.php" name="labelform">
            <table class="table table-responsive">
EOD;
LabelGroupSelect('groupbymode');

echo '  <tr><td>' . gettext('Bulk Mail Presort') . '</td>';
echo '  <td>';
echo '  <input name="bulkmailpresort" type="checkbox" onclick="codename()"';
echo '  id="BulkMailPresort" value="1" ';
if (array_key_exists('buildmailpresort', $_COOKIE) && $_COOKIE['bulkmailpresort']) {
    echo 'checked';
}
echo '  ><br></td></tr>';

echo '  <tr><td>' . gettext('Quiet Presort') . '</td>';
echo '  <td>';
echo '  <input ';
if (array_key_exists('buildmailpresort', $_COOKIE) && !$_COOKIE['bulkmailpresort']) {
    echo 'disabled ';
}   // This would be better with $_SESSION variable
// instead of cookie ... (save $_SESSION in MySQL)
echo 'name="bulkmailquiet" type="checkbox" onclick="codename()"';
echo '  id="QuietBulkMail" value="1" ';
if (array_key_exists('bulkmailquiet', $_COOKIE) && $_COOKIE['bulkmailquiet'] && array_key_exists('buildmailpresort', $_COOKIE) && $_COOKIE['bulkmailpresort']) {
    echo 'checked';
}
echo '  ><br></td></tr>';

ToParentsOfCheckBox('toparents');
LabelSelect('labeltype');
FontSelect('labelfont');
FontSizeSelect('labelfontsize');
StartRowStartColumn();
IgnoreIncompleteAddresses();
LabelFileType();

echo <<<'EOD'
                <tr>
                    <td></td>
                    <td><input type="submit" class="btn btn-primary"
                               value="<?= gettext('Generate Labels') ?>" name="Submit"></td>
                </tr>
            </table>
        </form>
        </td></tr></table>
    </div>
    <!-- /.box-body -->
</div>
EOD;
