<?php
// Outil CLI pour pinger les moteurs de recherche listés dans moteurlist.txt
// Usage : php moteur.php https://votre-site.com

$site = $argv[1] ?? '';
if (!$site) {
    fwrite(STDERR, "URL du site requise.\n");
    exit(1);
}
$site = rtrim($site, '/');
$listFile = __DIR__ . '/moteurlist.txt';
if (!file_exists($listFile)) {
    fwrite(STDERR, "Fichier de liste introuvable : $listFile\n");
    exit(1);
}
$services = array_filter(array_map('trim', file($listFile)), function ($line) {
    return $line !== '' && $line[0] !== '#';
});
$agentsFile = __DIR__ . '/robotindex.txt';
$agents = [];
if (file_exists($agentsFile)) {
    $agents = array_filter(array_map('trim', file($agentsFile)), function ($line) {
        return $line !== '' && $line[0] !== '#';
    });
}
shuffle($services);
$sitemap = urlencode($site . '/sitemap.xml');

$doPing = function ($url, $ua) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT => $ua,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 9,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => ['user_agent' => $ua, 'timeout' => 9]]);
        @file_get_contents($url, false, $context);
    }
};

foreach ($services as $service) {
    $service = str_replace('{sitemap}', $sitemap, $service);
    $service = str_replace('{url}', urlencode($site), $service);
    $ua = $agents ? $agents[array_rand($agents)] : 'Mozilla/5.0 (compatible; FEXU/5.0;)';
    echo "Ping : $service\n";
    $doPing($service, $ua);
}
