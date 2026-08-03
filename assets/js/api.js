export default class Api {
  constructor(csrfToken) {
    this.csrf = csrfToken;
    this.base = window.location.pathname.replace(/\/[^\/]*$/, '/');
    this.lang = window.EDITOR_LANG || 'it';
  }

  async get(action, params = {}) {
    const url = new URL("api-editor.php", window.location.origin + this.base);
    url.searchParams.set("action", action);
    url.searchParams.set("lang", this.lang);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    
    const res = await fetch(url, { credentials: "same-origin" });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || "Errore API");
    return json;
  }

  async post(action, body = {}, params = {}) {
    const url = new URL("api-editor.php", window.location.origin + this.base);
    url.searchParams.set("action", action);
    url.searchParams.set("lang", this.lang);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    
    const res = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": this.csrf
      },
      body: JSON.stringify(body)
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || "Errore API");
    return json;
  }

  manifest() { return this.get("manifest"); }
  section(id) { return this.get("section", { id }); }
  save(id, html) { return this.post("save", { html }, { id }); }
  createChapter(data) { return this.post("create-chapter", data); }
  renameChapter(id, title) { return this.post("rename-chapter", { id, title }); }
  deleteChapter(id) { return this.post("delete-chapter", { id }); }
  moveChapter(id, direction) { return this.post("move-chapter", { id, direction }); }
}