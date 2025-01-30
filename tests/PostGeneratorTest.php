<?php

namespace DeepBlogger\Tests;

use PHPUnit\Framework\TestCase;
use DeepBlogger\Services\PostGeneratorService;
use DeepBlogger\Services\OpenAIService;
use DeepBlogger\Services\ContentAnalysisService;

class PostGeneratorTest extends TestCase
{
    private $post_generator;
    private $openai_service;
    private $content_analysis_service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock OpenAI Service
        $this->openai_service = $this->createMock(OpenAIService::class);
        
        // Mock Content Analysis Service
        $this->content_analysis_service = $this->createMock(ContentAnalysisService::class);
        
        // Erstelle PostGeneratorService mit Mocks
        $this->post_generator = new PostGeneratorService($this->openai_service);
        
        // Setze private content_analysis_service über Reflection
        $reflection = new \ReflectionClass($this->post_generator);
        $property = $reflection->getProperty('content_analysis_service');
        $property->setAccessible(true);
        $property->setValue($this->post_generator, $this->content_analysis_service);
    }

    public function testGenerateAndPublishPost()
    {
        // Arrange
        $category = 'Test Kategorie';
        $options = [
            'category_id' => 1,
            'post_status' => 'draft',
            'generate_image' => '0'
        ];

        $expected_title = 'Test Titel';
        $expected_content = 'Test Inhalt';

        // Mock OpenAI Service Antworten
        $this->openai_service
            ->method('generate_title')
            ->willReturn($expected_title);

        $this->openai_service
            ->method('generate_post')
            ->willReturn($expected_content);

        // Mock Content Analysis Service
        $this->content_analysis_service
            ->method('analyze_and_decide')
            ->willReturn([
                'action' => 'create_new',
                'reason' => 'Kein ähnlicher Beitrag gefunden'
            ]);

        // Act
        $post_id = $this->post_generator->generate_and_publish_post($category, $options);

        // Assert
        $this->assertIsInt($post_id);
        $this->assertGreaterThan(0, $post_id);

        // Überprüfe den erstellten Beitrag
        $post = get_post($post_id);
        $this->assertEquals($expected_title, $post->post_title);
        $this->assertEquals($expected_content, $post->post_content);
        $this->assertEquals('draft', $post->post_status);
    }

    public function testGenerateAndPublishPostWithExtension()
    {
        // Arrange
        $category = 'Test Kategorie';
        $options = [
            'category_id' => 1,
            'post_status' => 'draft'
        ];

        $existing_post = [
            'id' => 123,
            'title' => 'Ursprünglicher Titel',
            'content' => 'Ursprünglicher Inhalt'
        ];

        // Mock Content Analysis Service für Erweiterung
        $this->content_analysis_service
            ->method('analyze_and_decide')
            ->willReturn([
                'action' => 'extend',
                'reason' => 'Ähnlicher Beitrag gefunden',
                'existing_post' => $existing_post
            ]);

        $this->content_analysis_service
            ->method('generate_extended_content')
            ->willReturn('Erweiterter Inhalt');

        // Act
        $post_id = $this->post_generator->generate_and_publish_post($category, $options);

        // Assert
        $this->assertIsInt($post_id);
        $post = get_post($post_id);
        $this->assertStringContainsString('Aktualisierung:', $post->post_title);
        $this->assertStringContainsString('Erweiterter Inhalt', $post->post_content);
        $this->assertStringContainsString('Zum ursprünglichen Artikel', $post->post_content);
    }

    public function testGenerateAndPublishPostWithImage()
    {
        // Arrange
        $category = 'Test Kategorie';
        $options = [
            'category_id' => 1,
            'generate_image' => '1'
        ];

        $image_url = 'https://example.com/test-image.jpg';

        // Mock OpenAI Service für Bildgenerierung
        $this->openai_service
            ->method('generate_image')
            ->willReturn($image_url);

        // Act
        $post_id = $this->post_generator->generate_and_publish_post($category, $options);

        // Assert
        $this->assertIsInt($post_id);
        $this->assertTrue(has_post_thumbnail($post_id));
    }

    public function testErrorHandling()
    {
        // Arrange
        $category = 'Test Kategorie';
        $options = ['category_id' => 1];

        // Mock OpenAI Service für Fehlerfall
        $this->openai_service
            ->method('generate_title')
            ->willThrowException(new \Exception('API Fehler'));

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Fehler');

        // Act
        $this->post_generator->generate_and_publish_post($category, $options);
    }
} 