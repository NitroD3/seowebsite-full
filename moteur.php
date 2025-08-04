<?php
// CLI script to ping search engines, directories and backlinks.
// Usage: php moteur.php https://example.com

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$target = $argv[1] ?? '';
if (empty($target)) {
    fwrite(STDERR, "Usage: php moteur.php <url>\n");
    exit(1);
}

$baseDir = __DIR__;
$lists = [
    'moteurlist.txt',
    'directory_sites.txt',
    'backlink_sites.txt'
];

foreach ($lists as $file) {
    $path = $baseDir . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($path)) {
        fwrite(STDERR, "List file not found: {$file}\n");
        continue;
    }

    $services = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($services as $service) {
        $service = trim($service);
        if ($service === '') {
            continue;
        }

        if (strpos($service, '{url}') !== false) {
            $url = str_replace('{url}', urlencode($target), $service);
        } else {
            $url = rtrim($service, '/') . '/' . urlencode($target);
        }

        ping_service($url);
    }
}

update_robots($baseDir . DIRECTORY_SEPARATOR . 'robotindex.txt');

function ping_service(string $url): void
{
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FEIndexer/1.0)'
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Throwable $e) {
        fwrite(STDERR, "Error pinging {$url}: " . $e->getMessage() . "\n");
    }
}

function update_robots(string $agentsFile): void
{
    if (!file_exists($agentsFile)) {
        return;
    }

    $robotsPath = getcwd() . DIRECTORY_SEPARATOR . 'robots.txt';
    $agents = file($agentsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $content = file_exists($robotsPath) ? file_get_contents($robotsPath) : '';

    foreach ($agents as $agent) {
        $agent = trim($agent);
        if ($agent === '') {
            continue;
        }
        $line = "User-agent: {$agent}\nAllow: /\n";
        if (strpos($content, $line) === false) {
            $content .= $line;
        }
    }

    file_put_contents($robotsPath, $content);
}
