<?php

namespace DeepBlogger\Tests;

class TestHelpers {
    /**
     * Erstellt einen temporären Test-Beitrag
     */
    public static function create_test_post($title, $content, $category_id = null) {
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'post'
        ];

        if ($category_id) {
            $post_data['post_category'] = [$category_id];
        }

        return wp_insert_post($post_data);
    }

    /**
     * Löscht einen Test-Beitrag und zugehörige Daten
     */
    public static function delete_test_post($post_id) {
        if ($thumbnail_id = get_post_thumbnail_id($post_id)) {
            wp_delete_attachment($thumbnail_id, true);
        }
        wp_delete_post($post_id, true);
    }

    /**
     * Erstellt ein temporäres Beitragsbild
     */
    public static function create_test_image($post_id) {
        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['path'] . '/test-image.jpg';
        
        // Erstelle ein leeres Test-Bild
        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $image_path);
        imagedestroy($image);

        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title' => 'Test Image',
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment, $image_path, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $image_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }

    /**
     * Bereinigt die Testumgebung
     */
    public static function cleanup_test_env() {
        // Lösche Test-Optionen
        delete_option('deepblogger_test_category');
        
        // Lösche Test-Uploads
        $upload_dir = wp_upload_dir();
        array_map('unlink', glob($upload_dir['path'] . '/test-*.*'));
    }
} 