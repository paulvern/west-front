<?php
/**
 * check-editor.php
 *
 * Diagnostica mirata per admin.php / api-editor.php / editor-config.php.
 * Compatibile con PHP 8.
 * Cancellare dopo l'uso.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function badge($ok) {
    return $ok
        ? '<span style="color:green;font-weight:bold">OK</span>'
        : '<span style="color:red;font-weight:bold">ERRORE</span>';
}

echo '<!doctype html>';
echo '<html lang="it">';
echo '<head>';
echo '<meta charset="utf-8">';
echo '<title>Check editor</title>';
echo '<style>
body {
  font-family: Arial, sans-serif;
  background: #efe2c4;
  color: #20170f;
  padding: 24px;
}
.panel {
  background: #fffaf0;
  border: 1px solid #8b7358;
  padding: 16px;
  margin-bottom: 18px;
}
pre {
  white-space: pre-wrap;
  background: white;
  border: 1px solid #8b7358;
  padding: 10px;
}
code {
  background: #f5ecd8;
  padding: 1px 4px;
}
</style>';
echo '</head>';
echo '<body>';

echo '<h1>Check editor</h1>';

echo '<div class="panel">';
echo '<h2>PHP</h2>';
echo '<p>Versione PHP: <strong>' . h(PHP_VERSION) . '</strong></p>';
echo '<p>Root: <code>' . h(__DIR__) . '</code></p>';
echo '</div>';

/**
 * Test 1: editor-config.php
 */
echo '<div class="panel">';
echo '<h2>1. Test editor-config.php</h2>';

try {
    ob_start();
    require_once __DIR__ . '/editor-config.php';
    $output = ob_get_clean();

    echo '<p>' . badge(true) . ' editor-config.php incluso correttamente.</p>';

    if ($output !== '') {
        echo '<p><strong>Output inatteso prodotto da editor-config.php:</strong></p>';
        echo '<pre>' . h($output) . '</pre>';
    }

    $constants = array(
        'EDITOR_ROOT',
        'MANIFEST_PATH',
        'SECTIONS_ROOT',
        'BACKUP_ROOT',
        'UPLOAD_ROOT',
        'UPLOAD_URL_PREFIX',
        'EDITOR_PASSWORD_HASH'
    );

    echo '<ul>';
    foreach ($constants as $c) {
        if (defined($c)) {
            $value = constant($c);

            if ($c === 'EDITOR_PASSWORD_HASH') {
                $value = substr((string) $value, 0, 16) . '...';
            }

            echo '<li><code>' . h($c) . '</code>: <code>' . h($value) . '</code></li>';
        } else {
            echo '<li><code>' . h($c) . '</code>: <strong style="color:red">NON DEFINITA</strong></li>';
        }
    }
    echo '</ul>';

} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo '<p>' . badge(false) . ' Errore in editor-config.php:</p>';
    echo '<pre>' . h($e->getMessage()) . '</pre>';
}

echo '</div>';

/**
 * Test 2: manifest tramite funzione.
 */
echo '<div class="panel">';
echo '<h2>2. Test manual.json tramite editor_load_manifest()</h2>';

if (function_exists('editor_load_manifest')) {
    try {
        $manifest = editor_load_manifest();

        echo '<p>' . badge(true) . ' manual.json caricato tramite funzione.</p>';
        echo '<p>Versione: <strong>' . h($manifest['version'] ?? 'non indicata') . '</strong></p>';
        echo '<p>Sezioni: <strong>' . h(count($manifest['sections'] ?? array())) . '</strong></p>';

    } catch (Throwable $e) {
        echo '<p>' . badge(false) . ' Errore caricamento manual.json:</p>';
        echo '<pre>' . h($e->getMessage()) . '</pre>';
    }
} else {
    echo '<p>' . badge(false) . ' Funzione editor_load_manifest() non trovata.</p>';
}

echo '</div>';

/**
 * Test 3: prima sezione.
 */
echo '<div class="panel">';
echo '<h2>3. Test prima sezione</h2>';

try {
    if (!isset($manifest)) {
        $manifest = editor_load_manifest();
    }

    if (empty($manifest['sections'][0])) {
        throw new RuntimeException('Nessuna sezione nel manifest.');
    }

    $first = $manifest['sections'][0];

    echo '<p>Prima sezione: <code>' . h($first['id'] ?? '') . '</code> — ' . h($first['title'] ?? '') . '</p>';
    echo '<p>File: <code>' . h($first['file'] ?? '') . '</code></p>';

    $path = editor_resolve_section_path($first['file'] ?? '');

    echo '<p>Path risolto: <code>' . h($path) . '</code></p>';

    $html = file_get_contents($path);

    if ($html === false) {
        throw new RuntimeException('file_get_contents fallito sulla prima sezione.');
    }

    echo '<p>' . badge(true) . ' Prima sezione letta correttamente.</p>';
    echo '<p>Lunghezza HTML: <strong>' . h(strlen($html)) . '</strong> caratteri.</p>';

} catch (Throwable $e) {
    echo '<p>' . badge(false) . ' Errore prima sezione:</p>';
    echo '<pre>' . h($e->getMessage()) . '</pre>';
}

echo '</div>';

/**
 * Test 4: cartelle scrivibili.
 */
echo '<div class="panel">';
echo '<h2>4. Test scrittura cartelle</h2>';

$dirs = array(
    'BACKUP_ROOT' => defined('BACKUP_ROOT') ? BACKUP_ROOT : null,
    'UPLOAD_ROOT' => defined('UPLOAD_ROOT') ? UPLOAD_ROOT : null
);

foreach ($dirs as $label => $dir) {
    echo '<h3>' . h($label) . '</h3>';

    if (!$dir) {
        echo '<p>' . badge(false) . ' Costante non definita.</p>';
        continue;
    }

    echo '<p>Cartella: <code>' . h($dir) . '</code></p>';

    if (!is_dir($dir)) {
        echo '<p>' . badge(false) . ' La cartella non esiste.</p>';
        continue;
    }

    if (!is_writable($dir)) {
        echo '<p>' . badge(false) . ' La cartella non è scrivibile.</p>';
        continue;
    }

    $testFile = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'test-write-' . time() . '.txt';

    $ok = file_put_contents($testFile, 'test') !== false;

    if ($ok) {
        @unlink($testFile);
        echo '<p>' . badge(true) . ' Scrittura OK.</p>';
    } else {
        echo '<p>' . badge(false) . ' file_put_contents fallito.</p>';
    }
}

echo '</div>';

/**
 * Test 5: password hash.
 */
echo '<div class="panel">';
echo '<h2>5. Test password hash</h2>';

if (!defined('EDITOR_PASSWORD_HASH')) {
    echo '<p>' . badge(false) . ' EDITOR_PASSWORD_HASH non definito.</p>';
} else {
    $hash = EDITOR_PASSWORD_HASH;

    if (strpos($hash, 'ESEMPIO_CAMBIA') !== false) {
        echo '<p>' . badge(false) . ' Stai ancora usando l’hash segnaposto.</p>';
    } else {
        echo '<p>' . badge(true) . ' Hash password personalizzato presente.</p>';
    }

    $info = password_get_info($hash);

    echo '<pre>' . h(print_r($info, true)) . '</pre>';

    if (($info['algo'] ?? 0) === 0) {
        echo '<p>' . badge(false) . ' Hash non riconosciuto da password_get_info().</p>';
    } else {
        echo '<p>' . badge(true) . ' Hash riconosciuto da PHP.</p>';
    }
}

echo '</div>';

echo '<div class="panel">';
echo '<h2>6. Prossimi test manuali</h2>';
echo '<p>Apri questi link dopo aver fatto login in <code>admin.php</code>:</p>';
echo '<ul>';
echo '<li><a href="api-editor.php?action=manifest" target="_blank">api-editor.php?action=manifest</a></li>';
echo '<li><a href="api-editor.php?action=section&id=intro" target="_blank">api-editor.php?action=section&id=intro</a></li>';
echo '<li><a href="admin.php" target="_blank">admin.php</a></li>';
echo '</ul>';
echo '</div>';

echo '<div class="panel">';
echo '<p><strong>Importante:</strong> cancella <code>check-editor.php</code> dopo il debug.</p>';
echo '</div>';

echo '</body>';
echo '</html>';