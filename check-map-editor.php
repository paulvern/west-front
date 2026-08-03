<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function result_row(string $label, bool $ok, string $detail = ''): void {
    $class = $ok ? 'ok' : 'error';
    $status = $ok ? 'OK' : 'ERROR';
    echo '<tr><th>' . h($label) . '</th><td class="' . $class . '">' . $status . '</td><td>' . h($detail) . '</td></tr>';
}

$database = __DIR__ . '/mappe_wargame.sqlite';
$basemaps = [
    'Verdun 1916' => 'assets/img/basemaps/base-illustrativa-verdun-1916.png',
    'Messines 1917' => 'assets/img/basemaps/base-illustrativa-messines-1917.png',
    'Cambrai 1917' => 'assets/img/basemaps/base-illustrativa-cambrai-1917.png',
    'Passchendaele 1917' => 'assets/img/basemaps/base-illustrativa-passchendaele-1917.png',
    'Chemin des Dames 1917' => 'assets/img/basemaps/base-illustrativa-chemin-des-dames-1917.png',
    'Isonzo 1916' => 'assets/img/basemaps/base-illustrativa-isonzo-1916.png',
    'Piave 1918' => 'assets/img/basemaps/base-illustrativa-piave-1918.png',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Scenario Map Editor Check</title>
  <style>
    body{font-family:system-ui,sans-serif;max-width:980px;margin:32px auto;padding:0 18px;background:#eee5d2;color:#2d241a}
    h1,h2{font-family:Georgia,serif}table{width:100%;border-collapse:collapse;background:#fffaf0;margin:18px 0}
    th,td{border:1px solid #9d8d72;padding:9px;text-align:left}th{width:30%}.ok{color:#17652f;font-weight:800}.error{color:#a11f1f;font-weight:800}
    .note{padding:12px;border-left:5px solid #8a6b35;background:#fff7df}a{color:#264f78}
  </style>
</head>
<body>
  <h1>Scenario Map Editor Check</h1>
  <p class="note">Run this page after uploading the project. It performs a transient SQLite write/read test inside a transaction and rolls it back. Delete this diagnostic file after testing a public deployment.</p>

  <h2>PHP and SQLite</h2>
  <table>
    <?php
    result_row('PHP version', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
    $pdoSqlite = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
    result_row('PDO SQLite extension', $pdoSqlite, $pdoSqlite ? 'Available' : 'Enable pdo_sqlite in PHP');
    result_row('Project directory writable', is_writable(__DIR__), is_writable(__DIR__) ? 'SQLite can create journal files' : 'Grant write permission to the PHP process');
    result_row('Database file exists', is_file($database), basename($database));
    result_row('Database file writable', is_file($database) && is_writable($database), is_writable($database) ? 'Writable' : 'Grant write permission to the PHP process');

    $cycleOk = false;
    $cycleDetail = 'Not run';
    if ($pdoSqlite && is_writable(__DIR__) && (!file_exists($database) || is_writable($database))) {
        try {
            $pdo = new PDO('sqlite:' . $database);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec("CREATE TABLE IF NOT EXISTS saved_maps (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, image_base64 TEXT, map_state TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $pdo->beginTransaction();
            $state = json_encode(['hexes' => [], 'manualLinks' => [], 'mapProvider' => 'local_verdun'], JSON_THROW_ON_ERROR);
            $stmt = $pdo->prepare('INSERT INTO saved_maps (title, image_base64, map_state) VALUES (?, ?, ?)');
            $stmt->execute(['__editor_diagnostic__', '', $state]);
            $id = (int)$pdo->lastInsertId();
            $check = $pdo->prepare('SELECT map_state FROM saved_maps WHERE id = ?');
            $check->execute([$id]);
            $stored = $check->fetchColumn();
            $cycleOk = is_string($stored) && json_decode($stored, true)['mapProvider'] === 'local_verdun';
            $cycleDetail = $cycleOk ? 'Insert and read succeeded; transaction rolled back' : 'Stored state did not match';
            $pdo->rollBack();
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $cycleDetail = $e->getMessage();
        }
    }
    result_row('Save/load cycle', $cycleOk, $cycleDetail);
    ?>
  </table>

  <h2>Local illustrative basemaps</h2>
  <table>
    <?php foreach ($basemaps as $name => $relative):
        $absolute = __DIR__ . '/' . $relative;
        $size = is_file($absolute) ? @getimagesize($absolute) : false;
        $ok = is_readable($absolute) && is_array($size);
        $detail = $ok ? $size[0] . ' × ' . $size[1] . ' px' : $relative;
        result_row($name, $ok, $detail);
    endforeach; ?>
  </table>

  <p><a href="editoretto.php">Open the bilingual editor</a> · <a href="editorettoe.php">Open the classic English editor</a></p>
</body>
</html>
