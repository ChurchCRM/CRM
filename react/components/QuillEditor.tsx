import * as React from "react";
import { useEffect, useRef } from "react";
import Quill from "quill";
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

  // Initialize Quill editor once on mount
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

    // Handle changes
    quill.on("text-change", () => {
      onChange(name, quill.root.innerHTML);
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
  }, [name]);

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
