import Api from "./api.js";
import ManualEditor from "./editor.js";
import Sidebar from "./sidebar.js";
import Toolbar from "./toolbar.js";
import Dialogs from "./dialogs.js";

class App {
  constructor() {
    this.api = new Api(window.CSRF_TOKEN);
    this.editor = null;
    this.sidebar = null;
    this.toolbar = null;
    this.dialogs = null;
    this.currentSection = null;
    this.manifest = null;
    
    this.el = {
      version: document.getElementById("versionLabel"),
      title: document.getElementById("currentTitle"),
      status: document.getElementById("status"),
      editor: document.getElementById("editor"),
      sections: document.getElementById("sectionList"),
      save: document.getElementById("saveBtn"),
      reload: document.getElementById("reloadBtn"),
      preview: document.getElementById("previewBtn"),
      add: document.getElementById("addChapterBtn")
    };
  }

  async init() {
    try {
      this.setStatus("Caricamento...");
      
      this.editor = new ManualEditor(this.el.editor);
      this.editor.init();
      
      this.sidebar = new Sidebar(this.el.sections, this);
      this.toolbar = new Toolbar(this);
      this.dialogs = new Dialogs();
      
      this.manifest = await this.api.manifest();
      this.el.version.textContent = this.manifest.version || "";
      this.sidebar.render(this.manifest.sections);
      
      this.el.save.addEventListener("click", () => this.save());
      this.el.reload.addEventListener("click", () => this.reload());
      this.el.preview.addEventListener("click", () => this.preview());
      this.el.add.addEventListener("click", () => this.newChapter());
      
      this.editor.onChange(() => this.setStatus("Modificato"));
      
      if (this.manifest.sections?.length) {
        await this.loadSection(this.manifest.sections[0].id);
      }
      
      this.setStatus("Pronto");
      window.app = this; // per debug
      
    } catch (err) {
      console.error(err);
      this.setStatus("Errore: " + err.message);
      alert("Errore avvio editor: " + err.message);
    }
  }

  async loadSection(id) {
    if (this.editor.isModified() && !confirm("Modifiche non salvate. Cambiare?")) return;
    
    try {
      this.setStatus("Caricamento...");
      const sec = await this.api.section(id);
      this.currentSection = sec;
      this.el.title.textContent = sec.title;
      this.editor.load(sec.html);
      this.sidebar.setActive(id);
      this.setStatus("Caricato");
    } catch (err) {
      this.setStatus("Errore");
      alert(err.message);
    }
  }

  async save() {
    if (!this.currentSection) return alert("Nessuna sezione");
    try {
      this.setStatus("Salvataggio...");
      const html = this.editor.getHTML();
      const res = await this.api.save(this.currentSection.id, html);
      this.editor.resetModified();
      this.setStatus("Salvato: " + res.backup);
    } catch (err) {
      this.setStatus("Errore salvataggio");
      alert(err.message);
    }
  }

  async reload() {
    if (!this.currentSection) return;
    if (this.editor.isModified() && !confirm("Perdere modifiche?")) return;
    await this.loadSection(this.currentSection.id);
  }

  preview() {
    if (!this.currentSection) return;
    const html = this.editor.getHTML();
    const base = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');
    const w = window.open("", "_blank");
    w.document.write(`
      <!doctype html><html lang="it"><head>
      <base href="${base}">
      <link rel="stylesheet" href="assets/css/manual.css">
      <style>body{background:#d6c6a8;padding:20px;}</style>
      </head><body><main class="manual">${html}</main></body></html>
    `);
    w.document.close();
  }

  async newChapter() {
    const res = await this.dialogs.promptNewChapter();
    if (!res) return;
    try {
      this.setStatus("Creo capitolo...");
      await this.api.createChapter({
        id: res.id,
        title: res.title,
        position: res.position,
        html: `<h1>${res.title}</h1><p>Contenuto...</p>`
      });
      this.manifest = await this.api.manifest();
      this.sidebar.render(this.manifest.sections);
      await this.loadSection(res.id);
    } catch (err) {
      alert(err.message);
      this.setStatus("Errore");
    }
  }

  async renameChapter(id, current) {
    const title = await this.dialogs.promptRename(current);
    if (!title) return;
    try {
      this.setStatus("Rinomino...");
      await this.api.renameChapter(id, title);
      if (this.currentSection?.id === id) {
        this.currentSection.title = title;
        this.el.title.textContent = title;
      }
      this.manifest = await this.api.manifest();
      this.sidebar.render(this.manifest.sections);
      this.setStatus("Rinominato");
    } catch (err) {
      alert(err.message);
    }
  }

  async deleteChapter(id, title) {
    if (!confirm(`Eliminare "${title}"?`)) return;
    try {
      this.setStatus("Elimino...");
      await this.api.deleteChapter(id);
      if (this.currentSection?.id === id) {
        this.currentSection = null;
        this.el.title.textContent = "Seleziona sezione";
        this.editor.clear();
      }
      this.manifest = await this.api.manifest();
      this.sidebar.render(this.manifest.sections);
      this.setStatus("Eliminato");
    } catch (err) {
      alert(err.message);
    }
  }

  async moveChapter(id, dir) {
    try {
      this.setStatus("Sposto...");
      await this.api.moveChapter(id, dir);
      this.manifest = await this.api.manifest();
      this.sidebar.render(this.manifest.sections);
      this.setStatus("Spostato");
    } catch (err) {
      alert(err.message);
    }
  }

  setStatus(msg) {
    this.el.status.textContent = msg;
    if (msg) setTimeout(() => { if (this.el.status.textContent === msg) this.el.status.textContent = ""; }, 3500);
  }
}

document.addEventListener("DOMContentLoaded", () => new App().init());