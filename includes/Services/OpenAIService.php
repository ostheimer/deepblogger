<?php
namespace DeepBlogger\Services;

/**
 * Service class for OpenAI API integration
 */
class OpenAIService {
    /**
     * OpenAI API key
     *
     * @var string
     */
    private $apiKey;

    /**
     * OpenAI model to use
     *
     * @var string
     */
    private $model;

    /**
     * OpenAI API base endpoint
     *
     * @var string
     */
    private $api_base = 'https://api.openai.com/v1';

    /**
     * OpenAI API endpoint for chat completions
     *
     * @var string
     */
    private $api_endpoint;

    /**
     * Available models cache
     *
     * @var array
     */
    private static $available_models = null;

    /**
     * Constructor
     */
    public function __construct(string $apiKey, string $model = 'gpt-3.5-turbo') {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->api_endpoint = $this->api_base . '/chat/completions';
    }

    /**
     * Get available models from OpenAI API
     *
     * @return array Array of available models
     * @throws \Exception If API request fails
     */
    public function get_available_models() {
        if (empty($this->apiKey)) {
            throw new \Exception(\esc_html__('OpenAI API key is not configured', 'deepblogger'));
        }

        if (self::$available_models !== null) {
            return self::$available_models;
        }

        try {
            $response = \wp_remote_get($this->api_base . '/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30
            ]);

            if (\is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $status_code = \wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                $error_message = \wp_remote_retrieve_response_message($response);
                throw new \Exception(\esc_html__('Invalid response from OpenAI API', 'deepblogger') . ': ' . $error_message);
            }

            $body = json_decode(\wp_remote_retrieve_body($response), true);
            
            if (!isset($body['data']) || !is_array($body['data'])) {
                throw new \Exception(\esc_html__('Invalid response from OpenAI API', 'deepblogger'));
            }

            // Filter für Chat-Modelle
            $chat_models = array_filter($body['data'], function($model) {
                return strpos($model['id'], 'gpt-') === 0;
            });

            if (empty($chat_models)) {
                throw new \Exception(\esc_html__('No GPT models found in OpenAI API response', 'deepblogger'));
            }

            self::$available_models = array_map(function($model) {
                return [
                    'id' => $model['id'],
                    'name' => $this->format_model_name($model['id'])
                ];
            }, $chat_models);

            return self::$available_models;
        } catch (\Exception $e) {
            error_log('DeepBlogger: Error fetching models: ' . $e->getMessage());
            throw new \Exception(sprintf(
                \esc_html__('Error fetching models: %s', 'deepblogger'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Format model name for display
     *
     * @param string $model_id The model ID from OpenAI
     * @return string Formatted model name
     */
    private function format_model_name($model_id) {
        $name = str_replace(['gpt-4', 'gpt-3.5-turbo'], ['GPT-4', 'GPT-3.5 Turbo'], $model_id);
        return ucwords(str_replace('-', ' ', $name));
    }

    /**
     * Get model description
     *
     * @param string $model_id The model ID from OpenAI
     * @return string Model description
     */
    private function get_model_description($model_id) {
        if (strpos($model_id, 'gpt-4') === 0) {
            return \esc_html__('Most capable GPT-4 model, better at complex tasks', 'deepblogger');
        }
        if (strpos($model_id, 'gpt-3.5-turbo') === 0) {
            return \esc_html__('Fast and cost-effective for most tasks', 'deepblogger');
        }
        return '';
    }

    /**
     * Generate a blog post using OpenAI's GPT model
     *
     * @param string $topic The topic to write about
     * @param array $options Additional options for content generation
     * @return string The generated content
     * @throws \Exception If API key is missing or API request fails
     */
    public function generate_post($topic, $options = []) {
        if (empty($this->apiKey)) {
            error_log('DeepBlogger: OpenAI API key is not configured');
            throw new \Exception(\esc_html__('OpenAI API key is not configured', 'deepblogger'));
        }

        try {
            $prompt = $this->create_prompt($topic, $options);
            
            error_log('DeepBlogger: Sending request to OpenAI API');
            error_log('DeepBlogger: Using model: ' . $this->model);
            
            $response = \wp_remote_post($this->api_endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->get_system_prompt($options)
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => $this->get_max_tokens($options)
                ]),
                'timeout' => 60
            ]);

            if (\is_wp_error($response)) {
                error_log('DeepBlogger: WordPress HTTP API Error: ' . $response->get_error_message());
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(\wp_remote_retrieve_body($response), true);

            if (empty($body['choices'][0]['message']['content'])) {
                error_log('DeepBlogger: Empty response from OpenAI API');
                error_log('DeepBlogger: Response body: ' . print_r($body, true));
                throw new \Exception(\esc_html__('No response received from OpenAI', 'deepblogger'));
            }

            error_log('DeepBlogger: Successfully generated content for topic: ' . $topic);
            return $this->format_response($body['choices'][0]['message']['content']);
            
        } catch (\Exception $e) {
            error_log('DeepBlogger: Error in generate_post: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a title for a post
     *
     * @param string $topic The topic to write about
     * @return string Generated title
     */
    public function generate_title($topic) {
        $prompt = sprintf(
            'Generate a catchy and SEO-friendly title for a blog post about "%s". ' .
            'The title should be engaging and under 60 characters.',
            $topic
        );

        try {
            $response = \wp_remote_post($this->api_endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a professional copywriter who creates engaging blog post titles.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 50
                ]),
                'timeout' => 30
            ]);

            if (\is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(\wp_remote_retrieve_body($response), true);
            return trim($body['choices'][0]['message']['content'], '"');
        } catch (\Exception $e) {
            error_log('DeepBlogger: Error generating title: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate an image using DALL-E
     *
     * @param string $prompt The prompt for image generation
     * @return string Image URL
     */
    public function generate_image($prompt) {
        try {
            $response = \wp_remote_post($this->api_base . '/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'response_format' => 'url'
                ]),
                'timeout' => 30
            ]);

            if (\is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(\wp_remote_retrieve_body($response), true);
            return $body['data'][0]['url'] ?? '';
        } catch (\Exception $e) {
            error_log('DeepBlogger: Error generating image: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Create the prompt for the GPT model
     *
     * @param string $topic The topic to write about
     * @param array $options Additional options for content generation
     * @return string The formatted prompt
     */
    private function create_prompt($topic, $options) {
        $length_descriptions = [
            'short' => '300-400',
            'medium' => '600-800',
            'long' => '1200-1500'
        ];

        $word_count = $length_descriptions[$options['length'] ?? 'medium'];

        return sprintf(
            'Write a blog post about "%s" in approximately %s words. ' .
            'The article should include: ' .
            '1. An engaging introduction ' .
            '2. At least three main sections with subheadings ' .
            '3. Practical examples or use cases ' .
            '4. A concluding summary ' .
            'Format the text with HTML tags for headings (h2, h3) and paragraphs (p).',
            \esc_html($topic),
            $word_count
        );
    }

    /**
     * Get the system prompt based on options
     *
     * @param array $options Content generation options
     * @return string System prompt
     */
    private function get_system_prompt($options) {
        $style_prompts = [
            'professional' => 'You are a professional business blogger who writes clear, informative content.',
            'casual' => 'You are a friendly blogger who writes in a conversational, engaging style.',
            'academic' => 'You are an academic expert who writes detailed, well-researched content.'
        ];

        $base_prompt = $style_prompts[$options['style'] ?? 'professional'];
        $language_prompt = '';

        if (!empty($options['language'])) {
            $language_prompt = sprintf(' Write exclusively in %s.', $this->get_language_name($options['language']));
        }

        return $base_prompt . $language_prompt;
    }

    /**
     * Get the maximum tokens based on content length
     *
     * @param array $options Content generation options
     * @return int Maximum tokens
     */
    private function get_max_tokens($options) {
        $length_tokens = [
            'short' => 1500,
            'medium' => 2500,
            'long' => 4000
        ];

        return $length_tokens[$options['length'] ?? 'medium'];
    }

    /**
     * Get the full language name from code
     *
     * @param string $code Language code
     * @return string Language name
     */
    private function get_language_name($code) {
        $languages = [
            'de' => 'German',
            'en' => 'English'
        ];

        return $languages[$code] ?? 'English';
    }

    /**
     * Format the API response for WordPress
     *
     * @param string $content The raw content from OpenAI
     * @return string The formatted content
     */
    private function format_response($content) {
        // Basic formatting
        $content = \wpautop($content);
        
        // Ensure all links are nofollow
        $content = preg_replace('/<a(.*?)>/i', '<a$1 rel="nofollow">', $content);
        
        return $content;
    }

    /**
     * Generiert einen Ähnlichkeitswert zwischen 0 und 1
     */
    public function generate_similarity_score($prompt) {
        $response = $this->client->chat()->create([
            'model' => $this->get_model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du bist ein Experte für semantische Textanalyse. Antworte nur mit einer Zahl zwischen 0 und 1, die die semantische Ähnlichkeit der Texte repräsentiert.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 10
        ]);

        $score = floatval(trim($response->choices[0]->message->content));
        return max(0, min(1, $score)); // Stelle sicher, dass der Wert zwischen 0 und 1 liegt
    }

    /**
     * Entscheidet, ob ein neuer Artikel erstellt oder ein bestehender erweitert werden soll
     */
    public function get_content_decision($prompt) {
        $response = $this->client->chat()->create([
            'model' => $this->get_model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du bist ein Experte für Content-Strategie. Antworte nur mit "create_new" oder "extend".'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 10
        ]);

        $decision = trim(strtolower($response->choices[0]->message->content));
        return in_array($decision, ['create_new', 'extend']) ? $decision : 'create_new';
    }

    public function generateTitle(string $topic): string
    {
        // Implementierung folgt
        return 'Test Titel';
    }

    public function generateContent(string $topic, bool $isExtension = false): string
    {
        // Implementierung folgt
        return 'Test Inhalt';
    }

    public function generateImage(string $prompt): string
    {
        // Implementierung folgt
        return 'test-image.jpg';
    }
} 