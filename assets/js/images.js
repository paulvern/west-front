import { Node, mergeAttributes } from "https://esm.sh/@tiptap/core@2.11.5";

export const ManualFigure = Node.create({
  name: "manualFigure",
  group: "block",
  content: "image figcaption",
  defining: true,
  isolating: true,
  draggable: true,

  addAttributes() {
    return {
      class: { default: "image" },
      style: { default: null }
    };
  },

  parseHTML() {
    return [
      { tag: "figure.image", getAttrs: el => ({ class: el.className, style: el.getAttribute("style") }) },
      { tag: "figure.image_resized", getAttrs: el => ({ class: "image image_resized", style: el.getAttribute("style") }) }
    ];
  },

  renderHTML({ HTMLAttributes }) {
    const attrs = HTMLAttributes.style 
      ? { class: HTMLAttributes.class, style: HTMLAttributes.style }
      : { class: HTMLAttributes.class };
    return ["figure", attrs, 0];
  }
});

export const FigureCaption = Node.create({
  name: "figcaption",
  group: "block",
  content: "inline*",
  
  parseHTML() { return [{ tag: "figcaption" }]; },
  renderHTML() { return ["figcaption", 0]; }
});