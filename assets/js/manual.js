// Language configuration
const LANG_CONFIG = {
  it: {
    manifestUrl: "./manual.json",
    sectionsDir: "sections",
    labels: {
      loadAll: "Carica tutto",
      print: "Stampa / PDF",
      index: "Indice",
      indexTitle: "Indice",
      loading: "Caricamento indice…",
      loadingSection: "Caricamento",
      loadingAll: "Caricamento manuale completo…",
      error: "Errore",
      errorLoadSection: "Impossibile caricare la sezione",
      sectionMissing: "Sezione mancante",
      errorStart: "Errore di avvio",
      errorManifest: "Impossibile caricare",
      localServerHint: "Se stai aprendo il file con doppio click, avvia invece un server locale:"
    }
  },
  en: {
    manifestUrl: "./manual_en.json",
    sectionsDir: "sections_en",
    labels: {
      loadAll: "Load All",
      print: "Print / PDF",
      index: "Index",
      indexTitle: "Index",
      loading: "Loading index…",
      loadingSection: "Loading",
      loadingAll: "Loading complete manual…",
      error: "Error",
      errorLoadSection: "Cannot load section",
      sectionMissing: "Missing section",
      errorStart: "Startup error",
      errorManifest: "Cannot load",
      localServerHint: "If you're opening the file with double-click, start a local server instead:"
    }
  }
};

const state = {
  sections: [],
  loadedAll: false,
  currentLang: localStorage.getItem('language') || 'it'
};

const root = document.getElementById("manual-root");
const nav = document.getElementById("nav-sections");
const btnLoadAll = document.getElementById("btn-load-all");
const btnPrint = document.getElementById("btn-print");
const btnToggleNav = document.getElementById("btn-toggle-nav");
const sidebar = document.getElementById("sidebar");
const langSelector = document.getElementById("language-selector");

function getLang() {
  return LANG_CONFIG[state.currentLang];
}

function updateUILabels() {
  const lang = getLang();
  btnLoadAll.textContent = lang.labels.loadAll;
  btnPrint.textContent = lang.labels.print;
  btnToggleNav.textContent = lang.labels.index;
  document.querySelector('.sidebar h2').textContent = lang.labels.indexTitle;
}

async function fetchJson(url) {
  const cacheBustedUrl = url + (url.includes('?') ? '&' : '?') + '_=' + Date.now();
  const response = await fetch(cacheBustedUrl, {
    cache: 'no-store',
    headers: {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    }
  });
  if (!response.ok) {
    throw new Error(`${getLang().labels.error} loading JSON: ${url}`);
  }
  return response.json();
}

async function fetchText(url) {
  const cacheBustedUrl = url + (url.includes('?') ? '&' : '?') + '_=' + Date.now();
  const response = await fetch(cacheBustedUrl, {
    cache: 'no-store',
    headers: {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    }
  });
  if (!response.ok) {
    throw new Error(`${getLang().labels.error} loading section: ${url}`);
  }
  return response.text();
}

function renderNav() {
  nav.innerHTML = "";

  state.sections.forEach((section) => {
    const link = document.createElement("a");
    link.className = "nav-link";
    link.href = `#${section.id}`;
    link.textContent = Number.isInteger(section.number)
      ? `${String(section.number).padStart(2, "0")}. ${section.title}`
      : section.title;
    link.dataset.id = section.id;

    link.addEventListener("click", async (event) => {
      event.preventDefault();
      await loadSingleSection(section.id);
      closeMobileSidebar();
    });

    nav.appendChild(link);
  });
}

function setActiveNav(sectionId) {
  document.querySelectorAll(".nav-link").forEach((link) => {
    link.classList.toggle("active", link.dataset.id === sectionId);
  });
}

async function loadSingleSection(sectionId) {
  const section = state.sections.find((s) => s.id === sectionId);
  if (!section) return;

  const lang = getLang();
  root.innerHTML = `<section class="loading-panel"><p>${lang.labels.loadingSection} ${section.title}…</p></section>`;

  try {
    const html = await fetchText(section.file);
    root.innerHTML = html;
    setActiveNav(sectionId);

    const target = document.getElementById(sectionId);
    if (target) {
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    state.loadedAll = false;
  } catch (error) {
    root.innerHTML = `
      <section class="box warning">
        <h1>${lang.labels.error}</h1>
        <p>${lang.labels.errorLoadSection} <code>${section.file}</code>.</p>
        <p>${error.message}</p>
      </section>
    `;
  }
}

async function loadAllSections() {
  const lang = getLang();
  root.innerHTML = `<section class="loading-panel"><p>${lang.labels.loadingAll}</p></section>`;

  const chunks = [];

  for (const section of state.sections) {
    try {
      const html = await fetchText(section.file);
      chunks.push(html);
    } catch (error) {
      chunks.push(`
        <section class="box warning">
          <h1>${lang.labels.sectionMissing}</h1>
          <p>${lang.labels.errorLoadSection} <code>${section.file}</code>.</p>
          <p>${error.message}</p>
        </section>
      `);
    }
  }

  root.innerHTML = chunks.join("\n\n");
  state.loadedAll = true;

  document.querySelectorAll(".nav-link").forEach((link) => {
    link.classList.remove("active");
  });

  window.scrollTo({ top: 0, behavior: "smooth" });
}

function closeMobileSidebar() {
  sidebar.classList.remove("is-open");
}

function toggleMobileSidebar() {
  sidebar.classList.toggle("is-open");
}

async function changeLanguage(newLang) {
  if (newLang === state.currentLang) return;
  
  state.currentLang = newLang;
  localStorage.setItem('language', newLang);
  
  // Update UI labels
  updateUILabels();
  
  // Reload content
  await init();
}

async function init() {
  const lang = getLang();
  
  // Set language selector
  langSelector.value = state.currentLang;
  
  // Update UI labels
  updateUILabels();
  
  // Show loading
  nav.innerHTML = `<p class="muted">${lang.labels.loading}</p>`;
  
  try {
    const manifest = await fetchJson(lang.manifestUrl);
    state.sections = manifest.sections;
    renderNav();

    if (state.sections.length > 0) {
      await loadSingleSection(state.sections[0].id);
    }
  } catch (error) {
    nav.innerHTML = `<p class="warning">${lang.labels.error} loading index.</p>`;
    root.innerHTML = `
      <section class="box warning">
        <h1>${lang.labels.errorStart}</h1>
        <p>${lang.labels.errorManifest} <code>${lang.manifestUrl}</code>.</p>
        <p>${error.message}</p>
        <p>
          ${lang.labels.localServerHint}
          <code>python -m http.server 8000</code>.
        </p>
      </section>
    `;
  }
}

// Event listeners
btnLoadAll.addEventListener("click", loadAllSections);

btnPrint.addEventListener("click", async () => {
  if (!state.loadedAll) {
    await loadAllSections();
  }
  window.print();
});

btnToggleNav.addEventListener("click", toggleMobileSidebar);

langSelector.addEventListener("change", (e) => {
  changeLanguage(e.target.value);
});

// Initialize
init();
