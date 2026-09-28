<?php
/**
 * Front controller bootstrap — every public page, admin page and API endpoint
 * starts by requiring this file.
 *
 * Pages in the admin area define ADMIN_AREA before requiring it.
 */

$GLOBALS['__config'] = require __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/components.php';
if (defined('ADMIN_AREA')) {
    require_once __DIR__ . '/../admin/partials/layout.php';
}

date_default_timezone_set(config('app.timezone', 'UTC'));

// Error handling: detailed in development, generic (but logged) in production.
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e) {
    error_log('[KCPM] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_ajax()) {
        json_response(['ok' => false, 'message' => config('app.debug') ? $e->getMessage() : 'A server error occurred. Please try again.'], 500);
    }
    $message = config('app.debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Something went wrong on our side. Please try again shortly.';
    render_error_page(500, 'Server Error', $message);
});

// Autoload models and controllers by class name.
spl_autoload_register(function (string $class) {
    foreach (['models', 'controllers'] as $dir) {
        $file = APP_ROOT . "/$dir/$class.php";
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

send_security_headers();
start_secure_session();

// Every state-changing request must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('SKIP_CSRF')) {
    verify_csrf();
}

// Maintenance mode hides the public site from visitors (staff can still sign in).
if (!defined('ADMIN_AREA') && PHP_SAPI !== 'cli' && setting('maintenance_mode') === '1' && !current_user()) {
    render_error_page(503, 'Under Maintenance', 'Our website is being updated. Please check back soon. God bless you!');
}
