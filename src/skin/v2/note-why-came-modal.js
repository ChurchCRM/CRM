// Shared modal for notes and Why Came. Loaded on the person and family views.

(function () {
  "use strict";

  function api(path) {
    const root = window.CRM && window.CRM.root ? window.CRM.root : "";
    return root + path;
  }

  function notify(message) {
    if (window.CRM && typeof window.CRM.notify === "function") {
      window.CRM.notify(message, { type: "danger" });
    }
  }

  function ensureModal() {
    if (document.getElementById("noteWhyCameModal")) {
      return;
    }
    const html = `
      <div class="modal fade" id="noteWhyCameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="noteWhyCameModalTitle"></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="noteWhyCameModalBody"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${i18next.t("Cancel")}</button>
              <button type="button" class="btn btn-primary" id="noteWhyCameSave">${i18next.t("Save")}</button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML("beforeend", html);
  }

  function showModal() {
    ensureModal();
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("noteWhyCameModal"));
  }

  function setBusy(ids, busy) {
    ids.forEach(function (id) {
      const el = document.getElementById(id);
      if (el) {
        el.disabled = busy;
      }
    });
  }

  function openNoteEditor(personId, familyId, noteId) {
    const modal = showModal();
    document.getElementById("noteWhyCameModalTitle").textContent = noteId
      ? i18next.t("Note Editor")
      : i18next.t("Add Note");
    document.getElementById("noteWhyCameModalBody").innerHTML = `
      <div class="mb-3">
        <label class="form-label" for="noteText">${i18next.t("Note")}</label>
        <textarea class="form-control" id="noteText" rows="6"></textarea>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="notePrivate">
        <label class="form-check-label" for="notePrivate">${i18next.t("Private")}</label>
      </div>`;

    const fields = ["noteText", "notePrivate", "noteWhyCameSave"];
    setBusy(fields, !!noteId);

    document.getElementById("noteWhyCameSave").onclick = async function () {
      const text = document.getElementById("noteText").value;
      const isPrivate = document.getElementById("notePrivate").checked;
      if (!text) {
        notify(i18next.t("Note text is required"));
        return;
      }
      const url = noteId
        ? api("/api/note/" + noteId)
        : personId
          ? api("/api/person/" + personId + "/note")
          : api("/api/family/" + familyId + "/note");
      const res = await fetch(url, {
        method: noteId ? "PUT" : "POST",
        headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
        body: JSON.stringify({ text: text, private: isPrivate }),
      });
      if (res.ok) {
        modal.hide();
        location.reload();
      } else {
        notify(i18next.t("Save failed"));
      }
    };

    modal.show();

    if (!noteId) {
      return;
    }

    fetch(api("/api/note/" + noteId), { headers: { "X-Requested-With": "XMLHttpRequest" } })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("load");
        }
        return res.json();
      })
      .then(function (data) {
        const note = data.note || {};
        document.getElementById("noteText").value = note.text || "";
        document.getElementById("notePrivate").checked = !!note.private;
        setBusy(fields, false);
      })
      .catch(function () {
        notify(i18next.t("Could not load note."));
      });
  }

  function openWhyCameEditor(personId) {
    const modal = showModal();
    document.getElementById("noteWhyCameModalTitle").textContent = i18next.t("Why Came");
    document.getElementById("noteWhyCameModalBody").innerHTML = `
      <div class="mb-3"><label class="form-label" for="whyJoin">${i18next.t("Why did you come to the church?")}</label><textarea class="form-control" id="whyJoin" rows="2"></textarea></div>
      <div class="mb-3"><label class="form-label" for="whyCome">${i18next.t("Why do you keep coming?")}</label><textarea class="form-control" id="whyCome" rows="2"></textarea></div>
      <div class="mb-3"><label class="form-label" for="whySuggest">${i18next.t("Do you have any suggestions for us?")}</label><textarea class="form-control" id="whySuggest" rows="2"></textarea></div>
      <div class="mb-3"><label class="form-label" for="whyHear">${i18next.t("How did you learn of the church?")}</label><textarea class="form-control" id="whyHear" rows="2"></textarea></div>`;

    const fields = ["whyJoin", "whyCome", "whySuggest", "whyHear", "noteWhyCameSave"];
    setBusy(fields, true);

    document.getElementById("noteWhyCameSave").onclick = async function () {
      const res = await fetch(api("/api/person/" + personId + "/why-came"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
        body: JSON.stringify({
          join: document.getElementById("whyJoin").value,
          come: document.getElementById("whyCome").value,
          suggest: document.getElementById("whySuggest").value,
          hearOfUs: document.getElementById("whyHear").value,
        }),
      });
      if (res.ok) {
        modal.hide();
        location.reload();
      } else {
        notify(i18next.t("Save failed"));
      }
    };

    modal.show();

    fetch(api("/api/person/" + personId + "/why-came"), {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("load");
        }
        return res.json();
      })
      .then(function (data) {
        const whyCame = data.whyCame;
        if (whyCame) {
          document.getElementById("whyJoin").value = whyCame.join || "";
          document.getElementById("whyCome").value = whyCame.come || "";
          document.getElementById("whySuggest").value = whyCame.suggest || "";
          document.getElementById("whyHear").value = whyCame.hearOfUs || "";
        }
        setBusy(fields, false);
      })
      .catch(function () {
        notify(i18next.t("Could not load data"));
      });
  }

  document.addEventListener("click", function (event) {
    const whyCame = event.target.closest("[data-open-why-came]");
    if (whyCame) {
      openWhyCameEditor(whyCame.getAttribute("data-open-why-came"));
      return;
    }
    const edit = event.target.closest("[data-open-note-id]");
    if (edit) {
      openNoteEditor(0, 0, edit.getAttribute("data-open-note-id"));
      return;
    }
    const person = event.target.closest("[data-open-note-person]");
    if (person) {
      openNoteEditor(person.getAttribute("data-open-note-person"), 0, 0);
      return;
    }
    const family = event.target.closest("[data-open-note-family]");
    if (family) {
      openNoteEditor(0, family.getAttribute("data-open-note-family"), 0);
    }
  });
})();
