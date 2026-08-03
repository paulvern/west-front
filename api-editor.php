<?php
// api-editor.php

declare(strict_types=1);

// Previeni cache del browser
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Start session before editor-config.php to access $_SESSION
session_name('fronte_editor_session');
session_start();

// Language detection - MUST happen before requiring editor-config.php
$lang = $_GET['lang'] ?? (isset($_SESSION['editor_lang']) ? $_SESSION['editor_lang'] : 'it');
if (!in_array($lang, ['it', 'en'])) {
    $lang = 'it';
}
$_SESSION['editor_lang'] = $lang;

// Set manifest and sections directory based on language
// These constants MUST be defined BEFORE editor-config.php is loaded
define('CURRENT_MANIFEST', $lang === 'en' ? 'manual_en.json' : 'manual.json');
define('CURRENT_SECTIONS_DIR', $lang === 'en' ? 'sections_en' : 'sections');

require_once __DIR__ . '/editor-config.php';

editor_require_login();

$action = $_GET['action'] ?? '';

try {
    /**
     * Restituisce manual.json.
     */
    if ($action === 'manifest') {
        $manifest = editor_load_manifest();
        editor_json_response($manifest);
    }

    /**
     * Carica una sezione HTML.
     */
    if ($action === 'section') {
        $id = $_GET['id'] ?? '';

        if ($id === '') {
            editor_json_response(['error' => 'Parametro id mancante'], 400);
        }

        $section = editor_find_section($id);

        if (empty($section['file'])) {
            throw new RuntimeException('La sezione non contiene il campo file');
        }

        $path = editor_resolve_section_path($section['file']);
        $html = file_get_contents($path);

        if ($html === false) {
            throw new RuntimeException('Impossibile leggere il file HTML');
        }

        editor_json_response([
            'id' => $section['id'],
            'title' => $section['title'] ?? $section['id'],
            'file' => $section['file'],
            'html' => $html,
        ]);
    }

    /**
     * Salva una sezione HTML.
     */
    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            editor_json_response(['error' => 'Metodo non consentito'], 405);
        }

        editor_verify_csrf();

        $id = $_GET['id'] ?? '';

        if ($id === '') {
            editor_json_response(['error' => 'Parametro id mancante'], 400);
        }

        $rawBody = file_get_contents('php://input');

        if ($rawBody === false) {
            throw new RuntimeException('Impossibile leggere il corpo della richiesta');
        }

        $payload = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            editor_json_response(['error' => 'JSON richiesta non valido'], 400);
        }

        if (!is_array($payload) || !array_key_exists('html', $payload)) {
            editor_json_response(['error' => 'Campo html mancante'], 400);
        }

        $html = $payload['html'];

        if (!is_string($html)) {
            editor_json_response(['error' => 'Campo html non valido'], 400);
        }

        $section = editor_find_section($id);

        if (empty($section['file'])) {
            throw new RuntimeException('La sezione non contiene il campo file');
        }

        $path = editor_resolve_section_path($section['file']);

        if (!is_writable($path)) {
            throw new RuntimeException('Il file sezione non è scrivibile da PHP');
        }

        if (!is_dir(BACKUP_ROOT)) {
            if (!mkdir(BACKUP_ROOT, 0755, true)) {
                throw new RuntimeException('Impossibile creare la cartella backups/');
            }
        }

        if (!is_writable(BACKUP_ROOT)) {
            throw new RuntimeException('La cartella backups/ non è scrivibile da PHP');
        }

        $oldHtml = file_get_contents($path);

        if ($oldHtml === false) {
            throw new RuntimeException('Impossibile leggere il vecchio contenuto per il backup');
        }

        $timestamp = date('Y-m-d_H-i-s');
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $section['id']);
        $backupFile = $safeId . '_' . $timestamp . '.html';
        $backupPath = BACKUP_ROOT . '/' . $backupFile;

        if (file_put_contents($backupPath, $oldHtml, LOCK_EX) === false) {
            throw new RuntimeException('Impossibile creare il backup');
        }

        if (file_put_contents($path, $html, LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare il nuovo HTML');
        }

        editor_json_response([
            'ok' => true,
            'id' => $section['id'],
            'title' => $section['title'] ?? $section['id'],
            'file' => $section['file'],
            'backup' => $backupFile,
        ]);
    }

    /**
     * Upload immagini per CKEditor 5.
     *
     * CKEditor invia il file nel campo "upload".
     * Risposta attesa:
     *
     * {
     *   "url": "assets/img/manual/immagine.jpg"
     * }
     */
    if ($action === 'upload-image') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            editor_json_response([
                'error' => [
                    'message' => 'Metodo non consentito'
                ]
            ], 405);
        }

        editor_verify_csrf();

        if (!isset($_FILES['upload'])) {
            editor_json_response([
                'error' => [
                    'message' => 'Nessun file ricevuto'
                ]
            ], 400);
        }

        $file = $_FILES['upload'];

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;

            editor_json_response([
                'error' => [
                    'message' => 'Errore upload file. Codice: ' . $uploadError
                ]
            ], 400);
        }

        $tmpPath = $file['tmp_name'];
        $originalName = $file['name'] ?? 'immagine';

        if (!is_uploaded_file($tmpPath)) {
            editor_json_response([
                'error' => [
                    'message' => 'Upload non valido'
                ]
            ], 400);
        }

        $maxSize = 5 * 1024 * 1024; // 5 MB

        if (($file['size'] ?? 0) > $maxSize) {
            editor_json_response([
                'error' => [
                    'message' => 'Immagine troppo grande. Massimo consentito: 5 MB'
                ]
            ], 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            editor_json_response([
                'error' => [
                    'message' => 'Formato immagine non consentito. Usa JPG, PNG, GIF o WEBP'
                ]
            ], 400);
        }

        $imageInfo = @getimagesize($tmpPath);

        if ($imageInfo === false) {
            editor_json_response([
                'error' => [
                    'message' => 'Il file non sembra essere una vera immagine'
                ]
            ], 400);
        }

        if (!is_dir(UPLOAD_ROOT)) {
            if (!mkdir(UPLOAD_ROOT, 0755, true)) {
                editor_json_response([
                    'error' => [
                        'message' => 'Impossibile creare la cartella immagini'
                    ]
                ], 500);
            }
        }

        if (!is_writable(UPLOAD_ROOT)) {
            editor_json_response([
                'error' => [
                    'message' => 'La cartella immagini non è scrivibile da PHP'
                ]
            ], 500);
        }

        $extension = $allowed[$mime];

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = editor_slug_filename($baseName);

        $random = bin2hex(random_bytes(6));
        $finalName = $baseName . '-' . date('Ymd-His') . '-' . $random . '.' . $extension;

        $destination = UPLOAD_ROOT . '/' . $finalName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            editor_json_response([
                'error' => [
                    'message' => 'Impossibile salvare immagine sul server'
                ]
            ], 500);
        }

        $url = UPLOAD_URL_PREFIX . '/' . $finalName;

        editor_json_response([
            'url' => $url
        ]);
    }

    /**
     * 🆕 Crea nuovo capitolo
     */
    if ($action === 'create-chapter') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            editor_json_response(['error' => 'Metodo non consentito'], 405);
        }

        editor_verify_csrf();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            throw new RuntimeException('Impossibile leggere il corpo della richiesta');
        }

        $payload = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            editor_json_response(['error' => 'JSON richiesta non valido'], 400);
        }

        $id = $payload['id'] ?? '';
        $title = $payload['title'] ?? '';
        $position = $payload['position'] ?? 'end';
        $html = $payload['html'] ?? '<h1>' . htmlspecialchars($title) . '</h1><p>Contenuto del nuovo capitolo...</p>';

        if ($id === '' || $title === '') {
            editor_json_response(['error' => 'ID e titolo richiesti'], 400);
        }

        if (!preg_match('/^[a-z0-9-]+$/', $id)) {
            editor_json_response(['error' => 'ID può contenere solo lettere minuscole, numeri e trattini'], 400);
        }

        $manifest = editor_load_manifest();

        // Verifica ID univoco
        foreach ($manifest['sections'] as $section) {
            if ($section['id'] === $id) {
                editor_json_response(['error' => 'ID già esistente'], 400);
            }
        }

        // Crea nuova sezione
        $newSection = [
            'id' => $id,
            'title' => $title,
            'file' => "sections/{$id}.html"
        ];

        // Inserisci nella posizione corretta
        if ($position === 'end') {
            $manifest['sections'][] = $newSection;
        } else {
            $pos = intval($position);
            if ($pos >= 0 && $pos <= count($manifest['sections'])) {
                array_splice($manifest['sections'], $pos, 0, [$newSection]);
            } else {
                $manifest['sections'][] = $newSection;
            }
        }

        // Salva manifest
        $manifestPath = __DIR__ . '/manual.json';
        if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare manifest');
        }

        // Crea file HTML
        $sectionPath = editor_resolve_section_path($newSection['file']);
        $sectionDir = dirname($sectionPath);
        
        if (!is_dir($sectionDir)) {
            if (!mkdir($sectionDir, 0755, true)) {
                throw new RuntimeException('Impossibile creare directory sections/');
            }
        }

        if (file_put_contents($sectionPath, $html, LOCK_EX) === false) {
            throw new RuntimeException('Impossibile creare file HTML');
        }

        editor_json_response(['success' => true, 'message' => 'Capitolo creato']);
    }

    /**
     * 🆕 Rinomina capitolo
     */
    if ($action === 'rename-chapter') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            editor_json_response(['error' => 'Metodo non consentito'], 405);
        }

        editor_verify_csrf();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            throw new RuntimeException('Impossibile leggere il corpo della richiesta');
        }

        $payload = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            editor_json_response(['error' => 'JSON richiesta non valido'], 400);
        }

        $id = $payload['id'] ?? '';
        $newTitle = $payload['title'] ?? '';

        if ($id === '' || $newTitle === '') {
            editor_json_response(['error' => 'ID e nuovo titolo richiesti'], 400);
        }

        $manifest = editor_load_manifest();

        // Trova e aggiorna sezione
        $found = false;
        foreach ($manifest['sections'] as &$section) {
            if ($section['id'] === $id) {
                $section['title'] = $newTitle;
                $found = true;
                break;
            }
        }

        if (!$found) {
            editor_json_response(['error' => 'Sezione non trovata'], 404);
        }

        // Salva manifest
        $manifestPath = __DIR__ . '/manual.json';
        if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare manifest');
        }

        editor_json_response(['success' => true, 'message' => 'Capitolo rinominato']);
    }

    /**
     * 🆕 Elimina capitolo
     */
    if ($action === 'delete-chapter') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            editor_json_response(['error' => 'Metodo non consentito'], 405);
        }

        editor_verify_csrf();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            throw new RuntimeException('Impossibile leggere il corpo della richiesta');
        }

        $payload = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            editor_json_response(['error' => 'JSON richiesta non valido'], 400);
        }

        $id = $payload['id'] ?? '';

        if ($id === '') {
            editor_json_response(['error' => 'ID richiesto'], 400);
        }

        $manifest = editor_load_manifest();

        // Trova e rimuovi sezione
        $removedSection = null;
        $manifest['sections'] = array_filter($manifest['sections'], function($section) use ($id, &$removedSection) {
            if ($section['id'] === $id) {
                $removedSection = $section;
                return false;
            }
            return true;
        });

        if (!$removedSection) {
            editor_json_response(['error' => 'Sezione non trovata'], 404);
        }

        // Reindicizza array
        $manifest['sections'] = array_values($manifest['sections']);

        // Salva manifest
        $manifestPath = __DIR__ . '/manual.json';
        if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare manifest');
        }

        // Sposta file HTML in backup
        $sectionPath = editor_resolve_section_path($removedSection['file']);
        if (file_exists($sectionPath)) {
            if (!is_dir(BACKUP_ROOT)) {
                mkdir(BACKUP_ROOT, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);
            $backupFile = "deleted_{$safeId}_{$timestamp}.html";
            $backupPath = BACKUP_ROOT . '/' . $backupFile;
            
            rename($sectionPath, $backupPath);
        }

        editor_json_response(['success' => true, 'message' => 'Capitolo eliminato']);
    }

    /**
     * 🆕 Sposta capitolo
     */
    if ($action === 'move-chapter') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            editor_json_response(['error' => 'Metodo non consentito'], 405);
        }

        editor_verify_csrf();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            throw new RuntimeException('Impossibile leggere il corpo della richiesta');
        }

        $payload = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            editor_json_response(['error' => 'JSON richiesta non valido'], 400);
        }

        $id = $payload['id'] ?? '';
        $direction = intval($payload['direction'] ?? 0);

        if ($id === '' || $direction === 0) {
            editor_json_response(['error' => 'ID e direzione richiesti'], 400);
        }

        $manifest = editor_load_manifest();
        $sections = $manifest['sections'];

        // Trova indice corrente
        $currentIndex = -1;
        foreach ($sections as $index => $section) {
            if ($section['id'] === $id) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === -1) {
            editor_json_response(['error' => 'Sezione non trovata'], 404);
        }

        $newIndex = $currentIndex + $direction;

        // Verifica limiti
        if ($newIndex < 0 || $newIndex >= count($sections)) {
            editor_json_response(['error' => 'Movimento non possibile'], 400);
        }

        // Scambia posizioni
        $temp = $sections[$currentIndex];
        $sections[$currentIndex] = $sections[$newIndex];
        $sections[$newIndex] = $temp;

        $manifest['sections'] = $sections;

        // Salva manifest
        $manifestPath = __DIR__ . '/manual.json';
        if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare manifest');
        }

        editor_json_response(['success' => true, 'message' => 'Capitolo spostato']);
    }

    editor_json_response(['error' => 'Azione non riconosciuta'], 400);

} catch (Throwable $e) {
    if ($action === 'upload-image') {
        editor_json_response([
            'error' => [
                'message' => $e->getMessage()
            ]
        ], 500);
    }

    editor_json_response([
        'error' => $e->getMessage(),
    ], 500);
}