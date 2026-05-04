<?php
// =============================================================
// CONFIG — UZUPEŁNIJ SWOJE DANE
// =============================================================
$TELEGRAM_BOT_TOKEN  = 'WSTAW_TUTAJ_TOKEN_BOTA';
$TELEGRAM_CHAT_ID    = 'WSTAW_TUTAJ_CHAT_ID';
$IPIFY_API_KEY       = 'WSTAW_TUTAJ_KLUCZ_IPIFY'; // geo.ipify.org

// Risk score — progi alertów
$RISK_HIGH_THRESHOLD   = 70;
$RISK_MEDIUM_THRESHOLD = 40;

// Rate limit — max wejść z jednego IP w oknie czasowym
$RATE_WINDOW_SECONDS = 30;
$RATE_MAX_HITS       = 5;

date_default_timezone_set('Europe/Warsaw');

// =============================================================
// HELPERY
// =============================================================

function getClientIp(): string {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

function fetchIpify(string $ip, string $apiKey): ?array {
    if (!$apiKey || !filter_var($ip, FILTER_VALIDATE_IP)) return null;
    $url = 'https://geo.ipify.org/api/v2/country,city?apiKey=' . urlencode($apiKey)
         . '&ipAddress=' . urlencode($ip);
    $ctx = stream_context_create(['http' => ['timeout' => 4]]);
    $r   = @file_get_contents($url, false, $ctx);
    if (!$r) return null;
    $d = json_decode($r, true);
    return is_array($d) ? $d : null;
}

function isBotUA(string $ua): bool {
    $bad = ['bot','crawl','spider','python-requests','curl','wget','headlesschrome','phantomjs','go-http'];
    $low = strtolower($ua);
    foreach ($bad as $p) if (str_contains($low, $p)) return true;
    return trim($ua) === '';
}

function extractTags(array $raw, bool $isBot): array {
    $tags = [];
    $proxy = $raw['proxy'] ?? [];
    if (!empty($proxy['vpn'])   || !empty($proxy['proxy'])) $tags[] = 'vpn';
    if (!empty($proxy['tor']))  $tags[] = 'tor';
    if (!empty($proxy['proxy'])) $tags[] = 'proxy';
    $isp = strtolower($raw['isp'] ?? '');
    $dcKeywords = ['amazon','digitalocean','ovh','hetzner','linode','vultr',
                   'google','cloudflare','m247','leaseweb','choopa','packethub'];
    foreach ($dcKeywords as $k) if (str_contains($isp, $k)) { $tags[] = 'datacenter'; break; }
    if ($isBot) $tags[] = 'bot_ua';
    return array_unique($tags);
}

function calcRiskScore(array $tags, string $referer, bool $isBot): int {
    $score = 0;
    if (in_array('tor',        $tags)) $score += 40;
    if (in_array('vpn',        $tags)) $score += 25;
    if (in_array('proxy',      $tags)) $score += 25;
    if (in_array('datacenter', $tags)) $score += 20;
    if (in_array('bot_ua',     $tags)) $score += 20;
    if ($referer === 'Direct' || $referer === '') $score += 5;
    return min(100, $score);
}

function buildSuspicionReason(array $tags, int $score, bool $burst): string {
    if (empty($tags) && !$burst) return 'Brak podejrzanych sygnałów';
    $reasons = [];
    if (in_array('tor',        $tags)) $reasons[] = 'Tor exit node';
    if (in_array('vpn',        $tags)) $reasons[] = 'VPN';
    if (in_array('proxy',      $tags)) $reasons[] = 'Proxy';
    if (in_array('datacenter', $tags)) $reasons[] = 'Datacenter ASN/ISP';
    if (in_array('bot_ua',     $tags)) $reasons[] = 'Bot-like User-Agent';
    if ($burst)                         $reasons[] = 'Burst activity (rate limit)';
    return implode(' + ', $reasons);
}

function buildRiskLabel(int $score): string {
    if ($score >= 70) return '🔴 WYSOKI';
    if ($score >= 40) return '🟡 ŚREDNI';
    return '🟢 NISKI';
}

function checkRateLimit(string $ip, string $dir, int $window, int $maxHits): bool {
    $file = $dir . '/ratelimit/' . md5($ip) . '.json';
    ensureDir($dir . '/ratelimit');
    $now  = time();
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    // Filtruj stare wpisy
    $data = array_filter($data, fn($t) => ($now - $t) < $window);
    $data = array_values($data);
    $burst = count($data) >= $maxHits;
    $data[] = $now;
    file_put_contents($file, json_encode($data));
    return $burst;
}

function reverseHostname(string $ip): string {
    $host = @gethostbyaddr($ip);
    return ($host && $host !== $ip) ? $host : '';
}

function categorizeTraffic(string $referer): string {
    if ($referer === 'Direct' || $referer === '') return 'direct';
    $r = strtolower($referer);
    if (str_contains($r, 'facebook') || str_contains($r, 'fb.com')) return 'facebook';
    if (str_contains($r, 'instagram')) return 'instagram';
    if (str_contains($r, 't.me') || str_contains($r, 'telegram')) return 'telegram';
    if (str_contains($r, 'twitter') || str_contains($r, 'x.com')) return 'twitter';
    if (str_contains($r, 'tiktok')) return 'tiktok';
    if (str_contains($r, 'google')) return 'google';
    if (str_contains($r, 'youtube')) return 'youtube';
    return 'other';
}

// =============================================================
// TELEGRAM
// =============================================================

function tgPost(string $endpoint, array $data, string $token): void {
    if (!$token) return;
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 6,
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

function sendTelegramAlert(array $e, string $token, string $chatId, int $riskHigh, int $riskMed): void {
    if (!$token || !$chatId) return;

    $score  = $e['risk_score'] ?? 0;
    $label  = buildRiskLabel($score);

    // Emoji flagi kraju (UpA=🇵🇱 etc.) — proste 2-letter country code
    $country = $e['country'] ?? '';
    $flag = '';
    if (strlen($country) === 2) {
        $flag = mb_convert_encoding(
            '&#' . (127397 + ord($country[0])) . ';&#' . (127397 + ord($country[1])) . ';',
            'UTF-8', 'HTML-ENTITIES'
        );
    }

    $tags    = implode(', ', $e['tags'] ?? []) ?: '—';
    $traffic = $e['traffic_source'] ?? 'direct';
    $burst   = !empty($e['burst_activity']) ? '⚡ TAK' : 'NIE';

    $lines = [
        "🕵️ *IP GRAB* — {$label} (score: {$score})",
        "──────────────────",
        "🆔 `{$e['id']}`",
        "🕐 {$e['timestamp']}",
        "🌐 IP: `{$e['ip']}`",
        "📍 {$flag} {$e['city']}, {$e['region']}, {$e['country']}",
        "🏢 ISP: {$e['isp']}",
        "🔖 ASN: {$e['asn']}",
        "🏷 Tagi: {$tags}",
        "⚠️ Powód: {$e['suspicion_reason']}",
        "📡 Burst: {$burst}",
        "📲 Źródło: {$traffic}",
        "🌍 Referer: " . substr($e['referer'], 0, 100),
        "🤖 UA: " . substr($e['user_agent'], 0, 160),
        "🌐 Lang: {$e['accept_language']}",
    ];
    if (!empty($e['hostname'])) $lines[] = "🖥 Host: {$e['hostname']}";
    if (!empty($e['map_link'])) $lines[] = "🗺 Mapa: {$e['map_link']}";

    $text = implode("\n", $lines);

    tgPost('sendMessage', [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'parse_mode'               => 'Markdown',
        'disable_web_page_preview' => 'true',
    ], $token);

    // Wyślij natywną lokalizację jeśli mamy koordynaty
    if (!empty($e['lat']) && !empty($e['lng'])) {
        tgPost('sendLocation', [
            'chat_id'   => $chatId,
            'latitude'  => $e['lat'],
            'longitude' => $e['lng'],
        ], $token);
    }
}

function sendTelegramDocument(string $filePath, string $caption, string $token, string $chatId): void {
    if (!$token || !$chatId || !file_exists($filePath)) return;
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendDocument');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id'  => $chatId,
            'document' => new CURLFile($filePath),
            'caption'  => $caption,
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 10,
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

// =============================================================
// DAILY SUMMARY
// =============================================================

function maybeSendDailySummary(string $logDir, string $token, string $chatId): void {
    if (!$token || !$chatId) return;
    $marker = $logDir . '/last_summary.txt';
    $today  = date('Y-m-d');
    if (file_exists($marker) && trim(file_get_contents($marker)) === $today) return;

    $jsonPath = $logDir . '/grabs.json';
    if (!file_exists($jsonPath)) return;
    $all = json_decode(file_get_contents($jsonPath), true) ?: [];
    if (empty($all)) return;

    $todayEntries = array_filter($all, fn($e) => str_starts_with($e['timestamp'] ?? '', $today));
    $total        = count($todayEntries);
    if ($total === 0) return;

    $ips      = array_unique(array_column(array_values($todayEntries), 'ip'));
    $vpnCount = count(array_filter($todayEntries, fn($e) => in_array('vpn', $e['tags'] ?? [])));
    $botCount = count(array_filter($todayEntries, fn($e) => in_array('bot_ua', $e['tags'] ?? [])));
    $highRisk = count(array_filter($todayEntries, fn($e) => ($e['risk_score'] ?? 0) >= 70));

    // Top kraje
    $countries = array_count_values(array_column(array_values($todayEntries), 'country'));
    arsort($countries);
    $topCountries = implode(', ', array_map(
        fn($c, $n) => "{$c} ({$n})",
        array_keys(array_slice($countries, 0, 5, true)),
        array_slice($countries, 0, 5, true)
    ));

    $text = "📊 *Dzienny raport — {$today}*\n"
          . "──────────────────\n"
          . "📥 Wejść: {$total}\n"
          . "🔑 Unikalne IP: " . count($ips) . "\n"
          . "🔒 VPN/Proxy: {$vpnCount}\n"
          . "🤖 Boty: {$botCount}\n"
          . "🔴 Wysokie ryzyko: {$highRisk}\n"
          . "🌍 Kraje: {$topCountries}";

    tgPost('sendMessage', [
        'chat_id'   => $chatId,
        'text'      => $text,
        'parse_mode'=> 'Markdown',
    ], $token);

    // Wyślij plik JSON jako załącznik
    sendTelegramDocument(
        $jsonPath,
        "grabs.json — {$today} ({$total} wejść)",
        $token,
        $chatId
    );

    file_put_contents($marker, $today);
}

// =============================================================
// GŁÓWNA LOGIKA
// =============================================================

$logDir = __DIR__ . '/logs';
ensureDir($logDir);

$time = date('Y-m-d H:i:s');
$ip   = getClientIp();
$ua   = $_SERVER['HTTP_USER_AGENT']       ?? '';
$ref  = $_SERVER['HTTP_REFERER']          ?? 'Direct';
$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE']  ?? '';

// Extra nagłówki (fingerprinting)
$secChUa     = $_SERVER['HTTP_SEC_CH_UA']       ?? '';
$secFetchSite= $_SERVER['HTTP_SEC_FETCH_SITE']  ?? '';
$secFetchMode= $_SERVER['HTTP_SEC_FETCH_MODE']  ?? '';
$dnt         = $_SERVER['HTTP_DNT']             ?? '';

$rawIpify = fetchIpify($ip, $IPIFY_API_KEY);

$city    = $rawIpify['location']['city']    ?? '';
$region  = $rawIpify['location']['region']  ?? '';
$country = $rawIpify['location']['country'] ?? '';
$lat     = $rawIpify['location']['lat']     ?? null;
$lng     = $rawIpify['location']['lng']     ?? null;
$isp     = $rawIpify['isp']                 ?? '';
$asn     = $rawIpify['as']['asn']            ?? $rawIpify['location']['geonameId'] ?? '';

$isBot = isBotUA($ua);
$tags  = extractTags($rawIpify ?? [], $isBot);
$burst = checkRateLimit($ip, $logDir, $RATE_WINDOW_SECONDS, $RATE_MAX_HITS);
if ($burst && !in_array('burst_activity', $tags)) $tags[] = 'burst_activity';

$riskScore       = calcRiskScore($tags, $ref, $isBot);
$suspicionReason = buildSuspicionReason(array_diff($tags, ['burst_activity']), $riskScore, $burst);

$mapLink       = ($lat && $lng) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode("{$lat},{$lng}") : '';
$trafficSource = categorizeTraffic($ref);
$hostname      = reverseHostname($ip);

// Session fingerprint
$sessionHash = hash('sha256', $ip . $ua . $lang . $ref);

$entry = [
    'id'               => uniqid('grab_'),
    'timestamp'        => $time,
    'ip'               => $ip,
    'hostname'         => $hostname,
    'city'             => $city,
    'region'           => $region,
    'country'          => $country,
    'lat'              => $lat,
    'lng'              => $lng,
    'isp'              => $isp,
    'asn'              => (string)$asn,
    'tags'             => $tags,
    'risk_score'       => $riskScore,
    'risk_label'       => buildRiskLabel($riskScore),
    'suspicion_reason' => $suspicionReason,
    'burst_activity'   => $burst,
    'traffic_source'   => $trafficSource,
    'is_bot'           => $isBot,
    'user_agent'       => $ua,
    'sec_ch_ua'        => $secChUa,
    'sec_fetch_site'   => $secFetchSite,
    'sec_fetch_mode'   => $secFetchMode,
    'dnt'              => $dnt,
    'referer'          => $ref,
    'accept_language'  => $lang,
    'session_hash'     => $sessionHash,
    'map_link'         => $mapLink,
    'raw_ipify'        => $rawIpify,
];

// === ZAPIS ===
file_put_contents(
    $logDir . '/grabs.txt',
    sprintf("[%s] IP:%-16s | %s, %s | RISK:%-3d | TAGS:%s | UA:%s\n",
        $time, $ip, $city ?: '-', $country ?: '-',
        $riskScore, implode(',', $tags), substr($ua, 0, 100)
    ),
    FILE_APPEND
);

file_put_contents($logDir . '/grabs.jsonl',
    json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND
);

$jsonPath = $logDir . '/grabs.json';
$all = [];
if (file_exists($jsonPath)) {
    $d = json_decode(file_get_contents($jsonPath), true);
    if (is_array($d)) $all = $d;
}
$all[] = $entry;
file_put_contents($jsonPath, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// === TELEGRAM ===
sendTelegramAlert($entry, $TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $RISK_HIGH_THRESHOLD, $RISK_MEDIUM_THRESHOLD);
maybeSendDailySummary($logDir, $TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID);

// === 1x1 PIXEL ===
header('Content-Type: image/gif');
header('Content-Length: 43');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
