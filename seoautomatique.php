<?php
/*
Plugin Name: Followersexpress Ultra Indexer Ultimate
Description: Automate l'indexation et la création de backlinks à grande échelle.
Version: 1.0.0
Author: Followersexpress
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!defined('FE_INDEXER_DIR')) {
    define('FE_INDEXER_DIR', plugin_dir_path(__FILE__));
}

define('FE_INDEXER_LISTS', [
    'moteurlist.txt',
    'directory_sites.txt',
    'backlink_sites.txt'
]);

function fe_indexer_ping_all(string $target = ''): void
{
    $target = $target !== '' ? $target : home_url();

    foreach (FE_INDEXER_LISTS as $file) {
        fe_indexer_ping_file(FE_INDEXER_DIR . $file, $target);
    }

    fe_indexer_update_robots();
}

function fe_indexer_ping_file(string $file, string $target): void
{
    if (!file_exists($file)) {
        return;
    }

    $services = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

        $args = [
            'timeout' => 10,
            'user-agent' => fe_indexer_user_agent()
        ];
        wp_remote_get($url, $args);
    }
}

function fe_indexer_update_robots(): void
{
    $agentsFile = FE_INDEXER_DIR . 'robotindex.txt';
    if (!file_exists($agentsFile)) {
        return;
    }

    $robotsPath = ABSPATH . 'robots.txt';
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

function fe_indexer_user_agent(): string
{
    static $agents = null;
    if ($agents === null) {
        $file = FE_INDEXER_DIR . 'robotindex.txt';
        $agents = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : ['Mozilla/5.0'];
    }
    return $agents[array_rand($agents)];
}

function fe_indexer_schedule(): void
{
    if (!wp_next_scheduled('fe_indexer_cron')) {
        wp_schedule_event(time(), 'hourly', 'fe_indexer_cron');
    }
}
add_action('wp', 'fe_indexer_schedule');
add_action('fe_indexer_cron', 'fe_indexer_ping_all');

register_deactivation_hook(__FILE__, function (): void {
    $timestamp = wp_next_scheduled('fe_indexer_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fe_indexer_cron');
    }
});
