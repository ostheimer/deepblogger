<?php

namespace DeepBlogger\Services;

class ContentAnalysisService {
    private $openai_service;

    public function __construct(OpenAIService $openai_service) {
        $this->openai_service = $openai_service;
    }

    /**
     * Findet ähnliche Beiträge in der angegebenen Kategorie
     */
    public function find_similar_posts($title, $category_id) {
        $args = [
            'post_type' => 'post',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'cat' => $category_id,
            'fields' => 'ids'
        ];

        $posts = get_posts($args);
        $similar_posts = [];

        foreach ($posts as $post_id) {
            $post_title = get_the_title($post_id);
            $similarity = $this->calculate_title_similarity($title, $post_title);
            
            if ($similarity >= 0.7) { // Schwellenwert für Ähnlichkeit
                $similar_posts[] = [
                    'id' => $post_id,
                    'title' => $post_title,
                    'similarity' => $similarity,
                    'content' => get_post_field('post_content', $post_id)
                ];
            }
        }

        return $similar_posts;
    }

    /**
     * Berechnet die Ähnlichkeit zwischen zwei Titeln
     */
    private function calculate_title_similarity($title1, $title2) {
        // Frage OpenAI nach der semantischen Ähnlichkeit
        $prompt = sprintf(
            'Bewerte die semantische Ähnlichkeit der folgenden Titel auf einer Skala von 0 bis 1:\nTitel 1: %s\nTitel 2: %s\nGib nur die Zahl zurück.',
            $title1,
            $title2
        );

        try {
            $similarity = floatval($this->openai_service->generate_similarity_score($prompt));
            return $similarity;
        } catch (\Exception $e) {
            // Fallback: Einfacher Textvergleich
            similar_text(strtolower($title1), strtolower($title2), $percentage);
            return $percentage / 100;
        }
    }

    /**
     * Generiert einen erweiterten Inhalt basierend auf dem vorhandenen Beitrag
     */
    public function generate_extended_content($existing_post, $options = []) {
        $existing_content = $existing_post['content'];
        $existing_title = $existing_post['title'];

        // Erstelle einen speziellen Prompt für die Erweiterung
        $extension_prompt = sprintf(
            'Der folgende Artikel soll erweitert werden. Analysiere den bestehenden Inhalt und ergänze neue, relevante Informationen. Vermeide Wiederholungen und stelle sicher, dass der neue Inhalt den bestehenden Artikel sinnvoll ergänzt.\n\nBestehender Artikel:\nTitel: %s\nInhalt: %s\n\nSchreibe eine Erweiterung im [SCHREIBSTIL] Stil mit etwa [LÄNGE] Wörtern. Beziehe dich auf den ursprünglichen Artikel und füge neue Perspektiven oder aktuelle Informationen hinzu.',
            $existing_title,
            $existing_content
        );

        // Ersetze den Standard-Prompt durch den Erweiterungs-Prompt
        $options['custom_prompt'] = $extension_prompt;

        return $this->openai_service->generate_post($options);
    }

    /**
     * Entscheidet, ob ein neuer Beitrag erstellt oder ein bestehender erweitert werden soll
     */
    public function analyze_and_decide($proposed_title, $category_id) {
        $similar_posts = $this->find_similar_posts($proposed_title, $category_id);

        if (empty($similar_posts)) {
            return [
                'action' => 'create_new',
                'reason' => 'Kein ähnlicher Beitrag gefunden'
            ];
        }

        // Finde den ähnlichsten Beitrag
        usort($similar_posts, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        $most_similar = $similar_posts[0];

        // Frage OpenAI nach der Empfehlung
        $decision_prompt = sprintf(
            'Analysiere diese beiden Artikel:\n1. Vorgeschlagener Titel: %s\n2. Bestehender Artikel: %s\n\nSollte ein neuer Artikel erstellt werden oder der bestehende erweitert werden? Antworte nur mit "create_new" oder "extend".',
            $proposed_title,
            $most_similar['title']
        );

        try {
            $decision = $this->openai_service->get_content_decision($decision_prompt);
            return [
                'action' => $decision,
                'reason' => $decision === 'extend' ? 'Ähnlicher Beitrag gefunden (Ähnlichkeit: ' . round($most_similar['similarity'] * 100) . '%)' : 'Neues Thema trotz Ähnlichkeit',
                'existing_post' => $decision === 'extend' ? $most_similar : null
            ];
        } catch (\Exception $e) {
            // Fallback: Bei hoher Ähnlichkeit erweitern
            return [
                'action' => $most_similar['similarity'] > 0.8 ? 'extend' : 'create_new',
                'reason' => 'Automatische Entscheidung basierend auf Ähnlichkeit',
                'existing_post' => $most_similar['similarity'] > 0.8 ? $most_similar : null
            ];
        }
    }

    public function calculateSimilarity(string $text1, string $text2): float
    {
        // Implementierung folgt
        return 0.1;
    }
} 