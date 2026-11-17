// Shared modal for Note and WhyCame editors
// Loaded on person/family views

(function () {
  'use strict';

  function ensureModal() {
    if (document.getElementById('noteWhyCameModal')) return;
    const html = `
      <div class="modal fade" id="noteWhyCameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="noteWhyCameModalTitle">Editor</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="noteWhyCameModalBody"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="noteWhyCameSave">Save</button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
  }

  window.openNoteEditor = function (personId, familyId, noteId) {
    ensureModal();
    const modal = new bootstrap.Modal(document.getElementById('noteWhyCameModal'));
    document.getElementById('noteWhyCameModalTitle').textContent = noteId ? 'Edit Note' : 'Add Note';
    document.getElementById('noteWhyCameModalBody').innerHTML = `
      <div class="mb-3">
        <label class="form-label">Note</label>
        <textarea class="form-control" id="noteText" rows="6" placeholder="Enter note text..."></textarea>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="notePrivate">
        <label class="form-check-label" for="notePrivate">Private</label>
      </div>`;
    // TODO: full Quill integration in follow-up; API accepts HTML
    document.getElementById('noteWhyCameSave').onclick = async function () {
      const text = document.getElementById('noteText').value;
      const priv = document.getElementById('notePrivate').checked;
      if (!text) { alert('Note text required'); return; }
      const url = noteId ? `/api/note/${noteId}` : (personId ? `/api/person/${personId}/note` : `/api/family/${familyId}/note`);
      const method = noteId ? 'PUT' : 'POST';
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ text, private: priv })
      });
      if (res.ok) {
        modal.hide();
        location.reload(); // timeline refresh; can be improved to partial update
      } else {
        alert('Save failed');
      }
    };
    modal.show();
  };

  window.openWhyCameEditor = function (personId) {
    ensureModal();
    const modal = new bootstrap.Modal(document.getElementById('noteWhyCameModal'));
    document.getElementById('noteWhyCameModalTitle').textContent = 'Why Came';
    document.getElementById('noteWhyCameModalBody').innerHTML = `
      <div class="mb-3"><label>Why did you come to the church?</label><textarea class="form-control" id="whyJoin" rows="2"></textarea></div>
      <div class="mb-3"><label>Why do you keep coming?</label><textarea class="form-control" id="whyCome" rows="2"></textarea></div>
      <div class="mb-3"><label>Do you have any suggestions for us?</label><textarea class="form-control" id="whySuggest" rows="2"></textarea></div>
      <div class="mb-3"><label>How did you learn of the church?</label><textarea class="form-control" id="whyHear" rows="2"></textarea></div>`;
    // Pre-fill if exists
    fetch(`/api/person/${personId}/why-came`).then(r => r.json()).then(data => {
      if (data.whyCame) {
        document.getElementById('whyJoin').value = data.whyCame.join || '';
        document.getElementById('whyCome').value = data.whyCame.come || '';
        document.getElementById('whySuggest').value = data.whyCame.suggest || '';
        document.getElementById('whyHear').value = data.whyCame.hearOfUs || '';
      }
    });
    document.getElementById('noteWhyCameSave').onclick = async function () {
      const body = {
        join: document.getElementById('whyJoin').value,
        come: document.getElementById('whyCome').value,
        suggest: document.getElementById('whySuggest').value,
        hearOfUs: document.getElementById('whyHear').value
      };
      const res = await fetch(`/api/person/${personId}/why-came`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body)
      });
      if (res.ok) {
        modal.hide();
        location.reload();
      } else {
        alert('Save failed');
      }
    };
    modal.show();
  };
})();
