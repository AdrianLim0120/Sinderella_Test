<?php
// /config/wa_config.php
// Keep this file OUT of your public repo. Rotate your token if it was ever public.
// Daily send window (MYT). Job runs only inside this window unless &force=1.
const DAILY_WINDOW_START = 0115;  // 01:10
const DAILY_WINDOW_END   = 0117;  // 01:15

date_default_timezone_set('Asia/Kuala_Lumpur');

const WA_GRAPH_API_VERSION = 'v20.0'; // use current Graph version
const WA_GRAPH_API_BASE    = 'https://graph.facebook.com/' . WA_GRAPH_API_VERSION . '/';

const WA_PHONE_NUMBER_ID   = '771566906040670';
const WA_ACCESS_TOKEN      = 'EAAUOcrtW2l8BPV8Bb31qH3K16WWOyVzwlfFb3gDVlLhZAuL4Hv29zGLIZAswLtPlYdFaHGDXWAR6JicQrKE2zmhuxBglDNTIctuApC8dVhZA2okdrTrxpwU7ZCjZAZAFKOaUoNU0C2ZBqjCOiGZBJDO2ZBZAjb2j0qZAfGyInstQrrYRtqbT7bbpBpP7x2rd8IgxzVQmAZDZD';   // <-- put your token here (NOT in GitHub)
const WA_TEMPLATE_BOOKING  = 'booking';                      // your approved template name
const WA_LANG_CODE         = 'en';                        // match your template’s language

// Security for triggering cron via URL
const CRON_HTTP_KEY        = 'sinderella-run-123';           // change this
const INTERNAL_KEY         = 'sinderella-internal-456';      // change this

// Admin recipients (E.164 without "+"): 60XXXXXXXXX
const ADMIN_NUMBERS = [
  '60169673981' // add more if you have multiple admins
];

// --- helpers ---
function wa_normalize_msisdn($raw, $cc = '60') {
  $d = preg_replace('/\D+/', '', (string)$raw);
  if ($d === '') return '';
  if (strpos($d, $cc) === 0) return $d;        // already starts with country code
  if ($d[0] === '0') return $cc . substr($d,1); // strip leading 0 -> prepend country code
  return $d;                                   // assume already in international format (no "+")
}
function wa_pretty_msisdn($digits) { return '+' . preg_replace('/\D+/', '', $digits); }

function wa_send_template(string $to, string $templateName, array $bodyParams, string $lang = WA_LANG_CODE): array {
  $url = WA_GRAPH_API_BASE . rawurlencode(WA_PHONE_NUMBER_ID) . '/messages';
  $components = [
    [
      'type' => 'body',
      'parameters' => array_map(fn($t) => ['type' => 'text', 'text' => (string)$t], $bodyParams)
    ]
  ];
  $payload = [
    'messaging_product' => 'whatsapp',
    'to' => $to,
    'type' => 'template',
    'template' => [
      'name' => $templateName,
      'language' => [ 'code' => $lang ],
      'components' => $components
    ]
  ];
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . WA_ACCESS_TOKEN
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['http_code'=>$code, 'error'=>$err, 'raw'=>$resp, 'payload'=>$payload];
}
