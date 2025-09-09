<?php
// /cron/remind_2days.php
// Finds bookings with service_date = today+2 and sends the approved template to admin, customer, and Sinderella.
error_reporting(E_ALL);
ini_set('display_errors', 0); // keep output clean (use &debug=1 to see details)

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../config/wa_config.php';

header('Content-Type: application/json');

function fmt_date($ymd)
{
    return (string) $ymd;
}                    // tweak if you want pretty date
function fmt_time($hms)
{
    return substr((string) $hms, 0, 5);
}      // 15:00:00 -> 15:00
function time_range($from, $to)
{
    return fmt_time($from) . ' - ' . fmt_time($to);
}

$tz = new DateTimeZone('Asia/Kuala_Lumpur');
$now = new DateTime('now', $tz);
$force = (int) ($_GET['force'] ?? 0);
$debug = (int) ($_GET['debug'] ?? 0);

// Optional guard so people don't hit this directly in production without the internal key.
if (PHP_SAPI !== 'cli') {
    $ik = $_GET['internal_key'] ?? '';
    if ($ik !== INTERNAL_KEY && !$force) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

// ===== Time gate to the configured window (unless force=1) =====
if (!$force) {
  $hhmm = (int)$now->format('Hi'); // e.g., 2324

  $inWindow = false;
  if (DAILY_WINDOW_START <= DAILY_WINDOW_END) {
    // normal window, e.g., 2320–2330
    $inWindow = ($hhmm >= DAILY_WINDOW_START && $hhmm <= DAILY_WINDOW_END);
  } else {
    // cross-midnight window support, e.g., 2358–0005
    $inWindow = ($hhmm >= DAILY_WINDOW_START || $hhmm <= DAILY_WINDOW_END);
  }

  if (!$inWindow) {
    echo json_encode([
      'ok' => true,
      'skipped' => 'outside_window',
      'server_time' => $now->format('c'),
      'window' => ['start' => DAILY_WINDOW_START, 'end' => DAILY_WINDOW_END]
    ]);
    exit;
  }
}

@mkdir(__DIR__ . '/../logs', 0775, true);
$marker = __DIR__ . '/../logs/daily_reminder_marker.json';
$lockfp = fopen(__DIR__ . '/../logs/daily_reminder.lock', 'c');
if ($lockfp && !flock($lockfp, LOCK_EX | LOCK_NB)) {
    echo json_encode(['ok' => true, 'skipped' => 'already_running']);
    exit;
}
$today = $now->format('Y-m-d');
try {
    $last = is_file($marker) ? json_decode((string) file_get_contents($marker), true) : [];
    if (($last['date'] ?? '') === $today && !$force) {
        echo json_encode(['ok' => true, 'skipped' => 'already_ran_today']);
        if ($lockfp) {
            flock($lockfp, LOCK_UN);
            fclose($lockfp);
        }
        exit;
    }
} catch (\Throwable $e) { /* ignore */
}

// ===== Target date (today + 2) or override with ?date=YYYY-MM-DD =====
$targetDate = $_GET['date'] ?? (new DateTime('now', $tz))->modify('+2 days')->format('Y-m-d');

// ---- QUERY YOUR DATA ----
// Assumed schema based on your repo; adjust table/column names if needed.
$sql = "
SELECT
      b.booking_id, b.booking_date, b.booking_from_time, b.booking_to_time, b.full_address,
      si.sind_name, si.sind_phno,
      c.cust_name, c.cust_phno
    FROM bookings b
    JOIN sinderellas si ON si.sind_id = b.sind_id
    JOIN customers  c  ON c.cust_id   = b.cust_id
    WHERE b.booking_status = 'confirm'
      AND b.booking_date   = ?
";
$debugInfo = ['target_date' => $targetDate, 'rows' => [], 'recipients' => []];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $targetDate);
    $stmt->execute();
    $res = $stmt->get_result();

    $sentCount = 0;
    $log = [];
    while ($row = $res->fetch_assoc()) {
        $serviceDateText = fmt_date($row['booking_date']);
        $serviceTimeText = time_range($row['booking_from_time'], $row['booking_to_time']);

        $sindName = $row['sind_name'] ?? '';
        $sindMsisdn = wa_normalize_msisdn($row['sind_phno'] ?? '');
        $sindPretty = wa_pretty_msisdn($sindMsisdn);

        $custName = $row['cust_name'] ?? '';
        $custMsisdn = wa_normalize_msisdn($row['cust_phno'] ?? '');
        $custPretty = wa_pretty_msisdn($custMsisdn);

        $addr = $row['full_address'] ?? '';

        $params = [
            $serviceDateText,
            $serviceTimeText,
            $sindName,
            $sindPretty,
            $custName,
            $custPretty,
            $addr
        ];

        // Recipients: customer, sinderella, and all admins
        $recipients = [];
        if ($custMsisdn)
            $recipients[] = $custMsisdn;
        if ($sindMsisdn)
            $recipients[] = $sindMsisdn;
        foreach (ADMIN_NUMBERS as $adm)
            $recipients[] = wa_normalize_msisdn($adm);

        // de-duplicate
        $recipients = array_values(array_unique(array_filter($recipients)));

        if ($debug) {
            $debugInfo['rows'][] = [
                'booking_id' => $row['booking_id'],
                'sinderella' => $sindName,
                'sinderella_msisdn' => $sindMsisdn,
                'customer' => $custName,
                'customer_msisdn' => $custMsisdn,
                'address' => $addr,
                'params' => $params
            ];
            $debugInfo['recipients'][] = $recipients;
        }

        foreach ($recipients as $to) {
            $r = wa_send_template($to, WA_TEMPLATE_BOOKING, $params, WA_LANG_CODE);
            $sentCount++;

            $log[] = [
                'booking_id' => $row['booking_id'],
                'to' => $to,
                'http_code' => $r['http_code'],
                'resp' => $r['raw'],
            ];

            // gentle pacing
            usleep(150000); // 0.15s
        }
    }
    file_put_contents($marker, json_encode([
        'date' => $today,
        'ran_at' => (new DateTime('now', $tz))->format('c'),
        'sent' => $sentCount
    ]));

    @file_put_contents(
        __DIR__ . '/../logs/wa_' . date('Ymd_Hi') . '.log',
        json_encode(['target_date' => $targetDate, 'sent' => $sentCount, 'rows' => $log], JSON_PRETTY_PRINT) . PHP_EOL,
        FILE_APPEND
    );

    $out = ['ok' => true, 'target_date' => $targetDate, 'messages_sent' => $sentCount];
    if ($debug)
        $out['debug'] = $debugInfo;
    echo json_encode($out);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'sql' => $sql]);
} finally {
    if (isset($stmt) && $stmt)
        $stmt->close();
    if ($lockfp) {
        flock($lockfp, LOCK_UN);
        fclose($lockfp);
    }
}