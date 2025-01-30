<?php
namespace DeepBlogger\Services;

/**
 * Service class for generating and publishing posts
 */
class PostGeneratorService {
    /**
     * OpenAI service instance
     *
     * @var OpenAIService
     */
    private $openAIService;
    private $contentAnalysisService;

    /**
     * Constructor
     *
     * @param OpenAIService $openAIService OpenAI service instance
     */
    public function __construct(OpenAIService $openAIService, ContentAnalysisService $contentAnalysisService) {
        $this->openAIService = $openAIService;
        $this->contentAnalysisService = $contentAnalysisService;
    }

    /**
     * Generate and publish a post
     *
     * @param string $topic Topic name
     * @param bool $isExtension Whether the post is an extension
     * @param bool $withImage Whether to generate and attach an image
     * @return int|false Post ID on success, false on failure
     */
    public function generateAndPublishPost(string $topic, bool $isExtension = false, bool $withImage = false): int
    {
        try {
            // Generiere Titel und Inhalt
            $title = $this->openAIService->generateTitle($topic);
            $content = $this->openAIService->generateContent($topic, $isExtension);

            // Wenn es eine Erweiterung ist, füge "Aktualisierung:" zum Titel hinzu
            if ($isExtension) {
                $title = 'Aktualisierung: ' . $title;
                $content = 'Erweiterter Inhalt: ' . $content;
            }

            // Erstelle den Post
            $postData = array(
                'post_title'    => $title,
                'post_content'  => $content,
                'post_status'   => 'draft',
                'post_type'     => 'post'
            );

            // Füge den Post ein
            $postId = wp_insert_post($postData);

            // Wenn ein Bild gewünscht ist, generiere und füge es hinzu
            if ($withImage) {
                $imageUrl = $this->openAIService->generateImage($topic);
                $this->attachImageToPost($postId, $imageUrl);
            }

            return $postId;
        } catch (\Exception $e) {
            throw new \Exception('Fehler beim Generieren des Posts: ' . $e->getMessage());
        }
    }

    private function attachImageToPost(int $postId, string $imageUrl): void
    {
        // Lade die erforderlichen WordPress-Dateien
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Prüfe, ob es sich um eine lokale Datei handelt
        if (strpos($imageUrl, 'file://') === 0) {
            $localFile = substr($imageUrl, 7);
            if (!file_exists($localFile)) {
                throw new \Exception('Die lokale Datei existiert nicht: ' . $localFile);
            }

            // Erstelle das Attachment direkt aus der lokalen Datei
            $filetype = wp_check_filetype(basename($localFile), null);
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name(basename($localFile)),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            // Füge das Attachment zur Datenbank hinzu
            $attachmentId = wp_insert_attachment($attachment, $localFile, $postId);

            // Generiere die Metadaten für das Attachment
            $attachmentData = wp_generate_attachment_metadata($attachmentId, $localFile);
            wp_update_attachment_metadata($attachmentId, $attachmentData);

            // Setze das Bild als Beitragsbild
            set_post_thumbnail($postId, $attachmentId);
        } else {
            // Lade das Bild herunter
            $tmpFile = download_url($imageUrl);

            if (is_wp_error($tmpFile)) {
                throw new \Exception('Fehler beim Herunterladen des Bildes: ' . $tmpFile->get_error_message());
            }

            // Bereite die Datei für den Upload vor
            $fileArray = array(
                'name' => basename($imageUrl),
                'tmp_name' => $tmpFile
            );

            // Füge das Bild zur Medienbibliothek hinzu
            $attachmentId = media_handle_sideload($fileArray, $postId);

            if (is_wp_error($attachmentId)) {
                @unlink($tmpFile);
                throw new \Exception('Fehler beim Hochladen des Bildes: ' . $attachmentId->get_error_message());
            }

            // Setze das Bild als Beitragsbild
            set_post_thumbnail($postId, $attachmentId);
        }
    }

    public function handle_generate_posts() {
        check_ajax_referer('deepblogger_generate_posts', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'deepblogger'));
        }

        try {
            wp_send_json_progress([
                'step' => 'preparing',
                'message' => __('Vorbereitung...', 'deepblogger')
            ]);

            $categories = get_option('deepblogger_post_categories', []);
            if (empty($categories)) {
                wp_send_json_error(__('No categories selected', 'deepblogger'));
            }

            $posts_per_category = get_option('deepblogger_posts_per_category', 1);
            $total_posts = count($categories) * $posts_per_category;
            $posts_created = 0;

            foreach ($categories as $category_id) {
                $category = get_category($category_id);
                if ($category) {
                    for ($i = 0; $i < $posts_per_category; $i++) {
                        wp_send_json_progress([
                            'step' => 'generating_content',
                            'message' => sprintf(
                                __('Generiere Inhalt für Kategorie "%s" (Beitrag %d von %d)...', 'deepblogger'),
                                $category->name,
                                $i + 1,
                                $posts_per_category
                            )
                        ]);

                        $post_id = $this->generate_and_publish_post(
                            $category->name,
                            [
                                'category_id' => $category_id,
                                'language' => get_option('deepblogger_content_language', 'site_language'),
                                'writing_style' => get_option('deepblogger_writing_style', 'professional'),
                                'content_length' => get_option('deepblogger_content_length', 'medium'),
                                'generate_image' => get_option('deepblogger_generate_images', '1'),
                                'post_status' => get_option('deepblogger_post_status', 'publish')
                            ]
                        );

                        if ($post_id) {
                            $posts_created++;
                            wp_send_json_progress([
                                'step' => 'publishing',
                                'message' => sprintf(
                                    __('Beitrag %d von %d erstellt', 'deepblogger'),
                                    $posts_created,
                                    $total_posts
                                )
                            ]);
                        }
                    }
                }
            }

            wp_send_json_success(sprintf(
                __('%d Beiträge erfolgreich erstellt', 'deepblogger'),
                $posts_created
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
} 