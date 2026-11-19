<?php
declare(strict_types=1);

set_exception_handler(function (Throwable $e): void {
    $msg = sprintf(
        "[GSSF Error] %s in %s:%d\n%s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @error_log($msg);
    @file_put_contents(sys_get_temp_dir() . '/gssf_error.log', date('[Y-m-d H:i:s] ') . $msg . "\n\n", FILE_APPEND);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $safeMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Application Error</title>";
    echo "<style>body{font-family:system-ui,sans-serif;background:#f6f5f1;color:#18181b;padding:40px 20px;}";
    echo ".box{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e2dfd7;border-radius:8px;padding:24px;}";
    echo "h1{margin:0 0 10px;font-size:1.25rem;color:#be123c;}p{margin:0 0 12px;line-height:1.5;}";
    echo "code{background:#faf9f6;border:1px solid #e2dfd7;padding:2px 6px;border-radius:4px;font-size:0.9rem;}</style></head><body>";
    echo "<div class='box'><h1>Application Error (500)</h1>";
    echo "<p><strong>Details:</strong> {$safeMsg}</p>";
    echo "<p>Check <code>" . htmlspecialchars(sys_get_temp_dir(), ENT_QUOTES) . "/gssf_error.log</code> for the full trace.</p></div></body></html>";
    exit;
});

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/layout.php';

$storage = new Storage($config);
$auth = new Auth($storage, $config);
