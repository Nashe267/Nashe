<?php
/**
 * Public Class
 *
 * Handles frontend functionality
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Public {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'init_shortcodes'));
        add_action('wp_footer', array($this, 'output_tracking_script'));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Public CSS
        wp_enqueue_style(
            'sbha-public',
            SBHA_PLUGIN_URL . 'assets/css/public.css',
            array(),
            SBHA_VERSION
        );

        // Public JS
        wp_enqueue_script(
            'sbha-public',
            SBHA_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            SBHA_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sbha-public', 'sbhaPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('sbha/v1/'),
            'nonce' => wp_create_nonce('sbha_public_nonce'),
            'sessionId' => $this->get_session_id(),
            'currency' => get_option('sbha_currency_symbol', '$'),
            'primaryColor' => get_option('sbha_primary_color', '#FF6600'),
            'secondaryColor' => get_option('sbha_secondary_color', '#000000'),
            'welcomeMessage' => get_option('sbha_welcome_message', 'How can we help you today?'),
            'strings' => array(
                'loading' => __('Loading...', 'switch-business-hub'),
                'error' => __('Something went wrong. Please try again.', 'switch-business-hub'),
                'success' => __('Thank you! We\'ll get back to you soon.', 'switch-business-hub'),
                'required' => __('This field is required', 'switch-business-hub')
            )
        ));
    }

    /**
     * Initialize shortcodes
     */
    public function init_shortcodes() {
        $shortcodes = new SBHA_Shortcodes();
    }

    /**
     * Get or create session ID
     */
    private function get_session_id() {
        if (isset($_COOKIE['sbha_session'])) {
            return sanitize_text_field($_COOKIE['sbha_session']);
        }

        $session_id = 'sess_' . bin2hex(random_bytes(16));

        // Set cookie for 30 days
        if (!headers_sent()) {
            setcookie('sbha_session', $session_id, time() + (30 * DAY_IN_SECONDS), '/', '', is_ssl(), true);
        }

        return $session_id;
    }

    /**
     * Output tracking script
     */
    public function output_tracking_script() {
        if (!get_option('sbha_ai_learning_enabled', 1)) {
            return;
        }
        ?>
        <script>
        (function() {
            // Track page view
            if (typeof sbhaPublic !== 'undefined') {
                fetch(sbhaPublic.restUrl + 'track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: sbhaPublic.sessionId,
                        stage: 'landing',
                        url: window.location.href,
                        referrer: document.referrer
                    })
                }).catch(function() {});
            }
        })();
        </script>
        <?php
    }
}
