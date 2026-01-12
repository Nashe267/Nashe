<?php
/**
 * Plugin Name: Switch Business Hub AI
 * Plugin URI: https://switchbusinesshub.com
 * Description: AI-Powered Business Management for Graphics Design, Printing, Web Services, Branding & Architectural Drawings
 * Version: 1.2.0
 * Author: Switch Business Hub
 * Author URI: https://switchbusinesshub.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: switch-business-hub
 * Domain Path: /languages
 *
 * @package SwitchBusinessHub
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('SBHA_VERSION', '1.3.0');
define('SBHA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SBHA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SBHA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SBHA_DB_VERSION', '1.1.0');

/**
 * Main Switch Business Hub AI Class
 */
final class Switch_Business_Hub_AI {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $admin;
    public $public_interface;
    public $ai_engine;
    public $job_manager;
    public $service_catalog;
    public $analytics;
    public $recommendations;

    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core includes
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-installer.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-database.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-ai-engine.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-service-catalog.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-job-manager.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-customer.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-recommendations.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-analytics.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-price-optimizer.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-insights.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-reports.php';
        require_once SBHA_PLUGIN_DIR . 'includes/class-sbha-api.php';

        // Admin
        if (is_admin()) {
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin.php';
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin-dashboard.php';
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin-services.php';
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin-jobs.php';
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin-customers.php';
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin-insights.php';
            require_once SBHA_PLUGIN_DIR . 'admin/class-sbha-admin-settings.php';
        }

        // Public
        require_once SBHA_PLUGIN_DIR . 'public/class-sbha-public.php';
        require_once SBHA_PLUGIN_DIR . 'public/class-sbha-shortcodes.php';
        require_once SBHA_PLUGIN_DIR . 'public/class-sbha-ajax.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array('SBHA_Installer', 'activate'));
        register_deactivation_hook(__FILE__, array('SBHA_Installer', 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize components
        $this->ai_engine = new SBHA_AI_Engine();
        $this->service_catalog = new SBHA_Service_Catalog();
        $this->job_manager = new SBHA_Job_Manager();
        $this->recommendations = new SBHA_Recommendations();
        $this->analytics = new SBHA_Analytics();

        if (is_admin()) {
            $this->admin = new SBHA_Admin();
        }

        $this->public_interface = new SBHA_Public();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'switch-business-hub',
            false,
            dirname(SBHA_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Get AI Engine instance
     */
    public function get_ai_engine() {
        return $this->ai_engine;
    }

    /**
     * Get Service Catalog instance
     */
    public function get_service_catalog() {
        return $this->service_catalog;
    }

    /**
     * Get Job Manager instance
     */
    public function get_job_manager() {
        return $this->job_manager;
    }

    /**
     * Get Recommendations instance
     */
    public function get_recommendations() {
        return $this->recommendations;
    }

    /**
     * Get Analytics instance
     */
    public function get_analytics() {
        return $this->analytics;
    }
}

/**
 * Returns the main instance of Switch_Business_Hub_AI
 */
function SBHA() {
    return Switch_Business_Hub_AI::instance();
}

// Initialize the plugin
SBHA();
