<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid <?= $isNew ? 'fa-plus' : 'fa-pen-to-square' ?> me-2"></i><?= gettext('Property Type') ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <strong><?= gettext('Error') ?>:</strong> <?= gettext('Please correct the errors below.') ?>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= InputUtils::escapeAttribute($formAction) ?>" id="property-type-form">
            <?= CSRFUtils::getTokenInputField('property_type_editor') ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label required" for="class"><?= gettext('Class') ?></label>
                        <select class="form-select <?= isset($errors['class']) ? 'is-invalid' : '' ?>" id="class" name="class" required>
                            <?php foreach ($classLabels as $classValue => $classLabel): ?>
                            <option value="<?= $classValue ?>" <?= $class === $classValue ? 'selected' : '' ?>><?= InputUtils::escapeHTML($classLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['class'])): ?>
                        <div class="invalid-feedback d-block"><?= InputUtils::escapeHTML($errors['class']) ?></div>
                        <?php endif; ?>
                        <small class="form-text text-body-secondary"><?= gettext('Select the type of record this property applies to') ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="name"><?= gettext('Name') ?></label>
                        <input type="text"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               id="name"
                               name="name"
                               value="<?= InputUtils::escapeAttribute($name) ?>"
                               maxlength="<?= (int) $nameMaxLength ?>"
                               required>
                        <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback d-block"><?= InputUtils::escapeHTML($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="description"><?= gettext('Description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="8"><?= InputUtils::escapeHTML($description) ?></textarea>
                        <small class="form-text text-body-secondary"><?= gettext('Optional detailed description of this property type') ?></small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between border-top pt-3 mt-3">
                <a href="<?= SystemURLs::getRootPath() ?>/PropertyTypeList.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Cancel') ?>
                </a>
                <button type="submit" class="btn btn-primary" id="save-property-type">
                    <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
