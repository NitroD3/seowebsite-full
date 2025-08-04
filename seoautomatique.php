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
=======
    exit;
}

// ===== Activation / Désactivation Cron + robots.txt =====
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'fexu_activate_full');
    register_deactivation_hook(__FILE__, 'fexu_clear_cron');
}

function fexu_activate_full() {
    fexu_schedule_cron();
    fexu_update_robots_txt();
}

function fexu_schedule_cron() {
    if (!wp_next_scheduled('fexu_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'fexu_cron_hook');
    }
}

function fexu_clear_cron() {
    wp_clear_scheduled_hook('fexu_cron_hook');
}

// ===== Indexation par Cron et Temps réel =====
if (function_exists('add_action')) {
    add_action('fexu_cron_hook', 'fexu_process_posts');
    add_action('save_post', 'fexu_realtime_process', 10, 3);
}

function fexu_process_posts() {
    $keywords = fexu_get_keywords();
    $services = fexu_get_all_services();
    $args = array(
        'post_type'      => 'any',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'orderby'        => 'modified',
        'order'          => 'DESC'
    );
    $posts = get_posts($args);
    foreach ($posts as $post) {
        $content = strtolower(strip_tags($post->post_content));
        foreach ($keywords as $kw) {
            if ($kw && strpos($content, strtolower($kw)) !== false) {
                fexu_send_requests($services);
                fexu_notify("Indexation CRON : Post ID ".$post->ID);
                fexu_log('Cron déclenché pour post ID ' . $post->ID);
                break 2;
            }
        }
    }
}
function fexu_realtime_process($post_ID, $post, $update) {
    if ($post->post_status !== 'publish') return;
    $keywords = fexu_get_keywords();
    $services = fexu_get_all_services();
    $content = strtolower(strip_tags($post->post_content));
    foreach ($keywords as $kw) {
        if ($kw && strpos($content, strtolower($kw)) !== false) {
            fexu_send_requests($services);
            fexu_notify("Indexation instantanée : Post ID $post_ID");
            fexu_log('Realtime déclenché pour post ID ' . $post_ID);
            // ===== Bonus : Hook IA API pour auto-content/spin (préparé) =====
            // fexu_ia_optimize_post($post_ID, $post);
            break;
        }
    }
}
function fexu_get_all_services() {
    $services = array_merge(
        fexu_get_ping_services(),
        fexu_get_directory_services(),
        fexu_get_backlink_services()
    );
    return array_values(array_unique($services));
}

// Lecture d'un fichier texte ligne par ligne avec remplacements facultatifs
function fexu_read_list_file($filename, $default = array(), $replacements = array()) {
    $path = plugin_dir_path(__FILE__) . $filename;
    if (file_exists($path)) {
        $lines = array_filter(array_map('trim', file($path)), function ($line) {
            return $line !== '' && $line[0] !== '#';
        });
        if ($replacements) {
            foreach ($lines as &$line) {
                $line = strtr($line, $replacements);
            }
        }
        return $lines;
    }
    return $default;
}
function fexu_random_user_agent() {
    static $agents = null;
    if ($agents === null) {
        $path = plugin_dir_path(__FILE__) . 'robotindex.txt';
        if (file_exists($path)) {
            $agents = array_filter(array_map('trim', file($path)), function ($line) {
                return $line !== '' && $line[0] !== '#';
            });
        } else {
            $agents = array();
        }
    }
    if ($agents) {
        return $agents[array_rand($agents)];
    }
    return 'Mozilla/5.0 (compatible; FEXU/5.0; +https://wordpress.org/)';
}
function fexu_send_requests($urls) {
    shuffle($urls);
    foreach ($urls as $url) {
        $response = wp_remote_get($url, array(
            'timeout'  => 9,
            'blocking' => false,
            'headers'  => array('User-Agent' => fexu_random_user_agent())
        ));
        if (is_wp_error($response)) {
            fexu_log('Ping error for ' . $url . ': ' . $response->get_error_message());
        }
    }
}
function fexu_log($message) {
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        return;
    }
    $file = $upload['basedir'] . '/fexu_log.txt';
    wp_mkdir_p(dirname($file));
    $time = date('Y-m-d H:i:s');
    file_put_contents($file, "[$time] $message\n", FILE_APPEND);
}
// ===== BONUS : Notification Telegram/Discord/Webhook =====
function fexu_notify($msg) {
    $telegram_token = ''; // ex: 123456789:ABCDEF123456789
    $telegram_chatid = ''; // ex: 12345678
    $discord_webhook = ''; // ex: https://discord.com/api/webhooks/...

    // Telegram
    if($telegram_token && $telegram_chatid){
        $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage?chat_id={$telegram_chatid}&text=" . urlencode($msg);
        wp_remote_get($url, array('timeout' => 5));
    }
    // Discord
    if($discord_webhook){
        $payload = json_encode(array("content" => $msg));
        wp_remote_post($discord_webhook, array(
            'body' => $payload,
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 5
        ));
    }
}

// === Page admin & Settings ultra-flex ===
if (function_exists('add_action')) {
    add_action('admin_menu', 'fexu_admin_menu');
    add_action('admin_init', 'fexu_settings_init');
}

function fexu_admin_menu() {
    add_menu_page(
        'FEXU Indexer',
        'SEO Indexer',
        'manage_options',
        'fexu-indexer',
        'fexu_settings_page',
        'dashicons-admin-generic'
    );
}
function fexu_settings_init() {
    register_setting('fexu-settings-group', 'fexu_keywords', array('sanitize_callback' => 'fexu_sanitize_list', 'default' => array()));
    register_setting('fexu-settings-group', 'fexu_ping_services', array('sanitize_callback' => 'fexu_sanitize_list', 'default' => array()));
    register_setting('fexu-settings-group', 'fexu_directory_services', array('sanitize_callback' => 'fexu_sanitize_list', 'default' => array()));
    register_setting('fexu-settings-group', 'fexu_backlink_services', array('sanitize_callback' => 'fexu_sanitize_list', 'default' => array()));
    register_setting('fexu-settings-group', 'fexu_webhook_url', array('sanitize_callback' => 'esc_url_raw', 'default' => ''));
    register_setting('fexu-settings-group', 'fexu_telegram_token', array('sanitize_callback' => 'sanitize_text_field', 'default' => ''));
    register_setting('fexu-settings-group', 'fexu_telegram_chatid', array('sanitize_callback' => 'sanitize_text_field', 'default' => ''));

    add_settings_section('fexu_section_main', 'Configuration SEO Indexer Ultimate', null, 'fexu-indexer');
    add_settings_field('fexu_keywords_field','Mots-clés','fexu_render_textarea','fexu-indexer','fexu_section_main',['label_for'=>'fexu_keywords','option_name'=>'fexu_keywords']);
    add_settings_field('fexu_ping_services_field','Services de Ping','fexu_render_textarea','fexu-indexer','fexu_section_main',['label_for'=>'fexu_ping_services','option_name'=>'fexu_ping_services']);
    add_settings_field('fexu_directory_services_field','Annuaires SMM','fexu_render_textarea','fexu-indexer','fexu_section_main',['label_for'=>'fexu_directory_services','option_name'=>'fexu_directory_services']);
    add_settings_field('fexu_backlink_services_field','Services de Backlinks','fexu_render_textarea','fexu-indexer','fexu_section_main',['label_for'=>'fexu_backlink_services','option_name'=>'fexu_backlink_services']);
    add_settings_field('fexu_webhook_field','Webhook Discord','fexu_render_input','fexu-indexer','fexu_section_main',['label_for'=>'fexu_webhook_url','option_name'=>'fexu_webhook_url']);
    add_settings_field('fexu_telegram_token_field','Token Telegram','fexu_render_input','fexu-indexer','fexu_section_main',['label_for'=>'fexu_telegram_token','option_name'=>'fexu_telegram_token']);
    add_settings_field('fexu_telegram_chatid_field','ChatID Telegram','fexu_render_input','fexu-indexer','fexu_section_main',['label_for'=>'fexu_telegram_chatid','option_name'=>'fexu_telegram_chatid']);
    add_settings_field('fexu_seo_plugin_info','Plugins SEO détectés','fexu_render_seo_plugin_info','fexu-indexer','fexu_section_main');
}
function fexu_sanitize_list($input) {
    $lines = preg_split('/[\r\n]+/', wp_strip_all_tags($input));
    return array_filter(array_map('trim', $lines));
}
function fexu_render_textarea($args) {
    $name = $args['option_name'];
    $values = get_option($name, array());
    echo "<textarea id='{$args['label_for']}' name='{$name}' rows='6' style='width:100%;'>"
         . esc_textarea(implode("\n", $values)) .
         "</textarea>";
    echo "<p class='description'>Une entrée par ligne.</p>";
}
function fexu_render_input($args){
    $name = $args['option_name'];
    $value = get_option($name, '');
    echo "<input type='text' id='{$args['label_for']}' name='{$name}' style='width:100%;' value='".esc_attr($value)."' />";
}
function fexu_render_seo_plugin_info() {
    $plugins = array();
    if (class_exists('AIOSEO')) $plugins[] = 'All in One SEO Pro';
    if (class_exists('RankMath\\Repository')) $plugins[] = 'Rank Math';
    if (class_exists('Jetpack')) $plugins[] = 'Jetpack SEO';
    if (class_exists('The_SEO_Framework')) $plugins[] = 'SEO Framework';
    if (class_exists('SEOPress')) $plugins[] = 'SEOPress';
    echo $plugins ? implode(', ', $plugins) : 'Aucun plugin SEO détecté.';
}
function fexu_settings_page() {
    echo '<div class="wrap"><h1>SEO Indexer Ultimate</h1>';
    if (isset($_GET['fexu_run']) && $_GET['fexu_run'] === '1') {
        fexu_process_posts();
        echo '<div class="updated notice"><p>Indexation manuelle exécutée.</p></div>';
    }
    if (isset($_POST['fexu_regen_robots'])) {
        fexu_update_robots_txt();
        echo '<div class="updated notice"><p>robots.txt régénéré.</p></div>';
    }
    echo '<form method="post" action="options.php">';
    settings_fields('fexu-settings-group');
    do_settings_sections('fexu-indexer');
    submit_button();
    echo '</form>';
    echo '<h2>Actions Manuelles</h2>';
    echo '<form method="post"><button class="button" name="fexu_regen_robots" value="1">Régénérer robots.txt</button></form>';
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=fexu-indexer&fexu_run=1')) . '" class="button button-primary">Lancer manuellement l\'indexation</a></p>';
    echo '<h2>Logs</h2><pre style="background:#f1f1f1;padding:10px;max-height:300px;overflow:auto;">';
    $upload = wp_upload_dir();
    $file = $upload['basedir'] . '/fexu_log.txt';
    echo file_exists($file) ? esc_html(file_get_contents($file)) : 'Aucun log.';
    echo '</pre></div>';
}
function fexu_get_keywords() {
    $opt = get_option('fexu_keywords', array());
    return $opt ? $opt : array(
        'achat followers', 'acheter des abonnés', 'followers pas cher', 'acheter des likes', 'acheter vues tiktok',
        'insta', 'like', 'achat likes', 'achat abonnés', 'acheter followers', 'buy followers', 'buy likes', 'réseaux sociaux', 'smm', 'seo', 'croissance instagram', 'augmenter abonnés'
    );
}
function fexu_get_ping_services() {
    $opt = get_option('fexu_ping_services', array());
    if ($opt) return $opt;
    $s = urlencode(get_site_url() . '/sitemap.xml');
    $default = array(
        "https://www.google.com/ping?sitemap=$s",
        "https://www.bing.com/ping?sitemap=$s",
        "https://webmaster.yandex.com/ping.xml?sitemap=$s",
        "https://pingler.com/",
        "https://feedshark.brainbliss.com/",
        "https://www.bulkping.com/",
        "https://www.indexkings.com/",
        "https://www.pingoat.net/",
        "https://ping.feedburner.com/",
        "http://ping.blo.gs/",
        "http://rpc.pingomatic.com/",
        "http://rpc.twingly.com/"
    );
    return fexu_read_list_file('moteurlist.txt', $default, array('{sitemap}' => $s, '{url}' => $s));
}
function fexu_get_directory_services() {
    $opt = get_option('fexu_directory_services', array());
    if ($opt) return $opt;
    $site = urlencode(get_site_url());
    $default = array(
        "https://submit-xseo.com?site=$site",
        "https://submithub.co/auto?url=$site",
        "https://addurl.nu/?url=$site",
        "https://www.sitelinkindexer.com/?url=$site",
        "https://rankersparadise.com/free-seo-tools/backlink-indexer/?url=$site",
        "https://indexinjector.com/submit?url=$site"
    );
    return fexu_read_list_file('directory_sites.txt', $default, array('{url}' => $site));
}
function fexu_get_backlink_services() {
    $opt = get_option('fexu_backlink_services', array());
    if ($opt) return $opt;
    $site = urlencode(get_site_url());
    $default = array(
        "https://linkcentaur.com/?url=$site",
        "https://instantlinkindexer.com/?url=$site",
        "https://rankersparadise.com/free-seo-tools/backlink-indexer/?url=$site",
        "https://seokicks.de/addurl?url=$site",
        "https://www.indexkings.com/?url=$site",
        "https://www.bulkping.com/ping/?url=$site",
        "https://www.pingfarm.com/?url=$site",
        "https://www.addurl.nu/?url=$site",
        "https://www.sitelinkindexer.com/?url=$site",
        "https://submit-xseo.com?site=$site"
    );
    return fexu_read_list_file('backlink_sites.txt', $default, array('{url}' => $site));
}

// ===== ROBOTS.TXT AUTO-AJOUT + bouton admin =====
function fexu_update_robots_txt() {
    $robots_path = ABSPATH . 'robots.txt';
    $home = get_site_url();
    $extra_agents = fexu_read_list_file('robotindex.txt', array());
    $agents_block = '';
    foreach ($extra_agents as $agent) {
        $agents_block .= "User-agent: {$agent}\nAllow: /\n\n";
    }
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url($home, PHP_URL_HOST);
    $robots = <<<TXT
User-agent: *
Disallow: /wp-content/uploads/wc-logs/
Disallow: /wp-content/uploads/woocommerce_transient_files/
Disallow: /wp-content/uploads/woocommerce_uploads/
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Allow: /wp-content/uploads/
Allow: /wp-content/themes/
Allow: /wp-content/plugins/
Allow: /wp-json/
Allow: /?rest_route=

Sitemap: {$home}/sitemap.xml
Sitemap: {$home}/sitemap_index.xml
Sitemap: {$home}/sitemap.rss

Host: {$host}
Crawl-delay: 1

{$agents_block}
TXT;
    file_put_contents($robots_path, $robots);
}

// ===== BONUS : Hook IA pour spinning/optimisation SEO auto (exemple de fonction à connecter à une API) =====
function fexu_ia_optimize_post($post_ID, $post){
    // Exemple : tu peux connecter ici une API GPT, Claude, ou autre
    // $content = $post->post_content;
    // $result = my_ia_api_optimize($content);
    // if($result) wp_update_post(array('ID' => $post_ID, 'post_content' => $result));
    // fexu_log('Contenu optimisé IA pour le post ID '.$post_ID);
}
?>

