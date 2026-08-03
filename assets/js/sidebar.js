export default class Sidebar {
  constructor(container, app) {
    this.container = container;
    this.app = app;
  }

  render(sections) {
    this.container.innerHTML = "";
    if (!sections || sections.length === 0) {
      this.container.innerHTML = `<div style="padding:10px; background:#fbe2dd; border-left:4px solid #8a2d24;">Nessuna sezione</div>`;
      return;
    }

    sections.forEach((section, index) => {
      const item = document.createElement("div");
      item.className = "section-item";
      item.dataset.id = section.id;
      
      item.innerHTML = `
        <button type="button" class="section-button" data-id="${section.id}">${this.escape(section.title)}</button>
        <div class="section-controls">
          <button type="button" class="btn-icon edit-btn" title="Rinomina">✏️</button>
          <button type="button" class="btn-icon delete-btn" title="Elimina">🗑️</button>
          ${index > 0 ? '<button type="button" class="btn-icon move-up-btn" title="Su">↑</button>' : ''}
          ${index < sections.length - 1 ? '<button type="button" class="btn-icon move-down-btn" title="Giù">↓</button>' : ''}
        </div>
      `;
      
      item.querySelector(".section-button").addEventListener("click", () => this.app.loadSection(section.id));
      item.querySelector(".edit-btn").addEventListener("click", (e) => { e.stopPropagation(); this.app.renameChapter(section.id, section.title); });
      item.querySelector(".delete-btn").addEventListener("click", (e) => { e.stopPropagation(); this.app.deleteChapter(section.id, section.title); });
      
      const up = item.querySelector(".move-up-btn");
      const down = item.querySelector(".move-down-btn");
      if (up) up.addEventListener("click", (e) => { e.stopPropagation(); this.app.moveChapter(section.id, -1); });
      if (down) down.addEventListener("click", (e) => { e.stopPropagation(); this.app.moveChapter(section.id, 1); });
      
      this.container.appendChild(item);
    });
  }

  setActive(id) {
    this.container.querySelectorAll(".section-button").forEach(btn => {
      btn.classList.toggle("active", btn.dataset.id === id);
    });
  }

  escape(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
}