<?php
/**
 * /cron/test_wa.php
 * Manual test: sends your approved template to ?to=60XXXXXXXXX with sample params.
 * Usage: https://yourdomain/cron/test_wa.php?to=60123456789
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/wa_config.php';
header('Content-Type: application/json');

$to = wa_digits($_GET['to'] ?? '');
if ($to === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Pass ?to=60XXXXXXXXX']);
  exit;
}

// Sample placeholders that match your template’s variables:
$sample = [
  date('d/m/Y'),         // {{1}} Service Date
  '15:45',               // {{2}} Service Time
  'Sinderella One',      // {{3}} Sinderella
  '+60123456789',        // {{4}} Sinderella Phone (pretty)
  'Customer Test',       // {{5}} Name
  '+60123456789',        // {{6}} Customer Phone (pretty)
  'Test Address',        // {{7}} Address
];

$r = wa_send_template($to, $sample);
echo json_encode(['ok' => ($r['http_code'] >= 200 && $r['http_code'] < 300), 'http_code' => $r['http_code'], 'resp' => $r['raw'], 'payload' => $r['payload']]);
