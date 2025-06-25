<?php
/*
Plugin Name: WP Automatic Lite Custom AI + Category (Dewa News, Clean Source)
Description: Autopost RSS ke kategori tertentu, rewrite judul & isi (OpenAI/Gemini), crop gambar, feed dinamis. Sumber diubah ke Dewa News, hapus "Baca Juga", googletag, dan script sampah lainnya. Tambahan: Sumber URL otomatis tercantum di bawah artikel.
Version: 2.8
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

// ====== ADMIN MENU & UI =======
add_action('admin_menu', function() {
    add_menu_page('WP Automatic Lite', 'WP Automatic Lite', 'manage_options', 'wp-automatic-lite', 'wal_admin_page', 'dashicons-rss', 25);
});
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_wp-automatic-lite') return;
    wp_enqueue_style('wal_admin_css', plugin_dir_url(__FILE__).'wal-admin.css');
});

// ====== DEFAULT SETTINGS =======
register_activation_hook(__FILE__, function() {
    if (false === get_option('wal_settings')) {
        update_option('wal_settings', [
            'feeds' => [],
            'feed_categories' => [],
            'interval' => 60,
            'filters' => [],
            'replace' => [],
            'openai_key' => '',
            'gemini_key' => '',
            'ai_engine' => 'none'
        ]);
    }
});

// ====== AJAX SAVE SETTINGS =======
add_action('wp_ajax_wal_save_settings', function() {
    check_admin_referer('wal_save_settings');
    $feeds = array_map('esc_url_raw', $_POST['feeds'] ?? []);
    $feed_cats = $_POST['feed_categories'] ?? [];
    $feeds_new = [];
    $feed_cats_new = [];
    foreach ($feeds as $i => $f) {
        if (!empty($f)) {
            $feeds_new[] = $f;
            $feed_cats_new[] = sanitize_text_field($feed_cats[$i] ?? '');
        }
    }
    $settings = [
        'feeds' => $feeds_new,
        'feed_categories' => $feed_cats_new,
        'interval' => intval($_POST['interval']),
        'filters' => array_filter(array_map('sanitize_text_field', explode("\n", $_POST['filters'][0] ?? ''))),
        'replace' => array_filter(array_map('sanitize_text_field', explode("\n", $_POST['replace'][0] ?? ''))),
        'openai_key' => sanitize_text_field($_POST['openai_key']),
        'gemini_key' => sanitize_text_field($_POST['gemini_key']),
        'ai_engine' => in_array($_POST['ai_engine'], ['none','openai','gemini']) ? $_POST['ai_engine'] : 'none'
    ];
    update_option('wal_settings', $settings);
    wp_send_json_success('Settings saved!');
});

// ====== ADMIN PAGE =======
function wal_admin_page() {
    $settings = get_option('wal_settings', []);
    $categories = get_categories(['hide_empty'=>0]);
    $feeds = $settings['feeds'] ?? [];
    $feed_cats = $settings['feed_categories'] ?? [];
    if (empty($feeds)) $feeds = [''];
    $max = max(count($feeds), count($feed_cats), 1);
    ?>
    <div class="wrap wal-container">
        <h1>WP Automatic Lite <span style="font-size:14px;color:#888;">Custom AI + Kategori</span></h1>
        <form id="wal-settings-form" autocomplete="off">
            <h2>RSS Feeds & Kategori</h2>
            <div id="feeds-list">
                <?php
                for($i=0;$i<$max;$i++):
                    $feedval = $feeds[$i] ?? "";
                    $catval = $feed_cats[$i] ?? "";
                ?>
                <div class="wal-feed-row">
                    <input type="url" name="feeds[]" value="<?php echo esc_attr($feedval); ?>" placeholder="https://feed-url.com/rss" style="min-width:240px">
                    <select name="feed_categories[]" class="wal-cat-select">
                        <option value="">Pilih Kategori</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($catval, $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button wal-remove-feed" onclick="this.parentNode.remove();">Hapus</button>
                </div>
                <?php endfor; ?>
            </div>
            <button type="button" onclick="walAddInputCat()" class="button">+ Feed</button>
            <h2>Penjadwalan (menit)</h2>
            <input type="number" min="10" name="interval" value="<?php echo esc_attr($settings['interval'] ?? 60); ?>" required>
            <h2>Filter Judul/Konten (satu per baris)</h2>
            <textarea name="filters[]" rows="3"><?php echo esc_textarea(implode("\n", $settings['filters'] ?? [])); ?></textarea>
            <h2>Replace Kata/Frasa (format: asal=>ganti, satu per baris)</h2>
            <textarea name="replace[]" rows="3"><?php echo esc_textarea(implode("\n", $settings['replace'] ?? [])); ?></textarea>
            <h2>Pilih AI untuk Rewrite</h2>
            <select name="ai_engine">
                <option value="none" <?php selected($settings['ai_engine'] ?? '', 'none'); ?>>Tidak Pakai AI</option>
                <option value="openai" <?php selected($settings['ai_engine'] ?? '', 'openai'); ?>>OpenAI (gpt-3.5/gpt-4)</option>
                <option value="gemini" <?php selected($settings['ai_engine'] ?? '', 'gemini'); ?>>Gemini (Google)</option>
            </select>
            <div style="margin-top:10px;">
                <b>OpenAI API Key:</b><br>
                <input type="text" name="openai_key" value="<?php echo esc_attr($settings['openai_key'] ?? ''); ?>" placeholder="sk-...">
            </div>
            <div style="margin-top:10px;">
                <b>Gemini API Key:</b><br>
                <input type="text" name="gemini_key" value="<?php echo esc_attr($settings['gemini_key'] ?? ''); ?>" placeholder="AIza...">
            </div>
            <br>
            <button type="submit" class="button button-primary">Simpan Pengaturan</button>
        </form>
        <div id="wal-save-msg"></div>
        <hr>
        <h2>Cara Pakai</h2>
        <ol>
            <li>Isi RSS Feed & pilih kategori (artikel akan masuk ke kategori tersebut).</li>
            <li>Bisa tambah feed sebanyak yang diinginkan, hapus feed dengan tombol Hapus.</li>
            <li>Feed/kategori kosong akan otomatis diabaikan saat disimpan.</li>
            <li>Pengaturan lain seperti sebelumnya.</li>
        </ol>
    </div>
    <script>
    document.getElementById('wal-settings-form').onsubmit = function(e){
        e.preventDefault();
        let fd = new FormData(e.target);
        // Remove all empty feeds before submit via JS
        let rows = Array.from(document.querySelectorAll('.wal-feed-row'));
        rows.forEach(function(row) {
            let inp = row.querySelector('input[name="feeds[]"]');
            if(inp && inp.value.trim() === "") {
                inp.removeAttribute('name');
                let sel = row.querySelector('select[name="feed_categories[]"]');
                if (sel) sel.removeAttribute('name');
            }
        });
        fd.append('action', 'wal_save_settings');
        fd.append('_wpnonce', '<?php echo wp_create_nonce('wal_save_settings'); ?>');
        fetch(ajaxurl, {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
            document.getElementById('wal-save-msg').innerHTML = "<div class='updated'><b>"+d.data+"</b></div>";
        });
    };
    function walAddInputCat(){
        let d=document.getElementById('feeds-list');
        let cats = <?php echo json_encode(array_map(function($cat){return ['id'=>$cat->term_id,'name'=>$cat->name];}, get_categories(['hide_empty'=>0]))); ?>;
        let row=document.createElement('div');
        row.className='wal-feed-row';
        let catopt = `<option value="">Pilih Kategori</option>`;
        cats.forEach(function(cat){
            catopt += `<option value="${cat.id}">${cat.name}</option>`;
        });
        row.innerHTML = `<input type="url" name="feeds[]" placeholder="https://feed-url.com/rss" style="min-width:240px">
        <select name="feed_categories[]" class="wal-cat-select">${catopt}</select>
        <button type="button" class="button wal-remove-feed" onclick="this.parentNode.remove();">Hapus</button>`;
        d.appendChild(row);
    }
    </script>
    <?php
}

// ====== CRON SCHEDULING =======
add_action('wp', function() {
    if (!wp_next_scheduled('wal_cron_job')) {
        wp_schedule_event(time(), 'wal_custom_interval', 'wal_cron_job');
    }
});
add_filter('cron_schedules', function($schedules) {
    $settings = get_option('wal_settings', []);
    $i = max(10, intval($settings['interval'] ?? 60));
    $schedules['wal_custom_interval'] = [
        'interval' => $i*60,
        'display' => 'WP Automatic Lite interval'
    ];
    return $schedules;
});
add_action('wal_cron_job', 'wal_fetch_and_post');

// ====== CORE AUTOPOST FUNCTION =======
function wal_fetch_and_post() {
    $settings = get_option('wal_settings', []);
    $feeds = $settings['feeds'] ?? [];
    $feed_cats = $settings['feed_categories'] ?? [];
    $filters = $settings['filters'] ?? [];
    $replace = [];
    foreach ($settings['replace'] ?? [] as $r) {
        if (strpos($r, '=>')!==false) {
            [$from,$to]=explode('=>',$r,2);
            $replace[trim($from)]=trim($to);
        }
    }
    $ai_engine = $settings['ai_engine'] ?? 'none';
    $openai_key = $settings['openai_key'] ?? '';
    $gemini_key = $settings['gemini_key'] ?? '';

    // Daftar sumber yang ingin diganti ke Dewa News
    $sumber_rss = [
        'REPUBLIKA.CO.ID', 'REPUBLIKA.CO.ID, JAKARTA', 'REPUBLIKA.CO.ID,', 'REPUBLIKA', 'detik.com', 'detikNews', 'CNN Indonesia', 'kompas.com', 'Kompas.com', 'sindonews.com', 'tribunnews.com', 'okezone.com', 'antara', 'ANTARA', 'merdeka.com', 'jpnn.com', 'viva.co.id', 'tempo.co', 'TEMPO.CO', 'inilah.com', 'BERITASATU.COM', 'Beritasatu.com', 'idntimes.com', 'suara.com', 'Liputan6.com', 'Bisnis.com', 'cnbcindonesia.com', 'BBC', 'bbc.com'
    ];

    foreach ($feeds as $idx => $feed_url) {
        if (!$feed_url) continue;
        $cat_id = intval($feed_cats[$idx] ?? 0);
        $rss = @simplexml_load_file($feed_url);
        if (!$rss || !isset($rss->channel->item)) continue;
        foreach ($rss->channel->item as $item) {
            $title = (string)$item->title;
            $link = (string)$item->link;
            $desc = (string)$item->description;
            $content = $desc;
            if (isset($item->children('content', true)->encoded)) {
                $content = (string)$item->children('content', true)->encoded;
            }

            // 1. Ganti sumber RSS di awal paragraf jadi "Dewa News"
            foreach ($sumber_rss as $src) {
                $content = preg_replace('/\b' . preg_quote($src, '/') . '\b([\s,–—-]*)/iu', 'Dewa News — ', $content, 1);
            }

            // 2. Hapus "Baca Juga ..." beserta link/barisnya
            $content = preg_replace('/Baca Juga.*?<a[^>]*>.*?<\/a>/is', '', $content);
            $content = preg_replace('/Baca Juga.*?(\.|\n)/is', '', $content);
            $content = preg_replace('/^.*Baca Juga.*$/im', '', $content);

            // 3. Hapus kode/script/junk (googletag, .push, window, script, iframes, dsb)
            $content = preg_replace('/^.*(googletag|gpt|rcMain|rcBuf|static\.main\.js|\.push|window\.|<script|<\/script>|<iframe|<\/iframe>).*$/im', '', $content);
            $content = preg_replace('/^.*function\s*\(.*$/im', '', $content);
            $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
            $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);
            $content = preg_replace('/^([^\s<]{15,})$/m', '', $content);

            // Jika konten pendek, ambil artikel full dari url sumber
            if (strlen(strip_tags($content)) < 300) {
                $real_content = wal_get_full_content_from_url($link);
                if ($real_content) {
                    foreach ($sumber_rss as $src) {
                        $real_content = preg_replace('/\b' . preg_quote($src, '/') . '\b([\s,–—-]*)/iu', 'Dewa News — ', $real_content, 1);
                    }
                    $real_content = preg_replace('/Baca Juga.*?<a[^>]*>.*?<\/a>/is', '', $real_content);
                    $real_content = preg_replace('/Baca Juga.*?(\.|\n)/is', '', $real_content);
                    $real_content = preg_replace('/^.*Baca Juga.*$/im', '', $real_content);
                    $real_content = preg_replace('/^.*(googletag|gpt|rcMain|rcBuf|static\.main\.js|\.push|window\.|<script|<\/script>|<iframe|<\/iframe>).*$/im', '', $real_content);
                    $real_content = preg_replace('/^.*function\s*\(.*$/im', '', $real_content);
                    $real_content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $real_content);
                    $real_content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $real_content);
                    $real_content = preg_replace('/^([^\s<]{15,})$/m', '', $real_content);
                    $content = $real_content;
                }
            }
            // Filter title/content
            $skip = false;
            foreach ($filters as $f) {
                if (stripos($title, $f)!==false || stripos($content, $f)!==false) $skip = true;
            }
            if ($skip) continue;
            // Replace kata/frasa
            foreach ($replace as $from=>$to) {
                $title = str_ireplace($from, $to, $title);
                $content = str_ireplace($from, $to, $content);
            }
            // AI Rewrite (judul dan isi)
            if ($ai_engine === 'openai' && $openai_key && !empty($title)) {
                $prompt = "Buat judul artikel yang singkat, menarik, dan menggambarkan isi berikut (Bahasa Indonesia):\n\n" . strip_tags($content);
                $title_ai = wal_rewrite_openai($prompt, $openai_key, true);
                if ($title_ai && $title_ai !== $prompt) {
                    $title = $title_ai;
                }
            } elseif ($ai_engine === 'gemini' && $gemini_key && !empty($title)) {
                $prompt = "Buat judul artikel yang singkat, menarik, dan menggambarkan isi berikut (Bahasa Indonesia):\n\n" . strip_tags($content);
                $title_ai = wal_rewrite_gemini($prompt, $gemini_key, true);
                if ($title_ai && $title_ai !== $prompt) {
                    $title = $title_ai;
                }
            }
            if ($ai_engine === 'openai' && $openai_key && !empty($content)) {
                $content_ai = wal_rewrite_openai($content, $openai_key);
                if ($content_ai && $content_ai !== $content) {
                    $content = $content_ai;
                }
            } elseif ($ai_engine === 'gemini' && $gemini_key && !empty($content)) {
                $content_ai = wal_rewrite_gemini($content, $gemini_key);
                if ($content_ai && $content_ai !== $content) {
                    $content = $content_ai;
                }
            }
            // Tambahkan sumber di akhir konten
            $sumber_html = '<p style="font-size:13px;color:#888;margin-top:28px;">Sumber: <a href="'.esc_url($link).'" rel="nofollow noopener" target="_blank">'.esc_html($link).'</a></p>';
            $content_with_sumber = $content . $sumber_html;
            // Cek sudah pernah posting (by link)
            if (get_posts(['post_type'=>'post','meta_key'=>'wal_source_link','meta_value'=>$link,'posts_per_page'=>1])) continue;
            // Ambil gambar pertama (auto featured image)
            $img_url = '';
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $img_matches)) {
                $img_url = $img_matches[1];
            }
            $post_args = [
                'post_title' => wp_strip_all_tags($title),
                'post_content' => $content_with_sumber,
                'post_status' => 'publish',
                'post_author' => 1,
                'meta_input' => ['wal_source_link' => $link],
            ];
            if ($cat_id) $post_args['post_category'] = [$cat_id];
            $post_id = wp_insert_post($post_args);
            // Set featured image dengan crop bagian bawah 1/6
            if ($post_id && $img_url) {
                $attach_id = wal_download_and_attach_image($img_url, $post_id);
                if ($attach_id) set_post_thumbnail($post_id, $attach_id);
            }
        }
    }
}

// ====== SCRAPE FULL CONTENT FROM URL =======
function wal_get_full_content_from_url($url) {
    $body = wp_remote_retrieve_body(wp_remote_get($url));
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $body, $m)) {
        return $m[1];
    }
    if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $body, $m)) {
        return implode("\n\n", $m[1]);
    }
    return '';
}

// ====== AI REWRITE: OPENAI =======
function wal_rewrite_openai($content, $api_key, $single_line = false) {
    $prompt = $single_line ? $content : "Rewrite the following text to be unique, natural, and engaging (Bahasa Indonesia if possible):\n\n".$content;
    $data = [
        "model" => "gpt-3.5-turbo",
        "messages" => [["role"=>"user","content"=>$prompt]],
        "temperature" => 0.8,
        "max_tokens" => $single_line ? 32 : 800,
    ];
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$api_key
        ],
        'body' => json_encode($data),
        'timeout' => 60,
    ];
    $res = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
    if (is_wp_error($res)) return $content;
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);
    if (isset($json['choices'][0]['message']['content'])) {
        $result = trim($json['choices'][0]['message']['content']);
        if ($single_line) $result = preg_replace('/[\r\n]+/', ' ', $result);
        return $result;
    }
    return $content;
}

// ====== AI REWRITE: GEMINI =======
function wal_rewrite_gemini($content, $api_key, $single_line = false) {
    $prompt = $single_line ? $content : "Rewrite the following article to be unique, natural, and engaging (Bahasa Indonesia if possible):\n\n" . $content;
    $data = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body' => json_encode($data),
        'timeout' => 60,
    ];
    $res = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key, $args);
    if (is_wp_error($res)) return $content;
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);
    if (!empty($json['candidates'][0]['content']['parts'][0]['text'])) {
        $result = trim($json['candidates'][0]['content']['parts'][0]['text']);
        if ($single_line) $result = preg_replace('/[\r\n]+/', ' ', $result);
        return $result;
    }
    return $content;
}

// ====== DOWNLOAD & ATTACH IMAGE + CROP BAGIAN BAWAH =======
function wal_download_and_attach_image($image_url, $post_id) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) return 0;
    $file = [
        'name'     => basename(parse_url($image_url, PHP_URL_PATH)),
        'type'     => mime_content_type($tmp),
        'tmp_name' => $tmp,
        'error'    => 0,
        'size'     => filesize($tmp),
    ];
    $attach_id = media_handle_sideload($file, $post_id);
    @unlink($tmp);
    if (is_wp_error($attach_id)) return 0;

    // --- PROSES CROP BAGIAN BAWAH 1/6 ---
    $image_path = get_attached_file($attach_id);
    $image = wp_get_image_editor($image_path);
    if (!is_wp_error($image)) {
        $size = $image->get_size();
        $w = $size['width'];
        $h = $size['height'];
        $crop_h = intval($h * (5/6));
        $image->crop(0, 0, $w, $crop_h, $w, $crop_h);
        $image->save($image_path);
    }
    return $attach_id;
}

?>
