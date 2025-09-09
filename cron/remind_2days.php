<?php
// /cron/remind_2days.php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/wa_config.php';
require_once __DIR__ . '/../db_connect.php';  // expects $conn (mysqli)

header('Content-Type: application/json');

// ---- 0) internal auth (avoid public calls)
if (($_GET['internal_key'] ?? '') !== INTERNAL_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    return false;
}

$debug = isset($_GET['debug']);
$baseDate = $_GET['date'] ?? date('Y-m-d');                     // YYYY-MM-DD
$target = (new DateTimeImmutable($baseDate))->modify('+2 days')->format('Y-m-d');

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

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => $conn->error, 'sql' => $sql]);
    return false;
}
$stmt->bind_param('s', $target);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc())
    $rows[] = $row;
$stmt->close();

// ---- 2) Nothing to do?
if (count($rows) === 0) {
    echo json_encode(['ok' => true, 'count' => 0, 'target_date' => $target]);
    return true; // still success (no sends)
}

// ---- 3) Send messages
$results = [];
$success = 0;
$failed = 0;

foreach ($rows as $r) {
    // Build placeholders according to your Meta template ({{1}}..)
    // Example mapping:
    //   1: Service Date (dd/mm/yyyy)
    //   2: Service Time (HH:MM)
    //   3: Sinderella Name
    //   4: Sinderella Phone (pretty)
    //   5: Customer Name
    //   6: Customer Phone (pretty)
    //   7: Address
    $datePretty = fmt_date($r['booking_date']);
    $timePretty = time_range($r['booking_from_time'], $r['booking_to_time']);
    $sindName = $r['sind_name'];
    $sindPh = wa_digits((string) $r['sind_phno']);
    $sindPhPretty = '+' . $sindPh;
    $custName = $r['cust_name'];
    $custPh = wa_digits((string) $r['cust_phno']);
    $custPhPretty = '+' . $custPh;
    $addr = $r['full_address'] ?: '-';

    $placeholders = [
        $datePretty,
        $timePretty,
        $sindName,
        $sindPhPretty,
        $custName,
        $custPhPretty,
        $addr,
    ];

    // to: customer
    if ($custPh !== '') {
        $r1 = wa_send_template($custPh, $placeholders);
        $ok1 = ($r1['http_code'] >= 200 && $r1['http_code'] < 300);
        $ok1 ? $success++ : $failed++;
        $results[] = ['to' => $custPh, 'http_code' => $r1['http_code'], 'resp' => $r1['raw']];
    }

    // to: sinderella
    if ($sindPh !== '') {
        $r2 = wa_send_template($sindPh, $placeholders);
        $ok2 = ($r2['http_code'] >= 200 && $r2['http_code'] < 300);
        $ok2 ? $success++ : $failed++;
        $results[] = ['to' => $sindPh, 'http_code' => $r2['http_code'], 'resp' => $r2['raw']];
    }

    // optional: admins copy
    foreach (ADMIN_NUMBERS as $adminDigits) {
        if ($adminDigits === '')
            continue;
        $rA = wa_send_template($adminDigits, $placeholders);
        $okA = ($rA['http_code'] >= 200 && $rA['http_code'] < 300);
        $okA ? $success++ : $failed++;
        if ($debug)
            $results[] = ['to' => $adminDigits, 'http_code' => $rA['http_code'], 'resp' => $rA['raw']];
    }
}

$out = [
    'ok' => ($failed === 0),
    'count' => count($rows),
    'sent_ok' => $success,
    'sent_failed' => $failed,
    'target_date' => $target,
];
if ($debug)
    $out['results'] = $results;

echo json_encode($out);
return ($failed === 0);