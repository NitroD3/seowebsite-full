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
foreach ($services as $service) {
    $service = str_replace('{sitemap}', $sitemap, $service);
    $service = str_replace('{url}', urlencode($site), $service);
    $ua = $agents ? $agents[array_rand($agents)] : 'Mozilla/5.0 (compatible; FEXU/5.0;)';
    $context = stream_context_create(['http' => ['user_agent' => $ua, 'timeout' => 9]]);
    echo "Ping : $service\n";
    @file_get_contents($service, false, $context);
}
