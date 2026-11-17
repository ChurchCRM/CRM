<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid <?= $isNew ? 'fa-plus' : 'fa-pen-to-square' ?> me-2"></i><?= gettext('Property') ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <strong><?= gettext('Error') ?>:</strong> <?= gettext('Please correct the errors below.') ?>
                </div>
                <?php endif; ?>

                <form method="post" action="<?= InputUtils::escapeAttribute($formAction) ?>" id="property-form">
                    <?= CSRFUtils::getTokenInputField('property_editor') ?>
                    <div class="mb-3">
                        <label class="form-label required" for="propertyTypeId"><?= gettext('Type') ?></label>
                        <select class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" id="propertyTypeId" name="propertyTypeId" required>
                            <option value=""><?= gettext('Select Property Type') ?></option>
                            <?php foreach ($propertyTypes as $propertyType): ?>
                            <option value="<?= (int) $propertyType->getPrtId() ?>" <?= $propertyTypeId === (int) $propertyType->getPrtId() ? 'selected' : '' ?>><?= InputUtils::escapeHTML($propertyType->getPrtName()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['type'])): ?>
                        <div class="invalid-feedback d-block"><?= InputUtils::escapeHTML($errors['type']) ?></div>
                        <?php endif; ?>
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

                    <div class="mb-3">
                        <label class="form-label" for="description"><?= gettext('A') ?> <?= InputUtils::escapeHTML($recordTypeName) ?> <?= gettext('with this property...') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= InputUtils::escapeHTML($description) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="prompt"><?= gettext('Prompt') ?></label>
                        <input type="text"
                               class="form-control <?= isset($errors['prompt']) ? 'is-invalid' : '' ?>"
                               id="prompt"
                               name="prompt"
                               value="<?= InputUtils::escapeAttribute($prompt) ?>"
                               maxlength="<?= (int) $promptMaxLength ?>">
                        <?php if (isset($errors['prompt'])): ?>
                        <div class="invalid-feedback d-block"><?= InputUtils::escapeHTML($errors['prompt']) ?></div>
                        <?php endif; ?>
                        <small class="form-text text-body-secondary"><?= gettext('Entering a Prompt value will allow the association of a free-form value.') ?></small>
                    </div>

                    <div class="d-flex justify-content-between border-top pt-3 mt-3">
                        <a href="<?= InputUtils::escapeAttribute($listUrl) ?>" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Cancel') ?>
                        </a>
                        <button type="submit" class="btn btn-primary" id="save-property">
                            <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Save') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
