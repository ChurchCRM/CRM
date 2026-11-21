<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\Service\FamilyPortalInvitationService;
use ChurchCRM\Service\FamilyVerificationStatusService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Service\PropertyService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PersonDeleteGuard;
use Propel\Runtime\ActiveQuery\Criteria;

$sPageTitle = InputUtils::escapeHTML($family->getName());
$sPageSubtitle = gettext('Family Profile') . ' — ID: ' . $family->getId();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Server-side "Send email" needs the Email permission and a working, enabled SMTP setup.
$canSendEmail = AuthenticationManager::getCurrentUser()->isEmailEnabled() && SystemConfig::isEmailEnabled();

$familyAddress = $family->getAddress();

$memberCount = count($family->getPeople());
$livingMemberCount = count($family->getLivingPeople());
$inactiveMemberCount = count(array_filter($family->getLivingPeople(), static fn ($member): bool => !$member->isActive()));
$deceasedMemberCount = $memberCount - $livingMemberCount;

// Build active events list for the family check-in modal (#6838)
$showFamilyCheckin = AuthenticationManager::getCurrentUser()->canManageEvents();
$activeEventsForCheckin = [];
$familyPersonIds = [];
if ($showFamilyCheckin) {
    foreach ($family->getLivingPeople() as $member) {
        $familyPersonIds[] = (int) $member->getId();
    }
    $activeEvents = EventQuery::create()
        ->filterByInActive(1, Criteria::NOT_EQUAL)
        ->filterByStart(['min' => date('Y-m-d 00:00:00', strtotime('-1 day'))])
        ->orderByStart()
        ->limit(50)
        ->find();
    foreach ($activeEvents as $evt) {
        $activeEventsForCheckin[] = [
            'id' => (int) $evt->getId(),
            'title' => $evt->getTitle(),
            'date' => $evt->getStart('M j, Y g:i A'),
        ];
    }
}

// Group family members by role
$headPeople = $family->getHeadPeople();
$spousePeople = $family->getSpousePeople();
$keyPeople = array_merge($headPeople, $spousePeople);

$adultsWithEmail = $family->getAdultsWithEmail();
$familyMailRecipient = $family->getEmailRecipient();
$familyMailEmail = $familyMailRecipient?->getEmail() ?? '';
$familyEmailMD5 = $familyMailEmail !== '' ? md5(strtolower($familyMailEmail)) : '';
$childPeople = $family->getChildPeople();
$otherPeople = $family->getOtherPeople();

// Identify any deceased persons who were the family Head (for succession prompt)
$deceasedHeadNames = [];
foreach ($headPeople as $headPerson) {
    if ($headPerson->isDeceased()) {
        $deceasedHeadNames[] = $headPerson->getFullName();
    }
}

$assignedFamilyProperties = PropertyService::getAssigned($family);
$allFamilyProperties = PropertyService::getAll($family);

$canEditRecords = AuthenticationManager::getCurrentUser()->isEditRecordsEnabled();
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentFamily = <?= $family->getId() ?>;
    window.CRM.currentFamilyName = <?= InputUtils::jsonEncodeForScript($family->getName()) ?>;
    window.CRM.currentActive = <?= $family->isActive() ?"true" :"false" ?>;
    window.CRM.currentFamilyView = 2;
    window.CRM.currentFYId = <?= (int) $currentFYId ?>;
    window.CRM.currentFY = <?= InputUtils::jsonEncodeForScript($currentFY) ?>;
    window.CRM.familyEmail = <?= InputUtils::jsonEncodeForScript($familyMailEmail) ?>;
    window.CRM.familyEmailMD5 ="<?= $familyEmailMD5 ?>";
    <?php if ($showFamilyCheckin): ?>
    window.CRM.familyCheckin = {
        familyPersonIds: <?= InputUtils::jsonEncodeForScript($familyPersonIds) ?>,
        activeEvents: <?= InputUtils::jsonEncodeForScript($activeEventsForCheckin) ?>
    };
    <?php endif; ?>
</script>

<?php
// Tax document email result notifications
$taxEmailSent  = filter_input(INPUT_GET, 'TaxEmailSent', FILTER_VALIDATE_INT);
$taxEmailError = filter_input(INPUT_GET, 'TaxEmailError', FILTER_DEFAULT);
?>
<div id="family-deactivated" class="alert alert-warning d-none">
    <strong><?= gettext("This Family is Inactive") ?> </strong>
</div>

<?php if ($taxEmailSent) { ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-check-circle me-2"></i>
    <?= sprintf(gettext('%d giving statement emailed to the family successfully.'), (int)$taxEmailSent) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= gettext('Close') ?>"></button>
</div>
<?php } elseif ($taxEmailError === 'NoEmail') { ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>
    <?= gettext('Unable to email giving statement: this family has no email address on file.') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= gettext('Close') ?>"></button>
</div>
<?php } elseif ($taxEmailError === 'NoData') { ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-triangle-exclamation me-2"></i>
    <?= gettext('Unable to email giving statement: no payment data found for that year.') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= gettext('Close') ?>"></button>
</div>
<?php } elseif ($taxEmailError === 'SendFailed') { ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-circle-xmark me-2"></i>
    <?= gettext('Giving statement email failed to send. Please check your email server settings.') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= gettext('Close') ?>"></button>
</div>
<?php } ?>

<?php $currentUser = AuthenticationManager::getCurrentUser(); ?>
<div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
    <a href="<?= SystemURLs::getRootPath()?>/people/family" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Back to Families'); ?>
    </a>
    <div class="btn-group" role="group" aria-label="<?= gettext('Family Navigation'); ?>">
        <a id="lastFamily" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
        <a id="nextFamily" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
</div>

<div class="card mb-3" id="family-profile-header">
    <div class="card-body">
        <div class="d-flex gap-3">
            <div class="position-relative profile-photo-thumb flex-shrink-0">
                <a href="#" id="uploadImageTrigger" class="d-block h-100" title="<?= $canEditRecords ? gettext("Click to upload photo") : gettext("View Photo") ?>">
                    <img data-image-entity-type="family" data-image-entity-id="<?= $family->getId() ?>" alt="" class="photo-profile rounded">
                </a>
                <button type="button"
                        class="photo-view-overlay btn btn-sm position-absolute bottom-0 end-0 m-1 d-none"
                        data-entity-type="family"
                        data-entity-id="<?= $family->getId() ?>"
                        title="<?= gettext('View full photo') ?>"
                        aria-label="<?= gettext('View full photo') ?>">
                    <i class="fa-solid fa-magnifying-glass photo-zoom-icon" aria-hidden="true"></i>
                </button>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <?php if ($family->isActive()) { ?>
                    <span class="badge bg-success-lt text-success"><i class="fa-solid fa-circle me-1"></i><?= gettext('Active') ?></span>
                    <?php } else { ?>
                    <span class="badge bg-secondary-lt text-secondary"><i class="fa-solid fa-circle me-1"></i><?= gettext('Inactive') ?></span>
                    <?php } ?>
                    <span class="badge bg-primary-lt text-primary"><i class="fa-solid fa-people-roof me-1"></i><?= $livingMemberCount ?> <?= $livingMemberCount === 1 ? gettext('Member') : gettext('Members') ?></span>
                    <?php if ($inactiveMemberCount > 0) { ?>
                    <span class="badge bg-secondary-lt text-secondary" id="family-inactive-count"><i class="fa-solid fa-user-slash me-1"></i><?= $inactiveMemberCount ?> <?= gettext('Inactive') ?></span>
                    <?php } ?>
                    <?php if ($deceasedMemberCount > 0) { ?>
                    <span class="badge bg-secondary-lt text-secondary" id="family-deceased-count"><i class="fa-solid fa-cross me-1"></i><?= $deceasedMemberCount ?> <?= gettext('deceased') ?></span>
                    <?php } ?>
                    <?php if ($family->getNeedsReview()) { ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/people/self-register" class="badge bg-warning-lt text-warning text-decoration-none"><i class="fa-solid fa-user-clock me-1"></i><?= gettext('Pending review') ?></a>
                    <?php if ($canEditRecords) { ?>
                    <button type="button" id="approveFamily" class="btn btn-sm btn-success py-0"><i class="fa-solid fa-check me-1"></i><?= gettext('Approve') ?></button>
                    <?php } ?>
                    <?php } ?>
                </div>
                <div class="d-flex flex-wrap column-gap-3 row-gap-1 text-body-secondary mb-3">
                    <?php if (!SystemConfig::getBooleanValue("bHideFamilyNewsletter")) { ?>
                    <span><i class="fa-solid fa-newspaper me-1"></i><?= gettext("Newsletter") ?>: <span class="<?= ($family->isSendNewsletter() ? "text-success" : "text-danger") ?>"><i class="fa-solid fa-<?= ($family->isSendNewsletter() ? "check" : "times") ?>"></i></span></span>
                    <?php } ?>
                    <?php if ($family->getEnvelope()) { ?>
                    <span><i class="fa-solid fa-envelope me-1"></i><?= gettext('Envelope') ?> #<?= $family->getEnvelope() ?></span>
                    <?php } ?>
                    <?php if (!SystemConfig::getBooleanValue("bHideWeddingDate") && !empty($family->getWeddingdate())) { ?>
                    <span><i class="fa-solid fa-ring me-1"></i><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?></span>
                    <?php } ?>
                </div>
                <div class="d-flex flex-wrap align-items-center column-gap-4 row-gap-2" id="family-contact">
                    <?php if (!empty($family->getHomePhone())) { ?>
                    <span class="text-nowrap" title="<?= gettext('Home') ?>">
                        <i class="fa-solid fa-house me-1 text-body-secondary"></i><a href="tel:<?= InputUtils::escapeAttribute($family->getHomePhone()) ?>"><?= InputUtils::escapeHTML($family->getHomePhone()) ?></a>
                        <button class="btn btn-sm btn-ghost-secondary py-0 px-1 copy-phone-btn" type="button" data-phone="<?= InputUtils::escapeAttribute($family->getHomePhone()) ?>" title="<?= gettext('Copy to clipboard') ?>"><i class="fa-solid fa-copy"></i></button>
                    </span>
                    <?php } ?>
                    <?php if ($canSendEmail && $familyMailRecipient !== null) { ?>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="family-email-composer"
                            data-email-composer
                            data-email-family-id="<?= (int) $family->getId() ?>"
                            data-email-address="<?= InputUtils::escapeAttribute($familyMailRecipient->getEmail()) ?>"
                            data-email-name="<?= InputUtils::escapeAttribute(sprintf(gettext('%s Family'), $family->getName())) ?>"
                            data-email-title="<?= InputUtils::escapeAttribute(sprintf(gettext('Email %s Family'), $family->getName())) ?>"
                            title="<?= gettext('Send email from ChurchCRM') ?>">
                        <i class="fa-solid fa-paper-plane me-1"></i><?= sprintf(gettext('Email %s Family'), InputUtils::escapeHTML($family->getName())) ?>
                    </button>
                    <?php } ?>
                    <?php if ($adultsWithEmail === []) { ?>
                    <span class="text-body-secondary small" id="family-email-none"><i class="fa-solid fa-envelope-circle-check me-1"></i><?= gettext('No head of household or spouse can receive family email.') ?></span>
                    <?php } ?>
                    <?php if ($currentUser->isFinanceEnabled()) { ?>
                    <span class="d-none text-nowrap" id="family-giving-summary" title="<?= gettext('Giving History') ?>">
                        <i class="fa-solid fa-circle-dollar-to-slot me-1 text-body-secondary"></i><a href="#giving-history" data-open-tab="giving-history"><?= gettext('YTD') ?> <span id="family-giving-ytd"></span></a>
                        <span class="text-body-secondary">· <?= gettext('Last Gift') ?>: <span id="family-giving-last"></span></span>
                    </span>
                    <?php } ?>
                    <?php if ($familyMailRecipient !== null) { ?>
                    <span class="d-none text-body-secondary" id="mailchimp-status-container">
                        <i class="fa-brands fa-mailchimp me-1"></i>Mailchimp: <span id="mailchimp-status">... <?= gettext("loading")?> ...</span>
                    </span>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer py-2 d-print-none">
        <div class="d-flex flex-wrap align-items-center gap-1">
            <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
            <a class="btn btn-ghost-primary" href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>">
                <i class="fa-solid fa-pen me-1"></i><?= gettext('Edit') ?>
            </a>
            <?php } ?>
            <button class="btn btn-ghost-secondary" id="printFamily" title="<?= gettext('Print') ?>">
                <i class="fa-solid fa-print me-sm-1"></i><span class="d-none d-sm-inline"><?= gettext('Print') ?></span>
            </button>
            <button class="btn btn-ghost-success AddToCart" id="AddFamilyToCart" data-cart-id="<?= $family->getId() ?>" data-cart-type="family">
                <i class="fa-solid fa-cart-plus me-1"></i><span class="cartActionDescription"><?= gettext('Cart') ?></span>
            </button>
            <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
            <button type="button" class="btn btn-ghost-info" data-open-note-family="<?= (int) $family->getId() ?>">
                <i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Add Note') ?>
            </button>
            <?php } ?>
            <?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) { ?>
            <div class="dropdown">
                <button class="btn btn-ghost-warning dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="fa-solid fa-circle-dollar-to-slot me-1"></i><?= gettext("Finance") ?>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#giving-history" data-open-tab="giving-history"><i class="fa-solid fa-list me-2"></i><?= gettext('View Giving History') ?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath()?>/finance/pledge/new?type=Pledge&familyId=<?= $family->getId() ?>">
                        <i class="fa-solid fa-hand-holding-dollar me-2"></i><?= gettext('Add Pledge') ?>
                    </a>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath()?>/finance/pledge/new?type=Payment&familyId=<?= $family->getId() ?>">
                        <i class="fa-solid fa-money-bill-wave me-2"></i><?= gettext('Add Payment') ?>
                    </a>
                    <?php if (!empty($taxYears)) { ?>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header"><i class="fa-solid fa-file-invoice-dollar me-1"></i><?= gettext('Giving Statements') ?></h6>
                    <?php foreach ($taxYears as $taxYear) { ?>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/finance/reports/giving-statements/download?familyId=<?= $family->getId() ?>&amp;year=<?= (int)$taxYear ?>" target="_blank">
                        <i class="fa-solid fa-print me-2"></i><?= sprintf(gettext('Print %s Giving Statement'), FiscalYearUtils::getFiscalYearDatesForCalendarYear((int) $taxYear)['label']) ?>
                    </a>
                    <form method="post" action="<?= SystemURLs::getRootPath() ?>/finance/reports/giving-statements/email" class="m-0">
                        <input type="hidden" name="familyId" value="<?= $family->getId() ?>">
                        <input type="hidden" name="year" value="<?= (int)$taxYear ?>">
                        <?= CSRFUtils::getTokenInputField('tax_email_' . (int)$taxYear) ?>
                        <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start">
                            <i class="fa-solid fa-envelope me-2"></i><?= sprintf(gettext('Email %s Giving Statement'), FiscalYearUtils::getFiscalYearDatesForCalendarYear((int) $taxYear)['label']) ?>
                        </button>
                    </form>
                    <?php } ?>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
            <div class="dropdown ms-auto">
                <button class="btn btn-ghost-secondary dropdown-toggle" id="family-actions-dropdown" aria-label="<?= gettext('Actions') ?>" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical me-sm-1"></i><span class="d-none d-sm-inline"><?= gettext("Actions") ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($showFamilyCheckin && $livingMemberCount > 0) { ?>
                    <button type="button" class="dropdown-item text-success fw-semibold" id="checkInFamilyBtn" data-bs-toggle="modal" data-bs-target="#familyCheckinModal">
                        <i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Check In Family') ?>
                    </button>
                    <div class="dropdown-divider"></div>
                    <?php } ?>
                    <?php if ($canEditRecords) { ?>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#confirm-verify">
                        <i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Verify Info') ?>
                    </a>
                    <?php } ?>
                    <?php if ($family->hasLatitudeAndLongitude()) { ?>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/people/map/neighbors?familyId=<?= $family->getId() ?>">
                        <i class="fa-solid fa-people-roof me-2"></i><?= gettext('Find Neighbors') ?>
                    </a>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header"><?= gettext("Photo") ?></h6>
                        <a class="dropdown-item" id="uploadImageButton" href="#"><i class="fa-solid fa-camera me-2"></i><?= gettext("Upload Photo") ?></a>
                        <?php if ($family->getPhoto()->hasUploadedPhoto()) { ?>
                            <a class="dropdown-item" id="view-larger-image-btn" href="#"><i class="fa-solid fa-magnifying-glass-plus me-2"></i><?= gettext("View Photo") ?></a>
                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#confirm-delete-image"><i class="fa-solid fa-trash-can me-2"></i><?= gettext("Delete Photo") ?></a>
                        <?php } ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" id="activateDeactivate">
                            <i class="fa-solid fa-power-off me-2"></i><?= ($family->isActive() ? gettext('Set Inactive') : gettext('Set Active')) ?>
                        </a>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger delete-family" id="deleteFamilyBtn" data-family_id="<?= $family->getId() ?>" data-family_name="<?= InputUtils::escapeAttribute($family->getName()) ?>">
                            <i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete Family') ?>
                        </button>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>


        <!-- Family Members Card (table layout matching Person page) -->
        <?php
        // Helper: section header
        function renderSectionHeader(string $label, string $icon, string $color, int $count): void { ?>
            <div class="d-flex align-items-center py-2">
                <i class="fa-solid <?= $icon ?> text-<?= $color ?> me-2"></i>
                <span class="fw-bold"><?= $label ?></span>
                <span class="badge bg-<?= $color ?>-lt text-<?= $color ?> ms-2"><?= $count ?></span>
            </div>
        <?php }

        // Helper: action dropdown for a member row
        function renderMemberActions($person): void { ?>
            <div class="dropdown d-print-none">
                <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= $person->getViewURI() ?>"><i class="fa-solid fa-eye me-2"></i><?= gettext('View') ?></a>
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()): ?>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $person->getID() ?>"><i class="fa-solid fa-pen me-2"></i><?= gettext('Edit') ?></a>
                    <?php endif; ?>
                    <button class="dropdown-item AddToCart" data-cart-id="<?= $person->getId() ?>" data-cart-type="person"><i class="fa-solid fa-cart-plus me-2"></i><?= gettext('Add to Cart') ?></button>
                    <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()): ?>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item text-danger delete-person" data-person_name="<?= InputUtils::escapeAttribute($person->getFullName()) ?>" data-person_id="<?= $person->getId() ?>" data-view="family"<?= PersonDeleteGuard::attributes((int) $person->getId()) ?>><i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete') ?></button>
                    <?php endif; ?>
                </div>
            </div>
        <?php }

        // Helper: "can sign in to the Member Portal" line under a member's email
        function renderPortalSignInStatus($person, array $portalStatuses): void {
            $status = $portalStatuses[(int) $person->getId()] ?? null;
            if ($status === null) { return; } ?>
            <div class="small mt-1" data-portal-signin-status="<?= $status['maySignIn'] ? 'yes' : 'no' ?>">
                <?php if ($status['maySignIn']) { ?>
                    <span class="text-green"><i class="fa-solid fa-circle-check me-1"></i><?= gettext('Can sign in to the Member Portal') ?></span>
                <?php } else { ?>
                    <a class="text-secondary" href="<?= $person->getViewURI() ?>#portal-signin-card"><i class="fa-solid fa-ban me-1"></i><?= gettext('Cannot sign in') ?>: <?= InputUtils::escapeHTML((string) $status['reason']) ?></a>
                <?php } ?>
            </div>
        <?php }

        // Helper: a member's email with copy / send buttons and portal sign-in status
        function renderMemberEmail($person, bool $canSendEmail, array $portalStatuses): void {
            $email = $person->getEmail();
            if (!empty($email)) { ?>
                <span class="text-nowrap">
                    <a href="mailto:<?= InputUtils::escapeAttribute($email) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($email) ?></a>
                    <button class="btn btn-sm btn-ghost-secondary py-0 px-1 copy-email-btn" type="button" data-email="<?= InputUtils::escapeAttribute($email) ?>" title="<?= gettext('Copy to clipboard') ?>"><i class="fa-solid fa-copy"></i></button>
                    <?php if ($canSendEmail) { ?>
                    <button class="btn btn-sm btn-ghost-primary py-0 px-1" type="button"
                            data-email-composer
                            data-email-person-id="<?= (int) $person->getId() ?>"
                            data-email-address="<?= InputUtils::escapeAttribute($email) ?>"
                            data-email-name="<?= InputUtils::escapeAttribute($person->getFullName()) ?>"
                            data-email-title="<?= InputUtils::escapeAttribute(sprintf(gettext('Email %s'), $person->getFullName())) ?>"
                            title="<?= gettext('Send email from ChurchCRM') ?>">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                    <?php } ?>
                </span>
            <?php }
            renderMemberPhones($person);
            renderPortalSignInStatus($person, $portalStatuses);
        }

        // Helper: a member's mobile, home and work numbers with call / text links
        function renderMemberPhones($person): void {
            $phones = [
                [$person->getCellPhone(), 'fa-mobile-screen', gettext('Mobile'), true],
                [$person->getHomePhone(), 'fa-house', gettext('Home'), false],
                [$person->getWorkPhone(), 'fa-briefcase', gettext('Work'), false],
            ];
            $shown = array_filter($phones, static fn (array $phone): bool => trim((string) $phone[0]) !== '');
            if ($shown === []) { return; } ?>
            <div class="d-flex flex-wrap column-gap-3 small mt-1 member-phones">
                <?php foreach ($shown as [$number, $icon, $label, $canText]) { ?>
                <span class="text-nowrap" title="<?= InputUtils::escapeAttribute($label) ?>">
                    <i class="fa-solid <?= $icon ?> me-1 text-body-secondary"></i><a href="tel:<?= InputUtils::escapeAttribute($number) ?>"><?= InputUtils::escapeHTML($number) ?></a>
                    <?php if ($canText) { ?>
                    <a href="sms:<?= InputUtils::escapeAttribute(preg_replace('/[^\d+]/', '', $number)) ?>" class="ms-1 text-body-secondary" title="<?= gettext('Send text message') ?>"><i class="fa-solid fa-comment-sms"></i></a>
                    <?php } ?>
                </span>
                <?php } ?>
            </div>
        <?php }

        // Helper: status badges next to a member's name (deceased, inactive, pending review)
        function renderMemberStatus($person): void {
            if ($person->isDeceased()) { ?>
                <span class="badge bg-secondary-lt text-secondary ms-2"><i class="fa-solid fa-cross me-1"></i><?= gettext('Deceased') ?></span>
            <?php } elseif (!$person->isActive()) { ?>
                <span class="badge bg-secondary-lt text-secondary ms-2"><i class="fa-solid fa-user-slash me-1"></i><?= gettext('Inactive') ?></span>
            <?php }
            if ($person->getNeedsReview()) { ?>
                <span class="badge bg-warning-lt text-warning ms-2"><i class="fa-solid fa-user-clock me-1"></i><?= gettext('Pending review') ?></span>
            <?php }
        }

        // Helper: standard member table (Key People / Other)
        function renderMemberTable(array $members, string $label, string $icon, string $color, bool $canSendEmail = false, array $portalStatuses = []): void {
            if (empty($members)) { return; } ?>
            <div class="mb-1">
                <?php renderSectionHeader($label, $icon, $color, count($members)); ?>
                <div class="table-clip-x">
                    <table class="table table-vcenter card-table mb-0 member-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th class="text-center d-none d-md-table-cell"><?= gettext('Role') ?></th>
                                <th class="d-none d-md-table-cell"><?= gettext('Birthday') ?></th>
                                <th class="member-email-cell"><?= gettext('Contact') ?></th>
                                <th class="w-1 member-actions-cell"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $person) { ?>
                                <tr class="<?= $person->isDeceased() || !$person->isActive() ? 'text-body-secondary opacity-75' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="avatar avatar-sm me-2">
                                            <a href="<?= $person->getViewURI() ?>"><?= InputUtils::escapeHTML($person->getTitle()) ?> <?= InputUtils::escapeHTML($person->getFullName()) ?></a>
                                            <?php renderMemberStatus($person); ?>
                                        </div>
                                        <div class="d-md-none small text-body-secondary mt-1"><?= InputUtils::escapeHTML($person->getFamilyRoleName()) ?><?php if ($person->getFormattedBirthDate()) { ?> · <?= $person->getFormattedBirthDate() ?><?php } ?></div>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <span class="badge bg-secondary-lt text-secondary"><?= InputUtils::escapeHTML($person->getFamilyRoleName()) ?></span>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= $person->getFormattedBirthDate() ?></td>
                                    <td class="member-email-cell"><?php renderMemberEmail($person, $canSendEmail, $portalStatuses); ?></td>
                                    <td class="member-actions-cell"><?php renderMemberActions($person); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php }

        // Helper: children table (no Role column, adds Sunday School column if enabled)
        function renderChildrenTable(array $members, string $label, string $icon, string $color, bool $canSendEmail = false, array $portalStatuses = []): void {
            if (empty($members)) { return; }

            $ssEnabled = SystemConfig::getBooleanValue('bEnabledSundaySchool');

            // Pre-fetch Sunday School groups for all children in one query (only if enabled)
            $ssGroups = [];
            if ($ssEnabled) {
                try {
                    $childIds = array_map(fn($p) => $p->getId(), $members);
                    if (!empty($childIds)) {
                        $groups = GroupQuery::create()
                            ->filterByType(4) // Sunday School type
                            ->usePerson2group2roleP2g2rQuery()
                                ->filterByPersonId($childIds)
                            ->endUse()
                            ->find();
                        foreach ($groups as $group) {
                            foreach ($group->getPerson2group2roleP2g2rs() as $p2g2r) {
                                $pid = $p2g2r->getPersonId();
                                if (in_array($pid, $childIds)) {
                                    $ssGroups[$pid][] = $group->getName();
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Query failed — show column but with no data
                    $ssGroups = [];
                }
            }
        ?>
            <div class="mb-1">
                <?php renderSectionHeader($label, $icon, $color, count($members)); ?>
                <div class="table-clip-x">
                    <table class="table table-vcenter card-table mb-0 member-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th class="d-none d-md-table-cell"><?= gettext('Birthday') ?></th>
                                <?php if ($ssEnabled) { ?>
                                <th class="d-none d-md-table-cell"><?= gettext('Sunday School') ?></th>
                                <?php } ?>
                                <th class="member-email-cell"><?= gettext('Contact') ?></th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $person) { ?>
                                <tr class="<?= $person->isDeceased() || !$person->isActive() ? 'text-body-secondary opacity-75' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="avatar avatar-sm me-2">
                                            <a href="<?= $person->getViewURI() ?>"><?= InputUtils::escapeHTML($person->getTitle()) ?> <?= InputUtils::escapeHTML($person->getFullName()) ?></a>
                                            <?php renderMemberStatus($person); ?>
                                        </div>
                                        <div class="d-md-none small text-body-secondary mt-1"><?= $person->getFormattedBirthDate() ?><?php if (!empty($ssGroups[$person->getId()])) { ?> · <?= InputUtils::escapeHTML(implode(', ', $ssGroups[$person->getId()])) ?><?php } ?></div>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= $person->getFormattedBirthDate() ?></td>
                                    <?php if ($ssEnabled) { ?>
                                    <td class="d-none d-md-table-cell">
                                        <?php $personSS = $ssGroups[$person->getId()] ?? [];
                                        if (!empty($personSS)) {
                                            foreach ($personSS as $ssName) { ?>
                                                <span class="badge bg-info-lt text-info me-1"><?= InputUtils::escapeHTML($ssName) ?></span>
                                            <?php }
                                        } else { ?>
                                            <span class="text-body-secondary">—</span>
                                        <?php } ?>
                                    </td>
                                    <?php } ?>
                                    <td class="member-email-cell"><?php renderMemberEmail($person, $canSendEmail, $portalStatuses); ?></td>
                                    <td class="member-actions-cell"><?php renderMemberActions($person); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>

        <?php if (!empty($deceasedHeadNames)) { ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong><?= gettext('Head of Household Update Needed') ?></strong>
            <?= sprintf(
                gettext('%s was the Head of this family and has been marked deceased. Please assign a new Head of Household.'),
                InputUtils::escapeHTML(implode(', ', $deceasedHeadNames))
            ) ?>
            <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>" class="btn btn-sm btn-warning ms-2"><?= gettext('Edit Family') ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= gettext('Close') ?>"></button>
        </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-people-roof me-1"></i> <?= gettext("Family Members") ?></h3>
                <span class="badge bg-primary-lt text-primary ms-2"><?= $livingMemberCount ?></span>
                <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) { ?>
                <a class="btn btn-sm btn-outline-primary ms-auto" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?FamilyID=<?= $family->getId() ?>">
                    <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add Member') ?>
                </a>
                <?php } ?>
            </div>
            <div class="card-body">
                <?php renderMemberTable($keyPeople, gettext("Key People"), 'fa-crown', 'warning', $canSendEmail, $portalSignInStatuses ?? []); ?>
                <?php renderChildrenTable($childPeople, gettext("Children"), 'fa-children', 'info', $canSendEmail, $portalSignInStatuses ?? []); ?>
                <?php renderMemberTable($otherPeople, gettext("Other Members"), 'fa-user-group', 'secondary', $canSendEmail, $portalSignInStatuses ?? []); ?>
            </div>
        </div>

        <?php if ($family->hasAddress() || $family->hasSecondAddress()) :
            $directionsUrl = $family->getDirectionsUrl();
            $appleDirectionsUrl = $family->getAppleMapsDirectionsUrl();
            $hasMap = $family->hasAddress() && $family->hasLatitudeAndLongitude();
        ?>
        <div class="card mb-3" id="family-address-card">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-map me-1"></i> <?= $family->hasSecondAddress() ? gettext("Primary Address") : gettext("Address") ?>
                    <?php if ($family->hasAddress() && $family->hasLatitudeAndLongitude()): ?>
                    <span class="badge bg-green-lt text-green ms-2" title="<?= gettext('Address has been geocoded (coordinates stored)') ?>"><i class="fa-solid fa-check"></i> <?= gettext('Geocoded') ?></span>
                    <?php elseif ($family->hasAddress()): ?>
                    <span class="badge bg-warning-lt text-warning ms-2" title="<?= gettext('Address entered but coordinates not yet set') ?>"><i class="fa-solid fa-triangle-exclamation"></i> <?= gettext('Unverified') ?></span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12 <?= $hasMap ? 'col-lg-5' : '' ?>" id="family-address">
                        <?php if ($family->hasAddress()): ?>
                        <a class="fs-3 d-block lh-sm" href="https://maps.google.com/?q=<?= urlencode($familyAddress) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($familyAddress) ?></a>
                        <div class="mt-3 d-flex flex-wrap gap-2 d-print-none">
                            <?php if (!empty($directionsUrl) || !empty($appleDirectionsUrl)) : ?>
                            <div class="btn-group directions-btn-group">
                                <?php if (!empty($directionsUrl)) : ?>
                                <a href="<?= InputUtils::escapeAttribute($directionsUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-diamond-turn-right me-1"></i><?= gettext('Get Directions') ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($appleDirectionsUrl)) : ?>
                                <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split directions-provider-toggle d-none" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                    <span class="visually-hidden"><?= gettext('Choose map provider') ?></span>
                                </button>
                                <div class="dropdown-menu directions-provider-menu d-none">
                                    <?php if (!empty($directionsUrl)) : ?>
                                    <a class="dropdown-item" href="<?= InputUtils::escapeAttribute($directionsUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-google me-2"></i><?= gettext('Open in Google Maps') ?></a>
                                    <?php endif; ?>
                                    <a class="dropdown-item apple-maps-option" href="<?= InputUtils::escapeAttribute($appleDirectionsUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-apple me-2"></i><?= gettext('Open in Apple Maps') ?></a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($family->hasLatitudeAndLongitude()) : ?>
                            <a href="<?= SystemURLs::getRootPath() ?>/people/map/neighbors?familyId=<?= $family->getId() ?>" class="btn btn-outline-primary">
                                <i class="fa-solid fa-people-roof me-1"></i><?= gettext('Find Neighbors') ?>
                            </a>
                            <?php endif; ?>
                            <?php if ($canEditRecords && !$family->hasLatitudeAndLongitude()) : ?>
                            <button type="button" class="btn btn-outline-success" id="refresh-coordinates-btn" data-family-id="<?= $family->getId() ?>" title="<?= gettext('Automatically detect coordinates using address') ?>">
                                <i class="fa-solid fa-location-dot me-1"></i><?= gettext('Refresh Coordinates') ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($family->hasSecondAddress()) :
                            $secondAddress = $family->getSecondaryAddress();
                            $isMailing = $family->isSecondAddressMailing();
                        ?>
                        <div class="<?= $family->hasAddress() ? 'mt-4 pt-3 border-top' : '' ?>" id="second-address-card">
                            <div class="text-body-secondary small mb-1">
                                <i class="fa-solid <?= $isMailing ? 'fa-envelope' : 'fa-house-chimney' ?> me-1"></i><strong><?= $isMailing ? gettext('Mailing Address') : gettext('Second Home') ?></strong>
                                <?php if ($isMailing) : ?>
                                <span class="badge bg-blue-lt text-blue ms-1" title="<?= gettext('Mail is sent to this address instead of the primary address') ?>"><?= gettext('Receives Mail') ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="https://maps.google.com/?q=<?= urlencode($secondAddress) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($secondAddress) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasMap) : ?>
                    <div class="col-12 col-lg-7">
                        <div class="rounded overflow-hidden"><div id="map1" class="map-canvas-md"></div></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $recordPerson = static function (?int $personId): string {
            if ($personId === null || $personId === 0) { return ''; }
            if ($personId === Person::SELF_REGISTER) { return gettext('Self Registration'); }
            $editor = PersonQuery::create()->findPk($personId);

            return $editor !== null ? $editor->getFullName() : '';
        };
        $enteredByName = $recordPerson($family->getEnteredBy());
        $editedByName = $recordPerson($family->getEditedBy());
        ?>
        <!-- Properties, custom fields and verification stay above the tabs -->
        <div class="card mb-3" id="family-overview">
            <div class="card-body">
                    <div id="overview">
                        <div class="row g-4">
                            <div class="col-12 col-lg-7">
                                <?php if (!empty($familyCustom)) { ?>
                                <div class="mb-4" id="family-custom-fields">
                                    <h4 class="mb-3"><i class="fa-solid fa-sliders me-1"></i> <?= gettext("Custom Fields") ?></h4>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($familyCustom as $customField) { ?>
                            <li class="mb-1">
                                <i class="<?= InputUtils::escapeAttribute($customField->getIcon()) ?> me-2 text-body-secondary icon-fixed-width"></i><?= InputUtils::escapeHTML($customField->getDisplayValue()) ?>:
                                <?php if ($customField->getLink()) { ?>
                                    <a href="<?= InputUtils::escapeAttribute($customField->getLink()) ?>"><?= InputUtils::escapeHTML($customField->getFormattedValue()) ?></a>
                                <?php } else {
                                    $val = $customField->getFormattedValue();
                                    if (strlen($val) > 40) { ?>
                                        <span class="d-block text-body-secondary text-truncate" title="<?= InputUtils::escapeAttribute($val) ?>"><?= InputUtils::escapeHTML($val) ?></span>
                                    <?php } else { ?>
                                        <span><?= InputUtils::escapeHTML($val) ?></span>
                                    <?php }
                                } ?>
                            </li>
                        <?php } ?>
                    </ul>
                                </div>
                                <?php } ?>
                                <div class="mb-4" id="family-properties">
                                    <div class="d-flex align-items-center mb-2">
                                        <h4 class="m-0"><i class="fa-solid fa-hashtag me-1"></i> <?= gettext("Properties") ?></h4>
                                        <?php if ($canEditRecords && count($allFamilyProperties) > count($assignedFamilyProperties)) : ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto d-print-none" id="add-family-property-btn" data-bs-toggle="modal" data-bs-target="#add-family-property-modal">
                                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Property') ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                <?php if (count($assignedFamilyProperties) === 0) : ?>
                    <div class="text-center text-body-secondary py-3">
                        <i class="fa-solid fa-tags fa-2x mb-2 d-block opacity-50"></i>
                        <p class="mb-0"><?= gettext("No properties assigned.") ?></p>
                    </div>
                <?php else : ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assignedFamilyProperties as $rp) {
                            $prop = $rp->getProperty();
                            $propType = $prop->getPropertyType();
                            $value = $rp->getPropertyValue(); ?>
                            <div class="list-group-item px-0 d-flex align-items-center">
                                <div class="me-auto">
                                    <strong><?= InputUtils::escapeHTML($prop->getProName()) ?></strong>
                                    <?php if ($propType) { ?>
                                        <span class="badge bg-secondary-lt text-secondary ms-1"><?= InputUtils::escapeHTML($propType->getPrtName()) ?></span>
                                    <?php } ?>
                                    <?php if (!empty($value)) { ?>
                                        <small class="text-body-secondary d-block"><?= InputUtils::escapeHTML($value) ?></small>
                                    <?php } ?>
                                </div>
                                <?php if ($canEditRecords && !empty($prop->getProPrompt())) { ?>
                                    <button type="button" class="btn btn-sm btn-ghost-secondary edit-property-btn" data-property_id="<?= (int) $prop->getProId() ?>" data-property_name="<?= InputUtils::escapeAttribute($prop->getProName()) ?>" data-prompt="<?= InputUtils::escapeAttribute($prop->getProPrompt()) ?>" data-value="<?= InputUtils::escapeAttribute((string) $value) ?>" title="<?= gettext('Edit') ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                <?php } ?>
                                <?php if ($canEditRecords) { ?>
                                    <button class="btn btn-sm btn-ghost-danger remove-family-property-btn" data-property_id="<?= (int) $prop->getProId() ?>" title="<?= gettext('Remove') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php endif; ?>

                
                                </div>
                            </div>
                            <div class="col-12 col-lg-5">
                                <?php if ($canEditRecords) {
                                    $verification = (new FamilyVerificationStatusService())->forFamily($family); ?>
                                <div class="mb-4" id="family-verification-card">
                                    <div class="d-flex align-items-center mb-2">
                                        <h4 class="m-0"><i class="fa-solid fa-clipboard-check me-1"></i> <?= gettext('Verification') ?></h4>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto d-print-none" id="verify-now-btn" data-bs-toggle="modal" data-bs-target="#confirm-verify">
                                            <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Verify now') ?>
                                        </button>
                                    </div>
                <ul class="list-unstyled mb-0">
                    <li class="mb-1"><i class="fa-solid fa-clipboard-check me-2 text-body-secondary icon-fixed-width"></i><?= gettext('Last verified') ?>:
                        <?php if ($verification['verified'] === null) { ?>
                        <span class="text-body-secondary"><?= gettext('Never') ?></span>
                        <?php } else { ?>
                        <?= InputUtils::escapeHTML($verification['verified']['date']) ?>
                        <span class="text-body-secondary small">(<?= $verification['verified']['byFamily'] ? gettext('by the family in the portal') : gettext('in person by staff') ?>)</span>
                        <?php } ?>
                    </li>
                    <li class="mb-1"><i class="fa-solid fa-envelope me-2 text-body-secondary icon-fixed-width"></i><?= gettext('Last invited') ?>:
                        <?= $verification['invited'] === null ? '<span class="text-body-secondary">' . gettext('Never') . '</span>' : InputUtils::escapeHTML($verification['invited']) ?>
                    </li>
                    <li class="mb-1"><i class="fa-solid fa-right-to-bracket me-2 text-body-secondary icon-fixed-width"></i><?= gettext('Last portal sign-in') ?>:
                        <?= $verification['lastSignIn'] === null ? '<span class="text-body-secondary">' . gettext('Never') . '</span>' : InputUtils::escapeHTML($verification['lastSignIn']) ?>
                    </li>
                </ul>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
            </div>
            <div class="card-footer small text-body-secondary d-print-none" id="family-record-history">
                <?php if ($family->getDateEntered()) { ?>
                <span class="text-nowrap me-3"><i class="fa-solid fa-circle-plus me-1"></i><?= gettext('Created') ?> <?= DateTimeUtils::formatDate($family->getDateEntered(), false) ?><?= $enteredByName !== '' ? ' ' . gettext('by') . ' ' . InputUtils::escapeHTML($enteredByName) : '' ?></span>
                <?php } ?>
                <?php if ($family->getDateLastEdited()) { ?>
                <span class="text-nowrap me-3"><i class="fa-solid fa-pen me-1"></i><?= gettext('Last edited') ?> <?= DateTimeUtils::formatDate($family->getDateLastEdited(), false) ?><?= $editedByName !== '' ? ' ' . gettext('by') . ' ' . InputUtils::escapeHTML($editedByName) : '' ?></span>
                <?php } ?>
                <?php if (!$family->isActive() && $family->getDateDeactivated()) { ?>
                <span class="text-nowrap"><i class="fa-solid fa-power-off me-1"></i><?= gettext('Deactivated') ?> <?= DateTimeUtils::formatDate($family->getDateDeactivated(), false) ?></span>
                <?php } ?>
            </div>
        </div>

        <!-- Tabbed Content -->
        <?php $noteCount = count(array_filter($familyTimeline, static fn (array $item): bool => ($item['category'] ?? 'notes') === 'notes')); ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <ul class="nav nav-pills card-header-pills flex-nowrap overflow-x-auto text-nowrap">
                    <li class="nav-item">
                        <a class="nav-link active" id="nav-item-notes" href="#notes" data-bs-toggle="tab"><i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Notes') ?>
                            <?php if ($noteCount > 0) : ?><span class="badge bg-secondary-lt text-secondary ms-1"><?= $noteCount ?></span><?php endif; ?>
                        </a>
                    </li>
                    <?php if ($currentUser->isFinanceEnabled()) { ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-giving" href="#giving-history" data-bs-toggle="tab"><i class="fa-solid fa-circle-dollar-to-slot me-1"></i><?= gettext("Giving History") ?></a>
                    </li>
                    <?php } ?>
                    <li class="nav-item">
                        <a class="nav-link" id="nav-item-timeline" href="#timeline" data-bs-toggle="tab"><i class="fa-solid fa-clock me-1"></i><?= gettext('Timeline') ?></a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane active timeline-container" id="notes">
                        <?php $timeline = $familyTimeline; $timelineScope = 'notes'; include __DIR__ . '/timeline-events.php'; ?>
                    </div>

                    <div class="tab-pane timeline-container" id="timeline" data-timeline-default="all">
                        <?php $timeline = $familyTimeline; $timelineScope = 'activity'; include __DIR__ . '/timeline-events.php'; ?>
                    </div>

                    <?php if ($currentUser->isFinanceEnabled()) { ?>
                    <div class="tab-pane" id="giving-history">
                        <div class="d-flex align-items-center flex-wrap gap-3 mb-2">
                <h3 class="card-title m-0"><i class="fa-solid fa-circle-dollar-to-slot me-1"></i> <?= gettext("Giving History") ?></h3>
                <span id="ytd-total-badge" class="badge bg-green-lt text-green ms-1 d-none"></span>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <ul class="nav nav-pills" role="tablist">
                        <li class="nav-item"><a class="nav-link active pledge-type-pill" href="#" data-filter=""><?= gettext("All") ?></a></li>
                        <li class="nav-item"><a class="nav-link pledge-type-pill" href="#" data-filter="Pledge"><?= gettext("Pledges") ?></a></li>
                        <li class="nav-item"><a class="nav-link pledge-type-pill" href="#" data-filter="Payment"><?= gettext("Payments") ?></a></li>
                    </ul>
                    <span class="vr mx-1"></span>
                    <ul class="nav nav-pills" role="tablist">
                        <li class="nav-item"><a class="nav-link pledge-fy-pill" href="#" data-fy="0"><?= gettext("All Time") ?></a></li>
                        <!-- Current FY is always rendered and active by default -->
                        <li class="nav-item">
                            <a class="nav-link active pledge-fy-pill" href="#" data-fy="<?= (int) $currentFYId ?>">
                                <?= InputUtils::escapeHTML(FinancialService::formatFiscalYear($currentFYId)) ?>
                            </a>
                        </li>
                        <!-- Additional years from this family's historical data (skip current FY, already shown) -->
                        <?php foreach ($familyAvailableFyids as $fyId): ?>
                        <?php if ($fyId !== $currentFYId): ?>
                        <li class="nav-item">
                            <a class="nav-link pledge-fy-pill" href="#" data-fy="<?= (int) $fyId ?>">
                                <?= InputUtils::escapeHTML(FinancialService::formatFiscalYear($fyId)) ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                        </div>
                        <div class="table-clip-x">
                <table id="pledge-payment-v2-table" class="table table-vcenter card-table w-100">
                    <tbody></tbody>
                    <tfoot>
                        <tr id="giving-summary-row" class="d-none">
                            <td colspan="7" class="border-top">
                                <div class="d-flex gap-4 justify-content-end py-3 pe-2">
                                    <span><strong><?= gettext("Pledged") ?>:</strong> <span id="giving-total-pledged" class="text-primary fw-bold">—</span></span>
                                    <span><strong><?= gettext("Paid") ?>:</strong> <span id="giving-total-paid" class="text-success fw-bold">—</span></span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

</div>


<?php if ($canEditRecords && count($allFamilyProperties) > 0) : ?>
<div class="modal fade" id="add-family-property-modal" data-edit-title="<?= InputUtils::escapeAttribute(gettext('Edit Property')) ?>" data-save-label="<?= InputUtils::escapeAttribute(gettext('Save')) ?>" tabindex="-1" aria-labelledby="add-family-property-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="add-family-property-title"><i class="fa-solid fa-hashtag me-2"></i><?= gettext('Add Property') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="assign-family-property-form" onsubmit="return false;">
                    <div class="mb-3">
                        <select name="PropertyId" id="input-family-properties" class="form-select" data-placeholder="<?= gettext('Choose a property...') ?>">
                                    <option value=""></option>
                                    <?php
                                    $valueByPropId = [];
                                    foreach ($assignedFamilyProperties as $rp) {
                                        $valueByPropId[(int) $rp->getProperty()->getProId()] = $rp->getPropertyValue();
                                    }
                                    foreach ($allFamilyProperties as $prop) {
                                        $pid = (int) $prop->getProId();
                                        if (array_key_exists($pid, $valueByPropId)) { continue; }
                                        $attrs = 'value="' . $pid . '"';
                                        $prompt = $prop->getProPrompt();
                                        if (!empty($prompt)) {
                                            $attrs .= ' data-pro_Prompt="' . InputUtils::escapeAttribute($prompt) . '"';
                                        }
                                        $optionText = InputUtils::escapeHTML($prop->getProName());
                                        echo "<option {$attrs}>{$optionText}</option>";
                                    } ?>
                                </select>
                    </div>
                    <div id="family-property-prompt-box"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button id="assign-family-property-btn" type="button" class="btn btn-primary ms-auto"><i class="fa-solid fa-check me-1"></i><?= gettext('Assign') ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Leaflet map (loaded only if geocoded) -->

<?php if ($family->hasAddress() && $family->hasLatitudeAndLongitude()) : ?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.familyMapConfig = <?= InputUtils::jsonEncodeForScript(['lat' => (float) $family->getLatitude(), 'lng' => (float) $family->getLongitude()]) ?>;
</script>
<?php endif; ?>
<?php if ($showFamilyCheckin): ?>
<!-- Family Check-In Modal (#6838) -->
<div class="modal fade" id="familyCheckinModal" tabindex="-1" aria-labelledby="familyCheckinModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyCheckinModalLabel">
                    <i class="fa-solid fa-clipboard-check me-2 text-success"></i>
                    <?= gettext('Check In Family') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary">
                    <?= sprintf(gettext('Check in all %d family members to a single event.'), $livingMemberCount) ?>
                </p>
                <div class="mb-3">
                    <label for="familyCheckinEventSelect" class="form-label fw-bold"><?= gettext('Select Event') ?></label>
                    <select class="form-select" id="familyCheckinEventSelect">
                        <option value=""><?= gettext('Choose an event...') ?></option>
                    </select>
                </div>
                <div id="familyCheckinNoEvents" class="alert alert-warning d-none">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= gettext('No active events found.') ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/event/editor"><?= gettext('Create one now') ?></a>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-success" id="familyCheckinSubmit" disabled>
                    <i class="fa-solid fa-check me-1"></i><?= gettext('Check In') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-family-view.min.js') ?>"></script>
<?php if ($canSendEmail) : ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-composer.min.js') ?>" defer nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>

<!-- Photo uploader bundle - loaded only on this page -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/js/FamilyView.js') ?>"></script>

<!-- Photos start -->
<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog"
     aria-labelledby="delete-Image-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm Delete") ?></h4>
            </div>

            <div class="modal-body">
                <p><?= gettext("You are about to delete the profile photo, this procedure is irreversible.") ?></p>

                <p><?= gettext("Do you want to proceed?") ?></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Copy photo uploader function from temporary storage to window.CRM
    // This must happen after Header-function.php initializes window.CRM
    if (window._CRM_createPhotoUploader) {
        window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
    } else {
        console.error('Photo uploader function not found in window._CRM_createPhotoUploader');
    }

    // Initialize photo uploader when window loads
    window.addEventListener('load', function() {
        if (typeof window.CRM.createPhotoUploader !== 'function') {
            console.error('window.CRM.createPhotoUploader is not a function');
            return;
        }

        window.CRM.photoUploader = window.CRM.createPhotoUploader({
            uploadUrl: window.CRM.root +"/api/family/" + window.CRM.currentFamily +"/photo",
            maxFileSize: window.CRM.maxUploadSizeBytes,
            photoHeight: <?= Photo::PHOTO_HEIGHT ?>,
            photoWidth: <?= Photo::PHOTO_WIDTH ?>,
            onComplete: function() {
                location.reload();
            }
        });
    });

    // Set up click handlers for photo upload (both action menu item and photo click)
    $(document).on('click', '#uploadImageButton, #uploadImageTrigger', function(e) {
        e.preventDefault();
        if (window.CRM && window.CRM.photoUploader) {
            window.CRM.photoUploader.show();
        } else {
            console.error('Photo uploader not initialized!');
        }
    });
</script>
<!-- Photos end -->

<?php if ($canEditRecords) {
    $portalInvitations = new FamilyPortalInvitationService();
    $invitationRecipients = $portalInvitations->recipientsFor($family);
    $canEmailVerify = !$family->getNeedsReview() && $family->isActive() && $invitationRecipients['emails'] !== [];
    ?>
<div class="modal fade" id="confirm-verify" tabindex="-1" role="dialog" aria-labelledby="confirm-verify-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="modal-title"
                    id="confirm-verify-label"><?= gettext("Request Family Info Verification") ?></h4>
            </div>
            <div class="modal-body">
                <?php if ($family->getNeedsReview()) { ?>
                <p><?= gettext("This self-registered family is waiting for approval. Approving it emails each member who can sign in to the member portal an invitation to review the family's information.") ?></p>
                <?php } elseif (!$family->isActive()) { ?>
                <p><?= gettext("This family is inactive, so it is not sent verification emails.") ?></p>
                <?php } elseif ($invitationRecipients['emails'] === []) { ?>
                <div class="alert alert-warning" id="verify-nobody-can-sign-in">
                    <p class="mb-1"><?= InputUtils::escapeHTML($portalInvitations->nobodyCanSignInMessage($family, $invitationRecipients)) ?></p>
                    <?php if ($invitationRecipients['cannotSignIn'] !== []) { ?>
                    <ul class="mb-1">
                        <?php foreach ($invitationRecipients['cannotSignIn'] as $member) { ?>
                        <li><?= InputUtils::escapeHTML($member['name']) ?>: <?= InputUtils::escapeHTML($member['reason']) ?></li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                    <p class="mb-0"><?= gettext("Verify the family in person instead.") ?></p>
                </div>
                <?php } else { ?>
                <p><?= gettext("Email the family a link to the member portal, where they can review and update their information, or record that you verified it in person.") ?></p>
                <ul>
                    <?php foreach ($invitationRecipients['emails'] as $tmpEmail) { ?>
                    <li><?= InputUtils::escapeHTML($tmpEmail) ?></li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
            <div class="modal-footer text-center">
                <?php if ($canEmailVerify) {
                    $emailEnabled = SystemConfig::isEmailEnabled();
                    $emailDisabledTitle = $emailEnabled ? '' : InputUtils::escapeAttribute(
                        gettext('Email is not configured. Please configure SMTP settings in System Settings.')
                    );
                    ?>
                    <button type="button" id="onlineVerify"
                            class="btn btn-warning warning" <?php if (!$emailEnabled) echo 'disabled'; ?> title="<?= $emailDisabledTitle ?>"><i
                            class="fa-solid fa-envelope"></i><?= gettext("Email Portal Link") ?>
                    </button>
                <?php } ?>
                <button type="button" id="verifyDownloadPDF"
                        class="btn btn-info"><i class="fa-solid fa-download"></i>PDF</button>
                <?php if (!$family->getNeedsReview()) { ?>
                <button type="button" id="verifyNow"
                        class="btn btn-success"><i class="fa-solid fa-check"></i><?= gettext("Verified In Person") ?>
                </button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    function isAppleMobile() {
        var ua = navigator.userAgent || "";
        if (/iPad|iPhone|iPod/.test(ua) && !window.MSStream) return true;
        if (navigator.platform === "MacIntel" && typeof navigator.maxTouchPoints === "number" && navigator.maxTouchPoints > 1) return true;
        return false;
    }
    document.addEventListener("DOMContentLoaded", function () {
        if (isAppleMobile()) {
            document.querySelectorAll(".directions-provider-toggle").forEach(function (el) { el.classList.remove("d-none"); });
            document.querySelectorAll(".directions-provider-menu").forEach(function (el) { el.classList.remove("d-none"); });
        }
    });
})();
</script>

<?php require_once __DIR__ . '/delete-note-modal.php'; ?>

<?php
echo '<script src="' . SystemURLs::assetVersioned('/skin/v2/note-why-came-modal.min.js') . '"></script>';
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
