export default class Dialogs {
  constructor() {
    this.overlay = null;
  }

  createModal(title, contentHTML) {
    this.close();
    this.overlay = document.createElement("div");
    this.overlay.className = "modal-overlay";
    this.overlay.innerHTML = `
      <div class="modal">
        <h3>${this.escape(title)}</h3>
        <div class="modal-content">${contentHTML}</div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel">Annulla</button>
          <button type="button" class="btn-confirm">Conferma</button>
        </div>
      </div>
    `;
    document.body.appendChild(this.overlay);
    
    return new Promise((resolve) => {
      this.overlay.querySelector(".btn-confirm").addEventListener("click", () => resolve(true));
      this.overlay.querySelector(".btn-cancel").addEventListener("click", () => resolve(false));
      this.overlay.addEventListener("click", (e) => { if (e.target === this.overlay) resolve(false); });
    });
  }

  close() {
    if (this.overlay) {
      this.overlay.remove();
      this.overlay = null;
    }
  }

  async promptNewChapter() {
    const sections = window.app?.manifest?.sections || [];
    const opts = sections.map((s, i) => `<option value="${i}">Prima di: ${this.escape(s.title)}</option>`).join("");
    
    const html = `
      <div class="modal-form">
        <label>ID (solo minuscole, numeri, trattini):</label>
        <input type="text" id="dlg-id" placeholder="es: capitolo-5">
        <label>Titolo:</label>
        <input type="text" id="dlg-title" placeholder="Titolo">
        <label>Posizione:</label>
        <select id="dlg-pos"><option value="end">Alla fine</option>${opts}</select>
      </div>
    `;
    
    const ok = await this.createModal("Nuovo Capitolo", html);
    if (!ok) { this.close(); return null; }
    
    const id = document.getElementById("dlg-id").value.trim();
    const title = document.getElementById("dlg-title").value.trim();
    const pos = document.getElementById("dlg-pos").value;
    this.close();
    
    if (!id || !title) { alert("Campi obbligatori"); return null; }
    if (!/^[a-z0-9-]+$/.test(id)) { alert("ID non valido"); return null; }
    
    return { id, title, position: pos };
  }

  async promptRename(current) {
    const html = `<div class="modal-form"><label>Nuovo titolo:</label><input type="text" id="dlg-title" value="${this.escape(current)}"></div>`;
    const ok = await this.createModal("Rinomina", html);
    if (!ok) { this.close(); return null; }
    const title = document.getElementById("dlg-title").value.trim();
    this.close();
    return title || null;
  }

  escape(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
}