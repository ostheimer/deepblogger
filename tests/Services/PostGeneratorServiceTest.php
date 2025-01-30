<?php
namespace DeepBlogger\Tests\Services;

use DeepBlogger\Services\PostGeneratorService;
use DeepBlogger\Services\OpenAIService;
use WP_UnitTestCase;

class PostGeneratorServiceTest extends WP_UnitTestCase {
    private $post_generator;
    private $openai_service;

    public function setUp(): void {
        parent::setUp();
        
        // Mock des OpenAI-Services
        $this->openai_service = $this->createMock(OpenAIService::class);
        $this->post_generator = new PostGeneratorService($this->openai_service);

        // Standard-Kategorien erstellen
        $category_ids = [];
        $category_ids[] = wp_create_category('Test Kategorie 1');
        $category_ids[] = wp_create_category('Test Kategorie 2');
        
        update_option('deepblogger_post_categories', $category_ids);
    }

    public function tearDown(): void {
        parent::tearDown();
        delete_option('deepblogger_post_categories');
    }

    public function test_generate_and_publish_post() {
        $test_content = '<h2>Test Artikel</h2><p>Dies ist ein Testinhalt.</p>';
        $test_topic = 'Test Thema';

        // OpenAI-Service Mock konfigurieren
        $this->openai_service
            ->expects($this->once())
            ->method('generate_post')
            ->with($test_topic)
            ->willReturn($test_content);

        // Post generieren
        $post_id = $this->post_generator->generate_and_publish_post($test_topic);

        // Prüfen, ob der Post korrekt erstellt wurde
        $post = get_post($post_id);
        
        $this->assertNotNull($post);
        $this->assertEquals($test_topic, $post->post_title);
        $this->assertEquals($test_content, $post->post_content);
        $this->assertEquals('draft', $post->post_status);

        // Prüfen, ob die Kategorien korrekt zugewiesen wurden
        $categories = wp_get_post_categories($post_id);
        $this->assertEquals(
            get_option('deepblogger_post_categories'),
            $categories
        );

        // Prüfen, ob SEO-Metadaten gesetzt wurden
        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $this->assertNotEmpty($meta_desc);
    }

    public function test_generate_post_with_error() {
        $test_topic = 'Test Thema';
        $error_message = 'Test Fehler';

        // OpenAI-Service Mock für Fehlerfall konfigurieren
        $this->openai_service
            ->expects($this->once())
            ->method('generate_post')
            ->with($test_topic)
            ->willThrowException(new \Exception($error_message));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($error_message);

        $this->post_generator->generate_and_publish_post($test_topic);
    }
} 