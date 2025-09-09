<?php
// /cron/run.php — quick ACK + run the worker (remind_2days.php)

require_once __DIR__ . '/../config/wa_config.php';

// validate key
if (($_GET['key'] ?? '') !== CRON_HTTP_KEY) {
  http_response_code(403);
  header('Cache-Control: no-store');
  echo 'forbidden';
  exit;
}

// --- optional: record that Better Stack pinged us ---
@mkdir(__DIR__ . '/logs', 0775, true);
$qs = $_SERVER['QUERY_STRING'] ?? '';
@file_put_contents(__DIR__ . '/logs/ping.log',
  '['.date('c')."] run.php hit QS={$qs}\n",
  FILE_APPEND
);

// prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// send quick response
ignore_user_abort(true);
ob_start();
header('Content-Type: application/json');
echo json_encode(['status'=>'accepted','ts'=>date('c')]);
$length = ob_get_length();
header('Content-Length: ' . $length);
ob_end_flush();
flush();

// hand off to the worker (pass through params if present)
$_GET['internal_key'] = INTERNAL_KEY;              // authorize internal call
require __DIR__ . '/remind_2days.php';             // this will respect the DAILY_WINDOW_* gate
