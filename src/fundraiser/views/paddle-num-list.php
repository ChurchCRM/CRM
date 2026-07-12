<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card">
<div class="card-body">
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-secondary" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers?selectAll=1">
      <?= gettext('Select all') ?>
    </a>
    <a class="btn btn-secondary" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers">
      <?= gettext('Select none') ?>
    </a>
    <a class="btn btn-secondary" href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor">
      <?= gettext('Add Buyer') ?>
    </a>
    <?php /* Generate-statements form wraps ONLY the button; JS guards selection and populates hidden Chk* inputs */ ?>
    <form id="generateStatementsForm" method="post"
          action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/reports/statement">
      <input type="submit" class="btn btn-primary" value="<?= gettext('Generate Statements for Selected') ?>"
             name="GenerateStatements">
    </form>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th><?= gettext('Select') ?></th>
        <th><?= gettext('Number') ?></th>
        <th><?= gettext('Buyer') ?></th>
        <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($paddleNums as $aRow): ?>
        <?php
          $pn_ID          = (int) $aRow['pn_ID'];
          $pn_Num         = (int) $aRow['pn_Num'];
          $buyerFirstName = $aRow['buyerFirstName'];
          $buyerLastName  = $aRow['buyerLastName'];
        ?>
        <tr>
          <td>
            <input class="form-check-input pledge-select"
                   type="checkbox"
                   data-pn-id="<?= $pn_ID ?>"
                   <?= $selectAll ? 'checked' : '' ?>>
          </td>
          <td>
            <a href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor/<?= $pn_ID ?>">
              <?= $pn_Num ?>
            </a>
          </td>
          <td>
            <?= InputUtils::escapeHTML($buyerFirstName) . ' ' . InputUtils::escapeHTML($buyerLastName) ?>&nbsp;
          </td>
          <td class="w-1">
            <div class="dropdown">
              <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown"
                      data-bs-display="static" aria-expanded="false">
                <i class="ti ti-dots-vertical"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item"
                   href="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/editor/<?= $pn_ID ?>">
                  <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                </a>
                <div class="dropdown-divider"></div>
                <?php /* Standalone form — NOT nested inside the statement form above */ ?>
                <form method="post"
                      action="<?= $sRootPath ?>/fundraiser/<?= (int) $fundraiserId ?>/paddle-numbers/<?= $pn_ID ?>/delete"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode(gettext('Delete this paddle number?'))) ?>)">
                  <?= CSRFUtils::getTokenInputField('paddle_num_delete') ?>
                  <button type="submit" class="dropdown-item text-danger border-0 bg-transparent">
                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                  </button>
                </form>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
  var noSelectionMsg = <?= json_encode(gettext('Please select at least one buyer to generate statements.')) ?>;

  document.getElementById('generateStatementsForm').addEventListener('submit', function (e) {
    var checked = document.querySelectorAll('.pledge-select:checked');

    // Guard: require at least one checkbox selected
    if (!checked.length) {
      e.preventDefault();
      window.CRM.notify(noSelectionMsg, { type: 'warning' });
      return;
    }

    // Remove any previously injected hidden fields to avoid duplicates on re-submit
    this.querySelectorAll('input[type="hidden"][name^="Chk"]').forEach(function (el) { el.remove(); });

    // Inject one hidden field per selected paddle so the server knows which ones to include
    var form = this;
    checked.forEach(function (cb) {
      var input   = document.createElement('input');
      input.type  = 'hidden';
      input.name  = 'Chk' + cb.dataset.pnId;
      input.value = '1';
      form.appendChild(input);
    });
  });
}());
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
