<?php
/**
 * FCE Writing Trainer — Configuration
 * Database connection + Mustache engine setup
 */

// ── Database Settings ──────────────────────────────────────────
// Change these values for your environment
define('DB_HOST', 'localhost');          // InfinityFree: sql###.infinityfree.com
define('DB_NAME', 'fce_writing');        // InfinityFree: epiz_XXXXXXXX_fce
define('DB_USER', 'root');              // InfinityFree: epiz_XXXXXXXX
define('DB_PASS', '');                  // InfinityFree: your password
define('DB_CHARSET', 'utf8mb4');

// ── Database Connection ────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// ── Mustache Engine ────────────────────────────────────────────
require_once __DIR__ . '/vendor/Mustache/Engine.php';

// Register autoloader for Mustache classes
spl_autoload_register(function ($class) {
    // Convert Mustache_Loader_FilesystemLoader → vendor/Mustache/Loader/FilesystemLoader.php
    if (strpos($class, 'Mustache_') === 0) {
        $path = __DIR__ . '/vendor/' . str_replace('_', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

$mustache = new Mustache_Engine([
    'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates', [
        'extension' => '.mustache',
    ]),
    'partials_loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates', [
        'extension' => '.mustache',
    ]),
    'escape' => function ($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    },
]);

// ── Helper Functions ───────────────────────────────────────────

/**
 * Render a page inside the layout template
 */
function render($mustache, $template, $data = []) {
    $data['current_page'] = $template;
    $data['is_' . $template] = true;

    $content = $mustache->render($template, $data);
    
    echo $mustache->render('layout', [
        'content'      => $content,
        'page_title'   => $data['page_title'] ?? 'FCE Writing Trainer',
        'current_page' => $template,
        'is_home'      => $template === 'home',
        'is_models'    => $template === 'models',
        'is_session'   => $template === 'session',
        'is_progress'  => $template === 'progress',
        'is_language'  => $template === 'language',
    ]);
}

/**
 * Format seconds into mm:ss
 */
function formatTime($seconds) {
    if ($seconds === null) return '--:--';
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d', $m, $s);
}

/**
 * Get type color class
 */
function getTypeColor($type) {
    $colors = [
        'essay'   => '#7c3aed',
        'email'   => '#3b82f6',
        'article' => '#10b981',
        'review'  => '#f59e0b',
        'report'  => '#ec4899',
    ];
    return $colors[$type] ?? '#6b7280';
}

/**
 * Get type emoji
 */
function getTypeEmoji($type) {
    $emojis = [
        'essay'   => '📝',
        'email'   => '📧',
        'article' => '📰',
        'review'  => '⭐',
        'report'  => '📊',
    ];
    return $emojis[$type] ?? '📄';
}
