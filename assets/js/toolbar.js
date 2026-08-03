export default class Toolbar {
  constructor(app) {
    this.app = app;
    this.container = document.getElementById("tiptapToolbar");
    this.init();
  }

  init() {
    if (!this.container) return;
    this.container.addEventListener("click", (e) => {
      const btn = e.target.closest("button");
      if (!btn) return;
      const cmd = btn.dataset.cmd;
      if (cmd) this.execute(cmd);
    });
  }

  execute(cmd) {
    const ed = this.app.editor;
    switch(cmd) {
      case "bold": ed.bold(); break;
      case "italic": ed.italic(); break;
      case "underline": ed.underline(); break;
      case "strike": ed.strike(); break;
      case "h1": ed.h1(); break;
      case "h2": ed.h2(); break;
      case "h3": ed.h3(); break;
      case "p": ed.paragraph(); break;
      case "bulletList": ed.bulletList(); break;
      case "orderedList": ed.orderedList(); break;
      case "boxRule": ed.insertManualBox("rule"); break;
      case "boxExample": ed.insertManualBox("example"); break;
      case "boxHistory": ed.insertManualBox("history"); break;
      case "boxWarning": ed.insertManualBox("warning"); break;
      case "boxProcedure": ed.insertManualBox("procedure"); break;
      case "chapterTitle": ed.insertChapterTitle({ title: "Titolo Capitolo", subtitle: "Descrizione..." }); break;
      case "lead": ed.insertLead("Testo introduttivo in evidenza..."); break;
      case "tag": ed.insertTag("Tag"); break;
      case "pageBreak": ed.insertPageBreak(); break;
      case "table": ed.insertTable({ rows: 3, cols: 3 }); break;
      case "image": {
        const url = prompt("URL immagine:", "assets/img/manual/immagine.jpg");
        if (url) ed.insertImage({ src: url, alt: "" });
        break;
      }
      case "undo": ed.undo(); break;
      case "redo": ed.redo(); break;
    }
  }
}