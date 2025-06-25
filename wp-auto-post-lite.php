<?php
/*
Plugin Name: WP Auto Post Lite
Plugin URI: https://github.com/dewa-news2/wp-auto-post-lite
Description: Plugin WordPress sederhana untuk auto post ke berbagai platform.
Version: 0.1.0
Author: dewa-news2
Author URI: https://github.com/dewa-news2
License: MIT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook: Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'WP Auto Post Lite',
        'WP Auto Post Lite',
        'manage_options',
        'wp-auto-post-lite',
        'wpapl_settings_page',
        'dashicons-share',
        99
    );
});

// Enqueue admin CSS only on plugin admin page
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook != 'toplevel_page_wp-auto-post-lite') return;
    wp_enqueue_style('wp-auto-post-lite-admin', plugin_dir_url(__FILE__) . 'admin-style.css');
});

// Admin page content
function wpapl_settings_page() {
    ?>
    <div class="wrap">
        <h1>WP Auto Post Lite</h1>
        <div class="wp-auto-post-lite-box">
            <p>Selamat datang di <b>WP Auto Post Lite</b>! Plugin ini memungkinkan Anda melakukan auto post ke berbagai platform secara otomatis.</p>
            <ul>
                <li>✦ Otomatisasi posting ke platform eksternal (fitur dapat dikembangkan lebih lanjut)</li>
                <li>✦ Pengaturan sederhana dan ringan</li>
            </ul>
            <p><b>Catatan:</b> Ini adalah versi awal, silakan kembangkan lebih lanjut sesuai kebutuhan Anda.</p>
        </div>
        <div class="wp-auto-post-lite-footer">
            <small>Developed by <a href="https://github.com/dewa-news2" target="_blank">dewa-news2</a> | Lisensi MIT</small>
        </div>
    </div>
    <?php
}