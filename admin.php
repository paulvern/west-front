<?php
// admin.php

require_once __DIR__ . '/editor-config.php';

// Language detection and configuration
$lang = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['editor_lang']) ? $_SESSION['editor_lang'] : 'it');
if (!in_array($lang, ['it', 'en'])) {
    $lang = 'it';
}
$_SESSION['editor_lang'] = $lang;

$manifestFile = $lang === 'en' ? 'manual_en.json' : 'manual.json';
$sectionsDir = $lang === 'en' ? 'sections_en' : 'sections';

$error = '';

if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    if (password_verify($password, EDITOR_PASSWORD_HASH)) {
        $_SESSION['editor_logged_in'] = true;
        editor_csrf_token();
        header('Location: admin.php');
        exit;
    }
    $error = 'Password non valida';
}

$isLoggedIn = editor_is_logged_in();
$csrfToken = $isLoggedIn ? editor_csrf_token() : '';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Editor — Fronte Occidentale</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS del manuale -->
  <link rel="stylesheet" href="assets/css/manual.css?v=10">

  <!-- CSS dell'editor -->
  <link rel="stylesheet" href="assets/css/editor.css?v=10">

  <style>
    /* Stili di base per la pagina di login (se non già in manual.css) */
    .login-page {
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: #d6c6a8;
      padding: 24px;
    }
    .login-card {
      width: min(440px, 100%);
      padding: 24px;
      border: 2px solid var(--line);
      background: var(--paper2);
      box-shadow: 0 8px 28px var(--shadow);
    }
    .login-card h1 {
      margin-top: 0;
      border-bottom: 2px solid var(--line);
    }
    .login-card input {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--line);
      font-size: 1rem;
      margin: 8px 0 14px;
      background: #fff;
    }
    .login-error {
      color: var(--red);
      font-weight: 700;
      margin-bottom: 12px;
    }

    /* Layout editor */
    .editor-layout {
      display: grid;
      grid-template-columns: 310px minmax(0, 1fr);
      min-height: calc(100vh - 53px);
      max-width: 1800px;
      margin: 0 auto;
    }
    .editor-sidebar {
      background: #ead7b4;
      border-right: 1px solid var(--line);
      padding: 18px;
      overflow: auto;
    }
    .editor-main {
      padding: 22px;
      background: var(--paper);
      min-width: 0;
    }
    .section-button {
      display: block;
      width: 100%;
      text-align: left;
      margin-bottom: 5px;
      border: 1px solid transparent;
      background: transparent;
      font-weight: 600;
      padding: 7px 8px;
      border-radius: 4px;
      cursor: pointer;
    }
    .section-button:hover {
      background: #fffaf0;
      border-color: var(--line2);
    }
    .section-button.active {
      background: #fff3d2;
      border-color: var(--line);
      font-weight: 800;
    }
    .current-title {
      margin-top: 0;
      border-bottom: 2px solid var(--line);
      padding-bottom: 8px;
    }

    /* Toolbar */
    .tiptap-toolbar {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      margin-bottom: 10px;
      padding: 8px;
      background: #ead7b4;
      border: 1px solid var(--line);
    }
    .tiptap-toolbar button {
      background: #fff3d2;
      border: 1px solid var(--line);
      padding: 5px 10px;
      border-radius: 3px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.85rem;
    }
    .tiptap-toolbar button.is-active {
      background: var(--ink);
      color: #fff8e8;
      border-color: var(--ink);
    }
    .tiptap-toolbar button:hover {
      background: #fff;
    }

    /* Area editor */
    .editor-container {
      border: 2px solid var(--line);
      background: #fff;
      min-height: 800px;
      box-shadow: 0 4px 16px var(--shadow);
    }
    .ProseMirror {
      min-height: 800px;
      padding: 44px 64px !important;
      outline: none;
    }
    .ProseMirror p.is-editor-empty:first-child::before {
      content: attr(data-placeholder);
      float: left;
      color: var(--muted);
      pointer-events: none;
      height: 0;
    }

    /* Sidebar e gestione capitoli */
    .sidebar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--line2);
    }
    .sidebar-header h2 {
      margin: 0;
      font-size: 1.2rem;
    }
    .small-btn {
      font-size: 0.75rem !important;
      padding: 4px 8px !important;
      border: 1px solid var(--line2) !important;
      background: var(--paper2) !important;
      cursor: pointer;
      font-weight: 700;
    }
    .section-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px;
    }
    .section-item .section-button {
      flex: 1;
      margin-right: 4px;
    }
    .section-controls {
      display: flex;
      gap: 2px;
    }
    .section-controls button {
      font-size: 0.7rem !important;
      padding: 3px 5px !important;
      border: 1px solid var(--line2) !important;
      background: var(--paper2) !important;
      cursor: pointer;
    }

    /* Modali */
    .modal-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .modal {
      background: var(--paper2);
      padding: 20px;
      border: 2px solid var(--line);
      max-width: 400px;
      width: 90%;
    }
    .modal h3 { margin-top: 0; border-bottom: 1px solid var(--line); padding-bottom: 8px; }
    .modal-form label { display: block; margin: 10px 0 4px; font-weight: 600; }
    .modal-form input, .modal-form select { width: 100%; padding: 8px; border: 1px solid var(--line); }
    .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 15px; }

    @media (max-width: 920px) {
      .editor-layout { grid-template-columns: 1fr; }
      .editor-sidebar { position: static; height: auto; }
      .ProseMirror { padding: 22px !important; }
    }
  </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>

  <main class="login-page">
    <form class="login-card" method="post" action="admin.php">
      <h1>Editor manuale</h1>
      <p class="muted">Accesso riservato alla modifica delle sezioni HTML.</p>
      <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <label for="password"><strong>Password</strong></label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Entra</button>
    </form>
  </main>

<?php else: ?>

  <header class="topbar">
    <div class="topbar__brand">
      <div class="topbar__title">FRONTE OCCIDENTALE — EDITOR TIPTAP</div>
      <div class="topbar__version" id="versionLabel"></div>
    </div>
    <div class="topbar__actions">
      <select id="admin-language-selector" onchange="window.location.href='admin.php?lang='+this.value" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; background: white; cursor: pointer; font-size: 14px; margin-right: 8px;">
        <option value="it" <?= $lang === 'it' ? 'selected' : '' ?>>🇮🇹 Italiano</option>
        <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
      </select>
      <a href="index.html" target="_blank"><button type="button">Apri manuale</button></a>
      <a href="admin.php?logout=1"><button type="button">Esci</button></a>
    </div>
  </header>

  <div class="editor-layout">
    <!-- Sidebar -->
    <aside class="editor-sidebar">
      <div class="sidebar-header">
        <h2>Sezioni</h2>
        <button type="button" id="addChapterBtn" class="small-btn">+ Nuovo</button>
      </div>
      <div id="sectionList"></div>
    </aside>

    <!-- Area principale -->
    <main class="editor-main">
      <h1 class="current-title" id="currentTitle">Seleziona una sezione</h1>

      <div class="editor-actions" style="display:flex; gap:8px; align-items:center; margin-bottom:14px; flex-wrap:wrap;">
        <button type="button" id="saveBtn">Salva</button>
        <button type="button" id="reloadBtn">Ricarica</button>
        <button type="button" id="previewBtn">Anteprima in finestra</button>
        <span class="editor-status" id="status" style="margin-left:auto; font-weight:700; color:var(--muted);"></span>
      </div>

      <!-- Toolbar -->
      <div class="tiptap-toolbar" id="tiptapToolbar">
        <button type="button" data-cmd="bold">Grassetto</button>
        <button type="button" data-cmd="italic">Corsivo</button>
        <button type="button" data-cmd="underline">Sottolineato</button>
        <span style="border-left:1px solid var(--line); margin:0 4px;"></span>
        <button type="button" data-cmd="h1">H1</button>
        <button type="button" data-cmd="h2">H2</button>
        <button type="button" data-cmd="h3">H3</button>
        <button type="button" data-cmd="p">Paragrafo</button>
        <span style="border-left:1px solid var(--line); margin:0 4px;"></span>
        <button type="button" data-cmd="bulletList">🔘 Lista</button>
        <button type="button" data-cmd="orderedList"># Lista</button>
        <span style="border-left:1px solid var(--line); margin:0 4px;"></span>
        <button type="button" data-cmd="boxRule">🟡 Regola</button>
        <button type="button" data-cmd="boxExample">🔵 Esempio</button>
        <button type="button" data-cmd="boxHistory">🟢 Storico</button>
        <button type="button" data-cmd="boxWarning">🔴 Avviso</button>
        <button type="button" data-cmd="boxProcedure">🟣 Procedura</button>
        <span style="border-left:1px solid var(--line); margin:0 4px;"></span>
        <button type="button" data-cmd="chapterTitle">📑 Capitolo</button>
        <button type="button" data-cmd="lead">📄 Lead</button>
        <button type="button" data-cmd="tag">🏷 Tag</button>
        <button type="button" data-cmd="table">📊 Tabella</button>
        <button type="button" data-cmd="image">🖼 Immagine</button>
        <button type="button" data-cmd="pageBreak">✂️ Page Break</button>
        <span style="border-left:1px solid var(--line); margin:0 4px;"></span>
        <button type="button" data-cmd="undo">↩️</button>
        <button type="button" data-cmd="redo">↪️</button>
      </div>

      <!-- Editor TipTap -->
      <div class="editor-container">
        <div id="editor"></div>
      </div>

    </main>
  </div>

  <footer class="footer">
    <span>Fronte Occidentale — Editor TipTap</span>
  </footer>

  <!-- Passaggio del CSRF token e configurazione lingua a JavaScript (globale) -->
  <script>
    window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    window.EDITOR_LANG = <?= json_encode($lang) ?>;
    window.EDITOR_MANIFEST = <?= json_encode($manifestFile) ?>;
    window.EDITOR_SECTIONS_DIR = <?= json_encode($sectionsDir) ?>;
  </script>

  <!-- Modulo principale dell'app (app.js importa tutti gli altri) -->
  <script type="module" src="assets/js/app.js?v=10"></script>

<?php endif; ?>
</body>
</html>