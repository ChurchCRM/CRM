/**
 * Quill Rich Text Editor initialization and helper functions
 * Replaces CKEditor4 with lightweight Quill editor
 */

import Quill from "quill";

// Global registry for Quill editors (for Cypress testing)
window.quillEditors = window.quillEditors || {};

/**
 * Initialize Quill editor for a given selector
 * @param {string} selector - CSS selector for the editor container
 * @param {Object} options - Custom options to merge with defaults
 * @returns {Quill} - Quill editor instance
 */
export function initializeQuillEditor(selector, options = {}) {
  const defaultOptions = {
    theme: "snow",
    placeholder: "Enter text here...",
    modules: {
      toolbar: [
        ["bold", "italic", "underline", "strike"],
        ["blockquote", "code-block"],
        [{ header: 1 }, { header: 2 }],
        [{ list: "ordered" }, { list: "bullet" }],
        [{ script: "sub" }, { script: "super" }],
        [{ indent: "-1" }, { indent: "+1" }],
        [{ size: ["small", false, "large", "huge"] }],
        [{ header: [1, 2, 3, 4, 5, 6, false] }],
        [{ color: [] }, { background: [] }],
        [{ align: [] }],
        ["link", "image", "video"],
        ["clean"],
      ],
    },
  };

  const mergedOptions = { ...defaultOptions, ...options };

  // Create container if it doesn't exist
  let container = document.querySelector(selector);
  if (!container) {
    console.error(`Quill editor container not found: ${selector}`);
    return null;
  }

  // Check if editor already exists
  if (container.querySelector(".ql-toolbar")) {
    console.warn(`Quill editor already initialized at: ${selector}`);
    return null;
  }

  const editor = new Quill(selector, mergedOptions);

  // Store reference globally for Cypress testing
  // Extract ID from selector (e.g., "#NoteText" -> "NoteText")
  const editorId = selector.replace(/^#/, "").replace(/^\./, "");
  window.quillEditors[editorId] = editor;

  return editor;
}

/**
 * Get HTML content from a Quill editor
 * @param {Quill} editor - Quill editor instance
 * @returns {string} - HTML content
 */
export function getEditorHtml(editor) {
  if (!editor) return "";
  const delta = editor.getContents();
  return editor.root.innerHTML;
}

/**
 * Set HTML content in a Quill editor
 * @param {Quill} editor - Quill editor instance
 * @param {string} html - HTML content to set
 */
export function setEditorHtml(editor, html) {
  if (!editor) return;
  editor.root.innerHTML = html;
}

/**
 * Get plain text from a Quill editor
 * @param {Quill} editor - Quill editor instance
 * @returns {string} - Plain text content
 */
export function getEditorText(editor) {
  if (!editor) return "";
  return editor.getText();
}

/**
 * Clear editor content
 * @param {Quill} editor - Quill editor instance
 */
export function clearEditor(editor) {
  if (!editor) return;
  editor.setContents([]);
}

/**
 * Enable/disable editor
 * @param {Quill} editor - Quill editor instance
 * @param {boolean} enabled - Enable state
 */
export function setEditorEnabled(editor, enabled) {
  if (!editor) return;
  editor.enable(enabled);
}

export default Quill;
