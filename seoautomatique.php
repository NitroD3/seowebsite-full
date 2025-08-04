<?php
/*
Plugin Name: Followersexpress Ultra Indexer Ultimate
Description: Indexation, backlinks, robots.txt SEO extrême, notifications, dashboard, IA ready. Ping 300+ services, annuaires, logs, cron, admin, tout inclus.
Version: 5.0
Author: Ayden Dev
*/

if (!defined('ABSPATH')) exit;

// ===== Activation / Désactivation Cron + robots.txt =====
register_activation_hook(__FILE__, 'fexu_activate_full');
register_deactivation_hook(__FILE__, 'fexu_clear_cron');

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
add_action('fexu_cron_hook', 'fexu_process_posts');
add_action('save_post', 'fexu_realtime_process', 10, 3);

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
    return array_merge(
        fexu_get_ping_services(),
        fexu_get_directory_services(),
        fexu_get_backlink_services()
    );
}
function fexu_send_requests($urls) {
    foreach ($urls as $url) {
        wp_remote_get($url, array('timeout' => 9));
    }
}
function fexu_log($message) {
    $upload = wp_upload_dir();
    $file = $upload['basedir'] . '/fexu_log.txt';
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
add_action('admin_menu', 'fexu_admin_menu');
add_action('admin_init', 'fexu_settings_init');

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
    return array(
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
}
function fexu_get_directory_services() {
    $opt = get_option('fexu_directory_services', array());
    $site = urlencode(get_site_url());
    return $opt ? $opt : array(
        "https://submit-xseo.com?site=$site",
        "https://submithub.co/auto?url=$site",
        "https://addurl.nu/?url=$site",
        "https://www.sitelinkindexer.com/?url=$site",
        "https://rankersparadise.com/free-seo-tools/backlink-indexer/?url=$site",
        "https://indexinjector.com/submit?url=$site"
    );
}
function fexu_get_backlink_services() {
    $opt = get_option('fexu_backlink_services', array());
    $site = urlencode(get_site_url());
    return $opt ? $opt : array(
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
}

// ===== ROBOTS.TXT AUTO-AJOUT + bouton admin =====
function fexu_update_robots_txt() {
    $robots_path = ABSPATH . 'robots.txt';
    $home = get_site_url();
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

Host: {$_SERVER['HTTP_HOST']}
Crawl-delay: 1

User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /

User-agent: Baiduspider
Allow: /

User-agent: Yandex
Allow: /

User-agent: AhrefsBot
Allow: /

User-agent: SemrushBot
Allow: /

User-agent: MJ12bot
Allow: /

User-agent: DotBot
Allow: /

User-agent: BLEXBot
Allow: /

User-agent: PetalBot
Allow: /

User-agent: Sogou
Allow: /

User-agent: CensysInspect
Allow: /

User-agent: MegaIndex.ru
Allow: /

User-agent: GPTBot
Allow: /

User-agent: AdsBot-Google
Allow: /

User-agent: Twitterbot
Allow: /

User-agent: FacebookExternalHit
Allow: /

User-agent: Slackbot
Allow: /

User-agent: TelegramBot
Allow: /

User-agent: Discordbot
Allow: /

User-agent: WhatsApp
Allow: /
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
