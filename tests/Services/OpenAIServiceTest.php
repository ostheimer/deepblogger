<?php
namespace DeepBlogger\Tests\Services;

use DeepBlogger\Services\OpenAIService;
use WP_UnitTestCase;

class OpenAIServiceTest extends WP_UnitTestCase {
    private $openai_service;

    public function setUp(): void {
        parent::setUp();
        
        // API-Key für Tests setzen
        update_option('deepblogger_openai_api_key', 'test_api_key');
        
        $this->openai_service = new OpenAIService();
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('deepblogger_openai_api_key');
    }

    public function test_generate_post_without_api_key() {
        delete_option('deepblogger_openai_api_key');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API-Schlüssel ist nicht konfiguriert');
        
        $this->openai_service->generate_post('Test Thema');
    }

    public function test_generate_post_with_api_key() {
        // Mock der wp_remote_post Funktion
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [
                            [
                                'message' => [
                                    'content' => '<h2>Test Artikel</h2><p>Dies ist ein Testinhalt.</p>'
                                ]
                            ]
                        ]
                    ])
                ];
            }
            return $preempt;
        }, 10, 3);

        $content = $this->openai_service->generate_post('Test Thema');

        // Prüfen, ob der Inhalt korrekt formatiert wurde
        $this->assertStringContainsString('<h2>Test Artikel</h2>', $content);
        $this->assertStringContainsString('<p>Dies ist ein Testinhalt.</p>', $content);
    }

    public function test_generate_post_api_error() {
        // Mock der wp_remote_post Funktion für Fehlerszenario
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return new \WP_Error('http_request_failed', 'API-Fehler');
            }
            return $preempt;
        }, 10, 3);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API-Fehler');

        $this->openai_service->generate_post('Test Thema');
    }
} 