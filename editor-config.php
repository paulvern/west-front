<?php
// editor-config.php

declare(strict_types=1);

/**
 * Configurazione generale editor.
 */

session_name('fronte_editor_session');
session_start();

define('EDITOR_ROOT', __DIR__);

// Language-specific paths are now defined dynamically in api-editor.php
// via CURRENT_MANIFEST and CURRENT_SECTIONS_DIR constants
// These fallback defines are only used if the constants aren't already set
if (!defined('CURRENT_MANIFEST')) {
    define('CURRENT_MANIFEST', 'manual.json');
}
if (!defined('CURRENT_SECTIONS_DIR')) {
    define('CURRENT_SECTIONS_DIR', 'sections');
}

define('MANIFEST_PATH', EDITOR_ROOT . '/' . CURRENT_MANIFEST);
define('SECTIONS_ROOT', EDITOR_ROOT . '/' . CURRENT_SECTIONS_DIR);
define('BACKUP_ROOT', EDITOR_ROOT . '/backups');

define('UPLOAD_ROOT', EDITOR_ROOT . '/assets/img/manual');
define('UPLOAD_URL_PREFIX', 'assets/img/manual');

/**
 * CAMBIA QUESTO HASH.
 *
 * Genera l'hash con:
 *
 * php -r "echo password_hash('la-tua-password', PASSWORD_DEFAULT) . PHP_EOL;"
 *
 * Poi incolla qui il risultato.
 */
define('EDITOR_PASSWORD_HASH', 'YOUR PASSWORD');

/**
 * Verifica se l'utente è autenticato.
 */
function editor_is_logged_in(): bool
{
    return isset($_SESSION['editor_logged_in']) && $_SESSION['editor_logged_in'] === true;
}

/**
 * Richiede login per accedere alle API.
 */
function editor_require_login(): void
{
    if (!editor_is_logged_in()) {
        editor_json_response(['error' => 'Non autorizzato'], 401);
    }
}

/**
 * Crea o restituisce token CSRF.
 */
function editor_csrf_token(): string
{
    if (empty($_SESSION['editor_csrf'])) {
        $_SESSION['editor_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['editor_csrf'];
}

/**
 * Verifica token CSRF per POST.
 */
function editor_verify_csrf(): void
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!$headerToken || !hash_equals($_SESSION['editor_csrf'] ?? '', $headerToken)) {
        editor_json_response(['error' => 'Token CSRF non valido'], 403);
    }
}

/**
 * Compatibilità: evita di dipendere da str_contains.
 */
function editor_contains(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

/**
 * Compatibilità: evita di dipendere da str_starts_with.
 */
function editor_starts_with(string $haystack, string $needle): bool
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Risposta JSON standard.
 */
function editor_json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    exit;
}

/**
 * Carica manual.json.
 */
function editor_load_manifest(): array
{
    if (!is_file(MANIFEST_PATH)) {
        throw new RuntimeException('manual.json non trovato');
    }

    $raw = file_get_contents(MANIFEST_PATH);

    if ($raw === false) {
        throw new RuntimeException('Impossibile leggere manual.json');
    }

    $manifest = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('manual.json non valido: ' . json_last_error_msg());
    }

    if (!is_array($manifest)) {
        throw new RuntimeException('manual.json non contiene un oggetto valido');
    }

    if (!isset($manifest['sections']) || !is_array($manifest['sections'])) {
        throw new RuntimeException('manual.json non contiene sections[]');
    }

    return $manifest;
}

/**
 * Trova sezione da id.
 */
function editor_find_section(string $id): array
{
    $manifest = editor_load_manifest();

    foreach ($manifest['sections'] as $section) {
        if (($section['id'] ?? '') === $id) {
            return $section;
        }
    }

    throw new RuntimeException('Sezione non trovata');
}

/**
 * Risolve in sicurezza il percorso di una sezione.
 * Accetta solo file dentro la cartella sections/ (o sections_en/ per inglese).
 * Se il percorso non contiene già la directory, la aggiunge automaticamente.
 */
function editor_resolve_section_path(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);

    if ($relativePath === '' || editor_contains($relativePath, "\0")) {
        throw new RuntimeException('Percorso file non valido');
    }

    if (editor_starts_with($relativePath, '/') || editor_contains($relativePath, '..')) {
        throw new RuntimeException('Percorso file non consentito');
    }

    // Se il percorso non inizia già con una directory sections, aggiungila
    if (!editor_starts_with($relativePath, 'sections')) {
        $relativePath = CURRENT_SECTIONS_DIR . '/' . $relativePath;
    }

    $fullPath = realpath(EDITOR_ROOT . '/' . $relativePath);
    $sectionsRoot = realpath(SECTIONS_ROOT);

    if ($fullPath === false) {
        throw new RuntimeException('File sezione non trovato: ' . $relativePath);
    }

    if ($sectionsRoot === false) {
        throw new RuntimeException('Cartella sections/ non trovata');
    }

    $sectionsRootWithSlash = rtrim($sectionsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!editor_starts_with($fullPath, $sectionsRootWithSlash)) {
        throw new RuntimeException('Il file non è dentro la cartella sections/');
    }

    if (!is_file($fullPath)) {
        throw new RuntimeException('Il percorso non è un file valido');
    }

    return $fullPath;
}

/**
 * Restituisce nome file sicuro.
 */
function editor_slug_filename(string $name): string
{
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9_-]+/', '-', $name);
    $name = trim((string) $name, '-');

    return $name !== '' ? $name : 'file';
}
