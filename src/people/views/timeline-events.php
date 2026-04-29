<?php

use ChurchCRM\Authentication\AuthenticationManager;

/**
 * Shared timeline events partial.
 *
 * Expects $timeline (array of timeline item arrays) set by the caller.
 * Renders filter chips + timeline event list; no outer card wrapper.
 */

if (empty($timeline)) { ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info fa-fw fa-lg"></i>
        <span><?= gettext('No timeline events yet.') ?></span>
    </div>
<?php } else {
    $timelineCounts = ['notes' => 0, 'events' => 0, 'system' => 0];
    foreach ($timeline as $tlItem) {
        $cat = $tlItem['category'] ?? 'notes';
        if (isset($timelineCounts[$cat])) {
            $timelineCounts[$cat]++;
        }
    }
    ?>
    <div class="timeline-filters d-flex flex-wrap align-items-center gap-2 mt-3 mb-1" role="group" aria-label="<?= gettext('Timeline filters') ?>">
        <span class="text-muted small me-1"><i class="fa-solid fa-filter me-1"></i><?= gettext('Show:') ?></span>
        <button type="button" class="btn btn-sm btn-primary timeline-filter-chip active" data-filter="notes">
            <i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Notes') ?>
            <span class="badge bg-white text-primary ms-1"><?= $timelineCounts['notes'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary timeline-filter-chip" data-filter="events">
            <i class="fa-solid fa-calendar-days me-1"></i><?= gettext('Events') ?>
            <span class="badge bg-secondary-lt text-secondary ms-1"><?= $timelineCounts['events'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary timeline-filter-chip" data-filter="system">
            <i class="fa-solid fa-gear me-1"></i><?= gettext('System') ?>
            <span class="badge bg-secondary-lt text-secondary ms-1"><?= $timelineCounts['system'] ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-link ms-auto timeline-filter-all" data-filter="all">
            <?= gettext('Show all') ?>
        </button>
    </div>
    <div class="timeline-empty-notice alert alert-info" style="display:none;">
        <i class="fa-solid fa-circle-info fa-fw me-1"></i><?= gettext('No matching entries for the selected filters.') ?>
    </div>
    <?php $currentYear = ''; ?>
    <div class="timeline mt-3">
        <?php foreach ($timeline as $item) {
            if ($currentYear !== $item['year']) {
                $currentYear = $item['year']; ?>
                <div class="hr-text timeline-year" data-timeline-year="<?= htmlspecialchars((string)$currentYear) ?>"> <i class="fa-solid fa-calendar-days"></i> <?= $currentYear ?></div>
            <?php } ?>
            <div class="timeline-event" data-timeline-category="<?= htmlspecialchars($item['category'] ?? 'notes') ?>" data-timeline-year="<?= htmlspecialchars((string)$item['year']) ?>">
                <div class="timeline-event-icon bg-<?= $item['color'] ?>-lt text-<?= $item['color'] ?>">
                    <i class="fa-solid <?= $item['style'] ?>"></i>
                </div>
                <div class="timeline-event-card card">
                    <div class="card-body p-2 px-3">
                        <?php if ($item['slim']) { ?>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="text-secondary small"><?= $item['text'] ?> <?= gettext($item['header']) ?></span>
                                <small class="text-muted flex-shrink-0"><?= $item['datetime'] ?></small>
                            </div>
                        <?php } else {
                            $hasActions = AuthenticationManager::getCurrentUser()->isNotesEnabled()
                                && (!empty($item["editLink"]) || isset($item["deleteLink"]));
                            $noteId = isset($item["deleteLink"])
                                ? str_replace('api-delete-note-', '', $item['deleteLink'])
                                : null;
                        ?>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <span class="fw-semibold">
                                    <?php if (!empty($item['headerLink'])) { ?>
                                        <a href="<?= $item['headerLink'] ?>"><?= $item['header'] ?></a>
                                    <?php } else { ?>
                                        <?= $item['header'] ?>
                                    <?php } ?>
                                </span>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <?php if (!empty($item['isPrivate'])) { ?>
                                        <span class="badge bg-warning-lt text-warning"><?= gettext('Private') ?></span>
                                    <?php } ?>
                                    <small class="text-muted"><?= $item['datetime'] ?></small>
                                </div>
                            </div>
                            <?php if (!empty($item['text'])) { ?>
                                <div class="note-preview text-secondary" style="font-size:0.875rem;max-height:4.5em;overflow:hidden;" data-note-id="<?= htmlspecialchars((string)$noteId) ?>"><?= $item['text'] ?></div>
                            <?php } ?>
                            <?php if ($hasActions || !empty($item['text'])) { ?>
                                <div class="d-flex align-items-center justify-content-between mt-2">
                                    <button type="button" class="btn btn-link btn-sm p-0 note-read-more d-none" style="font-size:0.8rem;">
                                        <?= gettext('Read more') ?> <i class="fa-solid fa-chevron-down fa-xs"></i>
                                    </button>
                                    <div class="ms-auto d-flex gap-1">
                                        <?php if ($hasActions) { ?>
                                            <?php if (!empty($item["editLink"])) { ?>
                                                <a href="<?= $item["editLink"] ?>" class="btn btn-sm btn-ghost-secondary py-0 px-2"><i class="fa-solid fa-pen fa-sm me-1"></i><?= gettext('Edit') ?></a>
                                            <?php } ?>
                                            <?php if ($noteId !== null) { ?>
                                                <button type="button" class="btn btn-sm btn-ghost-danger py-0 px-2" data-bs-toggle="modal" data-bs-target="#deleteNoteModal" data-note-id="<?= htmlspecialchars($noteId) ?>">
                                                    <i class="fa-solid fa-trash fa-sm me-1"></i><?= gettext('Delete') ?>
                                                </button>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } ?>
