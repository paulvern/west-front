<?php
// =======================================================
// WARGAME MAP EDITOR — WWI EDITION
// Single-file PHP app:
// - SQLite local database
// - Save/load/delete scenarios
// - PNG export
// - Inline WWI-style flags
// - Bottom hex list, open/close
// - Editable label font, font scale, flag scale
// - WMS PCN HTTP/HTTPS providers with diagnostics
// =======================================================

$db_file = __DIR__ . '/mappe_wargame.sqlite';
$pdo = null;
$storage_error = null;

function storage_json(array $payload, int $http_status = 200): void {
    http_response_code($http_status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('The PHP PDO SQLite extension is not installed or enabled.');
    }
    if (!is_writable(__DIR__)) {
        throw new RuntimeException('The project directory is not writable by PHP; SQLite cannot create its journal files.');
    }
    if (file_exists($db_file) && !is_writable($db_file)) {
        throw new RuntimeException('mappe_wargame.sqlite is not writable by PHP.');
    }

    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saved_maps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            image_base64 TEXT NOT NULL DEFAULT '',
            map_state TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Throwable $e) {
    $storage_error = $e->getMessage();
}

$requested_action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($requested_action === 'storage_status') {
    storage_json([
        'status' => $storage_error === null ? 'success' : 'error',
        'available' => $storage_error === null,
        'database' => basename($db_file),
        'message' => $storage_error ?? 'SQLite storage is ready.'
    ], $storage_error === null ? 200 : 503);
}

if ($requested_action !== '' && $storage_error !== null) {
    storage_json([
        'status' => 'error',
        'message' => 'Local storage unavailable: ' . $storage_error
    ], 503);
}

// -------------------------------------------------------
// AJAX: save scenario
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    header('Content-Type: application/json; charset=utf-8');

    $title = trim($_POST['title'] ?? 'Unknown Scenario');
    $image = $_POST['image'] ?? '';
    $state = $_POST['state'] ?? '{}';

    if ($title === '') {
        $title = 'Unknown Scenario';
    }

    json_decode($state, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        storage_json(['status' => 'error', 'message' => 'Invalid scenario state JSON.'], 422);
    }

    if (strlen($state) > 5 * 1024 * 1024) {
        storage_json(['status' => 'error', 'message' => 'Scenario state is too large.'], 413);
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
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Save error: ' . $e->getMessage()
        ]);
    }

    exit;
}

// -------------------------------------------------------
// AJAX: list saved scenarios
// -------------------------------------------------------
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
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

// -------------------------------------------------------
// AJAX: load saved scenario
// -------------------------------------------------------
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
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

// -------------------------------------------------------
// AJAX: delete saved scenario
// -------------------------------------------------------
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
    } catch (Throwable $e) {
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
      padding: 10px;
      background: var(--paper);
      font-family: var(--font-main);
      color: var(--ink);
      display: flex;
      flex-direction: column;
      height: 100vh;
      gap: 10px;
    }

    h1 {
      margin: 0;
      font-size: 18px;
      text-transform: uppercase;
      text-align: center;
      border-bottom: 2px solid var(--border-color);
      padding-bottom: 5px;
      letter-spacing: 1px;
    }

    .app-layout {
      display: flex;
      flex: 1;
      gap: 10px;
      min-height: 0;
    }

    .sidebar-left {
      width: 355px;
      min-width: 355px;
      background: var(--panel-bg);
      padding: 12px;
      border: 2px solid var(--border-color);
      border-radius: 4px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      overflow-y: auto;
      box-shadow: inset 0 0 12px rgba(0,0,0,0.05);
    }

    .main-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      min-height: 0;
      gap: 8px;
    }

    #map {
      flex: 1;
      border: 2px solid var(--border-color);
      border-radius: 4px;
      background: #e5e0d8;
      min-width: 0;
      min-height: 0;
      overflow: hidden;
    }

    .panel-title {
      font-weight: bold;
      font-size: 12px;
      text-transform: uppercase;
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 3px;
      margin-top: 4px;
    }

    .tool-palette,
    .form-row {
      display: flex;
      gap: 5px;
    }

    .tool-btn {
      flex: 1;
      padding: 8px 2px;
      background: #fff;
      border: 2px solid var(--border-color);
      border-radius: 4px;
      cursor: pointer;
      font-size: 11px;
      font-weight: bold;
    }

    .tool-btn.active {
      background: var(--active-color);
      color: white;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    label {
      font-weight: bold;
      font-size: 11px;
      text-transform: uppercase;
    }

    input,
    select,
    button {
      padding: 6px;
      border: 1px solid var(--border-color);
      font-family: inherit;
      font-size: 11px;
    }

    input[type="range"] {
      padding: 0;
    }

    button {
      border-radius: 3px;
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

    .saved-list {
      display: flex;
      flex-direction: column;
      gap: 5px;
      max-height: 170px;
      overflow-y: auto;
      background: rgba(255,255,255,0.35);
      border: 1px solid var(--border-color);
      padding: 5px;
    }

    .saved-row {
      display: grid;
      grid-template-columns: 1fr 48px 24px;
      gap: 4px;
      align-items: center;
      background: #fff;
      border: 1px solid #b7ad9f;
      padding: 4px;
      font-size: 10px;
    }

    .saved-row strong {
      display: block;
      font-size: 10px;
    }

    .saved-row span {
      display: block;
      font-size: 9px;
      color: #666;
    }

    .legend {
      display: grid;
      grid-template-columns: 24px 1fr;
      gap: 4px 6px;
      align-items: center;
      font-size: 10px;
      background: rgba(255,255,255,0.35);
      border: 1px solid var(--border-color);
      padding: 6px;
      max-height: 180px;
      overflow-y: auto;
    }

    .flag-img {
      width: 24px;
      height: 15px;
      object-fit: cover;
      border: 1px solid rgba(0,0,0,0.35);
      background: #fff;
      display: inline-block;
      vertical-align: middle;
    }

    .hex-bottom-panel {
      background: var(--panel-bg);
      border: 2px solid var(--border-color);
      border-radius: 4px;
      overflow: hidden;
      flex: 0 0 220px;
      display: flex;
      flex-direction: column;
      transition: flex-basis 0.2s ease;
      box-shadow: inset 0 0 12px rgba(0,0,0,0.05);
    }

    .hex-bottom-panel.closed {
      flex-basis: 39px;
    }

    .hex-bottom-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      padding: 6px 8px;
      border-bottom: 1px solid var(--border-color);
      background: rgba(255,255,255,0.35);
      font-size: 12px;
      text-transform: uppercase;
    }

    .hex-bottom-panel.closed .hex-list-scroll {
      display: none;
    }

    .hex-list-scroll.bottom {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 6px;
      padding: 8px;
      min-height: 0;
    }

    .hex-card {
      background: #fff;
      border: 1px solid var(--border-color);
      padding: 6px;
      display: grid;
      grid-template-columns: 60px minmax(160px, 1fr) 32px 180px 120px 34px;
      gap: 6px;
      align-items: center;
      font-size: 10px;
    }

    .hex-card.selected {
      border: 2px solid #d9534f;
      background: #fff8f8;
    }

    .hex-label-container {
      text-align: center;
      pointer-events: none;
      text-shadow: 0 1px 1px rgba(255,255,255,0.9);
    }

    .exporting .leaflet-control-container {
      display: none !important;
    }

    @media (max-width: 1100px) {
      body {
        height: auto;
      }

      .app-layout {
        flex-direction: column;
      }

      .sidebar-left {
        width: 100%;
        min-width: 0;
      }

      .main-area {
        min-height: 760px;
      }

      #map {
        min-height: 520px;
      }

      .hex-card {
        grid-template-columns: 50px minmax(120px, 1fr) 28px 130px 90px 30px;
      }
    }
  </style>
</head>

<body>

<h1>Wargame Map Editor — WWI Edition</h1>

<div class="app-layout">

  <div class="sidebar-left">

    <div class="panel-title">1. Search Operational Theatre</div>
    <div class="form-row">
      <input type="text" id="searchInput" placeholder="E.g. Verdun, Isonzo, Gallipoli..." style="flex:1;">
      <button class="action-btn" onclick="searchLocation()">Search</button>
    </div>

    <div class="panel-title">2. Tools</div>
    <div class="tool-palette">
      <button id="toolSelect" class="tool-btn active" onclick="setTool('select')">👆 Place</button>
      <button id="toolLink" class="tool-btn" onclick="setTool('link')">🔗 Link</button>
    </div>

    <button class="action-btn" onclick="addNewHexCenter()">+ Hex at Centre</button>

    <div class="panel-title">3. Cartography</div>

    <div class="form-group">
      <label>Map Type</label>
      <select id="mapProvider" onchange="changeMapProvider()">
        <optgroup label="Illustrative local bases — Western Front">
          <option value="local_verdun">Illustrative base — Verdun 1916</option>
          <option value="local_messines">Illustrative base — Messines 1917</option>
          <option value="local_cambrai">Illustrative base — Cambrai 1917</option>
          <option value="local_passchendaele">Illustrative base — Passchendaele 1917</option>
          <option value="local_chemin">Illustrative base — Chemin des Dames 1917</option>
        </optgroup>
        <optgroup label="Illustrative local bases — Italian Front">
          <option value="local_isonzo">Illustrative base — Isonzo 1916</option>
          <option value="local_piave">Illustrative base — Piave 1918</option>
        </optgroup>
        <option value="topo_historic" selected>Vintage Map — OpenTopo</option>
        <option value="osm">OpenStreetMap Standard</option>
        <option value="igm_25k_http">IGM 1:25,000 — Italy, PCN HTTP</option>
        <option value="igm_25k_https">IGM 1:25,000 — Italy, PCN HTTPS</option>
        <option value="ortofoto_88_http">Black & White Orthophoto 1988 — Italy, PCN HTTP</option>
        <option value="ortofoto_88_https">Black & White Orthophoto 1988 — Italy, PCN HTTPS</option>
        <option value="ign_france">État-Major Map — France</option>
        <option value="esri_sat">Satellite — Esri</option>
      </select>
    </div>

    <div class="form-group">
      <label>Map Opacity: <span id="opacityVal">80%</span></label>
      <input type="range" id="mapOpacity" min="0" max="1" step="0.1" value="0.8" oninput="updateOpacity(this.value)">
    </div>

    <div class="form-group">
      <label>Hex Radius: <span id="hexRadiusVal">800 m</span></label>
      <input type="range" id="hexRadius" min="200" max="2500" step="100" value="800" oninput="updateHexRadius(this.value)">
    </div>

    <div class="panel-title">4. Labels</div>

    <div class="form-group">
      <label>Label Font</label>
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
      <label>Font Scale: <span id="fontScaleVal">100%</span></label>
      <input type="range" id="labelFontScale" min="0.5" max="2" step="0.05" value="1" oninput="updateLabelFontScale(this.value)">
    </div>

    <div class="form-group">
      <label>Flag Scale: <span id="flagScaleVal">100%</span></label>
      <input type="range" id="labelFlagScale" min="0.4" max="2.5" step="0.05" value="1" oninput="updateLabelFlagScale(this.value)">
    </div>

    <div class="panel-title">5. Export / Save</div>

    <button class="primary-btn" onclick="exportPNG()">🖼 Export PNG</button>
    <button class="primary-btn" onclick="saveToDatabase()">💾 Save to Local DB</button>
    <button class="secondary-btn" onclick="refreshSavedMaps()">↻ Refresh Saved Maps</button>
    <button class="secondary-btn" onclick="testCurrentMapProvider()">🧪 Test Map/WMS</button>
    <button class="danger" onclick="clearAll()">🗑 Clear Map</button>

    <div id="storageStatus" class="small-note">Checking local SQLite storage…</div>

    <div class="small-note">
      <strong>WMS note:</strong> PCN/Geoportal services may only work over HTTP or may have CORS limits.
      If this page is served over HTTPS, HTTP WMS layers may be blocked by the browser.
    </div>

    <div class="panel-title">6. Saved Scenarios</div>
    <div class="saved-list" id="savedMapsList">
      <em style="font-size:10px;">Click “Refresh Saved Maps”.</em>
    </div>

    <div class="panel-title">7. WWI Flag Legend</div>
    <div class="legend" id="legendContainer"></div>

  </div>

  <div class="main-area">
    <div id="map"></div>

    <div id="hexBottomPanel" class="hex-bottom-panel open">
      <div class="hex-bottom-header">
        <strong>Hex List (<span id="hexCount">0</span>)</strong>
        <button class="secondary-btn" id="hexPanelToggleBtn" onclick="toggleHexPanel()">Close</button>
      </div>

      <div class="hex-list-scroll bottom" id="hexListContainer"></div>
    </div>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  let map;
  let currentTileLayer;
  let hexLayerGroup;
  let linkLayerGroup;

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
  let hexPanelOpen = true;

  const ALLOWED_FONTS = [
    'Georgia, serif',
    "'Times New Roman', serif",
    'Arial, sans-serif',
    'Verdana, sans-serif',
    "'Courier New', monospace",
    "'Trebuchet MS', sans-serif"
  ];

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
        <rect width="900" height="600" fill="#d8d2c0"/>
        <path d="M0 0L900 600M900 0L0 600" stroke="#8c8275" stroke-width="52"/>
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
      name: 'Neutral',
      historical: 'Neutral / unassigned sector',
      flag: FLAGS.neutral,
      bg: '#e0d8c3',
      border: '#7a7265',
      text: '#222'
    },
    IT: {
      name: 'Kingdom of Italy',
      historical: 'Flag of the Kingdom of Italy with the Savoy shield, 1861–1946',
      flag: FLAGS.italyKingdom,
      bg: '#d4e3d4',
      border: '#006622',
      text: '#004011'
    },
    AH: {
      name: 'Austria-Hungary',
      historical: 'Common Austro-Hungarian ensign, often used as a practical symbol of the Dual Monarchy',
      flag: FLAGS.austriaHungary,
      bg: '#f0d3d3',
      border: '#8b0000',
      text: '#500000'
    },
    DE: {
      name: 'German Empire',
      historical: 'Black-white-red flag of the German Empire, 1871–1918',
      flag: FLAGS.germanyEmpire,
      bg: '#dcdcdc',
      border: '#333333',
      text: '#111111'
    },
    FR: {
      name: 'France',
      historical: 'French tricolour',
      flag: FLAGS.france,
      bg: '#d4daf0',
      border: '#1c3163',
      text: '#0e1933'
    },
    UK: {
      name: 'British Empire',
      historical: 'Union Jack of the United Kingdom',
      flag: FLAGS.unitedKingdom,
      bg: '#f0e6d3',
      border: '#8b6508',
      text: '#4a3504'
    },
    RU: {
      name: 'Russian Empire',
      historical: 'White-blue-red tricolour of the Russian Empire',
      flag: FLAGS.russiaEmpire,
      bg: '#e6d4f0',
      border: '#5a189a',
      text: '#2e0c4f'
    },
    OT: {
      name: 'Ottoman Empire',
      historical: 'Ottoman red flag with crescent and star',
      flag: FLAGS.ottomanEmpire,
      bg: '#d3f0ea',
      border: '#006644',
      text: '#003322'
    },
    BG: {
      name: 'Kingdom of Bulgaria',
      historical: 'Bulgarian white-green-red tricolour',
      flag: FLAGS.bulgaria,
      bg: '#f0ebd3',
      border: '#8b7500',
      text: '#453c00'
    },
    BE: {
      name: 'Belgium',
      historical: 'Belgian black-yellow-red tricolour',
      flag: FLAGS.belgium,
      bg: '#f2e2b8',
      border: '#111',
      text: '#111'
    },
    RS: {
      name: 'Kingdom of Serbia',
      historical: 'Serbian red-blue-white tricolour',
      flag: FLAGS.serbia,
      bg: '#e8d5d8',
      border: '#7c1d25',
      text: '#3d0f13'
    },
    US: {
      name: 'United States',
      historical: '48-star United States flag, in use from 1912',
      flag: FLAGS.usa,
      bg: '#d6e0f2',
      border: '#243f75',
      text: '#162647'
    }
  };

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
  };

  const LOCAL_BASEMAPS = {
    local_verdun: {
      url: 'assets/img/basemaps/base-illustrativa-verdun-1916.png',
      bounds: [[49.05, 5.05], [49.35, 5.75]],
      label: 'Verdun 1916'
    },
    local_messines: {
      url: 'assets/img/basemaps/base-illustrativa-messines-1917.png',
      bounds: [[50.68, 2.70], [50.88, 3.17]],
      label: 'Messines 1917'
    },
    local_cambrai: {
      url: 'assets/img/basemaps/base-illustrativa-cambrai-1917.png',
      bounds: [[50.05, 2.90], [50.27, 3.42]],
      label: 'Cambrai 1917'
    },
    local_passchendaele: {
      url: 'assets/img/basemaps/base-illustrativa-passchendaele-1917.png',
      bounds: [[50.82, 2.78], [51.02, 3.26]],
      label: 'Passchendaele 1917'
    },
    local_chemin: {
      url: 'assets/img/basemaps/base-illustrativa-chemin-des-dames-1917.png',
      bounds: [[49.30, 3.30], [49.54, 3.87]],
      label: 'Chemin des Dames 1917'
    },
    local_isonzo: {
      url: 'assets/img/basemaps/base-illustrativa-isonzo-1916.png',
      bounds: [[45.78, 13.30], [46.08, 14.00]],
      label: 'Isonzo 1916'
    },
    local_piave: {
      url: 'assets/img/basemaps/base-illustrativa-piave-1918.png',
      bounds: [[45.35, 11.95], [45.80, 13.00]],
      label: 'Piave 1918'
    }
  };

  function createLocalBasemap(providerKey) {
    const config = LOCAL_BASEMAPS[providerKey];
    return L.imageOverlay(config.url, config.bounds, {
      opacity: 0.8,
      interactive: false,
      crossOrigin: false,
      attribution: `Illustrative AI-generated base — ${config.label}`
    });
  }

  const MAP_PROVIDERS = {
    local_verdun: () => createLocalBasemap('local_verdun'),
    local_messines: () => createLocalBasemap('local_messines'),
    local_cambrai: () => createLocalBasemap('local_cambrai'),
    local_passchendaele: () => createLocalBasemap('local_passchendaele'),
    local_chemin: () => createLocalBasemap('local_chemin'),
    local_isonzo: () => createLocalBasemap('local_isonzo'),
    local_piave: () => createLocalBasemap('local_piave'),
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
        name: 'SECTOR',
        nation: 'NEUTRAL',
        icon: 'NONE'
      };

      hexes.push(newHex);
      selectedHexId = newHex.id;

      renderHexList();
      renderMapOverlay();
    });

    renderLegend();
    refreshSavedMaps();
    checkStorageStatus();

    setTimeout(() => map.invalidateSize(), 250);
  });

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function editorEndpoint(params = {}) {
    const url = new URL(window.location.href);
    url.search = '';
    url.hash = '';
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    return url.toString();
  }

  async function requestEditorJson(url, options = {}) {
    const response = await fetch(url, options);
    const raw = await response.text();
    let result;

    try {
      result = JSON.parse(raw);
    } catch {
      throw new Error(`The server returned a non-JSON response (HTTP ${response.status}). Check PHP and PDO SQLite.`);
    }

    if (!response.ok || result.status === 'error') {
      throw new Error(result.message || `HTTP ${response.status}`);
    }

    return result;
  }

  async function checkStorageStatus() {
    const status = document.getElementById('storageStatus');
    if (!status) return;

    try {
      const result = await requestEditorJson(editorEndpoint({ action: 'storage_status' }));
      status.style.color = '#285d36';
      status.textContent = `✅ Local storage ready: ${result.database}`;
    } catch (error) {
      status.style.color = '#8b1f1f';
      status.textContent = `❌ ${error.message}`;
    }
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

  function getSelectedProviderKey() {
    return document.getElementById('mapProvider').value;
  }

  function normaliseProviderKey(key) {
    if (key === 'igm_25k') return 'igm_25k_http';
    if (key === 'ortofoto_88') return 'ortofoto_88_http';
    return key;
  }

  function normaliseFontFamily(font) {
    return ALLOWED_FONTS.includes(font) ? font : 'Georgia, serif';
  }

  function attachTileDiagnostics(layer, providerKey) {
    layer.on('tileerror', e => {
      console.warn('Tile/WMS loading error:', providerKey, e);

      if (providerKey.includes('igm_25k') || providerKey.includes('ortofoto_88')) {
        console.warn(
          'Possible PCN WMS issue: service unreachable, CORS, HTTPS/HTTP mixed content, certificate issue, or unavailable layer.'
        );
      }
    });
    layer.on('error', e => {
      console.warn('Local image loading error:', providerKey, e);
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

    const flagW = clamp(radiusPx * 0.28 * labelFlagScale, 5, 36);
    const flagH = clamp(radiusPx * 0.18 * labelFlagScale, 4, 24);

    const contentW = Math.max(radiusPx * 1.20, flagW + 6, titleSize * 3.5);
    const contentH = flagH + titleSize + subSize + iconSize + 6;

    return {
      titleSize,
      subSize,
      iconSize,
      flagW,
      flagH,
      boxW: clamp(contentW, 28, radiusPx * 1.55),
      boxH: clamp(contentH, 16, radiusPx * 1.15),
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

  window.toggleHexPanel = function() {
    const panel = document.getElementById('hexBottomPanel');
    const btn = document.getElementById('hexPanelToggleBtn');

    hexPanelOpen = !hexPanelOpen;

    panel.classList.toggle('closed', !hexPanelOpen);
    panel.classList.toggle('open', hexPanelOpen);

    btn.innerText = hexPanelOpen ? 'Close' : 'Open';

    setTimeout(() => {
      map.invalidateSize();
      renderMapOverlay();
    }, 220);
  };

  window.searchLocation = async function() {
    const query = document.getElementById('searchInput').value.trim();

    if (!query) {
      alert('Please enter a location.');
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
        alert('Location not found.');
      }
    } catch (err) {
      alert('Connection error while searching.');
      console.error(err);
    }
  };

  window.setTool = function(tool) {
    currentTool = tool;

    document.getElementById('toolSelect').classList.toggle('active', tool === 'select');
    document.getElementById('toolLink').classList.toggle('active', tool === 'link');

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
      alert('Invalid map provider: ' + val);
      return;
    }

    if (currentTileLayer) {
      map.removeLayer(currentTileLayer);
    }

    currentTileLayer = MAP_PROVIDERS[val]();
    attachTileDiagnostics(currentTileLayer, val);
    currentTileLayer.addTo(map);

    updateOpacity(document.getElementById('mapOpacity').value);

    if (LOCAL_BASEMAPS[val]) {
      map.fitBounds(LOCAL_BASEMAPS[val].bounds, { padding: [12, 12] });
    }
  };

  window.updateOpacity = function(val) {
    document.getElementById('opacityVal').innerText = Math.round(val * 100) + '%';

    if (currentTileLayer) {
      currentTileLayer.setOpacity(parseFloat(val));
    }
  };

  window.testCurrentMapProvider = async function() {
    const provider = getSelectedProviderKey();

    let message = `Active provider: ${provider}\n\n`;

    if (LOCAL_BASEMAPS[provider]) {
      const config = LOCAL_BASEMAPS[provider];
      try {
        const response = await fetch(config.url, { cache: 'no-store' });
        message += response.ok
          ? `✅ ${config.label}: local image available.\n\n`
          : `❌ ${config.label}: HTTP ${response.status}.\n\n`;
      } catch (error) {
        message += `❌ ${config.label}: local image unavailable.\n\n`;
        console.error(error);
      }
      message += 'Illustrative AI-generated base: this is not a georeferenced historical map.';
      alert(message);
      return;
    }

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

      message += `WMS endpoint:\n${base}\n\n`;
      message += `Map file:\n${mapParam}\n\n`;
      message += `Layer:\n${layer}\n\n`;
      message += `GetCapabilities:\n${capabilitiesUrl}\n\n`;

      try {
        const response = await fetch(capabilitiesUrl, {
          method: 'GET',
          mode: 'cors'
        });

        message += `GetCapabilities HTTP response: ${response.status} ${response.statusText}\n`;

        if (!response.ok) {
          message += '\nThe server responded, but not with an OK status.';
        } else {
          const text = await response.text();

          if (text.includes(layer)) {
            message += '\n✅ The layer appears to be present in GetCapabilities.';
          } else {
            message += '\n⚠️ GetCapabilities responded, but the layer name was not found in the response text.';
          }
        }
      } catch (error) {
        message +=
          '⚠️ GetCapabilities fetch failed.\n\n' +
          'Possible causes:\n' +
          '- CORS is not allowed by the WMS server;\n' +
          '- HTTP is blocked because this page is served over HTTPS;\n' +
          '- the PCN endpoint is temporarily unreachable;\n' +
          '- the HTTPS certificate is not accepted.\n\n' +
          'Also check the browser console.';
        console.error(error);
      }

      alert(message);
      return;
    }

    message += 'This provider is not a PCN WMS layer. Check the map visually and inspect the browser console for tile errors.';
    alert(message);
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
    const title = prompt('PNG file name:', 'ww1-wargame-map');

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
      alert('PNG export failed. This may be caused by CORS restrictions on map tiles.');
      console.error(error);
    }
  };

  window.saveToDatabase = async function() {
    const title = prompt('Name this scenario:', 'New Historical Map');

    if (!title) return;

    try {
      // Persist editable JSON even when remote tiles prevent PNG capture via CORS.
      const imgBase64 = '';

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
        hexPanelOpen
      });

      const formData = new FormData();
      formData.append('action', 'save');
      formData.append('title', title);
      formData.append('image', imgBase64);
      formData.append('state', stateJSON);

      const result = await requestEditorJson(editorEndpoint(), {
        method: 'POST',
        body: formData
      });

      if (result.status === 'success') {
        alert('✅ ' + result.message);
        refreshSavedMaps();
      } else {
        alert('❌ Error: ' + result.message);
      }
    } catch (error) {
      alert('Save failed.\n' + error.message);
      console.error(error);
    }
  };

  window.refreshSavedMaps = async function() {
    const container = document.getElementById('savedMapsList');
    container.innerHTML = '<em style="font-size:10px;">Loading...</em>';

    try {
      const result = await requestEditorJson(editorEndpoint({ action: 'list' }));

      if (result.status !== 'success') {
        container.innerHTML = `<em style="font-size:10px;">Error: ${escapeHtml(result.message)}</em>`;
        return;
      }

      if (!result.maps.length) {
        container.innerHTML = '<em style="font-size:10px;">No saved scenarios.</em>';
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
          <button class="action-btn" onclick="loadSavedMap(${parseInt(item.id, 10)})">Open</button>
          <button class="danger" onclick="deleteSavedMap(${parseInt(item.id, 10)})">×</button>
        `;

        container.appendChild(row);
      });
    } catch (error) {
      container.innerHTML = `<em style="font-size:10px;">Error loading scenarios. ${escapeHtml(error.message)}</em>`;
      console.error(error);
    }
  };

  window.loadSavedMap = async function(id) {
    if (!confirm('Load this scenario? Unsaved changes will be lost.')) return;

    try {
      const result = await requestEditorJson(editorEndpoint({ action: 'load', id }));

      if (result.status !== 'success') {
        alert('Error: ' + result.message);
        return;
      }

      const state = JSON.parse(result.map.map_state || '{}');

      hexes = Array.isArray(state.hexes) ? state.hexes : [];
      manualLinks = Array.isArray(state.manualLinks) ? state.manualLinks : [];
      selectedHexId = null;
      linkSourceId = null;

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

      if (typeof state.hexPanelOpen === 'boolean') {
        hexPanelOpen = state.hexPanelOpen;

        const panel = document.getElementById('hexBottomPanel');
        const btn = document.getElementById('hexPanelToggleBtn');

        panel.classList.toggle('closed', !hexPanelOpen);
        panel.classList.toggle('open', hexPanelOpen);
        btn.innerText = hexPanelOpen ? 'Close' : 'Open';

        setTimeout(() => map.invalidateSize(), 220);
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

      alert('Scenario loaded: ' + result.map.title);
    } catch (error) {
      alert('Load failed.\n' + error.message);
      console.error(error);
    }
  };

  window.deleteSavedMap = async function(id) {
    if (!confirm('Permanently delete this saved scenario?')) return;

    try {
      const formData = new FormData();
      formData.append('action', 'delete_saved');
      formData.append('id', id);

      const result = await requestEditorJson(editorEndpoint(), {
        method: 'POST',
        body: formData
      });

      if (result.status === 'success') {
        refreshSavedMaps();
      } else {
        alert('Error: ' + result.message);
      }
    } catch (error) {
      alert('Delete failed.\n' + error.message);
      console.error(error);
    }
  };

  window.clearAll = function() {
    if (!confirm('Clear the entire map?')) return;

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
            alt="${escapeHtml(nat.name)}"
            style="
              width:${m.flagW}px;
              height:${m.flagH}px;
              object-fit:cover;
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
    document.getElementById('hexCount').innerText = hexes.length;

    container.innerHTML = '';

    hexes.forEach(hex => {
      const isSelected = hex.id === selectedHexId;
      const nat = NATIONS[hex.nation] || NATIONS.NEUTRAL;

      const card = document.createElement('div');
      card.className = `hex-card ${isSelected ? 'selected' : ''}`;

      let nationOptionsHtml = '';
      for (const [key, data] of Object.entries(NATIONS)) {
        nationOptionsHtml += `
          <option value="${key}" ${hex.nation === key ? 'selected' : ''}>
            ${escapeHtml(data.name)}
          </option>
        `;
      }

      let iconOptionsHtml = '';
      for (const [key] of Object.entries(ICONS)) {
        const label = ICON_LABELS[key] || key;
        iconOptionsHtml += `
          <option value="${key}" ${hex.icon === key ? 'selected' : ''}>
            ${escapeHtml(label)}
          </option>
        `;
      }

      card.innerHTML = `
        <input type="text" value="${escapeHtml(hex.code)}" onchange="updateHex(${hex.id}, 'code', this.value)">
        <input type="text" value="${escapeHtml(hex.name)}" onchange="updateHex(${hex.id}, 'name', this.value)">
        <img class="flag-img" src="${nat.flag}" title="${escapeHtml(nat.historical)}" alt="${escapeHtml(nat.name)}">
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
    container.innerHTML = '';

    for (const [, nat] of Object.entries(NATIONS)) {
      const img = document.createElement('img');
      img.className = 'flag-img';
      img.src = nat.flag;
      img.alt = nat.name;
      img.title = nat.historical;

      const text = document.createElement('div');
      text.innerHTML = `
        <strong>${escapeHtml(nat.name)}</strong><br>
        <span>${escapeHtml(nat.historical)}</span>
      `;

      container.appendChild(img);
      container.appendChild(text);
    }
  }
</script>

</body>
</html>
