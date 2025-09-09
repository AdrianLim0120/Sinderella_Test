<?php
// /cron/run.php — quick ACK + background run of remind_2days.php

require_once __DIR__ . '/../config/wa_config.php';

// validate key
if (($_GET['key'] ?? '') !== CRON_HTTP_KEY) {
  http_response_code(403);
  header('Cache-Control: no-store');
  echo 'forbidden';
  exit;
}

// prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

ignore_user_abort(true);
ob_start();
header('Content-Type: application/json');
echo json_encode(['status'=>'accepted','ts'=>date('c')]);
$length = ob_get_length();
header('Content-Length: ' . $length);
ob_end_flush();
flush();

// hand off to the worker (supports ?date=YYYY-MM-DD &force=1 for testing)
$_GET['internal_key'] = INTERNAL_KEY;
require __DIR__ . '/remind_2days.php';
