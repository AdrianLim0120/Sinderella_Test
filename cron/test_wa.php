<?php
// /cron/test_wa.php
// Quick sanity check: sends your approved template to ?to=60XXXXXXXXX with sample params.

require_once __DIR__ . '/../config/wa_config.php';
header('Content-Type: application/json');

$to = preg_replace('/\D+/', '', $_GET['to'] ?? '');
if ($to === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Pass ?to=60XXXXXXXXX (digits only)']);
  exit;
}

// sample placeholders matching your approved template text
$params = [
  date('d/m/Y'),        // {{1}} Service Date
  '15:45',              // {{2}} Service Time
  'Sinderella One',     // {{3}} Sinderella
  '+60122287014',       // {{4}} Sinderella Phone (pretty)
  'Customer Test',      // {{5}} Name
  '+60125217014',       // {{6}} Phone (pretty)
  'Test Address'        // {{7}} Address
];

$r = wa_send_template($to, WA_TEMPLATE_BOOKING, $params, WA_LANG_CODE);
echo json_encode(['ok'=>true,'to'=>$to,'http_code'=>$r['http_code'],'resp'=>$r['raw'], 'payload'=>$r['payload']]);
