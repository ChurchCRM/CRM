/**
 * Type declarations for webpack/quill-editor.js — the app's Quill wrapper.
 *
 * The wrapper is plain JS (shared with the legacy bundles), so the surface
 * the TypeScript bundles use is declared here, the same approach
 * webpack/types/window.d.ts takes for TomSelect and bootbox.
 */

/** The slice of a Quill instance the app uses. */
export interface QuillEditorInstance {
  /** The editor's contenteditable root element; its innerHTML is the saved HTML. */
  root: HTMLElement;
  getText(): string;
  setContents(contents: unknown[]): void;
  on(event: string, handler: (...args: unknown[]) => void): void;
  enable(enabled: boolean): void;
}

/**
 * Create a Quill editor on the container matched by `selector` and register
 * it in `window.quillEditors` under the container's id. Returns null when
 * the container is missing or already hosts an editor.
 */
export function initializeQuillEditor(
  selector: string,
  options?: Record<string, unknown>,
): QuillEditorInstance | null;

export function getEditorHtml(editor: QuillEditorInstance | null): string;
export function setEditorHtml(editor: QuillEditorInstance | null, html: string): void;
export function getEditorText(editor: QuillEditorInstance | null): string;
export function clearEditor(editor: QuillEditorInstance | null): void;
export function setEditorEnabled(editor: QuillEditorInstance | null, enabled: boolean): void;

declare const Quill: {
  new (selector: string | HTMLElement, options?: Record<string, unknown>): QuillEditorInstance;
};
export default Quill;
