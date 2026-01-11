<?php
/**
 * Plugin Installer
 *
 * Handles plugin activation, deactivation, and database setup
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Installer {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::create_default_options();
        self::create_default_services();
        self::schedule_cron_jobs();

        // Set activation flag for welcome screen
        set_transient('sbha_activation_redirect', true, 30);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        self::clear_cron_jobs();
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Services table
        $table_services = $wpdb->prefix . 'sbha_services';
        $sql_services = "CREATE TABLE $table_services (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            category varchar(100) NOT NULL,
            description text,
            short_description varchar(500),
            base_price decimal(10,2) DEFAULT 0.00,
            price_type enum('fixed','starting_from','custom','hourly') DEFAULT 'fixed',
            features longtext,
            image_url varchar(500),
            gallery longtext,
            is_popular tinyint(1) DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            display_order int(11) DEFAULT 0,
            popularity_score int(11) DEFAULT 0,
            conversion_rate decimal(5,2) DEFAULT 0.00,
            status enum('active','inactive','draft') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category (category),
            KEY status (status),
            KEY popularity_score (popularity_score)
        ) $charset_collate;";
        dbDelta($sql_services);

        // Service packages/pricing tiers
        $table_packages = $wpdb->prefix . 'sbha_service_packages';
        $sql_packages = "CREATE TABLE $table_packages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            features longtext,
            is_popular tinyint(1) DEFAULT 0,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id)
        ) $charset_collate;";
        dbDelta($sql_packages);

        // Customers table
        $table_customers = $wpdb->prefix . 'sbha_customers';
        $sql_customers = "CREATE TABLE $table_customers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            first_name varchar(100),
            last_name varchar(100),
            company varchar(255),
            address text,
            city varchar(100),
            state varchar(100),
            country varchar(100),
            postal_code varchar(20),
            customer_type enum('individual','business') DEFAULT 'individual',
            lifetime_value decimal(12,2) DEFAULT 0.00,
            total_orders int(11) DEFAULT 0,
            average_order_value decimal(10,2) DEFAULT 0.00,
            last_order_date datetime,
            customer_score int(11) DEFAULT 0,
            predicted_ltv decimal(12,2) DEFAULT 0.00,
            churn_risk decimal(5,2) DEFAULT 0.00,
            preferred_services longtext,
            notes text,
            status enum('active','inactive','blocked') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY wp_user_id (wp_user_id),
            KEY customer_score (customer_score),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_customers);

        // Jobs/Orders table
        $table_jobs = $wpdb->prefix . 'sbha_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_number varchar(50) NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            service_id bigint(20) UNSIGNED,
            package_id bigint(20) UNSIGNED,
            title varchar(255) NOT NULL,
            description text,
            requirements longtext,
            files longtext,
            quantity int(11) DEFAULT 1,
            unit_price decimal(10,2) DEFAULT 0.00,
            subtotal decimal(10,2) DEFAULT 0.00,
            discount decimal(10,2) DEFAULT 0.00,
            discount_type enum('fixed','percentage') DEFAULT 'fixed',
            tax decimal(10,2) DEFAULT 0.00,
            total decimal(10,2) DEFAULT 0.00,
            payment_status enum('pending','partial','paid','refunded') DEFAULT 'pending',
            payment_method varchar(50),
            job_status enum('inquiry','quoted','confirmed','in_progress','review','revision','completed','delivered','cancelled') DEFAULT 'inquiry',
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            estimated_hours decimal(6,2),
            actual_hours decimal(6,2),
            estimated_completion datetime,
            actual_completion datetime,
            revision_count int(11) DEFAULT 0,
            max_revisions int(11) DEFAULT 2,
            ai_category varchar(100),
            ai_predicted_completion datetime,
            ai_risk_score decimal(5,2) DEFAULT 0.00,
            ai_suggested_upsells longtext,
            notes text,
            internal_notes text,
            assigned_to bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_number (job_number),
            KEY customer_id (customer_id),
            KEY service_id (service_id),
            KEY job_status (job_status),
            KEY payment_status (payment_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_jobs);

        // Job timeline/history
        $table_job_timeline = $wpdb->prefix . 'sbha_job_timeline';
        $sql_job_timeline = "CREATE TABLE $table_job_timeline (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            description text,
            old_value text,
            new_value text,
            user_id bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_job_timeline);

        // AI Customer Intentions
        $table_intentions = $wpdb->prefix . 'sbha_ai_customer_intentions';
        $sql_intentions = "CREATE TABLE $table_intentions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            customer_id bigint(20) UNSIGNED,
            raw_query text NOT NULL,
            interpreted_intent varchar(255),
            matched_services longtext,
            confidence_score decimal(5,4) DEFAULT 0.0000,
            sentiment varchar(50),
            sentiment_score decimal(5,4),
            keywords longtext,
            device_type varchar(50),
            browser varchar(100),
            ip_address varchar(45),
            location_data longtext,
            referrer varchar(500),
            page_url varchar(500),
            converted tinyint(1) DEFAULT 0,
            conversion_value decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY customer_id (customer_id),
            KEY interpreted_intent (interpreted_intent),
            KEY converted (converted),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_intentions);

        // AI Service Patterns
        $table_patterns = $wpdb->prefix . 'sbha_ai_service_patterns';
        $sql_patterns = "CREATE TABLE $table_patterns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id bigint(20) UNSIGNED NOT NULL,
            paired_service_id bigint(20) UNSIGNED NOT NULL,
            pairing_count int(11) DEFAULT 1,
            pairing_confidence decimal(5,4) DEFAULT 0.0000,
            average_bundle_discount decimal(5,2) DEFAULT 0.00,
            conversion_rate decimal(5,4) DEFAULT 0.0000,
            seasonal_data longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_pair (service_id, paired_service_id),
            KEY pairing_confidence (pairing_confidence)
        ) $charset_collate;";
        dbDelta($sql_patterns);

        // AI Business Insights
        $table_insights = $wpdb->prefix . 'sbha_ai_business_insights';
        $sql_insights = "CREATE TABLE $table_insights (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            insight_type varchar(100) NOT NULL,
            insight_title varchar(255) NOT NULL,
            insight_data longtext NOT NULL,
            severity enum('info','low','medium','high','critical') DEFAULT 'info',
            category varchar(100),
            action_required tinyint(1) DEFAULT 0,
            action_taken tinyint(1) DEFAULT 0,
            action_taken_date datetime,
            action_notes text,
            result_impact longtext,
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY insight_type (insight_type),
            KEY severity (severity),
            KEY action_required (action_required),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_insights);

        // AI Conversion Funnel
        $table_funnel = $wpdb->prefix . 'sbha_ai_conversion_funnel';
        $sql_funnel = "CREATE TABLE $table_funnel (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            customer_id bigint(20) UNSIGNED,
            funnel_stage enum('landing','service_view','inquiry','quote','confirmed','completed') NOT NULL,
            service_id bigint(20) UNSIGNED,
            time_in_stage int(11) DEFAULT 0,
            dropped_off tinyint(1) DEFAULT 0,
            drop_reason varchar(255),
            page_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY customer_id (customer_id),
            KEY funnel_stage (funnel_stage),
            KEY dropped_off (dropped_off),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_funnel);

        // Service Gap Analysis
        $table_gaps = $wpdb->prefix . 'sbha_service_gaps';
        $sql_gaps = "CREATE TABLE $table_gaps (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            request_count int(11) DEFAULT 1,
            sample_queries longtext,
            estimated_demand_score decimal(5,2) DEFAULT 0.00,
            estimated_revenue decimal(12,2) DEFAULT 0.00,
            competitor_analysis longtext,
            recommendation text,
            status enum('identified','reviewing','planned','rejected','implemented') DEFAULT 'identified',
            reviewed_at datetime,
            reviewed_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY keyword (keyword),
            KEY request_count (request_count),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_gaps);

        // Price History for optimization
        $table_price_history = $wpdb->prefix . 'sbha_price_history';
        $sql_price_history = "CREATE TABLE $table_price_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id bigint(20) UNSIGNED NOT NULL,
            package_id bigint(20) UNSIGNED,
            old_price decimal(10,2) NOT NULL,
            new_price decimal(10,2) NOT NULL,
            change_reason varchar(255),
            conversion_before decimal(5,4),
            conversion_after decimal(5,4),
            revenue_impact decimal(12,2),
            changed_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_price_history);

        // Quotes table
        $table_quotes = $wpdb->prefix . 'sbha_quotes';
        $sql_quotes = "CREATE TABLE $table_quotes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_number varchar(50) NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            items longtext NOT NULL,
            subtotal decimal(10,2) DEFAULT 0.00,
            discount decimal(10,2) DEFAULT 0.00,
            tax decimal(10,2) DEFAULT 0.00,
            total decimal(10,2) DEFAULT 0.00,
            valid_until datetime,
            notes text,
            status enum('draft','sent','viewed','accepted','rejected','expired') DEFAULT 'draft',
            converted_job_id bigint(20) UNSIGNED,
            sent_at datetime,
            viewed_at datetime,
            responded_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY quote_number (quote_number),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_quotes);

        // Analytics daily aggregates
        $table_analytics = $wpdb->prefix . 'sbha_analytics_daily';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            metric_type varchar(100) NOT NULL,
            metric_value decimal(15,4) DEFAULT 0.0000,
            metric_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date_metric (date, metric_type),
            KEY date (date),
            KEY metric_type (metric_type)
        ) $charset_collate;";
        dbDelta($sql_analytics);

        // AI Training Data
        $table_training = $wpdb->prefix . 'sbha_ai_training_data';
        $sql_training = "CREATE TABLE $table_training (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            data_type varchar(100) NOT NULL,
            input_data longtext NOT NULL,
            output_data longtext,
            is_validated tinyint(1) DEFAULT 0,
            validated_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY data_type (data_type),
            KEY is_validated (is_validated)
        ) $charset_collate;";
        dbDelta($sql_training);

        // Store DB version
        update_option('sbha_db_version', SBHA_DB_VERSION);
    }

    /**
     * Create default plugin options
     */
    private static function create_default_options() {
        $default_options = array(
            'sbha_business_name' => get_bloginfo('name'),
            'sbha_business_email' => get_option('admin_email'),
            'sbha_business_phone' => '',
            'sbha_business_address' => '',
            'sbha_currency' => 'USD',
            'sbha_currency_symbol' => '$',
            'sbha_tax_rate' => 0,
            'sbha_tax_label' => 'VAT',
            'sbha_quote_validity_days' => 30,
            'sbha_job_number_prefix' => 'SBH',
            'sbha_quote_number_prefix' => 'Q',
            'sbha_primary_color' => '#FF6600',
            'sbha_secondary_color' => '#000000',
            'sbha_accent_color' => '#FFFFFF',
            'sbha_ai_enabled' => 1,
            'sbha_ai_api_key' => '',
            'sbha_ai_provider' => 'local',
            'sbha_ai_learning_enabled' => 1,
            'sbha_ai_recommendations_enabled' => 1,
            'sbha_ai_insights_enabled' => 1,
            'sbha_email_notifications' => 1,
            'sbha_sms_notifications' => 0,
            'sbha_welcome_message' => 'Welcome to Switch Business Hub! How can we help you today?',
            'sbha_service_categories' => json_encode(array(
                'graphics' => 'Graphics Design',
                'printing' => 'Printing Services',
                'web' => 'Web Services',
                'branding' => 'Branding',
                'architectural' => 'Architectural Drawings'
            )),
        );

        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Create default services
     */
    private static function create_default_services() {
        global $wpdb;

        $table = $wpdb->prefix . 'sbha_services';

        // Check if services already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }

        $default_services = array(
            // Graphics Design
            array(
                'name' => 'Business Card Design',
                'slug' => 'business-card-design',
                'category' => 'graphics',
                'description' => 'Professional business card designs that make a lasting impression.',
                'short_description' => 'Custom business card designs',
                'base_price' => 50.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Custom design', '2 revisions', 'Print-ready files', 'Multiple formats')),
                'is_popular' => 1,
                'display_order' => 1
            ),
            array(
                'name' => 'Flyer Design',
                'slug' => 'flyer-design',
                'category' => 'graphics',
                'description' => 'Eye-catching flyer designs for events, promotions, and marketing.',
                'short_description' => 'Custom flyer designs for any occasion',
                'base_price' => 75.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Custom design', '2 revisions', 'Print-ready files', 'Social media versions')),
                'is_popular' => 1,
                'display_order' => 2
            ),
            array(
                'name' => 'Logo Design',
                'slug' => 'logo-design',
                'category' => 'graphics',
                'description' => 'Professional logo design to establish your brand identity.',
                'short_description' => 'Create your unique brand identity',
                'base_price' => 150.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('3 initial concepts', 'Unlimited revisions', 'All file formats', 'Brand guidelines')),
                'is_featured' => 1,
                'display_order' => 3
            ),
            array(
                'name' => 'Social Media Graphics',
                'slug' => 'social-media-graphics',
                'category' => 'graphics',
                'description' => 'Custom graphics optimized for all social media platforms.',
                'short_description' => 'Graphics for Facebook, Instagram, Twitter & more',
                'base_price' => 100.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Platform-optimized sizes', 'Brand consistency', 'Templates included', 'Quick turnaround')),
                'display_order' => 4
            ),
            array(
                'name' => 'Poster Design',
                'slug' => 'poster-design',
                'category' => 'graphics',
                'description' => 'Large format poster designs for maximum visual impact.',
                'short_description' => 'Stunning posters that grab attention',
                'base_price' => 100.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Custom design', '2 revisions', 'Print-ready', 'Multiple sizes')),
                'display_order' => 5
            ),

            // Printing Services
            array(
                'name' => 'Business Card Printing',
                'slug' => 'business-card-printing',
                'category' => 'printing',
                'description' => 'High-quality business card printing with various paper and finish options.',
                'short_description' => 'Premium business card printing',
                'base_price' => 30.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Premium cardstock', 'Matte/Gloss finish', 'Various sizes', 'Fast turnaround')),
                'is_popular' => 1,
                'display_order' => 10
            ),
            array(
                'name' => 'Flyer Printing',
                'slug' => 'flyer-printing',
                'category' => 'printing',
                'description' => 'Professional flyer printing for all your marketing needs.',
                'short_description' => 'Quality flyer printing services',
                'base_price' => 50.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Multiple paper options', 'Full color printing', 'Various sizes', 'Bulk discounts')),
                'display_order' => 11
            ),
            array(
                'name' => 'Banner Printing',
                'slug' => 'banner-printing',
                'category' => 'printing',
                'description' => 'Large format banner printing for events and promotions.',
                'short_description' => 'Indoor and outdoor banners',
                'base_price' => 75.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Vinyl or fabric', 'Weather resistant', 'Custom sizes', 'Mounting options')),
                'is_popular' => 1,
                'display_order' => 12
            ),
            array(
                'name' => 'T-Shirt Printing',
                'slug' => 't-shirt-printing',
                'category' => 'printing',
                'description' => 'Custom t-shirt printing for events, teams, and businesses.',
                'short_description' => 'Custom printed t-shirts',
                'base_price' => 15.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Screen printing', 'DTG printing', 'Various sizes', 'Bulk orders')),
                'is_featured' => 1,
                'display_order' => 13
            ),
            array(
                'name' => 'Welcome Board Printing',
                'slug' => 'welcome-board-printing',
                'category' => 'printing',
                'description' => 'Beautiful welcome boards for weddings, events, and corporate functions.',
                'short_description' => 'Custom welcome boards',
                'base_price' => 100.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Multiple materials', 'Custom sizes', 'Design included', 'Stand options')),
                'display_order' => 14
            ),

            // Web Services
            array(
                'name' => 'Website Design',
                'slug' => 'website-design',
                'category' => 'web',
                'description' => 'Custom website design tailored to your business needs.',
                'short_description' => 'Professional website design',
                'base_price' => 500.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Custom design', 'Mobile responsive', 'SEO optimized', 'Content management')),
                'is_featured' => 1,
                'display_order' => 20
            ),
            array(
                'name' => 'E-commerce Website',
                'slug' => 'ecommerce-website',
                'category' => 'web',
                'description' => 'Full-featured online store to sell your products or services.',
                'short_description' => 'Complete online store solution',
                'base_price' => 1000.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Product management', 'Payment integration', 'Inventory tracking', 'Order management')),
                'display_order' => 21
            ),
            array(
                'name' => 'Website Maintenance',
                'slug' => 'website-maintenance',
                'category' => 'web',
                'description' => 'Keep your website secure, updated, and running smoothly.',
                'short_description' => 'Monthly website maintenance',
                'base_price' => 100.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Security updates', 'Backups', 'Performance monitoring', 'Content updates')),
                'display_order' => 22
            ),

            // Branding
            array(
                'name' => 'Brand Identity Package',
                'slug' => 'brand-identity-package',
                'category' => 'branding',
                'description' => 'Complete brand identity including logo, colors, typography, and guidelines.',
                'short_description' => 'Complete branding solution',
                'base_price' => 500.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Logo design', 'Color palette', 'Typography', 'Brand guidelines', 'Stationery design')),
                'is_featured' => 1,
                'display_order' => 30
            ),
            array(
                'name' => 'Brand Refresh',
                'slug' => 'brand-refresh',
                'category' => 'branding',
                'description' => 'Update and modernize your existing brand identity.',
                'short_description' => 'Modernize your brand',
                'base_price' => 300.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Logo refinement', 'Updated colors', 'Modern typography', 'New guidelines')),
                'display_order' => 31
            ),
            array(
                'name' => 'Stationery Design',
                'slug' => 'stationery-design',
                'category' => 'branding',
                'description' => 'Professional business stationery including letterhead, envelopes, and more.',
                'short_description' => 'Complete stationery suite',
                'base_price' => 150.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Letterhead', 'Envelope', 'Compliment slip', 'Print-ready files')),
                'display_order' => 32
            ),

            // Architectural Drawings
            array(
                'name' => '2D Floor Plans',
                'slug' => '2d-floor-plans',
                'category' => 'architectural',
                'description' => 'Detailed 2D floor plans for residential and commercial properties.',
                'short_description' => 'Professional 2D floor plans',
                'base_price' => 200.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Accurate measurements', 'Furniture layout', 'Multiple floors', 'CAD files')),
                'display_order' => 40
            ),
            array(
                'name' => '3D Rendering',
                'slug' => '3d-rendering',
                'category' => 'architectural',
                'description' => 'Photorealistic 3D renderings of architectural designs.',
                'short_description' => 'Photorealistic 3D visuals',
                'base_price' => 300.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Photorealistic quality', 'Interior/Exterior views', 'Multiple angles', 'Revisions included')),
                'is_featured' => 1,
                'display_order' => 41
            ),
            array(
                'name' => 'Building Plans',
                'slug' => 'building-plans',
                'category' => 'architectural',
                'description' => 'Complete building plans for construction and permit applications.',
                'short_description' => 'Complete construction drawings',
                'base_price' => 500.00,
                'price_type' => 'starting_from',
                'features' => json_encode(array('Site plans', 'Floor plans', 'Elevations', 'Sections', 'Details')),
                'display_order' => 42
            ),
        );

        foreach ($default_services as $service) {
            $wpdb->insert($table, $service);
        }
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
        // Daily AI insights generation
        if (!wp_next_scheduled('sbha_daily_insights_generation')) {
            wp_schedule_event(strtotime('tomorrow 6:00:00'), 'daily', 'sbha_daily_insights_generation');
        }

        // Hourly analytics aggregation
        if (!wp_next_scheduled('sbha_hourly_analytics')) {
            wp_schedule_event(time(), 'hourly', 'sbha_hourly_analytics');
        }

        // Weekly reports
        if (!wp_next_scheduled('sbha_weekly_reports')) {
            wp_schedule_event(strtotime('next monday 8:00:00'), 'weekly', 'sbha_weekly_reports');
        }

        // AI model training (monthly)
        if (!wp_next_scheduled('sbha_monthly_ai_training')) {
            wp_schedule_event(strtotime('first day of next month'), 'monthly', 'sbha_monthly_ai_training');
        }
    }

    /**
     * Clear cron jobs
     */
    private static function clear_cron_jobs() {
        wp_clear_scheduled_hook('sbha_daily_insights_generation');
        wp_clear_scheduled_hook('sbha_hourly_analytics');
        wp_clear_scheduled_hook('sbha_weekly_reports');
        wp_clear_scheduled_hook('sbha_monthly_ai_training');
    }
}
