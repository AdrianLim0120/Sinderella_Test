<?php
// /config/wa_config.php
// Keep this file OUT of your public repo. Rotate your token if it was ever public.
// Daily send window (MYT). Job runs only inside this window unless &force=1.
const WINDOW_START_MIN  = 2*60;  // 02:00
const WINDOW_END_MIN    = 2*60+10;  // 02:10

date_default_timezone_set('Asia/Kuala_Lumpur');

const HEARTBEAT_URL = 'https://uptime.betterstack.com/api/v1/heartbeat/KkzCR1fWprTofnGRyZKyzN9A';

const WA_GRAPH_API_VERSION = 'v20.0'; // use current Graph version
const WA_GRAPH_API_BASE    = 'https://graph.facebook.com/' . WA_GRAPH_API_VERSION . '/';
const WA_PHONE_NUMBER_ID   = '771566906040670';
const WA_ACCESS_TOKEN      = 'EAAUOcrtW2l8BPV8Bb31qH3K16WWOyVzwlfFb3gDVlLhZAuL4Hv29zGLIZAswLtPlYdFaHGDXWAR6JicQrKE2zmhuxBglDNTIctuApC8dVhZA2okdrTrxpwU7ZCjZAZAFKOaUoNU0C2ZBqjCOiGZBJDO2ZBZAjb2j0qZAfGyInstQrrYRtqbT7bbpBpP7x2rd8IgxzVQmAZDZD';   // <-- put your token here (NOT in GitHub)

const WA_TEMPLATE_ID       = '';
const WA_TEMPLATE_NAME     = 'booking';                      // your approved template name
const WA_LANG_CODE         = 'en';                        // match your template’s language

// Security for triggering cron via URL
const CRON_HTTP_KEY        = 'sinderella-run-123';           // change this
const INTERNAL_KEY         = 'sinderella-internal-456';      // change this

// Admin recipients (E.164 without "+"): 60XXXXXXXXX
const ADMIN_NUMBERS = [
  '60169673981' // add more if you have multiple admins
];

function bs_ping_heartbeat(bool $ok): void {
  if (!HEARTBEAT_URL) return;
  $url = HEARTBEAT_URL . ($ok ? '' : '/fail');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT_MS => 1200,
    CURLOPT_TIMEOUT_MS => 1800,
  ]);
  @curl_exec($ch);
  @curl_close($ch);
}

// ---------- Helpers ----------

/** convert any phone-ish input to digits only E.164 without '+' (Cloud API format) */
function wa_digits(string $n, string $defaultCc = '60'): string {
  $d = preg_replace('/\D+/', '', $n);
  if ($d === '') return '';
  // if starts with 0, assume local MY and replace leading 0 with country code
  if ($d[0] === '0') $d = $defaultCc . substr($d, 1);
  return $d;
}

/** params -> component payload for WA template body */
function wa_body_components(array $placeholders): array {
  $params = [];
  foreach ($placeholders as $p) {
    $params[] = ['type' => 'text', 'text' => (string)$p];
  }
  return [['type' => 'body', 'parameters' => $params]];
}

/**
 * Send a template message using either template ID or template name.
 * $placeholders: ordered list that matches your template {{1}}, {{2}}, ...
 */
function wa_send_template(string $toDigits, array $placeholders, ?string $lang = WA_LANG_CODE): array {
  $url = WA_GRAPH_API_BASE . WA_PHONE_NUMBER_ID . '/messages';

  $template = [
    'language'   => ['code' => $lang],
    'components' => wa_body_components($placeholders),
  ];
  if (!empty(WA_TEMPLATE_ID)) {
    $template['id']   = WA_TEMPLATE_ID;           // send by ID
  } else {
    $template['name'] = WA_TEMPLATE_NAME;         // send by NAME
  }

  $payload = [
    'messaging_product' => 'whatsapp',
    'to'       => $toDigits,                       // e.g., "60123456789"
    'type'     => 'template',
    'template' => $template,
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . WA_ACCESS_TOKEN,
    ],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 45,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return ['http_code' => $code, 'error' => $err, 'raw' => $resp, 'payload' => $payload];
}

/** Ping Better Stack heartbeat (success or fail) */
function heartbeat_ping(bool $ok): void {
  if (!HEARTBEAT_URL) return;
  $url = HEARTBEAT_URL . ($ok ? '' : '/fail');
  @file_get_contents($url);
}