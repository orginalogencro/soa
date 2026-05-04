<?php
/**
 * blocklist_export.php
 * Generuje gotową listę blokowania IP i User-Agentów na podstawie grabs.json
 * Użycie: https://twojdomain.com/blocklist_export.php?key=TWOJSEKRETKLUCZ&format=txt
 * Formaty: txt (jeden IP per linia), json, nginx
 */

$SECRET_KEY   = 'WSTAW_TUTAJ_TAJNY_KLUCZ'; // np. losowy string 32 znaków
$MIN_RISK     = 60;       // minimalny risk_score do blokowania
$LOG_DIR      = __DIR__ . '/logs';

// Auth
$key = $_GET['key'] ?? '';
if ($key !== $SECRET_KEY) {
    http_response_code(403);
    die('Forbidden');
}

$format = $_GET['format'] ?? 'json';

$jsonPath = $LOG_DIR . '/grabs.json';
if (!file_exists($jsonPath)) {
    http_response_code(404);
    die('No data');
}

$all = json_decode(file_get_contents($jsonPath), true) ?: [];

// Zbierz kandydatów
$blackIps = [];
$blackUAs = [];

foreach ($all as $e) {
    $score = $e['risk_score'] ?? 0;
    $ip    = $e['ip']         ?? '';
    $ua    = $e['user_agent'] ?? '';
    $tags  = $e['tags']       ?? [];

    if ($score >= $MIN_RISK && $ip) {
        $blackIps[$ip] = ($blackIps[$ip] ?? 0) + 1;
    }

    if (in_array('bot_ua', $tags) && $ua) {
        $uaKey = substr($ua, 0, 100);
        $blackUAs[$uaKey] = ($blackUAs[$uaKey] ?? 0) + 1;
    }
}

// Sortuj po częstości
arsort($blackIps);
arsort($blackUAs);

if ($format === 'txt') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="blocklist_ips.txt"');
    echo "# IP Blocklist — wygenerowano: " . date('Y-m-d H:i:s') . "\n";
    echo "# risk_score >= {$MIN_RISK}\n\n";
    foreach ($blackIps as $ip => $count) {
        echo $ip . "\n";
    }

} elseif ($format === 'nginx') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="blocklist_nginx.conf"');
    echo "# Nginx deny rules — wygenerowano: " . date('Y-m-d H:i:s') . "\n\n";
    foreach ($blackIps as $ip => $count) {
        echo "deny {$ip}; # hits: {$count}\n";
    }
    echo "\nallow all;\n";

} else {
    header('Content-Type: application/json');
    echo json_encode([
        'generated'   => date('Y-m-d H:i:s'),
        'min_risk'    => $MIN_RISK,
        'total_entries' => count($all),
        'blocked_ips' => array_keys($blackIps),
        'blocked_ips_with_count' => $blackIps,
        'blocked_ua_patterns'   => array_keys($blackUAs),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
exit;
