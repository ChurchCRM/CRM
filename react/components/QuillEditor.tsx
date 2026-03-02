import Quill from "quill";
import type * as React from "react";
import { useEffect, useRef } from "react";
import "quill/dist/quill.snow.css";

declare global {
  interface Window {
    quillEditors?: { [key: string]: Quill };
  }
}

const QuillEditor: React.FunctionComponent<{
  name: string;
  value: string;
  onChange: (name: string, html: string) => void;
  placeholder?: string;
  minHeight?: string;
}> = ({ name, value, onChange, placeholder = "Enter text here...", minHeight = "200px" }) => {
  const editorRef = useRef<HTMLDivElement>(null);
  const quillRef = useRef<Quill | null>(null);
  // Keep a ref to the latest onChange so the text-change handler never goes stale
  // without causing Quill to re-initialize on every render.
  const onChangeRef = useRef(onChange);
  useEffect(() => {
    onChangeRef.current = onChange;
  }, [onChange]);

  // Initialize Quill editor once on mount only.
  // Using an empty dependency array is intentional: re-running this effect would
  // create a duplicate toolbar inside the same container element each time the
  // parent re-renders (e.g. when the user interacts with other form fields).
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    if (!editorRef.current || quillRef.current) {
      return;
    }

    const quill = new Quill(editorRef.current, {
      theme: "snow",
      placeholder: placeholder,
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
    });

    quillRef.current = quill;

    // Set initial content
    if (value) {
      quill.root.innerHTML = value;
    }

    // Handle changes via ref so the handler always calls the latest onChange
    // without needing onChange in the dependency array.
    quill.on("text-change", () => {
      onChangeRef.current(name, quill.root.innerHTML);
    });

    // Expose to global registry for Cypress testing
    if (!window.quillEditors) {
      window.quillEditors = {};
    }
    window.quillEditors[name] = quill;

    // Cleanup
    return () => {
      quillRef.current = null;
      if (window.quillEditors) {
        delete window.quillEditors[name];
      }
    };
  }, []);

  return (
    <div
      ref={editorRef}
      style={{
        minHeight: minHeight,
        border: "1px solid #ccc",
        borderRadius: "4px",
      }}
    />
  );
};

export default QuillEditor;
