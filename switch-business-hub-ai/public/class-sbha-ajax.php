<?php
/**
 * Public AJAX Handler
 *
 * Handles frontend AJAX requests
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        // Public (no login required)
        add_action('wp_ajax_nopriv_sbha_analyze_query', array($this, 'analyze_query'));
        add_action('wp_ajax_sbha_analyze_query', array($this, 'analyze_query'));

        add_action('wp_ajax_nopriv_sbha_submit_quote', array($this, 'submit_quote'));
        add_action('wp_ajax_sbha_submit_quote', array($this, 'submit_quote'));

        add_action('wp_ajax_nopriv_sbha_get_service', array($this, 'get_service'));
        add_action('wp_ajax_sbha_get_service', array($this, 'get_service'));

        add_action('wp_ajax_nopriv_sbha_track_job', array($this, 'track_job'));
        add_action('wp_ajax_sbha_track_job', array($this, 'track_job'));

        add_action('wp_ajax_nopriv_sbha_get_recommendations', array($this, 'get_recommendations'));
        add_action('wp_ajax_sbha_get_recommendations', array($this, 'get_recommendations'));
    }

    /**
     * Analyze query using AI
     */
    public function analyze_query() {
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : null;

        if (empty($query)) {
            wp_send_json_error('Query is required');
        }

        $analysis = SBHA()->get_ai_engine()->analyze_query($query, $session_id);

        // Get full service details for matches
        $services = array();
        if (!empty($analysis['matched_services'])) {
            foreach ($analysis['matched_services'] as $match) {
                $service = SBHA()->get_service_catalog()->get_service($match['service_id']);
                if ($service) {
                    $services[] = array(
                        'id' => $service['id'],
                        'name' => $service['name'],
                        'short_description' => $service['short_description'],
                        'base_price' => $service['base_price'],
                        'price_type' => $service['price_type'],
                        'image_url' => $service['image_url'],
                        'category' => $service['category'],
                        'match_score' => $match['score']
                    );
                }
            }
        }

        wp_send_json_success(array(
            'intent' => $analysis['intent'],
            'services' => $services,
            'sentiment' => $analysis['sentiment'],
            'suggestions' => $analysis['suggestions'],
            'message' => $this->get_response_message($analysis, $services)
        ));
    }

    /**
     * Get response message based on analysis
     */
    private function get_response_message($analysis, $services) {
        if (empty($services)) {
            return "I couldn't find an exact match, but our team can help! Please describe what you need and we'll get back to you with a custom quote.";
        }

        $count = count($services);

        if ($analysis['sentiment']['urgent']) {
            return "I understand this is urgent! Here " . ($count === 1 ? "is a service" : "are " . $count . " services") . " that match your needs. We offer rush delivery options.";
        }

        switch ($analysis['intent']) {
            case 'inquiry':
                return "Great question! Here " . ($count === 1 ? "is our service" : "are " . $count . " services") . " that match what you're looking for. Click any service for detailed pricing.";

            case 'bulk':
                return "We offer great bulk discounts! Here " . ($count === 1 ? "is a service" : "are " . $count . " services") . " for you. Request a quote for volume pricing.";

            default:
                return "I found " . $count . " " . ($count === 1 ? "service" : "services") . " that match your request:";
        }
    }

    /**
     * Submit quote request
     */
    public function submit_quote() {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';

        // Validate required fields
        $required = array('email', 'service_id', 'title', 'description');
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(ucfirst(str_replace('_', ' ', $field)) . ' is required');
            }
        }

        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
        }

        // Get or create customer
        $customer_id = SBHA_Customer::get_or_create($email, array(
            'first_name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'company' => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : ''
        ));

        if (is_wp_error($customer_id)) {
            wp_send_json_error($customer_id->get_error_message());
        }

        // Create job
        $job_id = SBHA()->get_job_manager()->create_job(array(
            'customer_id' => $customer_id,
            'service_id' => intval($_POST['service_id']),
            'package_id' => isset($_POST['package_id']) ? intval($_POST['package_id']) : null,
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'quantity' => isset($_POST['quantity']) ? intval($_POST['quantity']) : 1,
            'job_status' => 'inquiry'
        ));

        if (!$job_id) {
            wp_send_json_error('Failed to create request. Please try again.');
        }

        $job = SBHA()->get_job_manager()->get_job($job_id);

        // Mark intention as converted
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        if ($session_id) {
            global $wpdb;
            $wpdb->update(
                SBHA_Database::get_table('ai_customer_intentions'),
                array('converted' => 1, 'conversion_value' => $job['total']),
                array('session_id' => $session_id),
                array('%d', '%f'),
                array('%s')
            );
        }

        // Send notification
        $this->send_new_inquiry_notification($job, $customer_id);

        wp_send_json_success(array(
            'job_number' => $job['job_number'],
            'message' => 'Thank you! Your request has been submitted. We will get back to you within 24 hours.'
        ));
    }

    /**
     * Send new inquiry notification
     */
    private function send_new_inquiry_notification($job, $customer_id) {
        if (!get_option('sbha_email_notifications', 1)) {
            return;
        }

        $customer = SBHA_Customer::get_customer($customer_id);
        $service = $job['service_id'] ? SBHA()->get_service_catalog()->get_service($job['service_id']) : null;

        $to = get_option('sbha_notification_email', get_option('admin_email'));
        $subject = sprintf('[%s] New Inquiry: %s', get_option('sbha_business_name'), $job['job_number']);

        $message = sprintf(
            "New inquiry received!\n\n" .
            "Job Number: %s\n" .
            "Title: %s\n" .
            "Service: %s\n\n" .
            "Customer: %s\n" .
            "Email: %s\n" .
            "Phone: %s\n\n" .
            "Description:\n%s\n\n" .
            "View in admin: %s",
            $job['job_number'],
            $job['title'],
            $service ? $service['name'] : 'N/A',
            SBHA_Customer::get_display_name($customer),
            $customer['email'],
            $customer['phone'] ?: 'N/A',
            $job['description'],
            admin_url('admin.php?page=sbha-jobs&action=view&id=' . $job['id'])
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Get service details
     */
    public function get_service() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error('Invalid service ID');
        }

        $service = SBHA()->get_service_catalog()->get_service($id);

        if (!$service) {
            wp_send_json_error('Service not found');
        }

        // Get recommendations
        $recommendations = SBHA()->get_recommendations()->get_service_recommendations($id, 3);

        // Track service view
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        if ($session_id) {
            SBHA_Database::insert('ai_conversion_funnel', array(
                'session_id' => $session_id,
                'funnel_stage' => 'service_view',
                'service_id' => $id
            ));
        }

        // Increment popularity
        SBHA()->get_service_catalog()->increment_popularity($id);

        wp_send_json_success(array(
            'service' => $service,
            'recommendations' => array_map(function($rec) {
                return array(
                    'id' => $rec['id'],
                    'name' => $rec['name'],
                    'base_price' => $rec['base_price'],
                    'image_url' => $rec['image_url'],
                    'reason' => $rec['reason'] ?? ''
                );
            }, $recommendations),
            'currency' => get_option('sbha_currency_symbol', '$')
        ));
    }

    /**
     * Track job
     */
    public function track_job() {
        $job_number = isset($_POST['job_number']) ? sanitize_text_field($_POST['job_number']) : '';

        if (empty($job_number)) {
            wp_send_json_error('Please enter a job number');
        }

        $job = SBHA()->get_job_manager()->get_job_by_number($job_number);

        if (!$job) {
            wp_send_json_error('Job not found. Please check the job number and try again.');
        }

        $statuses = SBHA()->get_job_manager()->get_statuses();

        wp_send_json_success(array(
            'job_number' => $job['job_number'],
            'title' => $job['title'],
            'status' => $job['job_status'],
            'status_label' => $statuses[$job['job_status']],
            'estimated_completion' => $job['estimated_completion'] ? date('F j, Y', strtotime($job['estimated_completion'])) : null,
            'created_at' => date('F j, Y', strtotime($job['created_at'])),
            'timeline' => array_map(function($entry) {
                return array(
                    'action' => $entry['description'],
                    'date' => date('M j, g:i a', strtotime($entry['created_at']))
                );
            }, array_slice($job['timeline'], 0, 5))
        ));
    }

    /**
     * Get recommendations
     */
    public function get_recommendations() {
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

        if ($service_id) {
            $recommendations = SBHA()->get_recommendations()->get_service_recommendations($service_id);
        } elseif ($customer_id) {
            $recommendations = SBHA()->get_recommendations()->get_customer_recommendations($customer_id);
        } else {
            $recommendations = SBHA()->get_recommendations()->get_homepage_recommendations();
        }

        wp_send_json_success($recommendations);
    }
}

// Initialize
new SBHA_Ajax();
