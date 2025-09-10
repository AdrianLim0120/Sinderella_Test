<?php
// /config/whatsapp.php
declare(strict_types=1);

date_default_timezone_set('Asia/Kuala_Lumpur');

// ======= SET THESE (pull from env if you can) =======
const WA_GRAPH_API_VERSION = 'v20.0';
const WA_GRAPH_API_BASE    = 'https://graph.facebook.com/' . WA_GRAPH_API_VERSION . '/';
const WA_PHONE_NUMBER_ID   = '771566906040670';
const WA_ACCESS_TOKEN = 'EAAUOcrtW2l8BPV8Bb31qH3K16WWOyVzwlfFb3gDVlLhZAuL4Hv29zGLIZAswLtPlYdFaHGDXWAR6JicQrKE2zmhuxBglDNTIctuApC8dVhZA2okdrTrxpwU7ZCjZAZAFKOaUoNU0C2ZBqjCOiGZBJDO2ZBZAjb2j0qZAfGyInstQrrYRtqbT7bbpBpP7x2rd8IgxzVQmAZDZD';

const WA_TEMPLATE_NAME = 'booking'; // your approved template
const WA_LANG_CODE     = 'en';

// Security keys for HTTP endpoints
const CRON_HTTP_KEY    = 'sinderella-run-123';     // used by external callers (keep private)
const INTERNAL_KEY     = 'sinderella-internal-456';// used for internal chaining (optional)

// Admin numbers (E.164 WITHOUT “+”). Example: 60123456789
const ADMIN_NUMBERS = ['60169673981'];

// How many days ahead to remind
const REMIND_DAYS_AHEAD = 2;

// Safety: throttle sends slightly to avoid bursts (in microseconds)
const WHATSAPP_SEND_DELAY_US = 200_000; // 0.2s
