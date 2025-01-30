<?php
/**
 * Deepseek Service Klasse
 *
 * @package DeepBlogger
 * @subpackage Deepseek
 */

class DeepseekService {
    /**
     * OpenAI API Key (wird für Deepseek verwendet)
     *
     * @var string
     */
    private $api_key;

    /**
     * OpenAI API Base URL
     *
     * @var string
     */
    private $api_base = 'https://api.openai.com/v1';

    /**
     * Cache-Dauer für Analysen in Sekunden (1 Stunde)
     *
     * @var int
     */
    private $cache_duration = 3600;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->api_key = get_option('deepblogger_openai_api_key', '');
    }

    /**
     * Analysiert eine Kategorie und schlägt Themen vor
     *
     * @param int $category_id Die Kategorie-ID
     * @return array Array mit Themenvorschlägen
     */
    public function analyze_category($category_id) {
        // Prüfe Cache
        $cache_key = 'deepblogger_category_analysis_' . $category_id;
        $cached_analysis = get_transient($cache_key);
        if ($cached_analysis !== false) {
            return $cached_analysis;
        }

        // Hole Kategorie-Details
        $category = get_category($category_id);
        if (!$category) {
            return array('error' => 'Kategorie nicht gefunden');
        }

        // Hole existierende Beiträge der Kategorie
        $existing_posts = get_posts(array(
            'category' => $category_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        // Sammle Titel und Auszüge
        $existing_content = array();
        foreach ($existing_posts as $post_id) {
            $post = get_post($post_id);
            $existing_content[] = array(
                'title' => $post->post_title,
                'excerpt' => wp_strip_all_tags($post->post_content)
            );
        }

        // Erstelle Prompt für die Analyse
        $prompt = $this->create_analysis_prompt($category, $existing_content);

        // Hole das ausgewählte Modell
        $model = get_option('deepblogger_deepseek_model', 'deepseek-chat');

        // Sende Anfrage an OpenAI
        $response = wp_remote_post(
            $this->api_base . '/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'Du bist ein SEO- und Content-Experte, der Themenvorschläge für Blog-Artikel macht.'
                        ),
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'temperature' => 0.7
                ))
            )
        );

        if (is_wp_error($response)) {
            error_log('DeepBlogger Deepseek Error: ' . $response->get_error_message());
            return array('error' => 'API-Fehler');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('DeepBlogger Deepseek Error: Ungültige Antwort erhalten');
            return array('error' => 'Ungültige Antwort');
        }

        // Verarbeite die Antwort
        $analysis = $this->process_analysis_response($data['choices'][0]['message']['content']);

        // Cache die Ergebnisse
        set_transient($cache_key, $analysis, $this->cache_duration);

        return $analysis;
    }

    /**
     * Erstellt den Prompt für die Kategorieanalyse
     *
     * @param WP_Term $category Die Kategorie
     * @param array $existing_content Existierende Inhalte
     * @return string Der Prompt
     */
    private function create_analysis_prompt($category, $existing_content) {
        $prompt = "Analysiere die folgende Blog-Kategorie und schlage neue Themen vor:\n\n";
        $prompt .= "Kategorie: " . $category->name . "\n";
        $prompt .= "Beschreibung: " . $category->description . "\n\n";

        if (!empty($existing_content)) {
            $prompt .= "Existierende Artikel:\n";
            foreach ($existing_content as $content) {
                $prompt .= "- " . $content['title'] . "\n";
            }
        }

        $prompt .= "\nBitte schlage 5 neue Themen vor, die:\n";
        $prompt .= "1. Noch nicht behandelt wurden\n";
        $prompt .= "2. Relevant für die Kategorie sind\n";
        $prompt .= "3. SEO-Potenzial haben\n";
        $prompt .= "4. Aktuelle Trends berücksichtigen\n\n";
        $prompt .= "Formatiere die Antwort als JSON mit den Feldern 'title', 'description' und 'keywords' für jeden Vorschlag.";

        return $prompt;
    }

    /**
     * Verarbeitet die API-Antwort
     *
     * @param string $response_content Die Antwort von OpenAI
     * @return array Verarbeitete Themenvorschläge
     */
    private function process_analysis_response($response_content) {
        try {
            $data = json_decode($response_content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }

            // Fallback: Versuche strukturierten Text zu parsen
            $suggestions = array();
            $lines = explode("\n", $response_content);
            $current_suggestion = array();

            foreach ($lines as $line) {
                if (preg_match('/^Titel: (.+)$/', $line, $matches)) {
                    if (!empty($current_suggestion)) {
                        $suggestions[] = $current_suggestion;
                    }
                    $current_suggestion = array('title' => trim($matches[1]));
                } elseif (preg_match('/^Beschreibung: (.+)$/', $line, $matches)) {
                    $current_suggestion['description'] = trim($matches[1]);
                } elseif (preg_match('/^Keywords: (.+)$/', $line, $matches)) {
                    $current_suggestion['keywords'] = array_map('trim', explode(',', $matches[1]));
                }
            }

            if (!empty($current_suggestion)) {
                $suggestions[] = $current_suggestion;
            }

            return $suggestions;
        } catch (Exception $e) {
            error_log('DeepBlogger Deepseek Error: ' . $e->getMessage());
            return array('error' => 'Fehler bei der Verarbeitung der Antwort');
        }
    }
} 
