<?php
namespace DeepBlogger\Tests\Services;

use DeepBlogger\Services\SchedulerService;
use DeepBlogger\Services\PostGeneratorService;
use WP_UnitTestCase;

class SchedulerServiceTest extends WP_UnitTestCase {
    private $scheduler;
    private $post_generator;

    public function setUp(): void {
        parent::setUp();
        
        // Mock des Post-Generators
        $this->post_generator = $this->createMock(PostGeneratorService::class);
        $this->scheduler = new SchedulerService($this->post_generator);

        // Standard-Einstellungen
        update_option('deepblogger_post_schedule', 'daily');
        update_option('deepblogger_topics', [
            'Test Thema 1',
            'Test Thema 2'
        ]);
    }

    public function tearDown(): void {
        parent::tearDown();
        
        // Aufräumen
        delete_option('deepblogger_post_schedule');
        delete_option('deepblogger_topics');
        
        // Geplante Events entfernen
        $timestamp = wp_next_scheduled('deepblogger_generate_post');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'deepblogger_generate_post');
        }
    }

    public function test_schedule_posts() {
        $this->scheduler->schedule_posts();

        // Prüfen, ob ein Event geplant wurde
        $timestamp = wp_next_scheduled('deepblogger_generate_post');
        $this->assertNotFalse($timestamp);
    }

    public function test_unschedule_posts() {
        // Zuerst planen
        $this->scheduler->schedule_posts();
        
        // Dann entfernen
        $this->scheduler->unschedule_posts();

        // Prüfen, ob das Event entfernt wurde
        $timestamp = wp_next_scheduled('deepblogger_generate_post');
        $this->assertFalse($timestamp);
    }

    public function test_generate_scheduled_post() {
        $test_topic = 'Test Thema 1';
        $test_post_id = 123;

        // Post-Generator Mock konfigurieren
        $this->post_generator
            ->expects($this->once())
            ->method('generate_and_publish_post')
            ->with($this->isType('string'))
            ->willReturn($test_post_id);

        // Test ausführen
        $this->scheduler->generate_scheduled_post();

        // E-Mail-Benachrichtigung prüfen
        $this->assertTrue(
            isset($GLOBALS['phpmailer']->mock_sent) && 
            !empty($GLOBALS['phpmailer']->mock_sent)
        );
    }

    public function test_generate_scheduled_post_with_error() {
        // Themen entfernen
        delete_option('deepblogger_topics');

        // Test ausführen
        $this->scheduler->generate_scheduled_post();

        // Prüfen, ob eine Fehler-E-Mail gesendet wurde
        $this->assertTrue(
            isset($GLOBALS['phpmailer']->mock_sent) && 
            !empty($GLOBALS['phpmailer']->mock_sent)
        );

        // Prüfen, ob der Betreff der E-Mail korrekt ist
        $this->assertStringContainsString(
            'DeepBlogger Fehler',
            $GLOBALS['phpmailer']->mock_sent[0]['subject']
        );
    }
} 