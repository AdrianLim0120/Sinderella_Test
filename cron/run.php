<?php
/**
 * /cron/run.php
 * Monitor target for Better Stack (or any external cron). Fast-ACKs, checks time window,
 * runs remind_2days.php once per day, and pings the heartbeat.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/wa_config.php';

// ---- 1) Lightweight auth for external monitor
if (($_GET['key'] ?? '') !== CRON_HTTP_KEY) {
  http_response_code(403);
  header('Cache-Control: no-store');
  echo 'forbidden';
  exit;
}

// ---- 2) Fast ACK so your monitor stays green immediately
ignore_user_abort(true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Content-Type: application/json');
echo json_encode(['status' => 'accepted', 'ts' => date('c')]);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
flush();

// ---- 3) Decide whether to run now
$force   = isset($_GET['force']);
$nowMin  = (int)date('G') * 60 + (int)date('i');        // minutes since midnight
$inWindow = (WINDOW_START_MIN <= WINDOW_END_MIN)
  ? ($nowMin >= WINDOW_START_MIN && $nowMin <= WINDOW_END_MIN)
  : ($nowMin >= WINDOW_START_MIN || $nowMin <= WINDOW_END_MIN); // supports windows across midnight

if (!$force && !$inWindow) {
  // outside send window – do nothing
  exit;
}

// ---- 4) Simple once-per-day lock (unless &force=1)
@mkdir(__DIR__ . '/../logs', 0775, true);
$lockFile = __DIR__ . '/../logs/.daily-reminder-' . date('Y-m-d');
if (!$force && file_exists($lockFile)) {
  exit;
}

// ---- 5) Run the worker and heartbeat
$ok = false;
try {
  // pass internal auth + optional ?date=YYYY-MM-DD through to worker
  $_GET['internal_key'] = INTERNAL_KEY;
  if (isset($_GET['date'])) {
      // 'date' parameter is set, no action needed
  }
  $_GET['force'] = 1;

  // include executes the file; make it return true on success
  $ok = (bool) (require __DIR__ . '/remind_2days.php');

  if ($ok) {
    @touch($lockFile);
  }
} catch (\Throwable $e) {
  $ok = false;
} finally {
  heartbeat_ping($ok);
}
