<?php
/**
 * OpenAI Service Klasse
 *
 * @package DeepBlogger
 * @subpackage AI
 */

class OpenAIService {
    /**
     * OpenAI API Key
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
     * Cache-Dauer für Modelle in Sekunden (1 Stunde)
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
     * Holt die verfügbaren Modelle von der OpenAI API
     */
    public function get_available_models() {
        $logger = DeepBlogger_Logger::get_instance();
        $logger->debug('Starte Abruf der verfügbaren Modelle von OpenAI');

        $api_key = get_option('deepblogger_openai_api_key');
        if (empty($api_key)) {
            $logger->error('Kein OpenAI API Key konfiguriert');
            return array(
                'success' => false,
                'message' => __('Bitte konfigurieren Sie zuerst Ihren OpenAI API-Schlüssel.', 'deepblogger')
            );
        }

        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            $logger->error('API-Anfrage fehlgeschlagen', array(
                'error_message' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ));
            return array(
                'success' => false,
                'message' => __('Fehler bei der Verbindung zur OpenAI API.', 'deepblogger')
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['data']) || !is_array($data['data'])) {
            $logger->error('Ungültige API-Antwort', array(
                'response' => $body
            ));
            return array(
                'success' => false,
                'message' => __('Ungültige Antwort von der OpenAI API.', 'deepblogger')
            );
        }

        $chat_models = array_filter($data['data'], function($model) {
            return strpos($model['id'], 'gpt') === 0 && $model['object'] === 'model';
        });

        $logger->info('Chat-Modelle gefunden', array(
            'count' => count($chat_models),
            'models' => array_column($chat_models, 'id')
        ));

        if (empty($chat_models)) {
            $logger->warning('Keine Chat-Modelle gefunden');
            return array(
                'success' => false,
                'message' => __('Keine Chat-Modelle verfügbar.', 'deepblogger')
            );
        }

        $formatted_models = array_map(function($model) {
            return array(
                'id' => $model['id'],
                'name' => $this->get_model_display_name($model['id'])
            );
        }, $chat_models);

        $logger->debug('Formatierte Modelle', array(
            'models' => $formatted_models
        ));

        return array(
            'success' => true,
            'models' => $formatted_models,
            'message' => sprintf(
                __('%d Modelle gefunden.', 'deepblogger'),
                count($formatted_models)
            )
        );
    }

    /**
     * Prüft, ob ein Modell unterstützt wird
     *
     * @param string $model_id Model ID von OpenAI
     * @return boolean True wenn unterstützt
     */
    private function is_supported_model($model_id) {
        // Prüfe ob es sich um ein Chat-Modell handelt
        if (strpos($model_id, 'gpt') === false) {
            return false;
        }

        // Prüfe ob das Modell für Chat-Completions verfügbar ist
        $response = wp_remote_get(
            $this->api_base . '/models/' . $model_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                )
            )
        );

        if (is_wp_error($response)) {
            error_log('DeepBlogger OpenAI API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Prüfe ob das Modell aktiv und für Chat geeignet ist
        if (!isset($data['id']) || !isset($data['object']) || $data['object'] !== 'model') {
            return false;
        }

        return true;
    }

    /**
     * Gibt den Anzeigenamen für ein Modell zurück
     *
     * @param string $model_id Model ID von OpenAI
     * @return string Anzeigename
     */
    private function get_model_display_name($model_id) {
        // Hole die Modellinformationen von der API
        $response = wp_remote_get(
            $this->api_base . '/models/' . $model_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                )
            )
        );

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['id'])) {
                // Verwende den API-Namen und formatiere ihn
                $name = $data['id'];
                // Entferne technische Suffixe
                $name = preg_replace('/-\d{4}|-\d{2}|-preview/', '', $name);
                // Mache den Namen lesbarer
                return ucwords(str_replace('-', ' ', $name));
            }
        }

        // Fallback: Verwende die Model-ID als Name
        return ucwords(str_replace('-', ' ', $model_id));
    }

    /**
     * Generiert einen Blogbeitrag basierend auf Titel, Beschreibung und Keywords
     *
     * @param string $title Der Titel des Beitrags
     * @param string $description Die Beschreibung
     * @param array $keywords Die Keywords
     * @return int Die Post-ID des generierten Beitrags
     */
    public function generate_post($title, $description, $keywords) {
        // Erstelle den Prompt für die Beitragsgenerierung
        $prompt = $this->create_post_prompt($title, $description, $keywords);

        // Sende Anfrage an OpenAI
        $response = wp_remote_post(
            $this->api_base . '/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => get_option('deepblogger_openai_model', ''),
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'Du bist ein professioneller Blogger und SEO-Experte. Erstelle einen gut strukturierten, SEO-optimierten Blogbeitrag im HTML-Format.'
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
            throw new Exception('OpenAI API Fehler: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Ungültige Antwort von OpenAI erhalten');
        }

        // Verarbeite den generierten Content
        $content = $this->process_generated_content($data['choices'][0]['message']['content']);

        // Erstelle den WordPress-Beitrag
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        );

        // Füge den Beitrag hinzu
        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            throw new Exception('Fehler beim Erstellen des Beitrags: ' . $post_id->get_error_message());
        }

        // Füge Keywords als Tags hinzu
        if (!empty($keywords)) {
            wp_set_post_tags($post_id, $keywords);
        }

        return $post_id;
    }

    /**
     * Erstellt den Prompt für die Beitragsgenerierung
     *
     * @param string $title Der Titel des Beitrags
     * @param string $description Die Beschreibung
     * @param array $keywords Die Keywords
     * @return string Der generierte Prompt
     */
    private function create_post_prompt($title, $description, $keywords) {
        $prompt = "Erstelle einen SEO-optimierten Blogbeitrag mit folgendem Titel:\n\n";
        $prompt .= "Titel: {$title}\n\n";
        
        if (!empty($description)) {
            $prompt .= "Beschreibung/Kontext:\n{$description}\n\n";
        }
        
        if (!empty($keywords)) {
            $prompt .= "Keywords: " . implode(', ', $keywords) . "\n\n";
        }

        $prompt .= "Anforderungen:\n";
        $prompt .= "1. Erstelle einen gut strukturierten Artikel mit H2- und H3-Überschriften\n";
        $prompt .= "2. Verwende die Keywords natürlich im Text\n";
        $prompt .= "3. Füge relevante interne Verlinkungsmöglichkeiten ein (mit href=\"#\")\n";
        $prompt .= "4. Optimiere den Text für SEO\n";
        $prompt .= "5. Formatiere den Text mit HTML-Tags (p, h2, h3, ul, li, etc.)\n";
        $prompt .= "6. Füge einen Meta-Description-Vorschlag am Ende hinzu\n\n";
        $prompt .= "Bitte generiere den Artikel im HTML-Format.";

        return $prompt;
    }

    /**
     * Verarbeitet den generierten Content
     *
     * @param string $content Der generierte Content
     * @return string Der verarbeitete Content
     */
    private function process_generated_content($content) {
        // Entferne eventuell vorhandene <html>, <head> und <body> Tags
        $content = preg_replace('/<\/?(?:html|head|body)[^>]*>/', '', $content);

        // Extrahiere die Meta-Description
        $meta_description = '';
        if (preg_match('/Meta-Description:\s*(.+)$/im', $content, $matches)) {
            $meta_description = trim($matches[1]);
            $content = preg_replace('/Meta-Description:\s*.+$/im', '', $content);
            
            // Speichere die Meta-Description als Post-Meta
            add_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description, true);
        }

        // Bereinige den HTML-Code
        $allowed_html = array(
            'p' => array(),
            'h2' => array(),
            'h3' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'a' => array(
                'href' => array(),
                'title' => array()
            ),
            'strong' => array(),
            'em' => array(),
            'blockquote' => array()
        );

        return wp_kses($content, $allowed_html);
    }
} 
