<?php
/*
  debug.php
  Debug minimale compatibile con PHP vecchi.
  Cancellare dopo l'uso.
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function yn($value) {
    return $value ? 'SI' : 'NO';
}

function row($name, $value) {
    echo '<tr><th>' . e($name) . '</th><td>' . e($value) . '</td></tr>';
}

function file_row($label, $path, $needWritable) {
    $exists = file_exists($path);
    $isFile = is_file($path);
    $isDir = is_dir($path);
    $readable = $exists ? is_readable($path) : false;
    $writable = $exists ? is_writable($path) : false;
    $perms = '-';

    if ($exists) {
        $p = fileperms($path);
        if ($p !== false) {
            $perms = substr(sprintf('%o', $p), -4);
        }
    }

    $ok = $exists && $readable;
    if ($needWritable) {
        $ok = $ok && $writable;
    }

    echo '<tr>';
    echo '<td>' . e($label) . '</td>';
    echo '<td>' . ($ok ? '<strong style="color:green">OK</strong>' : '<strong style="color:red">ERRORE</strong>') . '</td>';
    echo '<td><code>' . e($path) . '</code></td>';
    echo '<td>' . e(yn($exists)) . '</td>';
    echo '<td>' . e(yn($isFile)) . '</td>';
    echo '<td>' . e(yn($isDir)) . '</td>';
    echo '<td>' . e(yn($readable)) . '</td>';
    echo '<td>' . e(yn($writable)) . '</td>';
    echo '<td>' . e($perms) . '</td>';
    echo '</tr>';
}

$root = dirname(__FILE__);

echo '<!doctype html>';
echo '<html lang="it">';
echo '<head>';
echo '<meta charset="utf-8">';
echo '<title>Debug minimale</title>';
echo '<style>
body {
  font-family: Arial, sans-serif;
  background: #efe2c4;
  color: #20170f;
  padding: 24px;
}
h1, h2 {
  margin-top: 0;
}
.panel {
  background: #fffaf0;
  border: 1px solid #8b7358;
  padding: 16px;
  margin-bottom: 18px;
}
table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}
th, td {
  border: 1px solid #8b7358;
  padding: 7px 9px;
  text-align: left;
  vertical-align: top;
}
th {
  background: #d9eaf7;
}
code {
  background: #f5ecd8;
  padding: 1px 4px;
}
.warning {
  border-left: 6px solid #8a2d24;
  background: #fbe2dd;
}
.ok {
  color: green;
  font-weight: bold;
}
.bad {
  color: red;
  font-weight: bold;
}
</style>';
echo '</head>';
echo '<body>';

echo '<h1>Debug minimale editor</h1>';

echo '<div class="panel warning">';
echo '<p><strong>Attenzione:</strong> cancella questo file dopo il debug.</p>';
echo '</div>';

echo '<div class="panel">';
echo '<h2>PHP</h2>';
echo '<table>';
row('PHP_VERSION', phpversion());
row('SAPI', php_sapi_name());
row('ROOT', $root);
row('display_errors', ini_get('display_errors'));
row('log_errors', ini_get('log_errors'));
row('error_log', ini_get('error_log'));
row('memory_limit', ini_get('memory_limit'));
row('upload_max_filesize', ini_get('upload_max_filesize'));
row('post_max_size', ini_get('post_max_size'));
row('file_uploads', ini_get('file_uploads'));
row('disable_functions', ini_get('disable_functions'));
echo '</table>';
echo '</div>';

echo '<div class="panel">';
echo '<h2>Estensioni PHP</h2>';
echo '<table>';
row('json', yn(extension_loaded('json')));
row('fileinfo', yn(extension_loaded('fileinfo')));
row('session', yn(extension_loaded('session')));
row('gd', yn(extension_loaded('gd')));
echo '</table>';
echo '</div>';

echo '<div class="panel">';
echo '<h2>File e cartelle</h2>';
echo '<table>';
echo '<tr>';
echo '<th>Elemento</th>';
echo '<th>Stato</th>';
echo '<th>Percorso</th>';
echo '<th>Esiste</th>';
echo '<th>File</th>';
echo '<th>Cartella</th>';
echo '<th>Leggibile</th>';
echo '<th>Scrivibile</th>';
echo '<th>Permessi</th>';
echo '</tr>';

file_row('admin.php', $root . '/admin.php', false);
file_row('api-editor.php', $root . '/api-editor.php', false);
file_row('editor-config.php', $root . '/editor-config.php', false);
file_row('manual.json', $root . '/manual.json', false);
file_row('assets/css/manual.css', $root . '/assets/css/manual.css', false);
file_row('sections/', $root . '/sections', false);
file_row('backups/', $root . '/backups', true);
file_row('assets/img/manual/', $root . '/assets/img/manual', true);

echo '</table>';
echo '</div>';

/*
  Controllo manual.json.
*/
echo '<div class="panel">';
echo '<h2>Controllo manual.json</h2>';

$manifestPath = $root . '/manual.json';

if (!file_exists($manifestPath)) {
    echo '<p class="bad">manual.json non trovato.</p>';
} elseif (!is_readable($manifestPath)) {
    echo '<p class="bad">manual.json non leggibile.</p>';
} else {
    $raw = file_get_contents($manifestPath);

    if ($raw === false) {
        echo '<p class="bad">Impossibile leggere manual.json.</p>';
    } else {
        $data = json_decode($raw, true);

        if (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE) {
            echo '<p class="bad">JSON non valido: ' . e(json_last_error_msg()) . '</p>';
        } elseif (!is_array($data)) {
            echo '<p class="bad">manual.json non contiene un oggetto JSON valido.</p>';
        } elseif (!isset($data['sections']) || !is_array($data['sections'])) {
            echo '<p class="bad">manual.json non contiene sections[].</p>';
        } else {
            echo '<p class="ok">manual.json valido.</p>';
            echo '<p>Versione: <strong>' . e(isset($data['version']) ? $data['version'] : 'non indicata') . '</strong></p>';
            echo '<p>Sezioni trovate: <strong>' . count($data['sections']) . '</strong></p>';

            echo '<table>';
            echo '<tr>';
            echo '<th>#</th>';
            echo '<th>ID</th>';
            echo '<th>Titolo</th>';
            echo '<th>File</th>';
            echo '<th>Esiste</th>';
            echo '<th>Leggibile</th>';
            echo '<th>Scrivibile</th>';
            echo '</tr>';

            foreach ($data['sections'] as $i => $section) {
                $id = isset($section['id']) ? $section['id'] : '';
                $title = isset($section['title']) ? $section['title'] : '';
                $file = isset($section['file']) ? $section['file'] : '';

                $path = $root . '/' . str_replace('\\', '/', $file);

                echo '<tr>';
                echo '<td>' . e($i) . '</td>';
                echo '<td><code>' . e($id) . '</code></td>';
                echo '<td>' . e($title) . '</td>';
                echo '<td><code>' . e($file) . '</code></td>';
                echo '<td>' . e(yn(is_file($path))) . '</td>';
                echo '<td>' . e(yn(is_readable($path))) . '</td>';
                echo '<td>' . e(yn(is_writable($path))) . '</td>';
                echo '</tr>';
            }

            echo '</table>';
        }
    }
}

echo '</div>';

/*
  Cerca sintassi moderna nei file PHP.
  Utile se il server usa PHP 5.x.
*/
echo '<div class="panel">';
echo '<h2>Possibili incompatibilità PHP</h2>';

$phpVersion = phpversion();
echo '<p>Versione PHP rilevata: <strong>' . e($phpVersion) . '</strong></p>';

$filesToScan = array(
    'admin.php',
    'api-editor.php',
    'editor-config.php'
);

echo '<table>';
echo '<tr>';
echo '<th>File</th>';
echo '<th>declare strict_types</th>';
echo '<th>Operatore ??</th>';
echo '<th>Return types</th>';
echo '<th>Throwable</th>';
echo '<th>random_bytes</th>';
echo '</tr>';

foreach ($filesToScan as $fileName) {
    $path = $root . '/' . $fileName;
    $content = '';

    if (is_file($path) && is_readable($path)) {
        $content = file_get_contents($path);
    }

    echo '<tr>';
    echo '<td><code>' . e($fileName) . '</code></td>';
    echo '<td>' . e(yn(strpos($content, 'strict_types') !== false)) . '</td>';
    echo '<td>' . e(yn(strpos($content, '??') !== false)) . '</td>';
    echo '<td>' . e(yn(preg_match('/function\s+[a-zA-Z0-9_]+\s*\([^)]*\)\s*:/', $content))) . '</td>';
    echo '<td>' . e(yn(strpos($content, 'Throwable') !== false)) . '</td>';
    echo '<td>' . e(yn(strpos($content, 'random_bytes') !== false)) . '</td>';
    echo '</tr>';
}

echo '</table>';

echo '<p>Se PHP è 5.x e qui compaiono molti "SI", è quasi certamente questa la causa dell’errore 500.</p>';

echo '</div>';

echo '<div class="panel">';
echo '<h2>Conclusione rapida</h2>';
echo '<ol>';
echo '<li>Se PHP è inferiore a 7.0, i file attuali vanno riscritti in PHP compatibile oppure devi attivare PHP 8 dal pannello hosting.</li>';
echo '<li>Se <code>backups/</code> o <code>assets/img/manual/</code> non sono scrivibili, salvataggio e upload immagini falliranno.</li>';
echo '<li>Se le sezioni HTML non sono scrivibili, l’editor potrà leggerle ma non salvarle.</li>';
echo '<li>Se <code>fileinfo</code> manca, l’upload immagini può fallire.</li>';
echo '</ol>';
echo '</div>';

echo '</body>';
echo '</html>';