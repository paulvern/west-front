<?php
$db_file = __DIR__ . '/ww1_wargame_maps.sqlite';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saved_maps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            image_base64 TEXT,
            map_state TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    $title = trim($_POST['title'] ?? 'Unknown Scenario');
    $image = $_POST['image'] ?? '';
    $state = $_POST['state'] ?? '{}';

    if ($title === '') {
        $title = 'Unknown Scenario';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO saved_maps (title, image_base64, map_state)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$title, $image, $state]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Saved to local database!',
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Save error: ' . $e->getMessage()
        ]);
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $stmt = $pdo->query("
            SELECT id, title, created_at
            FROM saved_maps
            ORDER BY created_at DESC
            LIMIT 100
        ");

        echo json_encode([
            'status' => 'success',
            'maps' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'load') {
    header('Content-Type: application/json; charset=utf-8');

    $id = (int)($_GET['id'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            SELECT id, title, image_base64, map_state, created_at
            FROM saved_maps
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $map = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$map) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Scenario not found.'
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'map' => $map
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_saved') {
    header('Content-Type: application/json; charset=utf-8');

    $id = (int)($_POST['id'] ?? 0);

    try {
        $stmt = $pdo->prepare("DELETE FROM saved_maps WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Scenario deleted.'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Wargame Map Editor — WWI Edition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

  <style>
    :root {
      --panel-bg: #e8e2d5;
      --border-color: #8c8275;
      --font-main: Georgia, serif;
      --active-color: #4a5d4e;
      --blue: #285473;
      --danger: #8b3a3a;
      --paper: #f4efe6;
      --ink: #2b2b2b;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      height: 100vh;
      overflow: hidden;
      background: #d8d2c4;
      font-family: var(--font-main);
      color: var(--ink);
    }

    button,
    input,
    select {
      font-family: inherit;
      font-size: 11px;
    }

    button {
      cursor: pointer;
      border-radius: 3px;
    }

    label {
      font-weight: bold;
      font-size: 11px;
      text-transform: uppercase;
    }

    .windows-app {
      height: 100vh;
      display: flex;
      flex-direction: column;
      background: #d8d2c4;
    }

    .app-titlebar {
      height: 34px;
      flex: 0 0 34px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #2f3e35;
      color: #fff;
      padding: 0 10px;
      border-bottom: 1px solid #1d261f;
    }

    .app-title {
      font-weight: bold;
      font-size: 13px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .app-title-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .titlebar-label {
      color: #fff;
      font-size: 10px;
    }

    .app-titlebar select {
      height: 24px;
      padding: 2px 6px;
      font-size: 11px;
    }

    .menu-bar {
      height: 29px;
      flex: 0 0 29px;
      display: flex;
      align-items: stretch;
      background: #ece7dc;
      border-bottom: 1px solid #8c8275;
      padding-left: 4px;
      z-index: 5000;
    }

    .menu-item {
      position: relative;
    }

    .menu-button {
      height: 29px;
      padding: 0 12px;
      border: none;
      background: transparent;
      cursor: pointer;
      font-size: 12px;
      color: #222;
      border-radius: 0;
    }

    .menu-button:hover,
    .menu-item:hover .menu-button {
      background: #d6cebf;
    }

    .menu-dropdown {
      display: none;
      position: absolute;
      top: 29px;
      left: 0;
      min-width: 230px;
      background: #f7f3eb;
      border: 1px solid #8c8275;
      box-shadow: 3px 4px 12px rgba(0,0,0,0.25);
      z-index: 10000;
      padding: 4px;
    }

    .menu-item:hover .menu-dropdown {
      display: flex;
      flex-direction: column;
    }

    .menu-dropdown button {
      text-align: left;
      border: none;
      background: transparent;
      padding: 7px 10px;
      cursor: pointer;
      font-size: 12px;
      border-radius: 2px;
    }

    .menu-dropdown button:hover {
      background: #d8e4f2;
    }

    .menu-separator {
      height: 1px;
      background: #b7ad9f;
      margin: 4px 2px;
    }

    .quick-toolbar {
      min-height: 38px;
      flex: 0 0 38px;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 5px 8px;
      background: #e8e2d5;
      border-bottom: 1px solid #8c8275;
      z-index: 4000;
    }

    .quick-toolbar input {
      height: 26px;
      width: 300px;
      padding: 5px;
      border: 1px solid #8c8275;
    }

    .toolbar-btn {
      height: 26px;
      padding: 3px 8px;
      background: #fff;
      border: 1px solid #8c8275;
      cursor: pointer;
      font-weight: bold;
    }

    .toolbar-btn.active {
      background: var(--active-color);
      color: white;
    }

    .toolbar-separator {
      width: 1px;
      height: 24px;
      background: #8c8275;
      margin: 0 4px;
    }

    .workspace {
      position: relative;
      flex: 1;
      min-height: 0;
      overflow: hidden;
      background: #bdb6a8;
    }

    #map {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 0;
      background: #e5e0d8;
    }

    .float-window {
      position: absolute;
      min-width: 260px;
      min-height: 42px;
      background: #e8e2d5;
      border: 2px solid #6f665b;
      box-shadow: 4px 6px 16px rgba(0,0,0,0.35);
      z-index: 3000;
      display: flex;
      flex-direction: column;
      resize: both;
      overflow: hidden;
    }

    .float-window.hidden {
      display: none;
    }

    .float-window.minimised {
      height: 34px !important;
      min-height: 34px;
      resize: none;
    }

    .float-window.minimised .float-body {
      display: none;
    }

    .float-titlebar {
      height: 30px;
      flex: 0 0 30px;
      background: #4a5d4e;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: move;
      user-select: none;
      padding: 0 6px 0 10px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .float-titlebar button {
      width: 24px;
      height: 22px;
      padding: 0;
      margin-left: 3px;
      border: 1px solid rgba(0,0,0,0.35);
      background: #f4efe6;
      color: #222;
      cursor: pointer;
      font-weight: bold;
    }

    .float-titlebar button:hover {
      background: #fff;
    }

    .float-body {
      flex: 1;
      overflow: auto;
      padding: 8px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    input,
    select {
      padding: 6px;
      border: 1px solid var(--border-color);
    }

    input[type="range"] {
      padding: 0;
    }

    button.action-btn {
      background: var(--active-color);
      color: white;
      font-weight: bold;
      cursor: pointer;
      border: none;
      padding: 8px;
    }

    button.primary-btn {
      background: var(--blue);
      color: white;
      border: none;
      font-weight: bold;
      padding: 10px;
      cursor: pointer;
    }

    button.secondary-btn {
      background: #6b5c45;
      color: white;
      border: none;
      font-weight: bold;
      padding: 8px;
      cursor: pointer;
    }

    button.danger {
      background: var(--danger);
      color: white;
      border: none;
      cursor: pointer;
      padding: 8px;
    }

    .small-note {
      font-size: 10px;
      line-height: 1.35;
      color: #50483d;
      background: rgba(255,255,255,0.45);
      padding: 6px;
      border: 1px dashed var(--border-color);
    }

    .hex-list-scroll.bottom {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 6px;
      padding: 0;
      min-height: 0;
    }

    .hex-card {
      background: #fff;
      border: 1px solid var(--border-color);
      padding: 6px;
      display: grid;
      grid-template-columns: 60px minmax(160px, 1fr) 46px 180px 120px 34px;
      gap: 6px;
      align-items: center;
      font-size: 10px;
    }

    .hex-card.selected {
      border: 2px solid #d9534f;
      background: #fff8f8;
    }

    .saved-list {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-height: 90px;
      overflow-y: auto;
      background: rgba(255,255,255,0.35);
      border: 1px solid var(--border-color);
      padding: 6px;
    }

    .saved-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 62px 32px;
      gap: 6px;
      align-items: center;
      background: #fff;
      border: 1px solid #b7ad9f;
      padding: 6px;
      font-size: 10px;
    }

    .saved-row div {
      min-width: 0;
    }

    .saved-row strong {
      display: block;
      font-size: 11px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .saved-row span {
      display: block;
      font-size: 9px;
      color: #666;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .legend {
      display: grid;
      grid-template-columns: 42px 1fr;
      gap: 6px 8px;
      align-items: center;
      font-size: 10px;
      background: rgba(255,255,255,0.35);
      border: 1px solid var(--border-color);
      padding: 8px;
      overflow-y: auto;
    }

    .flag-img {
      width: 38px;
      height: 24px;
      object-fit: contain;
      border: 1px solid rgba(0,0,0,0.35);
      background: #fff;
      display: inline-block;
      vertical-align: middle;
    }

    .hex-label-container {
      text-align: center;
      pointer-events: none;
      text-shadow: 0 1px 1px rgba(255,255,255,0.9);
    }

    .exporting .leaflet-control-container {
      display: none !important;
    }

    @media (max-width: 900px) {
      .quick-toolbar {
        height: auto;
        flex-wrap: wrap;
      }

      .quick-toolbar input {
        width: 180px;
      }

      .float-window {
        max-width: calc(100vw - 20px);
      }

      .hex-card {
        grid-template-columns: 50px minmax(120px, 1fr) 42px 130px 90px 30px;
      }
    }
  </style>
</head>

<body>

<div class="windows-app">

  <div class="app-titlebar">
    <div class="app-title" data-i18n="appTitle">Wargame Map Editor — WWI Edition</div>

    <div class="app-title-actions">
      <label class="titlebar-label" data-i18n="languageLabel">Interface language</label>
      <select id="languageSelect" onchange="setLanguage(this.value)">
        <option value="en">English</option>
        <option value="it">Italiano</option>
      </select>
    </div>
  </div>

  <div class="menu-bar">

    <div class="menu-item">
      <button class="menu-button" data-i18n="menuFile">File</button>
      <div class="menu-dropdown">
        <button onclick="saveToDatabase()" data-i18n="saveDb">💾 Save to Local DB</button>
        <button onclick="exportPNG()" data-i18n="exportPng">🖼 Export PNG</button>
        <div class="menu-separator"></div>
        <button onclick="refreshSavedMaps(); showFloatWindow('savedWindow')" data-i18n="refreshSaved">↻ Refresh Saved Maps</button>
      </div>
    </div>

    <div class="menu-item">
      <button class="menu-button" data-i18n="menuEdit">Edit</button>
      <div class="menu-dropdown">
        <button onclick="addNewHexCenter()" data-i18n="addHexCentre">+ Hex at Centre</button>
        <button onclick="clearAll()" data-i18n="clearMap">🗑 Clear Map</button>
      </div>
    </div>

    <div class="menu-item">
      <button class="menu-button" data-i18n="menuView">View</button>
      <div class="menu-dropdown">
        <button onclick="toggleFloatWindow('hexWindow')" data-i18n="hexList">Hex List</button>
        <button onclick="toggleFloatWindow('savedWindow')" data-i18n="panelSaved">Saved Scenarios</button>
        <button onclick="toggleFloatWindow('legendWindow')" data-i18n="panelLegend">WWI Flag Legend</button>
        <button onclick="toggleFloatWindow('mapWindow')" data-i18n="panelCartography">Cartography</button>
        <button onclick="toggleFloatWindow('labelWindow')" data-i18n="panelLabels">Labels</button>
      </div>
    </div>

    <div class="menu-item">
      <button class="menu-button" data-i18n="menuTools">Tools</button>
      <div class="menu-dropdown">
        <button onclick="setTool('select')" data-i18n="placeBtn">👆 Place</button>
        <button onclick="setTool('link')" data-i18n="linkBtn">🔗 Link</button>
      </div>
    </div>

    <div class="menu-item">
      <button class="menu-button" data-i18n="menuMap">Map</button>
      <div class="menu-dropdown">
        <button onclick="showFloatWindow('mapWindow')" data-i18n="panelCartography">Cartography</button>
        <button onclick="testCurrentMapProvider()" data-i18n="testMap">🧪 Test Map/WMS</button>
      </div>
    </div>

    <div class="menu-item">
      <button class="menu-button" data-i18n="menuLabels">Labels</button>
      <div class="menu-dropdown">
        <button onclick="showFloatWindow('labelWindow')" data-i18n="panelLabels">Labels</button>
      </div>
    </div>

  </div>

  <div class="quick-toolbar">
    <input type="text" id="searchInput" data-i18n-placeholder="searchPlaceholder" placeholder="E.g. Verdun, Isonzo, Gallipoli...">
    <button class="toolbar-btn" onclick="searchLocation()" data-i18n="searchBtn">Search</button>

    <span class="toolbar-separator"></span>

    <button id="toolSelect" class="toolbar-btn active" onclick="setTool('select')" data-i18n="placeBtn">👆 Place</button>
    <button id="toolLink" class="toolbar-btn" onclick="setTool('link')" data-i18n="linkBtn">🔗 Link</button>
    <button class="toolbar-btn" onclick="addNewHexCenter()" data-i18n="addHexCentre">+ Hex at Centre</button>
  </div>

  <div class="workspace">
    <div id="map"></div>

    <div id="hexWindow" class="float-window" style="left: 20px; top: 20px; width: 780px; height: 270px;">
      <div class="float-titlebar">
        <span><span data-i18n="hexList">Hex List</span> (<span id="hexCount">0</span>)</span>
        <div>
          <button onclick="minimiseFloatWindow('hexWindow')">_</button>
          <button onclick="hideFloatWindow('hexWindow')">×</button>
        </div>
      </div>
      <div class="float-body">
        <div class="hex-list-scroll bottom" id="hexListContainer"></div>
      </div>
    </div>

    <div id="savedWindow" class="float-window" style="left: 830px; top: 20px; width: 440px; height: 320px;">
      <div class="float-titlebar">
        <span data-i18n="panelSaved">Saved Scenarios</span>
        <div>
          <button onclick="minimiseFloatWindow('savedWindow')">_</button>
          <button onclick="hideFloatWindow('savedWindow')">×</button>
        </div>
      </div>
      <div class="float-body">
        <button class="secondary-btn" onclick="refreshSavedMaps()" data-i18n="refreshSaved">↻ Refresh Saved Maps</button>
        <div class="saved-list" id="savedMapsList">
          <em style="font-size:10px;" data-i18n="clickRefresh">Click “Refresh Saved Maps”.</em>
        </div>
      </div>
    </div>

    <div id="legendWindow" class="float-window hidden" style="left: 830px; top: 360px; width: 440px; height: 330px;">
      <div class="float-titlebar">
        <span data-i18n="panelLegend">WWI Flag Legend</span>
        <div>
          <button onclick="minimiseFloatWindow('legendWindow')">_</button>
          <button onclick="hideFloatWindow('legendWindow')">×</button>
        </div>
      </div>
      <div class="float-body">
        <div class="legend" id="legendContainer"></div>
      </div>
    </div>

    <div id="mapWindow" class="float-window hidden" style="left: 20px; top: 310px; width: 380px; height: 350px;">
      <div class="float-titlebar">
        <span data-i18n="panelCartography">Cartography</span>
        <div>
          <button onclick="minimiseFloatWindow('mapWindow')">_</button>
          <button onclick="hideFloatWindow('mapWindow')">×</button>
        </div>
      </div>
      <div class="float-body">
        <div class="form-group">
          <label data-i18n="mapType">Map Type</label>
          <select id="mapProvider" onchange="changeMapProvider()">
            <option value="topo_historic" data-i18n="mapTopo">Vintage Map — OpenTopo</option>
            <option value="osm" data-i18n="mapOsm">OpenStreetMap Standard</option>
            <option value="igm_25k_http" data-i18n="mapIgmHttp">IGM 1:25,000 — Italy, PCN HTTP</option>
            <option value="igm_25k_https" data-i18n="mapIgmHttps">IGM 1:25,000 — Italy, PCN HTTPS</option>
            <option value="ortofoto_88_http" data-i18n="mapOrthoHttp">Black & White Orthophoto 1988 — Italy, PCN HTTP</option>
            <option value="ortofoto_88_https" data-i18n="mapOrthoHttps">Black & White Orthophoto 1988 — Italy, PCN HTTPS</option>
            <option value="ign_france" data-i18n="mapIgn">État-Major Map — France</option>
            <option value="esri_sat" data-i18n="mapEsri">Satellite — Esri</option>
          </select>
        </div>

        <div class="form-group">
          <label><span data-i18n="mapOpacity">Map Opacity</span>: <span id="opacityVal">80%</span></label>
          <input type="range" id="mapOpacity" min="0" max="1" step="0.1" value="0.8" oninput="updateOpacity(this.value)">
        </div>

        <div class="form-group">
          <label><span data-i18n="hexRadius">Hex Radius</span>: <span id="hexRadiusVal">800 m</span></label>
          <input type="range" id="hexRadius" min="200" max="2500" step="100" value="800" oninput="updateHexRadius(this.value)">
        </div>

        <button class="secondary-btn" onclick="testCurrentMapProvider()" data-i18n="testMap">🧪 Test Map/WMS</button>

        <div class="small-note">
          <strong data-i18n="wmsNoteTitle">WMS note:</strong>
          <span data-i18n="wmsNoteText">PCN/Geoportal services may only work over HTTP or may have CORS limits.</span>
        </div>
      </div>
    </div>

    <div id="labelWindow" class="float-window hidden" style="left: 420px; top: 310px; width: 380px; height: 300px;">
      <div class="float-titlebar">
        <span data-i18n="panelLabels">Labels</span>
        <div>
          <button onclick="minimiseFloatWindow('labelWindow')">_</button>
          <button onclick="hideFloatWindow('labelWindow')">×</button>
        </div>
      </div>
      <div class="float-body">
        <div class="form-group">
          <label data-i18n="labelFont">Label Font</label>
          <select id="labelFontFamily" onchange="updateLabelFont(this.value)">
            <option value="Georgia, serif" selected>Georgia</option>
            <option value="'Times New Roman', serif">Times New Roman</option>
            <option value="Arial, sans-serif">Arial</option>
            <option value="Verdana, sans-serif">Verdana</option>
            <option value="'Courier New', monospace">Courier New</option>
            <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
          </select>
        </div>

        <div class="form-group">
          <label><span data-i18n="fontScale">Font Scale</span>: <span id="fontScaleVal">100%</span></label>
          <input type="range" id="labelFontScale" min="0.5" max="2" step="0.05" value="1" oninput="updateLabelFontScale(this.value)">
        </div>

        <div class="form-group">
          <label><span data-i18n="flagScale">Flag Scale</span>: <span id="flagScaleVal">100%</span></label>
          <input type="range" id="labelFlagScale" min="0.4" max="2.5" step="0.05" value="1" oninput="updateLabelFlagScale(this.value)">
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map, currentTileLayer, hexLayerGroup, linkLayerGroup;
let hexes = [];
let manualLinks = [];
let currentTool = 'select';
let selectedHexId = null;
let linkSourceId = null;
let exportCleanMode = false;

let hexRadiusMeters = 800;
let labelFontFamily = 'Georgia, serif';
let labelFontScale = 1;
let labelFlagScale = 1;

let currentLang = localStorage.getItem('ww1_editor_lang') || 'en';
let topFloatZ = 3000;

const ALLOWED_FONTS = [
  'Georgia, serif',
  "'Times New Roman', serif",
  'Arial, sans-serif',
  'Verdana, sans-serif',
  "'Courier New', monospace",
  "'Trebuchet MS', sans-serif"
];

const I18N = {
  en: {
    appTitle: 'Wargame Map Editor — WWI Edition',
    languageLabel: 'Interface language',
    menuFile: 'File',
    menuEdit: 'Edit',
    menuView: 'View',
    menuTools: 'Tools',
    menuMap: 'Map',
    menuLabels: 'Labels',
    searchPlaceholder: 'E.g. Verdun, Isonzo, Gallipoli...',
    searchBtn: 'Search',
    placeBtn: '👆 Place',
    linkBtn: '🔗 Link',
    addHexCentre: '+ Hex at Centre',
    panelCartography: 'Cartography',
    panelLabels: 'Labels',
    panelSaved: 'Saved Scenarios',
    panelLegend: 'WWI Flag Legend',
    mapType: 'Map Type',
    mapTopo: 'Vintage Map — OpenTopo',
    mapOsm: 'OpenStreetMap Standard',
    mapIgmHttp: 'IGM 1:25,000 — Italy, PCN HTTP',
    mapIgmHttps: 'IGM 1:25,000 — Italy, PCN HTTPS',
    mapOrthoHttp: 'Black & White Orthophoto 1988 — Italy, PCN HTTP',
    mapOrthoHttps: 'Black & White Orthophoto 1988 — Italy, PCN HTTPS',
    mapIgn: 'État-Major Map — France',
    mapEsri: 'Satellite — Esri',
    mapOpacity: 'Map Opacity',
    hexRadius: 'Hex Radius',
    labelFont: 'Label Font',
    fontScale: 'Font Scale',
    flagScale: 'Flag Scale',
    exportPng: '🖼 Export PNG',
    saveDb: '💾 Save to Local DB',
    refreshSaved: '↻ Refresh Saved Maps',
    testMap: '🧪 Test Map/WMS',
    clearMap: '🗑 Clear Map',
    wmsNoteTitle: 'WMS note:',
    wmsNoteText: 'PCN/Geoportal services may only work over HTTP or may have CORS limits. If this page is served over HTTPS, HTTP WMS layers may be blocked by the browser.',
    clickRefresh: 'Click “Refresh Saved Maps”.',
    hexList: 'Hex List',
    loading: 'Loading...',
    noSaved: 'No saved scenarios.',
    errorLoading: 'Error loading scenarios.',
    openSaved: 'Open',
    enterLocation: 'Please enter a location.',
    locationNotFound: 'Location not found.',
    searchError: 'Connection error while searching.',
    invalidProvider: 'Invalid map provider: ',
    pngFileName: 'PNG file name:',
    pngDefault: 'ww1-wargame-map',
    pngError: 'PNG export failed. This may be caused by CORS restrictions on map tiles.',
    scenarioName: 'Name this scenario:',
    scenarioDefault: 'New Historical Map',
    saveFailed: 'Save failed.',
    loadConfirm: 'Load this scenario? Unsaved changes will be lost.',
    scenarioLoaded: 'Scenario loaded: ',
    loadFailed: 'Load failed.',
    deleteConfirm: 'Permanently delete this saved scenario?',
    deleteFailed: 'Delete failed.',
    clearConfirm: 'Clear the entire map?',
    sectorDefault: 'SECTOR',
    activeProvider: 'Active provider',
    wmsEndpoint: 'WMS endpoint',
    mapFile: 'Map file',
    layer: 'Layer',
    capabilities: 'GetCapabilities',
    capabilitiesResponse: 'GetCapabilities HTTP response',
    capabilitiesOk: '✅ The layer appears to be present in GetCapabilities.',
    capabilitiesLayerMissing: '⚠️ GetCapabilities responded, but the layer name was not found.',
    capabilitiesFailed: '⚠️ GetCapabilities fetch failed.',
    nonWmsProvider: 'This provider is not a PCN WMS layer. Check the map visually and inspect the browser console for tile errors.',
    error: 'Error'
  },
  it: {
    appTitle: 'Editor Mappe Wargame — Edizione WWI',
    languageLabel: 'Lingua interfaccia',
    menuFile: 'File',
    menuEdit: 'Modifica',
    menuView: 'Visualizza',
    menuTools: 'Strumenti',
    menuMap: 'Mappa',
    menuLabels: 'Etichette',
    searchPlaceholder: 'Es. Verdun, Isonzo, Gallipoli...',
    searchBtn: 'Cerca',
    placeBtn: '👆 Posiziona',
    linkBtn: '🔗 Linka',
    addHexCentre: '+ Esagono al Centro',
    panelCartography: 'Cartografia',
    panelLabels: 'Etichette',
    panelSaved: 'Scenari Salvati',
    panelLegend: 'Legenda Bandiere WWI',
    mapType: 'Tipo mappa',
    mapTopo: 'Carta Vintage — OpenTopo',
    mapOsm: 'OpenStreetMap Standard',
    mapIgmHttp: 'IGM 1:25.000 — Italia, PCN HTTP',
    mapIgmHttps: 'IGM 1:25.000 — Italia, PCN HTTPS',
    mapOrthoHttp: 'Ortofoto B/N 1988 — Italia, PCN HTTP',
    mapOrthoHttps: 'Ortofoto B/N 1988 — Italia, PCN HTTPS',
    mapIgn: 'Carta État-Major — Francia',
    mapEsri: 'Satellite — Esri',
    mapOpacity: 'Opacità Mappa',
    hexRadius: 'Raggio Esagoni',
    labelFont: 'Font etichette',
    fontScale: 'Scala Font',
    flagScale: 'Scala Bandiere',
    exportPng: '🖼 Esporta PNG',
    saveDb: '💾 Salva nel DB Locale',
    refreshSaved: '↻ Aggiorna Salvati',
    testMap: '🧪 Test Mappa/WMS',
    clearMap: '🗑 Svuota Mappa',
    wmsNoteTitle: 'Nota WMS:',
    wmsNoteText: 'I servizi PCN/Geoportale possono funzionare solo in HTTP oppure avere limiti CORS. Se la pagina è in HTTPS, i layer WMS HTTP possono essere bloccati dal browser.',
    clickRefresh: 'Clicca “Aggiorna Salvati”.',
    hexList: 'Lista Esagoni',
    loading: 'Caricamento...',
    noSaved: 'Nessuno scenario salvato.',
    errorLoading: 'Errore caricamento scenari.',
    openSaved: 'Apri',
    enterLocation: 'Inserisci una località.',
    locationNotFound: 'Località non trovata.',
    searchError: 'Errore di connessione durante la ricerca.',
    invalidProvider: 'Provider mappa non valido: ',
    pngFileName: 'Nome file PNG:',
    pngDefault: 'mappa-wargame-ww1',
    pngError: 'Esportazione PNG fallita. Possibile problema CORS dei tile cartografici.',
    scenarioName: 'Assegna un nome a questo scenario:',
    scenarioDefault: 'Nuova Mappa Storica',
    saveFailed: 'Salvataggio fallito.',
    loadConfirm: 'Caricare questo scenario? Le modifiche non salvate andranno perse.',
    scenarioLoaded: 'Scenario caricato: ',
    loadFailed: 'Caricamento fallito.',
    deleteConfirm: 'Eliminare definitivamente questo scenario salvato?',
    deleteFailed: 'Eliminazione fallita.',
    clearConfirm: 'Svuotare interamente la mappa?',
    sectorDefault: 'SETTORE',
    activeProvider: 'Provider attivo',
    wmsEndpoint: 'Endpoint WMS',
    mapFile: 'File mappa',
    layer: 'Layer',
    capabilities: 'GetCapabilities',
    capabilitiesResponse: 'Risposta HTTP GetCapabilities',
    capabilitiesOk: '✅ Il layer sembra presente nel GetCapabilities.',
    capabilitiesLayerMissing: '⚠️ GetCapabilities risponde, ma il nome layer non è stato trovato.',
    capabilitiesFailed: '⚠️ Fetch GetCapabilities fallito.',
    nonWmsProvider: 'Questo provider non è un layer WMS PCN. Controlla visivamente la mappa e la console del browser.',
    error: 'Errore'
  }
};

function tr(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

function setLanguage(lang) {
  currentLang = lang === 'it' ? 'it' : 'en';
  localStorage.setItem('ww1_editor_lang', currentLang);
  document.documentElement.lang = currentLang;
  document.title = tr('appTitle');

  const selector = document.getElementById('languageSelect');
  if (selector) selector.value = currentLang;

  document.querySelectorAll('[data-i18n]').forEach(el => {
    el.innerText = tr(el.dataset.i18n);
  });

  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    el.placeholder = tr(el.dataset.i18nPlaceholder);
  });

  renderLegend();
  renderHexList();
  renderMapOverlay();
}
window.setLanguage = setLanguage;

function svgData(svg) {
  return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
}

function flagHorizontal(c1, c2, c3) {
  return svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="900" height="200" y="0" fill="${c1}"/>
      <rect width="900" height="200" y="200" fill="${c2}"/>
      <rect width="900" height="200" y="400" fill="${c3}"/>
    </svg>
  `);
}

function flagVertical(c1, c2, c3) {
  return svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="300" height="600" x="0" fill="${c1}"/>
      <rect width="300" height="600" x="300" fill="${c2}"/>
      <rect width="300" height="600" x="600" fill="${c3}"/>
    </svg>
  `);
}

const FLAGS = {
  neutral: svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="900" height="600" fill="#ddd6c4"/>
      <rect x="35" y="35" width="830" height="530" fill="none" stroke="#8c8275" stroke-width="34"/>
      <path d="M110 110 L790 490 M790 110 L110 490" stroke="#8c8275" stroke-width="58" stroke-linecap="round"/>
      <circle cx="450" cy="300" r="95" fill="#f4efe6" stroke="#8c8275" stroke-width="24"/>
    </svg>
  `),
  italyKingdom: svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="300" height="600" x="0" fill="#009246"/>
      <rect width="300" height="600" x="300" fill="#fff"/>
      <rect width="300" height="600" x="600" fill="#ce2b37"/>
      <path d="M450 210 L525 250 L505 365 L450 405 L395 365 L375 250 Z" fill="#d71920" stroke="#1f4f9a" stroke-width="12"/>
      <path d="M395 250 H505 L450 390 Z" fill="#fff" opacity="0.9"/>
    </svg>
  `),
  austriaHungary: svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="450" height="200" x="0" y="0" fill="#c8102e"/>
      <rect width="450" height="200" x="0" y="200" fill="#fff"/>
      <rect width="450" height="200" x="0" y="400" fill="#c8102e"/>
      <rect width="450" height="200" x="450" y="0" fill="#c8102e"/>
      <rect width="450" height="200" x="450" y="200" fill="#fff"/>
      <rect width="450" height="200" x="450" y="400" fill="#007a3d"/>
      <line x1="450" y1="0" x2="450" y2="600" stroke="#d4af37" stroke-width="10"/>
    </svg>
  `),
  germanyEmpire: flagHorizontal('#000', '#fff', '#dd0000'),
  france: flagVertical('#002395', '#fff', '#ed2939'),
  unitedKingdom: svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="900" height="600" fill="#012169"/>
      <path d="M0 0 L900 600 M900 0 L0 600" stroke="#fff" stroke-width="120"/>
      <path d="M0 0 L900 600 M900 0 L0 600" stroke="#C8102E" stroke-width="70"/>
      <path d="M450 0 V600 M0 300 H900" stroke="#fff" stroke-width="190"/>
      <path d="M450 0 V600 M0 300 H900" stroke="#C8102E" stroke-width="110"/>
    </svg>
  `),
  russiaEmpire: flagHorizontal('#fff', '#0039a6', '#d52b1e'),
  ottomanEmpire: svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600">
      <rect width="900" height="600" fill="#e30a17"/>
      <circle cx="385" cy="300" r="142" fill="#fff"/>
      <circle cx="425" cy="300" r="115" fill="#e30a17"/>
      <polygon fill="#fff" points="575,300 538,313 540,352 516,321 478,334 500,300 478,266 516,279 540,248 538,287"/>
    </svg>
  `),
  bulgaria: flagHorizontal('#fff', '#00966e', '#d62612'),
  belgium: flagVertical('#000', '#fae042', '#ed2939'),
  serbia: flagHorizontal('#c6363c', '#0c4076', '#fff'),
  usa: svgData(`
    <svg xmlns="http://www.w3.org/2000/svg" width="950" height="500" viewBox="0 0 950 500">
      <rect width="950" height="500" fill="#b22234"/>
      <g fill="#fff">
        <rect y="38.46" width="950" height="38.46"/>
        <rect y="115.38" width="950" height="38.46"/>
        <rect y="192.3" width="950" height="38.46"/>
        <rect y="269.22" width="950" height="38.46"/>
        <rect y="346.14" width="950" height="38.46"/>
        <rect y="423.06" width="950" height="38.46"/>
      </g>
      <rect width="380" height="269.23" fill="#3c3b6e"/>
    </svg>
  `)
};

const NATIONS = {
  NEUTRAL: {
    name: { en: 'Neutral', it: 'Neutrale' },
    historical: { en: 'Neutral / unassigned sector', it: 'Settore neutrale / non assegnato' },
    flag: FLAGS.neutral,
    bg: '#e0d8c3',
    border: '#7a7265',
    text: '#222'
  },
  IT: {
    name: { en: 'Kingdom of Italy', it: 'Regno d’Italia' },
    historical: { en: 'Flag of the Kingdom of Italy with the Savoy shield, 1861–1946', it: 'Bandiera del Regno d’Italia con scudo sabaudo, 1861–1946' },
    flag: FLAGS.italyKingdom,
    bg: '#d4e3d4',
    border: '#006622',
    text: '#004011'
  },
  AH: {
    name: { en: 'Austria-Hungary', it: 'Austria-Ungheria' },
    historical: { en: 'Common Austro-Hungarian ensign, often used as a practical symbol of the Dual Monarchy', it: 'Insegna comune austro-ungarica usata come simbolo pratico della Duplice Monarchia' },
    flag: FLAGS.austriaHungary,
    bg: '#f0d3d3',
    border: '#8b0000',
    text: '#500000'
  },
  DE: {
    name: { en: 'German Empire', it: 'Impero Tedesco' },
    historical: { en: 'Black-white-red flag of the German Empire, 1871–1918', it: 'Bandiera nero-bianco-rosso dell’Impero Tedesco, 1871–1918' },
    flag: FLAGS.germanyEmpire,
    bg: '#dcdcdc',
    border: '#333333',
    text: '#111111'
  },
  FR: {
    name: { en: 'France', it: 'Francia' },
    historical: { en: 'French tricolour', it: 'Tricolore francese' },
    flag: FLAGS.france,
    bg: '#d4daf0',
    border: '#1c3163',
    text: '#0e1933'
  },
  UK: {
    name: { en: 'British Empire', it: 'Impero Britannico' },
    historical: { en: 'Union Jack of the United Kingdom', it: 'Union Jack del Regno Unito' },
    flag: FLAGS.unitedKingdom,
    bg: '#f0e6d3',
    border: '#8b6508',
    text: '#4a3504'
  },
  RU: {
    name: { en: 'Russian Empire', it: 'Impero Russo' },
    historical: { en: 'White-blue-red tricolour of the Russian Empire', it: 'Tricolore bianco-blu-rosso dell’Impero Russo' },
    flag: FLAGS.russiaEmpire,
    bg: '#e6d4f0',
    border: '#5a189a',
    text: '#2e0c4f'
  },
  OT: {
    name: { en: 'Ottoman Empire', it: 'Impero Ottomano' },
    historical: { en: 'Ottoman red flag with crescent and star', it: 'Bandiera ottomana rossa con mezzaluna e stella' },
    flag: FLAGS.ottomanEmpire,
    bg: '#d3f0ea',
    border: '#006644',
    text: '#003322'
  },
  BG: {
    name: { en: 'Kingdom of Bulgaria', it: 'Regno di Bulgaria' },
    historical: { en: 'Bulgarian white-green-red tricolour', it: 'Tricolore bulgaro bianco-verde-rosso' },
    flag: FLAGS.bulgaria,
    bg: '#f0ebd3',
    border: '#8b7500',
    text: '#453c00'
  },
  BE: {
    name: { en: 'Belgium', it: 'Belgio' },
    historical: { en: 'Belgian black-yellow-red tricolour', it: 'Tricolore belga nero-giallo-rosso' },
    flag: FLAGS.belgium,
    bg: '#f2e2b8',
    border: '#111',
    text: '#111'
  },
  RS: {
    name: { en: 'Kingdom of Serbia', it: 'Regno di Serbia' },
    historical: { en: 'Serbian red-blue-white tricolour', it: 'Tricolore serbo rosso-blu-bianco' },
    flag: FLAGS.serbia,
    bg: '#e8d5d8',
    border: '#7c1d25',
    text: '#3d0f13'
  },
  US: {
    name: { en: 'United States', it: 'Stati Uniti' },
    historical: { en: '48-star United States flag, in use from 1912', it: 'Bandiera statunitense a 48 stelle, in uso dal 1912' },
    flag: FLAGS.usa,
    bg: '#d6e0f2',
    border: '#243f75',
    text: '#162647'
  }
};

function nationName(key) {
  return NATIONS[key]?.name?.[currentLang] || NATIONS[key]?.name?.en || key;
}

function nationHistorical(key) {
  return NATIONS[key]?.historical?.[currentLang] || NATIONS[key]?.historical?.en || '';
}

const ICONS = {
  NONE: '',
  ALTURA: '▲',
  FORTE: '🏰',
  CITTA: '★',
  CARSO: '🪨',
  FIUME: '〰',
  TRINCEA: '▰',
  BOSCO: '♣',
  PONTE: '≋',
  FERROVIA: '╬'
};

const ICON_LABELS = {
  en: {
    NONE: '-',
    ALTURA: '▲ Height',
    FORTE: '🏰 Fort',
    CITTA: '★ City',
    CARSO: '🪨 Karst',
    FIUME: '〰 River',
    TRINCEA: '▰ Trench',
    BOSCO: '♣ Woods',
    PONTE: '≋ Bridge',
    FERROVIA: '╬ Railway'
  },
  it: {
    NONE: '-',
    ALTURA: '▲ Altura',
    FORTE: '🏰 Forte',
    CITTA: '★ Città',
    CARSO: '🪨 Carso',
    FIUME: '〰 Fiume',
    TRINCEA: '▰ Trincea',
    BOSCO: '♣ Bosco',
    PONTE: '≋ Ponte',
    FERROVIA: '╬ Ferrovia'
  }
};

const MAP_PROVIDERS = {
  topo_historic: () => L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    maxZoom: 17,
    opacity: 0.8,
    crossOrigin: true,
    attribution: '© OpenTopoMap contributors'
  }),

  osm: () => L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    opacity: 0.8,
    crossOrigin: true,
    attribution: '© OpenStreetMap contributors'
  }),

  igm_25k_http: () => L.tileLayer.wms('http://wms.pcn.minambiente.it/ogc', {
    map: '/ms_ogc/WMS_v1.3/raster/IGM_25000.map',
    layers: 'CB.IGM25000',
    format: 'image/png',
    transparent: true,
    version: '1.3.0',
    opacity: 0.8,
    crossOrigin: true,
    attribution: 'PCN / Geoportale Nazionale — IGM 1:25,000 HTTP'
  }),

  igm_25k_https: () => L.tileLayer.wms('https://wms.pcn.minambiente.it/ogc', {
    map: '/ms_ogc/WMS_v1.3/raster/IGM_25000.map',
    layers: 'CB.IGM25000',
    format: 'image/png',
    transparent: true,
    version: '1.3.0',
    opacity: 0.8,
    crossOrigin: true,
    attribution: 'PCN / Geoportale Nazionale — IGM 1:25,000 HTTPS'
  }),

  ortofoto_88_http: () => L.tileLayer.wms('http://wms.pcn.minambiente.it/ogc', {
    map: '/ms_ogc/WMS_v1.3/raster/ortofoto_bn_88.map',
    layers: 'ortofoto_bn_88',
    format: 'image/png',
    transparent: true,
    version: '1.3.0',
    opacity: 0.8,
    crossOrigin: true,
    attribution: 'PCN / Geoportale Nazionale — Black & White Orthophoto 1988 HTTP'
  }),

  ortofoto_88_https: () => L.tileLayer.wms('https://wms.pcn.minambiente.it/ogc', {
    map: '/ms_ogc/WMS_v1.3/raster/ortofoto_bn_88.map',
    layers: 'ortofoto_bn_88',
    format: 'image/png',
    transparent: true,
    version: '1.3.0',
    opacity: 0.8,
    crossOrigin: true,
    attribution: 'PCN / Geoportale Nazionale — Black & White Orthophoto 1988 HTTPS'
  }),

  ign_france: () => L.tileLayer('https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.ETATMAJOR40&STYLE=normal&FORMAT=image/jpeg&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
    maxZoom: 18,
    opacity: 0.8,
    crossOrigin: true,
    attribution: 'IGN / Geoportail'
  }),

  esri_sat: () => L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 19,
    opacity: 0.8,
    crossOrigin: true,
    attribution: 'Tiles © Esri'
  })
};

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('languageSelect').value = currentLang;

  map = L.map('map').setView([45.9402, 13.6217], 12);

  hexLayerGroup = L.layerGroup().addTo(map);
  linkLayerGroup = L.layerGroup().addTo(map);

  currentTileLayer = MAP_PROVIDERS.topo_historic().addTo(map);
  attachTileDiagnostics(currentTileLayer, 'topo_historic');

  map.on('zoomend', renderMapOverlay);

  map.on('click', e => {
    if (currentTool !== 'select') return;

    const newHex = {
      id: Date.now() + Math.floor(Math.random() * 1000),
      lat: e.latlng.lat,
      lng: e.latlng.lng,
      code: `N${hexes.length + 1}`,
      name: tr('sectorDefault'),
      nation: 'NEUTRAL',
      icon: 'NONE'
    };

    hexes.push(newHex);
    selectedHexId = newHex.id;

    renderHexList();
    renderMapOverlay();
  });

  setLanguage(currentLang);
  refreshSavedMaps();
  makeFloatingWindowsDraggable();
  restoreFloatingWindowLayout();

  window.addEventListener('beforeunload', saveFloatingWindowLayout);

  setTimeout(() => {
    map.invalidateSize();
    renderMapOverlay();
  }, 250);
});

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function slugifyFileName(name) {
  return String(name || 'ww1-wargame-map')
    .trim()
    .toLowerCase()
    .replace(/[àáâäå]/g, 'a')
    .replace(/[èéêë]/g, 'e')
    .replace(/[ìíîï]/g, 'i')
    .replace(/[òóôö]/g, 'o')
    .replace(/[ùúûü]/g, 'u')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'ww1-wargame-map';
}

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function normaliseFontFamily(font) {
  return ALLOWED_FONTS.includes(font) ? font : 'Georgia, serif';
}

function normaliseProviderKey(key) {
  if (key === 'igm_25k') return 'igm_25k_http';
  if (key === 'ortofoto_88') return 'ortofoto_88_http';
  return key;
}

function getSelectedProviderKey() {
  return document.getElementById('mapProvider').value;
}

function attachTileDiagnostics(layer, providerKey) {
  layer.on('tileerror', e => {
    console.warn('Tile/WMS loading error:', providerKey, e);
  });
}

function bringFloatToFront(id) {
  const win = document.getElementById(id);
  if (!win) return;
  topFloatZ += 1;
  win.style.zIndex = topFloatZ;
}

window.showFloatWindow = function(id) {
  const win = document.getElementById(id);
  if (!win) return;

  win.classList.remove('hidden');
  win.classList.remove('minimised');
  bringFloatToFront(id);

  setTimeout(() => {
    if (map) {
      map.invalidateSize();
      renderMapOverlay();
    }
  }, 100);
};

window.hideFloatWindow = function(id) {
  const win = document.getElementById(id);
  if (!win) return;
  win.classList.add('hidden');
};

window.toggleFloatWindow = function(id) {
  const win = document.getElementById(id);
  if (!win) return;

  if (win.classList.contains('hidden')) {
    showFloatWindow(id);
  } else {
    hideFloatWindow(id);
  }
};

window.minimiseFloatWindow = function(id) {
  const win = document.getElementById(id);
  if (!win) return;

  win.classList.toggle('minimised');
  bringFloatToFront(id);
};

function makeFloatingWindowsDraggable() {
  document.querySelectorAll('.float-window').forEach(win => {
    const titlebar = win.querySelector('.float-titlebar');
    if (!titlebar) return;

    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    titlebar.addEventListener('mousedown', e => {
      if (e.target.tagName === 'BUTTON') return;

      isDragging = true;
      bringFloatToFront(win.id);

      startX = e.clientX;
      startY = e.clientY;
      startLeft = parseInt(win.style.left || win.offsetLeft, 10);
      startTop = parseInt(win.style.top || win.offsetTop, 10);

      document.body.style.userSelect = 'none';
    });

    window.addEventListener('mousemove', e => {
      if (!isDragging) return;

      const workspace = document.querySelector('.workspace');
      const bounds = workspace.getBoundingClientRect();

      let newLeft = startLeft + (e.clientX - startX);
      let newTop = startTop + (e.clientY - startY);

      newLeft = Math.max(-win.offsetWidth + 80, Math.min(newLeft, bounds.width - 80));
      newTop = Math.max(0, Math.min(newTop, bounds.height - 34));

      win.style.left = `${newLeft}px`;
      win.style.top = `${newTop}px`;
    });

    window.addEventListener('mouseup', () => {
      if (!isDragging) return;
      isDragging = false;
      document.body.style.userSelect = '';
      saveFloatingWindowLayout();
    });

    win.addEventListener('mousedown', () => bringFloatToFront(win.id));
  });
}

function saveFloatingWindowLayout() {
  const layout = {};

  document.querySelectorAll('.float-window').forEach(win => {
    layout[win.id] = {
      left: win.style.left,
      top: win.style.top,
      width: win.style.width,
      height: win.style.height,
      hidden: win.classList.contains('hidden'),
      minimised: win.classList.contains('minimised'),
      zIndex: win.style.zIndex
    };
  });

  localStorage.setItem('ww1_editor_float_layout', JSON.stringify(layout));
}

function restoreFloatingWindowLayout() {
  let layout = {};

  try {
    layout = JSON.parse(localStorage.getItem('ww1_editor_float_layout') || '{}');
  } catch {
    layout = {};
  }

  Object.entries(layout).forEach(([id, data]) => {
    const win = document.getElementById(id);
    if (!win) return;

    if (data.left) win.style.left = data.left;
    if (data.top) win.style.top = data.top;
    if (data.width) win.style.width = data.width;
    if (data.height) win.style.height = data.height;
    if (data.zIndex) win.style.zIndex = data.zIndex;

    win.classList.toggle('hidden', !!data.hidden);
    win.classList.toggle('minimised', !!data.minimised);
  });
}

function getHexPolygonCoords(lat, lng, radiusMeters = 800) {
  const coords = [];

  for (let i = 0; i < 6; i++) {
    const angle = (Math.PI / 3) * i;
    const dx = radiusMeters * Math.cos(angle);
    const dy = radiusMeters * Math.sin(angle);

    const dLat = dy / 111111;
    const dLng = dx / (111111 * Math.cos(lat * Math.PI / 180));

    coords.push([lat + dLat, lng + dLng]);
  }

  return coords;
}

function getHexLabelMetrics(hex) {
  const centre = map.latLngToLayerPoint([hex.lat, hex.lng]);
  const edgeLatLng = getHexPolygonCoords(hex.lat, hex.lng, hexRadiusMeters)[0];
  const edge = map.latLngToLayerPoint(edgeLatLng);
  const radiusPx = Math.max(6, centre.distanceTo(edge));

  const titleSize = clamp(radiusPx * 0.17 * labelFontScale, 4, 18);
  const subSize = clamp(radiusPx * 0.11 * labelFontScale, 3.5, 14);
  const iconSize = clamp(radiusPx * 0.16 * labelFontScale, 4, 18);

  const flagW = clamp(radiusPx * 0.34 * labelFlagScale, 9, 46);
  const flagH = clamp(flagW * 0.62, 6, 28);

  const contentW = Math.max(radiusPx * 1.20, flagW + 6, titleSize * 3.5);
  const contentH = flagH + titleSize + subSize + iconSize + 6;

  return {
    titleSize,
    subSize,
    iconSize,
    flagW,
    flagH,
    boxW: clamp(contentW, 28, radiusPx * 1.65),
    boxH: clamp(contentH, 16, radiusPx * 1.20),
    yShift: clamp(radiusPx * 0.20, 2, 10)
  };
}

window.updateLabelFont = function(value) {
  labelFontFamily = normaliseFontFamily(value);
  document.getElementById('labelFontFamily').value = labelFontFamily;
  renderMapOverlay();
};

window.updateLabelFontScale = function(value) {
  labelFontScale = parseFloat(value);
  document.getElementById('fontScaleVal').innerText = Math.round(labelFontScale * 100) + '%';
  renderMapOverlay();
};

window.updateLabelFlagScale = function(value) {
  labelFlagScale = parseFloat(value);
  document.getElementById('flagScaleVal').innerText = Math.round(labelFlagScale * 100) + '%';
  renderMapOverlay();
};

window.searchLocation = async function() {
  const query = document.getElementById('searchInput').value.trim();

  if (!query) {
    alert(tr('enterLocation'));
    return;
  }

  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&limit=5&q=${encodeURIComponent(query)}`
    );
    const data = await response.json();

    if (data && data.length > 0) {
      map.flyTo([parseFloat(data[0].lat), parseFloat(data[0].lon)], 12, {
        duration: 1.5
      });
    } else {
      alert(tr('locationNotFound'));
    }
  } catch (err) {
    alert(tr('searchError'));
    console.error(err);
  }
};

window.setTool = function(tool) {
  currentTool = tool;

  const selectBtn = document.getElementById('toolSelect');
  const linkBtn = document.getElementById('toolLink');

  if (selectBtn) selectBtn.classList.toggle('active', tool === 'select');
  if (linkBtn) linkBtn.classList.toggle('active', tool === 'link');

  linkSourceId = null;
  renderMapOverlay();
};

window.addNewHexCenter = function() {
  map.fire('click', {
    latlng: map.getCenter()
  });
};

window.updateHexRadius = function(value) {
  hexRadiusMeters = parseInt(value, 10);
  document.getElementById('hexRadiusVal').innerText = `${hexRadiusMeters} m`;
  renderMapOverlay();
};

window.changeMapProvider = function() {
  let val = normaliseProviderKey(getSelectedProviderKey());

  if (!MAP_PROVIDERS[val]) {
    alert(tr('invalidProvider') + val);
    return;
  }

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer);
  }

  currentTileLayer = MAP_PROVIDERS[val]();
  attachTileDiagnostics(currentTileLayer, val);
  currentTileLayer.addTo(map);

  updateOpacity(document.getElementById('mapOpacity').value);
};

window.updateOpacity = function(val) {
  document.getElementById('opacityVal').innerText = Math.round(val * 100) + '%';

  if (currentTileLayer) {
    currentTileLayer.setOpacity(parseFloat(val));
  }
};

window.testCurrentMapProvider = async function() {
  const provider = getSelectedProviderKey();
  let message = `${tr('activeProvider')}: ${provider}\n\n`;

  if (
    provider === 'igm_25k_http' ||
    provider === 'igm_25k_https' ||
    provider === 'ortofoto_88_http' ||
    provider === 'ortofoto_88_https'
  ) {
    const isHttps = provider.endsWith('_https');
    const base = isHttps
      ? 'https://wms.pcn.minambiente.it/ogc'
      : 'http://wms.pcn.minambiente.it/ogc';

    const mapParam = provider.includes('igm_25k')
      ? '/ms_ogc/WMS_v1.3/raster/IGM_25000.map'
      : '/ms_ogc/WMS_v1.3/raster/ortofoto_bn_88.map';

    const layer = provider.includes('igm_25k')
      ? 'CB.IGM25000'
      : 'ortofoto_bn_88';

    const capabilitiesUrl =
      `${base}?map=${encodeURIComponent(mapParam)}` +
      `&SERVICE=WMS&VERSION=1.3.0&REQUEST=GetCapabilities`;

    message += `${tr('wmsEndpoint')}:\n${base}\n\n`;
    message += `${tr('mapFile')}:\n${mapParam}\n\n`;
    message += `${tr('layer')}:\n${layer}\n\n`;
    message += `${tr('capabilities')}:\n${capabilitiesUrl}\n\n`;

    try {
      const response = await fetch(capabilitiesUrl, {
        method: 'GET',
        mode: 'cors'
      });

      message += `${tr('capabilitiesResponse')}: ${response.status} ${response.statusText}\n`;

      if (response.ok) {
        const text = await response.text();
        message += text.includes(layer)
          ? '\n' + tr('capabilitiesOk')
          : '\n' + tr('capabilitiesLayerMissing');
      }
    } catch (error) {
      message += '\n' + tr('capabilitiesFailed') + '\n\nCORS / HTTP / HTTPS / certificate issue.';
      console.error(error);
    }

    alert(message);
    return;
  }

  alert(message + tr('nonWmsProvider'));
};

async function captureMapAsCanvas() {
  const mapDiv = document.getElementById('map');

  map.invalidateSize();

  const oldSelectedHexId = selectedHexId;
  exportCleanMode = true;
  renderMapOverlay();

  await wait(450);

  document.body.classList.add('exporting');

  try {
    return await html2canvas(mapDiv, {
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#e5e0d8',
      scale: 2,
      imageTimeout: 15000,
      logging: false
    });
  } finally {
    exportCleanMode = false;
    selectedHexId = oldSelectedHexId;
    document.body.classList.remove('exporting');
    renderMapOverlay();
  }
}

window.exportPNG = async function() {
  const title = prompt(tr('pngFileName'), tr('pngDefault'));
  if (!title) return;

  try {
    const canvas = await captureMapAsCanvas();
    const imgBase64 = canvas.toDataURL('image/png');

    const link = document.createElement('a');
    link.href = imgBase64;
    link.download = slugifyFileName(title) + '.png';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } catch (error) {
    alert(tr('pngError'));
    console.error(error);
  }
};

window.saveToDatabase = async function() {
  const title = prompt(tr('scenarioName'), tr('scenarioDefault'));
  if (!title) return;

  try {
    const canvas = await captureMapAsCanvas();
    const imgBase64 = canvas.toDataURL('image/png');

    saveFloatingWindowLayout();

    let floatLayout = {};
    try {
      floatLayout = JSON.parse(localStorage.getItem('ww1_editor_float_layout') || '{}');
    } catch {
      floatLayout = {};
    }

    const stateJSON = JSON.stringify({
      hexes,
      manualLinks,
      center: {
        lat: map.getCenter().lat,
        lng: map.getCenter().lng
      },
      zoom: map.getZoom(),
      mapProvider: getSelectedProviderKey(),
      mapOpacity: document.getElementById('mapOpacity').value,
      hexRadiusMeters,
      labelFontFamily,
      labelFontScale,
      labelFlagScale,
      currentLang,
      floatLayout
    });

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('title', title);
    formData.append('image', imgBase64);
    formData.append('state', stateJSON);

    const response = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.status === 'success') {
      alert('✅ ' + result.message);
      refreshSavedMaps();
      showFloatWindow('savedWindow');
    } else {
      alert('❌ ' + tr('error') + ': ' + result.message);
    }
  } catch (error) {
    alert(tr('saveFailed'));
    console.error(error);
  }
};

window.refreshSavedMaps = async function(showLoading = true) {
  const container = document.getElementById('savedMapsList');

  if (showLoading) {
    container.innerHTML = `<em style="font-size:10px;">${escapeHtml(tr('loading'))}</em>`;
  }

  try {
    const response = await fetch(window.location.href + '?action=list');
    const result = await response.json();

    if (result.status !== 'success') {
      container.innerHTML = `<em style="font-size:10px;">${escapeHtml(tr('error'))}: ${escapeHtml(result.message)}</em>`;
      return;
    }

    if (!result.maps.length) {
      container.innerHTML = `<em style="font-size:10px;">${escapeHtml(tr('noSaved'))}</em>`;
      return;
    }

    container.innerHTML = '';

    result.maps.forEach(item => {
      const row = document.createElement('div');
      row.className = 'saved-row';

      row.innerHTML = `
        <div>
          <strong>${escapeHtml(item.title)}</strong>
          <span>${escapeHtml(item.created_at)}</span>
        </div>
        <button class="action-btn" onclick="loadSavedMap(${parseInt(item.id, 10)})">${escapeHtml(tr('openSaved'))}</button>
        <button class="danger" onclick="deleteSavedMap(${parseInt(item.id, 10)})">×</button>
      `;

      container.appendChild(row);
    });
  } catch (error) {
    container.innerHTML = `<em style="font-size:10px;">${escapeHtml(tr('errorLoading'))}</em>`;
    console.error(error);
  }
};

window.loadSavedMap = async function(id) {
  if (!confirm(tr('loadConfirm'))) return;

  try {
    const response = await fetch(window.location.href + '?action=load&id=' + encodeURIComponent(id));
    const result = await response.json();

    if (result.status !== 'success') {
      alert(tr('error') + ': ' + result.message);
      return;
    }

    const state = JSON.parse(result.map.map_state || '{}');

    hexes = Array.isArray(state.hexes) ? state.hexes : [];
    manualLinks = Array.isArray(state.manualLinks) ? state.manualLinks : [];
    selectedHexId = null;
    linkSourceId = null;

    if (state.currentLang) {
      setLanguage(state.currentLang);
    }

    if (state.hexRadiusMeters) {
      hexRadiusMeters = parseInt(state.hexRadiusMeters, 10);
      document.getElementById('hexRadius').value = hexRadiusMeters;
      document.getElementById('hexRadiusVal').innerText = `${hexRadiusMeters} m`;
    }

    if (state.labelFontFamily) {
      labelFontFamily = normaliseFontFamily(state.labelFontFamily);
      document.getElementById('labelFontFamily').value = labelFontFamily;
    }

    if (state.labelFontScale) {
      labelFontScale = parseFloat(state.labelFontScale);
      document.getElementById('labelFontScale').value = labelFontScale;
      document.getElementById('fontScaleVal').innerText = Math.round(labelFontScale * 100) + '%';
    }

    if (state.labelFlagScale) {
      labelFlagScale = parseFloat(state.labelFlagScale);
      document.getElementById('labelFlagScale').value = labelFlagScale;
      document.getElementById('flagScaleVal').innerText = Math.round(labelFlagScale * 100) + '%';
    }

    if (state.floatLayout && typeof state.floatLayout === 'object') {
      localStorage.setItem('ww1_editor_float_layout', JSON.stringify(state.floatLayout));
      restoreFloatingWindowLayout();
    }

    if (state.mapProvider) {
      const provider = normaliseProviderKey(state.mapProvider);

      if (MAP_PROVIDERS[provider]) {
        const select = document.getElementById('mapProvider');

        if ([...select.options].some(opt => opt.value === provider)) {
          select.value = provider;
        }

        changeMapProvider();
      }
    }

    if (state.mapOpacity !== undefined) {
      document.getElementById('mapOpacity').value = state.mapOpacity;
      updateOpacity(state.mapOpacity);
    }

    if (state.center && state.zoom) {
      map.setView([state.center.lat, state.center.lng], state.zoom);
    }

    renderHexList();
    renderMapOverlay();

    alert(tr('scenarioLoaded') + result.map.title);
  } catch (error) {
    alert(tr('loadFailed'));
    console.error(error);
  }
};

window.deleteSavedMap = async function(id) {
  if (!confirm(tr('deleteConfirm'))) return;

  try {
    const formData = new FormData();
    formData.append('action', 'delete_saved');
    formData.append('id', id);

    const response = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.status === 'success') {
      refreshSavedMaps();
    } else {
      alert(tr('error') + ': ' + result.message);
    }
  } catch (error) {
    alert(tr('deleteFailed'));
    console.error(error);
  }
};

window.clearAll = function() {
  if (!confirm(tr('clearConfirm'))) return;

  hexes = [];
  manualLinks = [];
  selectedHexId = null;
  linkSourceId = null;

  renderHexList();
  renderMapOverlay();
};

window.updateHex = function(id, key, val) {
  const h = hexes.find(x => x.id === id);
  if (!h) return;

  h[key] = val;
  renderHexList();
  renderMapOverlay();
};

window.deleteHex = function(id) {
  hexes = hexes.filter(h => h.id !== id);
  manualLinks = manualLinks.filter(l => l[0] !== id && l[1] !== id);

  if (selectedHexId === id) {
    selectedHexId = null;
  }

  renderHexList();
  renderMapOverlay();
};

function toggleLink(id1, id2) {
  const idx = manualLinks.findIndex(l =>
    (l[0] === id1 && l[1] === id2) ||
    (l[0] === id2 && l[1] === id1)
  );

  if (idx >= 0) {
    manualLinks.splice(idx, 1);
  } else {
    manualLinks.push([id1, id2]);
  }
}

function renderMapOverlay() {
  if (!hexLayerGroup || !linkLayerGroup || !map) return;

  hexLayerGroup.clearLayers();
  linkLayerGroup.clearLayers();

  manualLinks.forEach(([id1, id2]) => {
    const h1 = hexes.find(h => h.id === id1);
    const h2 = hexes.find(h => h.id === id2);

    if (!h1 || !h2) return;

    L.polyline(
      [[h1.lat, h1.lng], [h2.lat, h2.lng]],
      {
        color: '#333',
        weight: 3,
        dashArray: '5, 5',
        opacity: 0.9
      }
    ).addTo(linkLayerGroup);
  });

  hexes.forEach(hex => {
    const nat = NATIONS[hex.nation] || NATIONS.NEUTRAL;
    const isSelected = !exportCleanMode && hex.id === selectedHexId;
    const isLinkSource = !exportCleanMode && currentTool === 'link' && linkSourceId === hex.id;

    const polygon = L.polygon(getHexPolygonCoords(hex.lat, hex.lng, hexRadiusMeters), {
      color: isSelected ? '#d9534f' : isLinkSource ? '#285473' : nat.border,
      fillColor: nat.bg,
      fillOpacity: 0.85,
      weight: isSelected || isLinkSource ? 4 : 2
    });

    polygon.on('click', e => {
      L.DomEvent.stopPropagation(e);

      if (currentTool === 'select') {
        selectedHexId = hex.id;
      } else if (currentTool === 'link') {
        if (!linkSourceId) {
          linkSourceId = hex.id;
        } else if (linkSourceId !== hex.id) {
          toggleLink(linkSourceId, hex.id);
          linkSourceId = null;
        }
      }

      renderHexList();
      renderMapOverlay();
    });

    polygon.on('mousedown', () => {
      if (currentTool !== 'select') return;

      map.dragging.disable();

      const onMouseMove = ev => {
        hex.lat = ev.latlng.lat;
        hex.lng = ev.latlng.lng;
        renderMapOverlay();
      };

      map.once('mouseup', () => {
        map.dragging.enable();
        map.off('mousemove', onMouseMove);
        renderHexList();
      });

      map.on('mousemove', onMouseMove);
    });

    polygon.addTo(hexLayerGroup);

    const m = getHexLabelMetrics(hex);

    const labelHtml = `
      <div class="hex-label-container"
           style="
             width:${m.boxW}px;
             height:${m.boxH}px;
             transform: translateY(-${m.yShift}px);
             overflow:hidden;
             font-family:${labelFontFamily};
           ">
        <img
          src="${nat.flag}"
          alt="${escapeHtml(nationName(hex.nation))}"
          style="
            width:${m.flagW}px;
            height:${m.flagH}px;
            object-fit:contain;
            border:1px solid rgba(0,0,0,0.35);
            background:#fff;
            display:inline-block;
            margin-bottom:1px;
          "
        >
        <strong style="
          font-size:${m.titleSize}px;
          color:${nat.text};
          display:block;
          line-height:1;
          font-weight:bold;
          font-family:${labelFontFamily};
        ">${escapeHtml(hex.code)}</strong>
        <span style="
          font-size:${m.subSize}px;
          color:${nat.text};
          text-transform:uppercase;
          display:block;
          line-height:1;
          white-space:nowrap;
          overflow:hidden;
          text-overflow:ellipsis;
          font-family:${labelFontFamily};
        ">${escapeHtml(hex.name)}</span>
        <div style="
          font-size:${m.iconSize}px;
          line-height:1;
          font-family:${labelFontFamily};
        ">${ICONS[hex.icon] || ''}</div>
      </div>
    `;

    L.marker([hex.lat, hex.lng], {
      icon: L.divIcon({
        className: '',
        html: labelHtml,
        iconSize: [m.boxW, m.boxH],
        iconAnchor: [m.boxW / 2, m.boxH / 2]
      }),
      interactive: false
    }).addTo(hexLayerGroup);
  });
}

function renderHexList() {
  const container = document.getElementById('hexListContainer');
  const count = document.getElementById('hexCount');

  if (!container || !count) return;

  count.innerText = hexes.length;
  container.innerHTML = '';

  hexes.forEach(hex => {
    const isSelected = hex.id === selectedHexId;
    const nat = NATIONS[hex.nation] || NATIONS.NEUTRAL;

    const card = document.createElement('div');
    card.className = `hex-card ${isSelected ? 'selected' : ''}`;

    let nationOptionsHtml = '';
    for (const [key] of Object.entries(NATIONS)) {
      nationOptionsHtml += `
        <option value="${key}" ${hex.nation === key ? 'selected' : ''}>
          ${escapeHtml(nationName(key))}
        </option>
      `;
    }

    let iconOptionsHtml = '';
    for (const [key] of Object.entries(ICONS)) {
      const label = ICON_LABELS[currentLang]?.[key] || ICON_LABELS.en[key] || key;
      iconOptionsHtml += `
        <option value="${key}" ${hex.icon === key ? 'selected' : ''}>
          ${escapeHtml(label)}
        </option>
      `;
    }

    card.innerHTML = `
      <input type="text" value="${escapeHtml(hex.code)}" onchange="updateHex(${hex.id}, 'code', this.value)">
      <input type="text" value="${escapeHtml(hex.name)}" onchange="updateHex(${hex.id}, 'name', this.value)">
      <img class="flag-img" src="${nat.flag}" title="${escapeHtml(nationHistorical(hex.nation))}" alt="${escapeHtml(nationName(hex.nation))}">
      <select onchange="updateHex(${hex.id}, 'nation', this.value)">
        ${nationOptionsHtml}
      </select>
      <select onchange="updateHex(${hex.id}, 'icon', this.value)">
        ${iconOptionsHtml}
      </select>
      <button class="danger" onclick="deleteHex(${hex.id})">×</button>
    `;

    card.addEventListener('click', e => {
      const tag = e.target.tagName;
      if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'BUTTON') {
        selectedHexId = hex.id;
        map.panTo([hex.lat, hex.lng]);
        renderHexList();
        renderMapOverlay();
      }
    });

    container.appendChild(card);
  });
}

function renderLegend() {
  const container = document.getElementById('legendContainer');
  if (!container) return;

  container.innerHTML = '';

  for (const [key, nat] of Object.entries(NATIONS)) {
    const img = document.createElement('img');
    img.className = 'flag-img';
    img.src = nat.flag;
    img.alt = nationName(key);
    img.title = nationHistorical(key);

    const text = document.createElement('div');
    text.innerHTML = `
      <strong>${escapeHtml(nationName(key))}</strong><br>
      <span>${escapeHtml(nationHistorical(key))}</span>
    `;

    container.appendChild(img);
    container.appendChild(text);
  }
}
</script>

</body>
</html>