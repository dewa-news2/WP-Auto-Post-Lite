<?php
/*
Plugin Name: WP Auto Post Lite
Description: Simple plugin to fetch an article and its main image from a target website, then auto post to WordPress.
Version: 0.2
Author: Dewa
*/

add_action('admin_menu', function() {
    add_menu_page('WP Auto Post Lite', 'WP Auto Post Lite', 'manage_options', 'wp-auto-post-lite', 'wapl_admin_page');
});

function wapl_admin_page() {
    if (isset($_POST['wapl_url']) && !empty($_POST['wapl_url'])) {
        $url = esc_url_raw($_POST['wapl_url']);
        $result = wapl_fetch_and_post($url);
        if ($result['success']) {
            echo '<div class="notice notice-success">Post berhasil dibuat! <a href="' . get_edit_post_link($result['post_id']) . '">Edit Post</a></div>';
        } else {
            echo '<div class="notice notice-error">Gagal: ' . esc_html($result['error']) . '</div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>WP Auto Post Lite</h1>
        <form method="post">
            <label for="wapl_url">URL sumber artikel:</label><br>
            <input type="url" name="wapl_url" id="wapl_url" style="width:400px" required>
            <input type="submit" value="Ambil & Posting" class="button button-primary">
        </form>
    </div>
    <?php
}

function wapl_fetch_and_post($url) {
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }
    $body = wp_remote_retrieve_body($response);

    // Ambil judul dari <title>
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m_title)) {
        $title = wp_strip_all_tags($m_title[1]);
    } else {
        $title = 'Auto Post ' . date('Y-m-d H:i:s');
    }

    // Ambil isi artikel dari tag <article>
    $content = '';
    $image_url = '';
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $body, $m_content)) {
        $content = $m_content[1];

        // Cari gambar pertama di dalam <article>
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $img_matches)) {
            $image_url = wapl_make_absolute_url($img_matches[1], $url);
        }
    } else {
        // fallback: ambil semua <p>
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $body, $m_paragraphs)) {
            $content = implode("\n\n", $m_paragraphs[1]);
        } else {
            return ['success' => false, 'error' => 'Tidak ditemukan konten artikel!'];
        }
        // Cari gambar pertama di halaman
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $body, $img_matches)) {
            $image_url = wapl_make_absolute_url($img_matches[1], $url);
        }
    }

    // Posting ke WordPress
    $post_id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => wp_kses_post($content),
        'post_status'  => 'draft', // Ubah ke 'publish' jika ingin langsung terbit
        'post_author'  => get_current_user_id()
    ]);

    // Jika ada gambar, download dan set featured image
    if ($post_id && $image_url) {
        $attach_id = wapl_download_and_attach_image($image_url, $post_id);
        if ($attach_id) {
            set_post_thumbnail($post_id, $attach_id);
        }
    }

    if ($post_id) {
        return ['success' => true, 'post_id' => $post_id];
    } else {
        return ['success' => false, 'error' => 'Gagal membuat post!'];
    }
}

// Membuat absolute url untuk src gambar jika relative
function wapl_make_absolute_url($src, $base_url) {
    if (parse_url($src, PHP_URL_SCHEME) != '') {
        return $src; // Sudah absolute
    }
    // Buat absolute
    $parsed = parse_url($base_url);
    $host = $parsed['scheme'] . '://' . $parsed['host'];
    if (substr($src, 0, 1) == '/') {
        return $host . $src;
    } else {
        // Untuk path relatif
        $path = isset($parsed['path']) ? dirname($parsed['path']) : '';
        return $host . $path . '/' . $src;
    }
}

// Download gambar dan attach ke post
function wapl_download_and_attach_image($image_url, $post_id) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Download file ke media library
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) return 0;

    $file = [
        'name'     => basename($image_url),
        'type'     => mime_content_type($tmp),
        'tmp_name' => $tmp,
        'error'    => 0,
        'size'     => filesize($tmp),
    ];

    $attach_id = media_handle_sideload($file, $post_id);

    @unlink($tmp); // Hapus file temp

    if (is_wp_error($attach_id)) return 0;

    return $attach_id;
}
?>
