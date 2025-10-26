<?php

use ChurchCRM\dto\SystemURLs;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= gettext('Upload Photo') ?></h4>
                <p class="text-muted mb-0">
                    <?= gettext('Upload a photo for') ?> <?= ucfirst($type) ?> #<?= $id ?>
                </p>
            </div>
            <div class="card-body">
                <!-- Uppy Dashboard Container -->
                <div id="photo-uploader"></div>
                
                <div class="mt-3">
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> <?= gettext('Cancel') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Uppy Photo Uploader -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/photo-uploader.min.js"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        // Copy photo uploader function from temporary storage
        if (window._CRM_createPhotoUploader) {
            window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
        } else {
            console.error('Photo uploader function not found');
            alert('<?= gettext("Photo uploader failed to load. Please refresh the page.") ?>');
            return;
        }
        
        // Initialize Uppy
        const uppy = window.CRM.createPhotoUploader({
            target: '#photo-uploader',
            uploadUrl: '<?= $uploadUrl ?>',
            maxFileSize: <?= $maxFileSizeBytes ?>,
            photoWidth: <?= $photoWidth ?>,
            photoHeight: <?= $photoHeight ?>,
            onComplete: function(result) {
                // Redirect back after successful upload
                window.location.href = '<?= $returnUrl ?>';
            }
        });
        
        console.log('Photo uploader initialized successfully');
    });
</script>

<?php
include SystemURLs::getDocumentRoot() . '/Include/Footer.php';
