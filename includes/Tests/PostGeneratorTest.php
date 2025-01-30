<?php

namespace DeepBlogger\Tests;

use PHPUnit\Framework\TestCase;
use DeepBlogger\Services\PostGeneratorService;
use DeepBlogger\Services\OpenAIService;
use DeepBlogger\Services\ContentAnalysisService;

class PostGeneratorTest extends TestCase
{
    private $postGenerator;
    private $openAIService;
    private $contentAnalysisService;
    private $testImagePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Stelle sicher, dass WordPress-Funktionen verfügbar sind
        if (!function_exists('get_post')) {
            require_once(ABSPATH . 'wp-includes/post.php');
        }
        
        $this->testImagePath = dirname(dirname(__DIR__)) . '/tests/fixtures/test-image.jpg';
        
        // Mock OpenAIService
        $this->openAIService = $this->createMock(OpenAIService::class);
        $this->openAIService->method('generateTitle')
            ->willReturn('Test Titel');
        $this->openAIService->method('generateContent')
            ->willReturn('Test Inhalt');
        $this->openAIService->method('generateImage')
            ->willReturn('file://' . $this->testImagePath);
            
        // Mock ContentAnalysisService
        $this->contentAnalysisService = $this->createMock(ContentAnalysisService::class);
        $this->contentAnalysisService->method('calculateSimilarity')
            ->willReturn(0.1);
            
        // Erstelle PostGeneratorService mit Mocks
        $this->postGenerator = new PostGeneratorService(
            $this->openAIService,
            $this->contentAnalysisService
        );
    }

    public function testGenerateAndPublishPost()
    {
        $result = $this->postGenerator->generateAndPublishPost('Test Thema');
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        $post = \get_post($result);
        $this->assertEquals('Test Titel', $post->post_title);
        $this->assertEquals('Test Inhalt', $post->post_content);
    }

    public function testGenerateAndPublishPostWithExtension()
    {
        $result = $this->postGenerator->generateAndPublishPost('Test Thema', true);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        $post = \get_post($result);
        $this->assertStringContainsString('Aktualisierung:', $post->post_title);
        $this->assertStringContainsString('Erweiterter Inhalt', $post->post_content);
    }

    public function testGenerateAndPublishPostWithImage()
    {
        $result = $this->postGenerator->generateAndPublishPost('Test Thema', false, true);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        $post = \get_post($result);
        $this->assertTrue(\has_post_thumbnail($result));
    }

    public function testErrorHandling()
    {
        $this->openAIService = $this->createMock(OpenAIService::class);
        $this->openAIService->method('generateTitle')
            ->willThrowException(new \Exception('API Error'));
            
        $this->postGenerator = new PostGeneratorService(
            $this->openAIService,
            $this->contentAnalysisService
        );
        
        $this->expectException(\Exception::class);
        $this->postGenerator->generateAndPublishPost('Test Thema');
    }
} 
