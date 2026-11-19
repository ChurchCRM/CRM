/** Shared modal for notes and Why Came. Loaded on the person and family views. */
import { notifyError } from "./common/notify";
import { initializeQuillEditor, type QuillEditorInstance } from "./quill-editor.js";

const MODAL_ID = "noteWhyCameModal";
const SAVE_ID = "noteWhyCameSave";
const AJAX = { "X-Requested-With": "XMLHttpRequest" };
const JSON_HEADERS = { "Content-Type": "application/json", ...AJAX };

const t = (key: string): string => i18next.t(key);
const api = (path: string): string => (window.CRM?.root ?? "") + path;
const byId = <T extends HTMLElement>(id: string): T => document.getElementById(id) as T;

function showModal(): ReturnType<typeof window.bootstrap.Modal.getOrCreateInstance> {
  if (!document.getElementById(MODAL_ID)) {
    document.body.insertAdjacentHTML(
      "beforeend",
      `<div class="modal fade" id="${MODAL_ID}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="${MODAL_ID}Title"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="${MODAL_ID}Body"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${t("Cancel")}</button>
            <button type="button" class="btn btn-primary" id="${SAVE_ID}">${t("Save")}</button>
          </div>
        </div></div>
      </div>`,
    );
  }
  return window.bootstrap.Modal.getOrCreateInstance(byId(MODAL_ID));
}

function setBusy(ids: string[], busy: boolean): void {
  for (const id of ids) {
    const el = document.getElementById(id) as HTMLInputElement | null;
    if (el) el.disabled = busy;
  }
}

function open(title: string, bodyHtml: string, save: () => Promise<Response>): ReturnType<typeof showModal> {
  const modal = showModal();
  byId(`${MODAL_ID}Title`).textContent = title;
  byId(`${MODAL_ID}Body`).innerHTML = bodyHtml;
  byId(SAVE_ID).onclick = async () => {
    const res = await save();
    if (res?.ok) {
      modal.hide();
      location.reload();
    } else if (res) {
      notifyError(t("Save failed"));
    }
  };
  modal.show();
  return modal;
}

const field = (id: string, label: string, rows: number): string =>
  `<div class="mb-3"><label class="form-label" for="${id}">${label}</label><textarea class="form-control" id="${id}" rows="${rows}"></textarea></div>`;

function openNoteEditor(personId: string, familyId: string, noteId: string): void {
  const fields = ["notePrivate", SAVE_ID];
  let quill: QuillEditorInstance | null = null;
  open(
    noteId ? t("Note Editor") : t("Add Note"),
    `<div class="mb-3"><label class="form-label" for="noteText">${t("Note")}</label><div id="noteText" class="w-100 quill-editor-container" data-editor-size="tall"></div></div>
     <div class="form-check"><input class="form-check-input" type="checkbox" id="notePrivate">
     <label class="form-check-label" for="notePrivate">${t("Private")}</label></div>`,
    async () => {
      if (!quill || quill.getText().trim() === "") {
        notifyError(t("Note text is required"));
        return undefined as unknown as Response;
      }
      const text = quill.root.innerHTML;
      const url = noteId
        ? `/api/note/${noteId}`
        : personId
          ? `/api/person/${personId}/note`
          : `/api/family/${familyId}/note`;
      return fetch(api(url), {
        method: noteId ? "PUT" : "POST",
        headers: JSON_HEADERS,
        body: JSON.stringify({ text, private: byId<HTMLInputElement>("notePrivate").checked }),
      });
    },
  );
  // Rich text, as the old NoteEditor.php page had it: a Quill editor in the
  // same container/init shape the event form uses (webpack/event-form.js).
  // The stored note HTML is loaded into the editor and the editor's HTML is
  // what gets saved — the API sanitizes it (InputUtils::sanitizeHTML).
  quill = initializeQuillEditor("#noteText", { placeholder: t("Enter note text here...") });
  if (!noteId) return;
  setBusy(fields, true);
  quill?.enable(false);
  fetch(api(`/api/note/${noteId}`), { headers: AJAX })
    .then((res) => {
      if (!res.ok) throw new Error("load");
      return res.json();
    })
    .then(({ note }) => {
      if (quill && note?.text) quill.root.innerHTML = note.text;
      byId<HTMLInputElement>("notePrivate").checked = !!note?.private;
      setBusy(fields, false);
      quill?.enable(true);
    })
    .catch(() => notifyError(t("Could not load note.")));
}

function openWhyCameEditor(personId: string): void {
  const ids = { join: "whyJoin", come: "whyCome", suggest: "whySuggest", hearOfUs: "whyHear" } as const;
  const fields = [...Object.values(ids), SAVE_ID];
  const value = (id: string): string => byId<HTMLTextAreaElement>(id).value;
  open(
    t("Why Came"),
    field(ids.join, t("Why did you come to the church?"), 2) +
      field(ids.come, t("Why do you keep coming?"), 2) +
      field(ids.suggest, t("Do you have any suggestions for us?"), 2) +
      field(ids.hearOfUs, t("How did you learn of the church?"), 2),
    () =>
      fetch(api(`/api/person/${personId}/why-came`), {
        method: "POST",
        headers: JSON_HEADERS,
        body: JSON.stringify(Object.fromEntries(Object.entries(ids).map(([k, id]) => [k, value(id)]))),
      }),
  );
  setBusy(fields, true);
  fetch(api(`/api/person/${personId}/why-came`), { headers: AJAX })
    .then((res) => {
      if (!res.ok) throw new Error("load");
      return res.json();
    })
    .then(({ whyCame }) => {
      if (whyCame) {
        for (const [key, id] of Object.entries(ids)) byId<HTMLTextAreaElement>(id).value = whyCame[key] ?? "";
      }
      setBusy(fields, false);
    })
    .catch(() => notifyError(t("Could not load data")));
}

document.addEventListener("click", (event) => {
  const target = event.target as Element;
  const attr = (name: string): string | null => target.closest(`[${name}]`)?.getAttribute(name) ?? null;
  const whyCame = attr("data-open-why-came");
  const noteId = attr("data-open-note-id");
  const person = attr("data-open-note-person");
  const family = attr("data-open-note-family");
  if (whyCame) openWhyCameEditor(whyCame);
  else if (noteId) openNoteEditor("", "", noteId);
  else if (person) openNoteEditor(person, "", "");
  else if (family) openNoteEditor("", family, "");
});
