<?php
/**
 * REST API Class
 *
 * REST API endpoints for frontend and integrations
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'sbha/v1';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register API routes
     */
    public function register_routes() {
        // Services
        register_rest_route(self::NAMESPACE, '/services', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_services'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route(self::NAMESPACE, '/services/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_service'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route(self::NAMESPACE, '/services/(?P<id>\d+)/recommendations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_service_recommendations'),
            'permission_callback' => '__return_true'
        ));

        // Categories
        register_rest_route(self::NAMESPACE, '/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_categories'),
            'permission_callback' => '__return_true'
        ));

        // AI Query Analysis
        register_rest_route(self::NAMESPACE, '/analyze-query', array(
            'methods' => 'POST',
            'callback' => array($this, 'analyze_query'),
            'permission_callback' => '__return_true'
        ));

        // Quotes
        register_rest_route(self::NAMESPACE, '/quotes', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_quote_request'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route(self::NAMESPACE, '/quotes/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_quote'),
            'permission_callback' => array($this, 'check_quote_access')
        ));

        // Jobs (customer view)
        register_rest_route(self::NAMESPACE, '/jobs/track/(?P<number>[A-Za-z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'track_job'),
            'permission_callback' => '__return_true'
        ));

        // Customer
        register_rest_route(self::NAMESPACE, '/customer/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_customer'),
            'permission_callback' => '__return_true'
        ));

        // Funnel tracking
        register_rest_route(self::NAMESPACE, '/track', array(
            'methods' => 'POST',
            'callback' => array($this, 'track_funnel_event'),
            'permission_callback' => '__return_true'
        ));

        // Admin endpoints
        register_rest_route(self::NAMESPACE, '/admin/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_admin_dashboard'),
            'permission_callback' => array($this, 'check_admin_access')
        ));

        register_rest_route(self::NAMESPACE, '/admin/insights', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_admin_insights'),
            'permission_callback' => array($this, 'check_admin_access')
        ));

        register_rest_route(self::NAMESPACE, '/admin/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_admin_analytics'),
            'permission_callback' => array($this, 'check_admin_access')
        ));
    }

    /**
     * Get services
     */
    public function get_services($request) {
        $params = $request->get_params();

        $args = array(
            'status' => 'active',
            'category' => isset($params['category']) ? sanitize_text_field($params['category']) : '',
            'featured' => isset($params['featured']) ? (bool) $params['featured'] : null,
            'popular' => isset($params['popular']) ? (bool) $params['popular'] : null
        );

        if (function_exists('SBHA')) {
            $services = SBHA()->get_service_catalog()->get_services($args);
        } else {
            $services = array();
        }

        // Parse JSON fields
        foreach ($services as &$service) {
            $service['features'] = json_decode($service['features'], true) ?: array();
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $services
        ), 200);
    }

    /**
     * Get single service
     */
    public function get_service($request) {
        $id = intval($request['id']);

        if (function_exists('SBHA')) {
            $service = SBHA()->get_service_catalog()->get_service($id);
        } else {
            $service = null;
        }

        if (!$service) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Service not found'
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $service
        ), 200);
    }

    /**
     * Get service recommendations
     */
    public function get_service_recommendations($request) {
        $id = intval($request['id']);

        if (function_exists('SBHA')) {
            $recommendations = SBHA()->get_recommendations()->get_service_recommendations($id);
        } else {
            $recommendations = array();
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $recommendations
        ), 200);
    }

    /**
     * Get categories
     */
    public function get_categories($request) {
        if (function_exists('SBHA')) {
            $categories = SBHA()->get_service_catalog()->get_categories();
        } else {
            $categories = array();
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $categories
        ), 200);
    }

    /**
     * Analyze customer query
     */
    public function analyze_query($request) {
        $body = $request->get_json_params();
        $query = isset($body['query']) ? sanitize_text_field($body['query']) : '';
        $session_id = isset($body['session_id']) ? sanitize_text_field($body['session_id']) : null;

        if (empty($query)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Query is required'
            ), 400);
        }

        if (function_exists('SBHA')) {
            $analysis = SBHA()->get_ai_engine()->analyze_query($query, $session_id);
        } else {
            $analysis = array(
                'matched_services' => array(),
                'suggestions' => array()
            );
        }

        // Get service details for matched services
        $matched_services = array();
        if (!empty($analysis['matched_services']) && function_exists('SBHA')) {
            foreach ($analysis['matched_services'] as $match) {
                $service = SBHA()->get_service_catalog()->get_service($match['service_id']);
                if ($service) {
                    $matched_services[] = array(
                        'service' => $service,
                        'score' => $match['score'],
                        'confidence' => $analysis['confidence']
                    );
                }
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'intent' => $analysis['intent'],
                'matched_services' => $matched_services,
                'sentiment' => $analysis['sentiment'],
                'suggestions' => $analysis['suggestions']
            )
        ), 200);
    }

    /**
     * Create quote request
     */
    public function create_quote_request($request) {
        $body = $request->get_json_params();

        // Validate required fields
        $required = array('email', 'service_id');
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => ucfirst($field) . ' is required'
                ), 400);
            }
        }

        // Get or create customer
        $customer_id = SBHA_Customer::get_or_create(
            sanitize_email($body['email']),
            array(
                'first_name' => isset($body['name']) ? sanitize_text_field($body['name']) : '',
                'phone' => isset($body['phone']) ? sanitize_text_field($body['phone']) : '',
                'company' => isset($body['company']) ? sanitize_text_field($body['company']) : ''
            )
        );

        if (is_wp_error($customer_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $customer_id->get_error_message()
            ), 400);
        }

        // Create job as inquiry
        if (function_exists('SBHA')) {
            $job_id = SBHA()->get_job_manager()->create_job(array(
                'customer_id' => $customer_id,
                'service_id' => intval($body['service_id']),
                'package_id' => isset($body['package_id']) ? intval($body['package_id']) : null,
                'title' => isset($body['title']) ? sanitize_text_field($body['title']) : 'Quote Request',
                'description' => isset($body['description']) ? sanitize_textarea_field($body['description']) : '',
                'requirements' => isset($body['requirements']) ? $body['requirements'] : array(),
                'quantity' => isset($body['quantity']) ? intval($body['quantity']) : 1,
                'job_status' => 'inquiry'
            ));

            if ($job_id) {
                $job = SBHA()->get_job_manager()->get_job($job_id);

                // Send notification
                do_action('sbha_new_inquiry', $job_id, $job);

                return new WP_REST_Response(array(
                    'success' => true,
                    'data' => array(
                        'job_number' => $job['job_number'],
                        'message' => 'Your request has been submitted. We will get back to you shortly!'
                    )
                ), 200);
            }
        }

        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Failed to create request'
        ), 500);
    }

    /**
     * Get quote
     */
    public function get_quote($request) {
        $id = intval($request['id']);
        $quote = SBHA_Database::get_by_id('quotes', $id);

        if (!$quote) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Quote not found'
            ), 404);
        }

        $quote['items'] = json_decode($quote['items'], true);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $quote
        ), 200);
    }

    /**
     * Track job
     */
    public function track_job($request) {
        $job_number = sanitize_text_field($request['number']);

        if (function_exists('SBHA')) {
            $job = SBHA()->get_job_manager()->get_job_by_number($job_number);
        } else {
            $job = null;
        }

        if (!$job) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Job not found'
            ), 404);
        }

        // Return limited info for public tracking
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'job_number' => $job['job_number'],
                'title' => $job['title'],
                'status' => $job['job_status'],
                'status_label' => SBHA()->get_job_manager()->get_statuses()[$job['job_status']],
                'estimated_completion' => $job['estimated_completion'],
                'created_at' => $job['created_at']
            )
        ), 200);
    }

    /**
     * Register customer
     */
    public function register_customer($request) {
        $body = $request->get_json_params();

        if (empty($body['email'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Email is required'
            ), 400);
        }

        $customer_id = SBHA_Customer::create_customer(array(
            'email' => sanitize_email($body['email']),
            'first_name' => isset($body['first_name']) ? sanitize_text_field($body['first_name']) : '',
            'last_name' => isset($body['last_name']) ? sanitize_text_field($body['last_name']) : '',
            'phone' => isset($body['phone']) ? sanitize_text_field($body['phone']) : '',
            'company' => isset($body['company']) ? sanitize_text_field($body['company']) : '',
            'customer_type' => isset($body['customer_type']) ? sanitize_text_field($body['customer_type']) : 'individual'
        ));

        if (is_wp_error($customer_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $customer_id->get_error_message()
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'customer_id' => $customer_id,
                'message' => 'Registration successful'
            )
        ), 200);
    }

    /**
     * Track funnel event
     */
    public function track_funnel_event($request) {
        $body = $request->get_json_params();

        $session_id = isset($body['session_id']) ? sanitize_text_field($body['session_id']) : '';
        $stage = isset($body['stage']) ? sanitize_text_field($body['stage']) : '';
        $service_id = isset($body['service_id']) ? intval($body['service_id']) : null;

        $valid_stages = array('landing', 'service_view', 'inquiry', 'quote', 'confirmed', 'completed');
        if (!in_array($stage, $valid_stages)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid funnel stage'
            ), 400);
        }

        SBHA_Database::insert('ai_conversion_funnel', array(
            'session_id' => $session_id,
            'funnel_stage' => $stage,
            'service_id' => $service_id,
            'page_data' => json_encode(array(
                'url' => isset($body['url']) ? esc_url($body['url']) : '',
                'referrer' => isset($body['referrer']) ? esc_url($body['referrer']) : '',
                'timestamp' => current_time('mysql')
            ))
        ));

        return new WP_REST_Response(array(
            'success' => true
        ), 200);
    }

    /**
     * Get admin dashboard data
     */
    public function get_admin_dashboard($request) {
        $period = sanitize_text_field($request->get_param('period') ?: 'month');

        $data = array();

        if (function_exists('SBHA')) {
            $data['stats'] = SBHA()->get_analytics()->get_dashboard_stats();
            $data['recent_jobs'] = SBHA()->get_job_manager()->get_jobs(array('limit' => 10));
            $data['pending_count'] = SBHA_Database::get_count('jobs', array('job_status' => 'inquiry'));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data
        ), 200);
    }

    /**
     * Get admin insights
     */
    public function get_admin_insights($request) {
        $insights = SBHA_Insights::get_active_insights(20);
        $gaps = SBHA_Insights::get_service_gaps();
        $opportunities = SBHA_Insights::get_expansion_opportunities();

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'insights' => $insights,
                'service_gaps' => $gaps,
                'opportunities' => $opportunities
            )
        ), 200);
    }

    /**
     * Get admin analytics
     */
    public function get_admin_analytics($request) {
        $period = sanitize_text_field($request->get_param('period') ?: 'month');

        $data = array();

        if (function_exists('SBHA')) {
            $analytics = SBHA()->get_analytics();
            $data['revenue_chart'] = $analytics->get_revenue_chart($period);
            $data['service_performance'] = $analytics->get_service_performance();
            $data['category_performance'] = $analytics->get_category_performance();
            $data['customer_analytics'] = $analytics->get_customer_analytics();
            $data['funnel'] = $analytics->get_funnel_analytics($period);
            $data['keywords'] = $analytics->get_keyword_analytics();
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $data
        ), 200);
    }

    /**
     * Check admin access
     */
    public function check_admin_access($request) {
        return current_user_can('manage_options');
    }

    /**
     * Check quote access
     */
    public function check_quote_access($request) {
        // Allow access with token or if logged in
        $token = $request->get_param('token');
        if ($token) {
            // Validate token (implement your token validation)
            return true;
        }
        return is_user_logged_in();
    }
}

// Initialize API
new SBHA_API();
