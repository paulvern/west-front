import { Node, mergeAttributes } from "https://esm.sh/@tiptap/core@2.11.5";

export const ManualBox = Node.create({
  name: "manualBox",
  group: "block",
  content: "block+",
  defining: true,
  isolating: true,
  draggable: false,
  
  addAttributes() {
    return { type: { default: "rule" } };
  },
  
  parseHTML() {
    return [
      { tag: "div.box.rule", attrs: { type: "rule" } },
      { tag: "div.box.example", attrs: { type: "example" } },
      { tag: "div.box.history", attrs: { type: "history" } },
      { tag: "div.box.warning", attrs: { type: "warning" } },
      { tag: "div.box.procedure", attrs: { type: "procedure" } }
    ];
  },
  
  renderHTML({ HTMLAttributes }) {
    return ["div", mergeAttributes({ class: `box ${HTMLAttributes.type}` }), 0];
  }
});

export const ChapterTitle = Node.create({
  name: "chapterTitle",
  group: "block",
  content: "heading paragraph",
  defining: true,
  isolating: true,
  
  parseHTML() { return [{ tag: "div.chapter-title" }]; },
  renderHTML() { return ["div", { class: "chapter-title" }, 0]; }
});

export const Lead = Node.create({
  name: "lead",
  group: "block",
  content: "inline*",
  defining: true,
  
  parseHTML() { return [{ tag: "p.lead" }]; },
  renderHTML() { return ["p", { class: "lead" }, 0]; }
});

export const PageBreak = Node.create({
  name: "pageBreak",
  group: "block",
  content: "block*",
  defining: true,
  isolating: true,
  
  parseHTML() { return [{ tag: "section.page-break" }]; },
  renderHTML() { return ["section", { class: "page-break" }, 0]; }
});

export const ManualTag = Node.create({
  name: "manualTag",
  inline: true,
  group: "inline",
  content: "text*",
  
  parseHTML() { return [{ tag: "span.tag" }]; },
  renderHTML() { return ["span", { class: "tag" }, 0]; }
});