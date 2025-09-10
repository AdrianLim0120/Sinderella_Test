<?php
// /cron/lib/wa.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/whatsapp.php';

/**
 * Normalize Malaysian mobile numbers to E.164 without '+'
 * Accepts inputs like '011-234 56789', '+601123456789', '01123456789'
 */
function format_msisdn(?string $raw): ?string {
    if (!$raw) return null;
    $n = preg_replace('/\D+/', '', $raw); // keep digits only
    // If it starts with '0', assume MY and replace leading 0 with '60'
    if (preg_match('/^0\d+$/', $n)) {
        $n = '60' . substr($n, 1);
    }
    // If it starts with '60...' already, leave it.
    if (!preg_match('/^6\d{9,12}$/', $n)) {
        return null; // fail formatting
    }
    return $n;
}

function fmt_time($hms)
{
    return substr((string) $hms, 0, 5);
}      // 15:00:00 -> 15:00
function time_range($from, $to)
{
    return fmt_time($from) . ' - ' . fmt_time($to);
}

/**
 * Send a WhatsApp template message
 * $to: string E.164 without '+'
 * $components: WhatsApp template "components" array
 */
function wa_send_template(string $to, string $templateName, string $langCode, array $components = []): array {
    $url  = WA_GRAPH_API_BASE . WA_PHONE_NUMBER_ID . '/messages';
    $body = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'      => $templateName,
            'language'  => ['code' => $langCode],
            'components'=> $components,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . WA_ACCESS_TOKEN,
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($resp ?? '', true);
    $ok   = ($err === '' && $code >= 200 && $code < 300 && isset($json['messages'][0]['id']));
    return [
        'ok'        => $ok,
        'http_code' => $code,
        'error'     => $err,
        'response'  => $json ?: $resp,
        'to'        => $to,
    ];
}

/**
 * Build the template "body" parameters for your `booking` template.
 * Adjust the order/count to match your template placeholders.
 *
 * Template body example (you likely configured something like):
 *  {{1}} Service Date
 *  {{2}} From Time
 *  {{3}} To Time
 *  {{4}} Sinderella Name
 *  {{5}} Sinderella Phone
 *  {{6}} Customer Name
 *  {{7}} Customer Phone
 *  {{8}} Address
 */
function build_booking_components(array $row): array {
    $serviceDate = date('Y-m-d', strtotime($row['booking_date']));
    $fromTime    = fmt_time($row['booking_from_time']);
    $toTime      = fmt_time($row['booking_to_time']);
    $serviceTime = time_range($fromTime, $toTime);

    $params = [
        ['type' => 'text', 'text' => $serviceDate],
        ['type' => 'text', 'text' => $serviceTime],
        ['type' => 'text', 'text' => $row['sind_name'] ?? ''],
        ['type' => 'text', 'text' => format_msisdn($row['sind_phno']) ?: ($row['sind_phno'] ?? '')],
        ['type' => 'text', 'text' => $row['cust_name'] ?? ''],
        ['type' => 'text', 'text' => format_msisdn($row['cust_phno']) ?: ($row['cust_phno'] ?? '')],
        ['type' => 'text', 'text' => $row['full_address'] ?? ''],
    ];

    return [[ 'type' => 'body', 'parameters' => $params ]];
}
