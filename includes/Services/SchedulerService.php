<?php
namespace DeepBlogger\Services;

/**
 * Service class for scheduling automatic post generation
 */
class SchedulerService {
    /**
     * Post generator service instance
     *
     * @var PostGeneratorService
     */
    private $post_generator;

    /**
     * Hook name for scheduled events
     *
     * @var string
     */
    private $hook_name = 'deepblogger_generate_post';

    /**
     * Constructor
     *
     * @param PostGeneratorService $post_generator Post generator service instance
     */
    public function __construct(PostGeneratorService $post_generator) {
        $this->post_generator = $post_generator;
        add_action($this->hook_name, [$this, 'generate_scheduled_post']);
    }

    /**
     * Schedule automatic post generation
     */
    public function schedule_posts() {
        if (!wp_next_scheduled($this->hook_name)) {
            $schedule = get_option('deepblogger_post_schedule', 'daily');
            
            switch ($schedule) {
                case 'weekly':
                    $interval = 'weekly';
                    break;
                case 'monthly':
                    $interval = 'monthly';
                    break;
                default:
                    $interval = 'daily';
            }

            wp_schedule_event(time(), $interval, $this->hook_name);
        }
    }

    /**
     * Unschedule automatic post generation
     */
    public function unschedule_posts() {
        $timestamp = wp_next_scheduled($this->hook_name);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->hook_name);
        }
    }

    /**
     * Generate a scheduled post
     */
    public function generate_scheduled_post() {
        try {
            // Get configured topics
            $topics = get_option('deepblogger_topics', []);
            
            if (empty($topics)) {
                throw new \Exception(esc_html__('No topics configured', 'deepblogger'));
            }

            // Randomly select a topic
            $topic = $topics[array_rand($topics)];

            // Generate and publish the post
            $post_id = $this->post_generator->generate_and_publish_post($topic);

            // Send email notification to admin
            $this->notify_admin($post_id, $topic);

        } catch (\Exception $e) {
            error_log('DeepBlogger Scheduler Error: ' . $e->getMessage());
            
            // Notify admin about the error
            $this->notify_admin_error($e->getMessage());
        }
    }

    /**
     * Send notification email to admin about new post
     *
     * @param int $post_id Post ID
     * @param string $topic Post topic
     */
    private function notify_admin($post_id, $topic) {
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            /* translators: %s: Post topic */
            esc_html__('New DeepBlogger Post: %s', 'deepblogger'),
            $topic
        );
        
        $message = sprintf(
            /* translators: 1: Post topic, 2: Edit link */
            esc_html__('A new post has been created:\n\nTopic: %1$s\nEdit: %2$s', 'deepblogger'),
            $topic,
            get_edit_post_link($post_id, '')
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Send error notification email to admin
     *
     * @param string $error_message Error message
     */
    private function notify_admin_error($error_message) {
        $admin_email = get_option('admin_email');
        $subject = esc_html__('DeepBlogger Error', 'deepblogger');
        
        $message = sprintf(
            /* translators: %s: Error message */
            esc_html__('An error occurred during automatic post creation:\n\n%s', 'deepblogger'),
            $error_message
        );

        wp_mail($admin_email, $subject, $message);
    }
} 