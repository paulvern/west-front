import { Editor } from "https://esm.sh/@tiptap/core@2.11.5";
import StarterKit from "https://esm.sh/@tiptap/starter-kit@2.11.5";
import Underline from "https://esm.sh/@tiptap/extension-underline@2.11.5";
import Link from "https://esm.sh/@tiptap/extension-link@2.11.5";
import Image from "https://esm.sh/@tiptap/extension-image@2.11.5";
import Table from "https://esm.sh/@tiptap/extension-table@2.11.5";
import TableRow from "https://esm.sh/@tiptap/extension-table-row@2.11.5";
import TableCell from "https://esm.sh/@tiptap/extension-table-cell@2.11.5";
import TableHeader from "https://esm.sh/@tiptap/extension-table-header@2.11.5";

import { ManualBox, ChapterTitle, Lead, ManualTag, PageBreak } from "./nodes.js";
import { ManualFigure, FigureCaption } from "./images.js";

export default class ManualEditor {
  constructor(containerEl) {
    this.containerEl = containerEl;
    this.editor = null;
    this.modified = false;
    this.changeCallbacks = [];
    this._initialized = false;
  }

  init({ extraExtensions = [] } = {}) {
    if (this._initialized) return;
    if (!this.containerEl) throw new Error("ManualEditor: containerEl mancante");

    this.editor = new Editor({
      element: this.containerEl,
      extensions: [
        StarterKit.configure({ history: true }),
        Underline,
        Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true }),
        Image.configure({ inline: false, allowBase64: true }),
        Table.configure({ resizable: true }),
        TableRow, TableHeader, TableCell,
        ManualBox, ChapterTitle, Lead, ManualTag, PageBreak,
        ManualFigure, FigureCaption,
        ...extraExtensions
      ],
      editorProps: {
        attributes: { class: "manual" }
      },
      onUpdate: () => {
        this.modified = true;
        for (const cb of this.changeCallbacks) cb(this);
      }
    });

    this._initialized = true;
  }

  destroy() {
    if (this.editor) this.editor.destroy();
    this.editor = null;
    this._initialized = false;
  }

  focus() { this.editor?.chain().focus().run(); }
  
  load(html = "") {
    if (!this.editor) throw new Error("Non inizializzato");
    this.modified = false;
    this.editor.commands.setContent(html ?? "", false);
  }

  clear() {
    this.modified = false;
    this.editor?.commands.clearContent();
  }

  getHTML() { return this.editor ? this.editor.getHTML() : ""; }
  isModified() { return this.modified; }
  resetModified() { this.modified = false; }
  
  onChange(cb) {
    if (typeof cb === "function") this.changeCallbacks.push(cb);
  }

  // Formattazione
  paragraph() { this.editor?.chain().focus().setParagraph().run(); }
  h1() { this.editor?.chain().focus().toggleHeading({ level: 1 }).run(); }
  h2() { this.editor?.chain().focus().toggleHeading({ level: 2 }).run(); }
  h3() { this.editor?.chain().focus().toggleHeading({ level: 3 }).run(); }
  bold() { this.editor?.chain().focus().toggleBold().run(); }
  italic() { this.editor?.chain().focus().toggleItalic().run(); }
  underline() { this.editor?.chain().focus().toggleUnderline().run(); }
  strike() { try { this.editor?.chain().focus().toggleStrike().run(); } catch(_) {} }
  blockquote() { try { this.editor?.chain().focus().toggleBlockquote().run(); } catch(_) {} }

  // Liste
  bulletList() { this.editor?.chain().focus().toggleBulletList().run(); }
  orderedList() { this.editor?.chain().focus().toggleOrderedList().run(); }

  // Link
  setLink(url) {
    const safe = String(url ?? "").trim();
    if (!safe || !this.editor) return;
    this.editor.chain().focus().extendMarkRange("link").setLink({ href: safe }).run();
  }
  unsetLink() { this.editor?.chain().focus().unsetLink().run(); }

  // Undo/Redo
  undo() { try { this.editor?.chain().focus().undo().run(); } catch(_) {} }
  redo() { try { this.editor?.chain().focus().redo().run(); } catch(_) {} }

  // Tabelle
  insertTable({ rows = 3, cols = 3 } = {}) {
    this.editor?.chain().focus().insertTable({ rows, cols, withHeaderRow: true }).run();
  }

  // Immagini base
  insertImage({ src, alt = "" } = {}) {
    const s = String(src ?? "").trim();
    if (!s || !this.editor) return;
    this.editor.chain().focus().setImage({ src: s, alt: String(alt) }).run();
  }

  // Nodi del manuale
  insertManualBox(type = "rule") {
    if (!this.editor) return;
    const types = ["rule", "example", "history", "warning", "procedure"];
    const safe = types.includes(type) ? type : "rule";
    
    this.editor.chain().focus().insertContent({
      type: "manualBox",
      attrs: { type: safe },
      content: [{
        type: "paragraph",
        content: [{ 
          type: "text", 
          text: safe === "rule" ? "Regola: " : safe === "example" ? "Esempio: " : "" 
        }]
      }]
    }).run();
  }

  insertChapterTitle({ title = "Titolo", subtitle = "" } = {}) {
    if (!this.editor) return;
    this.editor.chain().focus().insertContent({
      type: "chapterTitle",
      content: [
        { type: "heading", attrs: { level: 1 }, content: [{ type: "text", text: title }] },
        { type: "paragraph", content: subtitle ? [{ type: "text", text: subtitle }] : [] }
      ]
    }).run();
  }

  insertLead(text = "Testo introduttivo...") {
    if (!this.editor) return;
    this.editor.chain().focus().insertContent({
      type: "lead",
      content: [{ type: "text", text: String(text) }]
    }).run();
  }

  insertTag(text = "Tag") {
    if (!this.editor) return;
    this.editor.chain().focus().insertContent({
      type: "manualTag",
      content: [{ type: "text", text: String(text) }]
    }).run();
  }

  insertPageBreak() {
    if (!this.editor) return;
    this.editor.chain().focus().insertContent({ type: "pageBreak" }).run();
  }

  insertRawHTML(html) {
    if (!this.editor) return;
    this.editor.chain().focus().insertContent(String(html)).run();
  }
}