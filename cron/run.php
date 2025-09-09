<?php
declare(strict_types=1);

/**
 * /cron/run.php — synchronous runner for Better Stack monitor.
 * - Checks the HH:MM window from /config/wa_config.php
 * - Runs remind_2days.php once per day (unless &force=1)
 * - Pings Better Stack heartbeat on success/fail
 * - Returns a single JSON response
 */

require_once __DIR__ . '/../config/wa_config.php';

// ------- auth & headers -------
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_GET['key'] ?? '') !== CRON_HTTP_KEY) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden']);
  exit;
}

// ------- window check (HH:MM from config) -------
$force  = isset($_GET['force']);
$nowMin = (int)date('G') * 60 + (int)date('i');
$start  = window_start_min();   // from wa_config.php
$end    = window_end_min();     // from wa_config.php
$inWindow = ($start <= $end)
  ? ($nowMin >= $start && $nowMin <= $end)
  : ($nowMin >= $start || $nowMin <= $end);  // handles windows crossing midnight

if (!$force && !$inWindow) {
  echo json_encode([
    'ok'        => true,
    'status'    => 'skip_outside_window',
    'now'       => date('c'),
    'window'    => [WINDOW_START, WINDOW_END],
    'nowMin'    => $nowMin,
    'startMin'  => $start,
    'endMin'    => $end
  ]);
  exit;
}

// ------- once-per-day lock (avoid duplicate sends) -------
@mkdir(__DIR__ . '/../logs', 0775, true);
$lockFile = __DIR__ . '/../logs/.daily-reminder-' . date('Y-m-d');
if (!$force && file_exists($lockFile)) {
  echo json_encode(['ok' => true, 'status' => 'skip_already_ran_today']);
  exit;
}

// ------- run worker synchronously -------
@ignore_user_abort(true);
@ini_set('max_execution_time', '25');

// Pass internal auth + optional date down to the worker
$_GET['internal_key'] = INTERNAL_KEY;
if (isset($_GET['date'])) $_GET['date'] = $_GET['date'];
$_GET['force'] = 1;

// Capture worker output (so we don't send two JSON objects to the monitor)
ob_start();
$workerOk = false;
$workerPayload = null;
$err = null;

try {
  $workerOk = (bool)(require __DIR__ . '/remind_2days.php'); // this script echoes JSON
  $workerOutput = trim(ob_get_clean());
  // If the worker printed JSON, try to parse a few fields for the summary
  if ($workerOutput !== '') {
    $workerPayload = json_decode($workerOutput, true);
  }
  if ($workerOk) { @touch($lockFile); }
} catch (\Throwable $e) {
  ob_end_clean();
  $workerOk = false;
  $err = $e->getMessage();
}

// ------- heartbeat & final JSON -------
heartbeat_ping($workerOk);

echo json_encode([
  'ok'        => $workerOk,
  'status'    => $workerOk ? 'completed' : 'failed',
  'now'       => date('c'),
  'window'    => [WINDOW_START, WINDOW_END],
  'inWindow'  => $inWindow,
  'summary'   => [
    'count'        => $workerPayload['count']       ?? null,
    'sent_ok'      => $workerPayload['sent_ok']     ?? null,
    'sent_failed'  => $workerPayload['sent_failed'] ?? null,
    'target_date'  => $workerPayload['target_date'] ?? null,
  ],
  'error'     => $err,
], JSON_UNESCAPED_SLASHES);
