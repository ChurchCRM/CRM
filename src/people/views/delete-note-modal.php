<?php

use ChurchCRM\dto\SystemURLs;

use ChurchCRM\Utils\InputUtils;
/**
 * Shared delete-note confirmation modal.
 *
 * Include once per page, just before the Footer include.
 * Trigger buttons must carry:
 *   data-bs-toggle="modal"
 *   data-bs-target="#deleteNoteModal"
 *   data-note-id="<noteId>"
 */
?>
<!-- View full note modal -->
<div class="modal fade" id="viewNoteModal" tabindex="-1" aria-labelledby="viewNoteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNoteLabel"><?= gettext('Note') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewNoteBody" style="min-height:4rem;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteNoteModal" tabindex="-1" aria-labelledby="deleteNoteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteNoteLabel"><?= gettext('Confirm Delete') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?= gettext('Are you sure you want to delete this note?') ?></p>
                <p><small class="text-muted"><?= gettext('This action cannot be undone.') ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmDeleteNoteBtn"><?= gettext('Delete') ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    const rootPath = <?= InputUtils::jsonEncodeForScript(SystemURLs::getRootPath()) ?>;

    // ── Delete modal ──────────────────────────────────────────────────────────
    const deleteModal = document.getElementById("deleteNoteModal");
    if (deleteModal) {
        let pendingNoteId = null;

        deleteModal.addEventListener("show.bs.modal", function (event) {
            pendingNoteId = event.relatedTarget ? event.relatedTarget.getAttribute("data-note-id") : null;
        });
        deleteModal.addEventListener("hidden.bs.modal", function () { pendingNoteId = null; });

        document.getElementById("confirmDeleteNoteBtn").addEventListener("click", async function () {
            if (!pendingNoteId) { return; }
            bootstrap.Modal.getInstance(deleteModal).hide();
            try {
                const r = await fetch(`${rootPath}/api/note/${pendingNoteId}`, {
                    method: "DELETE",
                    headers: { "content-type": "application/json", Authorization: `Bearer ${sessionStorage.getItem("apiKey")}` },
                });
                if (r.ok) {
                    location.reload();
                } else if (r.status === 403) {
                    bootbox.alert(<?= InputUtils::jsonEncodeForScript(gettext('You do not have permission to delete this note.')) ?>);
                } else if (r.status === 404) {
                    bootbox.alert(<?= InputUtils::jsonEncodeForScript(gettext('Note not found.')) ?>);
                } else {
                    bootbox.alert(<?= InputUtils::jsonEncodeForScript(gettext('Failed to delete note.')) ?>);
                }
            } catch (e) {
                console.error("Delete note error:", e);
                bootbox.alert(<?= InputUtils::jsonEncodeForScript(gettext('Error deleting note.')) ?>);
            }
        });
    }

    // ── "Read more" — reveal full note in view modal ──────────────────────────
    const viewModal = document.getElementById("viewNoteModal");
    const viewBody  = document.getElementById("viewNoteBody");

    // After paint, show "Read more" button only on actually-clipped previews
    function initReadMore() {
        document.querySelectorAll(".note-preview").forEach(function (el) {
            if (el.scrollHeight > el.clientHeight + 2) {
                const btn = el.parentElement.querySelector(".note-read-more");
                if (btn) { btn.classList.remove("d-none"); }
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initReadMore);
    } else {
        initReadMore();
    }

    if (viewModal && viewBody) {
        document.addEventListener("click", async function (e) {
            const btn = e.target.closest(".note-read-more");
            if (!btn) { return; }
            const noteId = btn.closest(".card-body").querySelector(".note-preview")?.dataset.noteId;
            if (!noteId) { return; }

            viewBody.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';
            bootstrap.Modal.getOrCreateInstance(viewModal).show();

            try {
                const r = await fetch(`${rootPath}/api/note/${noteId}`, {
                    headers: { Authorization: `Bearer ${sessionStorage.getItem("apiKey")}` },
                });
                viewBody.innerHTML = r.ok
                    ? ((await r.json()).note?.text ?? "")
                    : '<p class="text-danger">' + <?= InputUtils::jsonEncodeForScript(gettext('Could not load note.')) ?> + '</p>';
            } catch {
                viewBody.innerHTML = '<p class="text-danger">' + <?= InputUtils::jsonEncodeForScript(gettext('Could not load note.')) ?> + '</p>';
            }
        });
    }
})();
</script>
