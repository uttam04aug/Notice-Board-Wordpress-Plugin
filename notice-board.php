<?php
/*
Plugin Name: Notice Board
Plugin URI: https://github.com/uttam04aug/Notice-Board-Wordpress-Plugin
Description: A simple Notice Board plugin with Bold & Italic editor support, success messages, and a scrolling notice display on the frontend.
Version: 1.2
Author: Uttam Singh
Author URI: https://github.com/uttam04aug
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: notice-board
*/

if (!defined('ABSPATH')) exit;

/* ===============================
   CREATE TABLE
================================ */
register_activation_hook(__FILE__, 'nb_create_table');
function nb_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'notice_board';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notice_text TEXT NOT NULL,
        notice_date DATE NOT NULL
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/* ===============================
   ADMIN MENU
================================ */
add_action('admin_menu', 'nb_admin_menu');
function nb_admin_menu() {
    add_menu_page(
        'Notice Board',
        'Notice Board',
        'manage_options',
        'notice-board',
        'nb_admin_page',
        'dashicons-megaphone'
    );
}

/* ===============================
   ADMIN PAGE
================================ */
function nb_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'notice_board';

    $notice_updated = false;
    $notice_saved = false;

    // SAVE
    if (isset($_POST['nb_save'])) {

    $date = !empty($_POST['notice_date']) ? $_POST['notice_date'] : current_time('Y-m-d');
    $text = wp_unslash($_POST['notice_text']);

    // 🔹 MULTIPLE NOTICE MODE
    if (!empty($_POST['nb_multiple'])) {

        $notices = array_filter(array_map('trim', explode('---', $text)));

        foreach ($notices as $single_notice) {
            $wpdb->insert($table, [
                'notice_text' => $single_notice,
                'notice_date' => $date
            ]);
        }

    } 
    // 🔹 SINGLE NOTICE MODE
    else {
        $wpdb->insert($table, [
            'notice_text' => $text,
            'notice_date' => $date
        ]);
    }

    $notice_saved = true;
}


    // DELETE
    if (isset($_GET['delete'])) {
        $wpdb->delete($table, ['id' => intval($_GET['delete'])]);
    }

    // EDIT FETCH
    $edit = null;
    if (isset($_GET['edit'])) {
        $edit = $wpdb->get_row("SELECT * FROM $table WHERE id=" . intval($_GET['edit']));
    }

    // UPDATE
    if (isset($_POST['nb_update'])) {
        $updated = $wpdb->update(
            $table,
            [
                'notice_text' => wp_unslash($_POST['notice_text']), // keep HTML
                'notice_date' => $_POST['notice_date']
            ],
            ['id' => intval($_POST['notice_id'])]
        );
        if ($updated !== false) {
            $notice_updated = true;
            // refetch edited row to show updated content
            $edit = $wpdb->get_row("SELECT * FROM $table WHERE id=" . intval($_POST['notice_id']));
        }
    }
    ?>

    <div class="wrap">
        <h1>Notice Board</h1>

        <?php if ($notice_saved) : ?>
            <div id="message" class="updated notice is-dismissible">
                <p>Notice saved successfully!</p>
            </div>
        <?php endif; ?>

        <?php if ($notice_updated) : ?>
            <div id="message" class="updated notice is-dismissible">
                <p>Notice updated successfully!</p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php
            $content = $edit->notice_text ?? '';

            wp_editor($content, 'notice_editor', array(
                'textarea_name' => 'notice_text',
                'teeny'         => true,
                'media_buttons' => false,
                'quicktags'     => true,
                'tinymce'       => array(
                    'toolbar1' => 'bold italic | alignleft aligncenter alignright | bullist numlist',
                    'toolbar2' => '',
                    'valid_elements' => 'b,strong,i,em,p,br',
                ),
            ));
            ?>

            <br>
            <input type="date" name="notice_date" value="<?php echo $edit->notice_date ?? ''; ?>">
            <br><br>

            <?php if ($edit) { ?>
                <input type="hidden" name="notice_id" value="<?php echo $edit->id; ?>">
                <input type="submit" name="nb_update" class="button button-primary" value="Update Notice">
            <?php } else { ?>
                <input type="submit" name="nb_save" class="button button-primary" value="Save Notice">
            <?php } ?>
        </form>

        <hr>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Notice</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY notice_date DESC");
                foreach ($rows as $r) {
                    echo "<tr>
                        <td>{$r->notice_text}</td>
                        <td>{$r->notice_date}</td>
                        <td>
                            <a href='?page=notice-board&edit={$r->id}'>Edit</a> |
                            <a href='?page=notice-board&delete={$r->id}' onclick='return confirm(\"Delete notice?\")'>Delete</a>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php }

/* ===============================
   FRONTEND SHORTCODE
================================ */
add_shortcode('notice_board', 'nb_frontend');
function nb_frontend() {
    global $wpdb;
    $table = $wpdb->prefix . 'notice_board';
    $data = $wpdb->get_results("SELECT * FROM $table ORDER BY notice_date DESC");

    if (!$data) return '';

    ob_start(); 
    ?>
    <style>
        .notice-board-wrapper {
            height: 250px; /* adjust as needed */
            overflow: hidden;
            position: relative;
        }
        .notice-board {
            display: block;
        }
        .notice-item {
            padding: 10px;  margin-bottom:10px; border-radius:10px; background:#fff6e7;
        }
        .notice-board b, .notice-board strong { font-weight: bold; }
        .notice-board i, .notice-board em { font-style: italic; }
    </style>

    <div class="notice-board-wrapper">
        <div class="notice-board">
            <?php foreach ($data as $n): ?>
                <div class="notice-item">
                    <div class="notice-text">
                        <?php 
                        $allowed_tags = [
                            'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'br' => [], 'p' => [],
                        ];
                        echo wpautop(wp_kses($n->notice_text, $allowed_tags)); 
                        ?>
                    </div>
                    <div class="notice-date" style="background:#ffb129; padding:5px 10px; color:#fff;display:inline-block; font-size:12px; border-radius:30px;">
                       Date -  <?php echo esc_html($n->notice_date); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

 <script>
(function(){
    const wrapper = document.querySelector('.notice-board-wrapper');
    const board = wrapper.querySelector('.notice-board');

    // Duplicate content for seamless scroll
    board.innerHTML += board.innerHTML;

    let speed = 0.5; // pixels per frame
    let paused = false;
    let pos = 0;

    wrapper.addEventListener('mouseenter', () => paused = true);
    wrapper.addEventListener('mouseleave', () => paused = false);

    const originalHeight = board.scrollHeight / 2;

    function scrollStep() {
        if (!paused) {
            pos += speed;
            if (pos >= originalHeight) pos = 0;
            board.style.transform = `translateY(-${pos}px)`;
        }
        requestAnimationFrame(scrollStep);
    }

    // Initialize
    board.style.willChange = 'transform';
    scrollStep();
})();
</script>



    <?php

    return ob_get_clean();
}
