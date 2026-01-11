<?php
/**
 * Admin Main Class
 *
 * Handles admin initialization and menu
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin {

    /**
     * Admin pages
     */
    private $dashboard;
    private $services;
    private $jobs;
    private $customers;
    private $insights;
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->dashboard = new SBHA_Admin_Dashboard();
        $this->services = new SBHA_Admin_Services();
        $this->jobs = new SBHA_Admin_Jobs();
        $this->customers = new SBHA_Admin_Customers();
        $this->insights = new SBHA_Admin_Insights();
        $this->settings = new SBHA_Admin_Settings();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_activation_redirect'));
        add_action('wp_ajax_sbha_admin_action', array($this, 'handle_ajax'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Switch Business Hub', 'switch-business-hub'),
            __('Business Hub', 'switch-business-hub'),
            'manage_options',
            'sbha-dashboard',
            array($this->dashboard, 'render'),
            'dashicons-chart-area',
            30
        );

        // Dashboard
        add_submenu_page(
            'sbha-dashboard',
            __('Dashboard', 'switch-business-hub'),
            __('Dashboard', 'switch-business-hub'),
            'manage_options',
            'sbha-dashboard',
            array($this->dashboard, 'render')
        );

        // Jobs
        add_submenu_page(
            'sbha-dashboard',
            __('Jobs', 'switch-business-hub'),
            __('Jobs', 'switch-business-hub'),
            'manage_options',
            'sbha-jobs',
            array($this->jobs, 'render')
        );

        // Services
        add_submenu_page(
            'sbha-dashboard',
            __('Services', 'switch-business-hub'),
            __('Services', 'switch-business-hub'),
            'manage_options',
            'sbha-services',
            array($this->services, 'render')
        );

        // Customers
        add_submenu_page(
            'sbha-dashboard',
            __('Customers', 'switch-business-hub'),
            __('Customers', 'switch-business-hub'),
            'manage_options',
            'sbha-customers',
            array($this->customers, 'render')
        );

        // AI Insights
        add_submenu_page(
            'sbha-dashboard',
            __('AI Insights', 'switch-business-hub'),
            __('AI Insights', 'switch-business-hub'),
            'manage_options',
            'sbha-insights',
            array($this->insights, 'render')
        );

        // Settings
        add_submenu_page(
            'sbha-dashboard',
            __('Settings', 'switch-business-hub'),
            __('Settings', 'switch-business-hub'),
            'manage_options',
            'sbha-settings',
            array($this->settings, 'render')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our pages
        if (strpos($hook, 'sbha-') === false) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'sbha-admin',
            SBHA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SBHA_VERSION
        );

        // Chart.js for analytics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Admin JS
        wp_enqueue_script(
            'sbha-admin',
            SBHA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'chartjs'),
            SBHA_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sbha-admin', 'sbhaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('sbha/v1/'),
            'nonce' => wp_create_nonce('sbha_admin_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'currency' => get_option('sbha_currency_symbol', '$'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'switch-business-hub'),
                'saving' => __('Saving...', 'switch-business-hub'),
                'saved' => __('Saved!', 'switch-business-hub'),
                'error' => __('An error occurred', 'switch-business-hub')
            )
        ));

        // Media uploader
        wp_enqueue_media();
    }

    /**
     * Handle activation redirect
     */
    public function handle_activation_redirect() {
        if (get_transient('sbha_activation_redirect')) {
            delete_transient('sbha_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=sbha-dashboard&welcome=1'));
                exit;
            }
        }
    }

    /**
     * Handle AJAX requests
     */
    public function handle_ajax() {
        check_ajax_referer('sbha_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $action = isset($_POST['sbha_action']) ? sanitize_text_field($_POST['sbha_action']) : '';

        switch ($action) {
            case 'update_job_status':
                $this->ajax_update_job_status();
                break;

            case 'delete_job':
                $this->ajax_delete_job();
                break;

            case 'update_service':
                $this->ajax_update_service();
                break;

            case 'delete_service':
                $this->ajax_delete_service();
                break;

            case 'update_customer':
                $this->ajax_update_customer();
                break;

            case 'dismiss_insight':
                $this->ajax_dismiss_insight();
                break;

            case 'export_report':
                $this->ajax_export_report();
                break;

            case 'get_dashboard_stats':
                $this->ajax_get_dashboard_stats();
                break;

            default:
                wp_send_json_error('Unknown action');
        }
    }

    /**
     * AJAX: Update job status
     */
    private function ajax_update_job_status() {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$job_id || !$status) {
            wp_send_json_error('Invalid data');
        }

        $result = SBHA()->get_job_manager()->update_status($job_id, $status);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Status updated'));
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    /**
     * AJAX: Delete job
     */
    private function ajax_delete_job() {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

        if (!$job_id) {
            wp_send_json_error('Invalid job ID');
        }

        $result = SBHA()->get_job_manager()->delete_job($job_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Job deleted'));
        } else {
            wp_send_json_error('Failed to delete job');
        }
    }

    /**
     * AJAX: Update service
     */
    private function ajax_update_service() {
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (!$service_id) {
            wp_send_json_error('Invalid service ID');
        }

        // Sanitize data
        $sanitized = array();
        $allowed_fields = array('name', 'description', 'short_description', 'base_price', 'price_type', 'category', 'status', 'is_featured', 'is_popular');

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, array('base_price'))) {
                    $sanitized[$field] = floatval($data[$field]);
                } elseif (in_array($field, array('is_featured', 'is_popular'))) {
                    $sanitized[$field] = intval($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        $result = SBHA()->get_service_catalog()->update_service($service_id, $sanitized);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Service updated'));
        } else {
            wp_send_json_error('Failed to update service');
        }
    }

    /**
     * AJAX: Delete service
     */
    private function ajax_delete_service() {
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

        if (!$service_id) {
            wp_send_json_error('Invalid service ID');
        }

        $result = SBHA()->get_service_catalog()->delete_service($service_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Service deleted'));
        } else {
            wp_send_json_error('Failed to delete service');
        }
    }

    /**
     * AJAX: Update customer
     */
    private function ajax_update_customer() {
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (!$customer_id) {
            wp_send_json_error('Invalid customer ID');
        }

        // Sanitize data
        $sanitized = array();
        $allowed_fields = array('first_name', 'last_name', 'email', 'phone', 'company', 'address', 'city', 'state', 'country', 'postal_code', 'customer_type', 'status', 'notes');

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'email') {
                    $sanitized[$field] = sanitize_email($data[$field]);
                } elseif ($field === 'notes') {
                    $sanitized[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        $result = SBHA_Customer::update_customer($customer_id, $sanitized);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Customer updated'));
        } else {
            wp_send_json_error('Failed to update customer');
        }
    }

    /**
     * AJAX: Dismiss insight
     */
    private function ajax_dismiss_insight() {
        $insight_id = isset($_POST['insight_id']) ? intval($_POST['insight_id']) : 0;

        if (!$insight_id) {
            wp_send_json_error('Invalid insight ID');
        }

        $result = SBHA_Insights::dismiss_insight($insight_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Insight dismissed'));
        } else {
            wp_send_json_error('Failed to dismiss insight');
        }
    }

    /**
     * AJAX: Export report
     */
    private function ajax_export_report() {
        $type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : 'business';
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';

        $report = SBHA_Reports::generate_business_report($period);

        if ($format === 'csv') {
            $content = SBHA_Reports::export_to_csv($report);
            $filename = 'business-report-' . date('Y-m-d') . '.csv';
            $mime = 'text/csv';
        } else {
            $content = SBHA_Reports::generate_html_report($report);
            $filename = 'business-report-' . date('Y-m-d') . '.html';
            $mime = 'text/html';
        }

        wp_send_json_success(array(
            'content' => base64_encode($content),
            'filename' => $filename,
            'mime' => $mime
        ));
    }

    /**
     * AJAX: Get dashboard stats
     */
    private function ajax_get_dashboard_stats() {
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';

        $stats = SBHA()->get_analytics()->get_period_stats($period);
        $comparison = SBHA()->get_analytics()->get_comparison_stats($period);

        wp_send_json_success(array(
            'stats' => $stats,
            'comparison' => $comparison
        ));
    }
}
