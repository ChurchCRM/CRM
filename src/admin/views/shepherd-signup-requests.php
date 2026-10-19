<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

function shepherdStatusClass(string $status): string
{
    return match ($status) {
        'pending_review' => 'bg-yellow-lt',
        'approved' => 'bg-green-lt',
        'rejected' => 'bg-red-lt',
        default => 'bg-secondary-lt',
    };
}
?>
<div class="container-xl">
  <?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><?= InputUtils::escapeHTML($notice) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= InputUtils::escapeHTML($error) ?></div><?php endif; ?>

  <?php if ($requests === []): ?>
    <div class="empty"><div class="empty-icon"><i class="fa-solid fa-user-clock"></i></div><p class="empty-title">No account requests</p></div>
  <?php endif; ?>

  <?php foreach ($requests as $signup): ?>
    <article class="card mb-3">
      <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <h2 class="card-title mb-0"><?= InputUtils::escapeHTML($signup['first_name'] . ' ' . $signup['last_name']) ?></h2>
        <span class="badge <?= shepherdStatusClass((string) $signup['status']) ?>"><?= InputUtils::escapeHTML(str_replace('_', ' ', (string) $signup['status'])) ?></span>
        <span class="ms-auto text-secondary small"><?= InputUtils::escapeHTML((string) $signup['created_at']) ?> UTC</span>
      </div>
      <div class="card-body">
        <dl class="row mb-3">
          <dt class="col-sm-2">Email</dt><dd class="col-sm-10"><?= InputUtils::escapeHTML((string) $signup['email']) ?></dd>
          <dt class="col-sm-2">Username</dt><dd class="col-sm-10"><code><?= InputUtils::escapeHTML((string) $signup['username']) ?></code></dd>
          <?php if (!empty($signup['note'])): ?><dt class="col-sm-2">Note</dt><dd class="col-sm-10"><?= nl2br(InputUtils::escapeHTML((string) $signup['note'])) ?></dd><?php endif; ?>
          <?php if (!empty($signup['rejection_reason'])): ?><dt class="col-sm-2">Rejection reason</dt><dd class="col-sm-10"><?= InputUtils::escapeHTML((string) $signup['rejection_reason']) ?></dd><?php endif; ?>
        </dl>

        <?php if ($signup['status'] === 'pending_review'): ?>
          <div class="row g-4">
            <form class="col-lg-7" method="post" action="<?= $sRootPath ?>/admin/shepherd/signup-requests/<?= (int) $signup['id'] ?>/approve">
              <?= CSRFUtils::getTokenInputField('shepherd_approve') ?>
              <h3 class="h4">Approve</h3>
              <div class="mb-3"><label class="form-label" for="person-<?= (int) $signup['id'] ?>">Link to an existing person (optional)</label>
                <select class="form-select" id="person-<?= (int) $signup['id'] ?>" name="existing_person_id">
                  <option value="0">Create a new Visitor record</option>
                  <?php foreach ($people as $person): ?><option value="<?= (int) $person->getId() ?>"><?= InputUtils::escapeHTML($person->getLastName() . ', ' . $person->getFirstName() . ($person->getEmail() ? ' — ' . $person->getEmail() : '')) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3"><label class="form-label" for="profile-<?= (int) $signup['id'] ?>">Access profile</label>
                <select class="form-select" id="profile-<?= (int) $signup['id'] ?>" name="profile" required>
                  <option value="self_service">Self-service — own household only</option>
                  <option value="staff">Staff</option>
                  <option value="treasurer">Treasurer</option>
                  <option value="administrator">Administrator — 2FA required</option>
                </select>
              </div>
              <button class="btn btn-success" type="submit"><i class="fa-solid fa-check me-1"></i>Approve and send setup link</button>
            </form>
            <form class="col-lg-5" method="post" action="<?= $sRootPath ?>/admin/shepherd/signup-requests/<?= (int) $signup['id'] ?>/reject">
              <?= CSRFUtils::getTokenInputField('shepherd_reject') ?>
              <h3 class="h4">Reject</h3>
              <div class="mb-3"><label class="form-label" for="reason-<?= (int) $signup['id'] ?>">Reason (kept in the audit record)</label><textarea class="form-control" id="reason-<?= (int) $signup['id'] ?>" name="reason" maxlength="500" required></textarea></div>
              <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-xmark me-1"></i>Reject request</button>
            </form>
          </div>
        <?php elseif ($signup['status'] === 'approved' && empty($signup['password_setup_used_at'])): ?>
          <form method="post" action="<?= $sRootPath ?>/admin/shepherd/signup-requests/<?= (int) $signup['id'] ?>/resend-password">
            <?= CSRFUtils::getTokenInputField('shepherd_resend_password') ?>
            <button class="btn btn-outline-primary" type="submit">Replace and resend password setup link</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
