<?php
/**
 * Shortcodes Class
 *
 * All frontend shortcodes
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        // Main shortcodes
        add_shortcode('sbha_hub', array($this, 'render_hub'));
        add_shortcode('sbha_services', array($this, 'render_services'));
        add_shortcode('sbha_service', array($this, 'render_single_service'));
        add_shortcode('sbha_quote_form', array($this, 'render_quote_form'));
        add_shortcode('sbha_job_tracker', array($this, 'render_job_tracker'));
        add_shortcode('sbha_welcome', array($this, 'render_welcome'));
        add_shortcode('sbha_categories', array($this, 'render_categories'));
    }

    /**
     * Main hub - complete single page app
     */
    public function render_hub($atts) {
        $atts = shortcode_atts(array(
            'show_welcome' => 'yes',
            'show_categories' => 'yes',
            'show_featured' => 'yes'
        ), $atts);

        $services = SBHA()->get_service_catalog()->get_services();
        $categories = SBHA()->get_service_catalog()->get_categories();
        $featured = SBHA()->get_service_catalog()->get_featured_services(6);

        $primary_color = get_option('sbha_primary_color', '#FF6600');
        $secondary_color = get_option('sbha_secondary_color', '#000000');

        ob_start();
        ?>
        <div class="sbha-hub" data-primary="<?php echo esc_attr($primary_color); ?>" data-secondary="<?php echo esc_attr($secondary_color); ?>">
            <?php if ($atts['show_welcome'] === 'yes'): ?>
            <!-- Welcome Section -->
            <section class="sbha-welcome-section">
                <div class="sbha-welcome-content">
                    <h1><?php echo esc_html(get_option('sbha_business_name', 'Switch Business Hub')); ?></h1>
                    <p class="sbha-welcome-tagline">Your one-stop solution for Graphics, Printing, Web Services, Branding & Architectural Drawings</p>

                    <!-- AI-Powered Search -->
                    <div class="sbha-smart-search">
                        <input type="text" id="sbha-query-input" placeholder="Tell us what you need... (e.g., 'I need business cards for my new company')">
                        <button id="sbha-search-btn" class="sbha-btn sbha-btn-primary">
                            <span>Find Services</span>
                        </button>
                    </div>

                    <div id="sbha-search-results" class="sbha-search-results"></div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($atts['show_categories'] === 'yes'): ?>
            <!-- Categories Section -->
            <section class="sbha-categories-section">
                <h2>Our Services</h2>
                <div class="sbha-categories-grid">
                    <?php foreach ($categories as $slug => $name): ?>
                        <div class="sbha-category-card" data-category="<?php echo esc_attr($slug); ?>">
                            <div class="sbha-category-icon sbha-icon-<?php echo esc_attr($slug); ?>"></div>
                            <h3><?php echo esc_html($name); ?></h3>
                            <span class="sbha-category-count">
                                <?php
                                $count = count(array_filter($services, function($s) use ($slug) {
                                    return $s['category'] === $slug;
                                }));
                                echo $count . ' ' . _n('service', 'services', $count, 'switch-business-hub');
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($atts['show_featured'] === 'yes' && !empty($featured)): ?>
            <!-- Featured Services -->
            <section class="sbha-featured-section">
                <h2>Featured Services</h2>
                <div class="sbha-services-grid">
                    <?php foreach ($featured as $service): ?>
                        <?php echo $this->render_service_card($service); ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- All Services (filterable) -->
            <section class="sbha-services-section" id="sbha-all-services">
                <div class="sbha-section-header">
                    <h2>All Services</h2>
                    <div class="sbha-filters">
                        <select id="sbha-category-filter" class="sbha-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $slug => $name): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="sbha-services-grid" id="sbha-services-list">
                    <?php foreach ($services as $service): ?>
                        <?php echo $this->render_service_card($service); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Service Modal -->
            <div id="sbha-service-modal" class="sbha-modal">
                <div class="sbha-modal-content">
                    <button class="sbha-modal-close">&times;</button>
                    <div id="sbha-modal-body"></div>
                </div>
            </div>

            <!-- Quote Request Modal -->
            <div id="sbha-quote-modal" class="sbha-modal">
                <div class="sbha-modal-content">
                    <button class="sbha-modal-close">&times;</button>
                    <div class="sbha-quote-form-container">
                        <h2>Request a Quote</h2>
                        <form id="sbha-quote-form" class="sbha-form">
                            <input type="hidden" id="sbha-selected-service" name="service_id" value="">

                            <div class="sbha-form-row">
                                <label for="sbha-quote-name">Your Name *</label>
                                <input type="text" id="sbha-quote-name" name="name" required>
                            </div>

                            <div class="sbha-form-row">
                                <label for="sbha-quote-email">Email Address *</label>
                                <input type="email" id="sbha-quote-email" name="email" required>
                            </div>

                            <div class="sbha-form-row">
                                <label for="sbha-quote-phone">Phone Number</label>
                                <input type="tel" id="sbha-quote-phone" name="phone">
                            </div>

                            <div class="sbha-form-row">
                                <label for="sbha-quote-company">Company (optional)</label>
                                <input type="text" id="sbha-quote-company" name="company">
                            </div>

                            <div class="sbha-form-row">
                                <label for="sbha-quote-title">Project Title *</label>
                                <input type="text" id="sbha-quote-title" name="title" required>
                            </div>

                            <div class="sbha-form-row">
                                <label for="sbha-quote-description">Project Details *</label>
                                <textarea id="sbha-quote-description" name="description" rows="4" required placeholder="Please describe your project requirements..."></textarea>
                            </div>

                            <div class="sbha-form-row">
                                <label for="sbha-quote-quantity">Quantity</label>
                                <input type="number" id="sbha-quote-quantity" name="quantity" value="1" min="1">
                            </div>

                            <div class="sbha-form-actions">
                                <button type="submit" class="sbha-btn sbha-btn-primary sbha-btn-large">Submit Request</button>
                            </div>

                            <div id="sbha-quote-message" class="sbha-form-message"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render service card
     */
    private function render_service_card($service) {
        $features = is_string($service['features']) ? json_decode($service['features'], true) : $service['features'];
        $currency = get_option('sbha_currency_symbol', '$');

        ob_start();
        ?>
        <div class="sbha-service-card" data-id="<?php echo esc_attr($service['id']); ?>" data-category="<?php echo esc_attr($service['category']); ?>">
            <?php if ($service['is_featured']): ?>
                <span class="sbha-badge featured">Featured</span>
            <?php endif; ?>
            <?php if ($service['is_popular']): ?>
                <span class="sbha-badge popular">Popular</span>
            <?php endif; ?>

            <?php if (!empty($service['image_url'])): ?>
                <div class="sbha-service-image">
                    <img src="<?php echo esc_url($service['image_url']); ?>" alt="<?php echo esc_attr($service['name']); ?>">
                </div>
            <?php endif; ?>

            <div class="sbha-service-content">
                <h3 class="sbha-service-title"><?php echo esc_html($service['name']); ?></h3>

                <?php if (!empty($service['short_description'])): ?>
                    <p class="sbha-service-description"><?php echo esc_html($service['short_description']); ?></p>
                <?php endif; ?>

                <div class="sbha-service-price">
                    <?php if ($service['price_type'] === 'starting_from'): ?>
                        <span class="sbha-price-from">Starting from</span>
                    <?php endif; ?>
                    <span class="sbha-price-value"><?php echo esc_html($currency); ?><?php echo number_format($service['base_price'], 2); ?></span>
                    <?php if ($service['price_type'] === 'hourly'): ?>
                        <span class="sbha-price-per">/hour</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($features) && is_array($features)): ?>
                    <ul class="sbha-service-features">
                        <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="sbha-service-actions">
                    <button class="sbha-btn sbha-btn-outline sbha-view-service" data-id="<?php echo esc_attr($service['id']); ?>">Learn More</button>
                    <button class="sbha-btn sbha-btn-primary sbha-get-quote" data-id="<?php echo esc_attr($service['id']); ?>" data-name="<?php echo esc_attr($service['name']); ?>">Get Quote</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render services grid
     */
    public function render_services($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'featured' => '',
            'popular' => '',
            'limit' => 0,
            'columns' => 3
        ), $atts);

        $args = array();
        if (!empty($atts['category'])) {
            $args['category'] = $atts['category'];
        }
        if ($atts['featured'] === 'yes') {
            $args['featured'] = true;
        }
        if ($atts['popular'] === 'yes') {
            $args['popular'] = true;
        }
        if ($atts['limit'] > 0) {
            $args['limit'] = intval($atts['limit']);
        }

        $services = SBHA()->get_service_catalog()->get_services($args);

        ob_start();
        ?>
        <div class="sbha-services-grid sbha-cols-<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($services as $service): ?>
                <?php echo $this->render_service_card($service); ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single service
     */
    public function render_single_service($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'slug' => ''
        ), $atts);

        if ($atts['id'] > 0) {
            $service = SBHA()->get_service_catalog()->get_service($atts['id']);
        } elseif (!empty($atts['slug'])) {
            $service = SBHA()->get_service_catalog()->get_service_by_slug($atts['slug']);
        } else {
            return '<p>Please specify a service ID or slug.</p>';
        }

        if (!$service) {
            return '<p>Service not found.</p>';
        }

        $recommendations = SBHA()->get_recommendations()->get_service_recommendations($service['id'], 3);
        $currency = get_option('sbha_currency_symbol', '$');

        ob_start();
        ?>
        <div class="sbha-single-service">
            <div class="sbha-service-header">
                <?php if (!empty($service['image_url'])): ?>
                    <div class="sbha-service-image-large">
                        <img src="<?php echo esc_url($service['image_url']); ?>" alt="<?php echo esc_attr($service['name']); ?>">
                    </div>
                <?php endif; ?>

                <div class="sbha-service-info">
                    <h1><?php echo esc_html($service['name']); ?></h1>

                    <div class="sbha-service-price-large">
                        <?php if ($service['price_type'] === 'starting_from'): ?>
                            <span class="sbha-price-from">Starting from</span>
                        <?php endif; ?>
                        <span class="sbha-price-value"><?php echo esc_html($currency); ?><?php echo number_format($service['base_price'], 2); ?></span>
                    </div>

                    <?php if (!empty($service['features'])): ?>
                        <ul class="sbha-features-list">
                            <?php foreach ($service['features'] as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <button class="sbha-btn sbha-btn-primary sbha-btn-large sbha-get-quote" data-id="<?php echo esc_attr($service['id']); ?>" data-name="<?php echo esc_attr($service['name']); ?>">
                        Request Quote
                    </button>
                </div>
            </div>

            <?php if (!empty($service['description'])): ?>
                <div class="sbha-service-description-full">
                    <?php echo wp_kses_post($service['description']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($service['packages'])): ?>
                <div class="sbha-service-packages">
                    <h2>Packages</h2>
                    <div class="sbha-packages-grid">
                        <?php foreach ($service['packages'] as $package): ?>
                            <div class="sbha-package-card <?php echo $package['is_popular'] ? 'popular' : ''; ?>">
                                <?php if ($package['is_popular']): ?>
                                    <span class="sbha-badge popular">Most Popular</span>
                                <?php endif; ?>
                                <h3><?php echo esc_html($package['name']); ?></h3>
                                <div class="sbha-package-price">
                                    <?php echo esc_html($currency); ?><?php echo number_format($package['price'], 2); ?>
                                </div>
                                <?php if (!empty($package['description'])): ?>
                                    <p><?php echo esc_html($package['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($package['features'])): ?>
                                    <ul>
                                        <?php foreach ($package['features'] as $feature): ?>
                                            <li><?php echo esc_html($feature); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button class="sbha-btn sbha-btn-primary sbha-get-quote" data-id="<?php echo esc_attr($service['id']); ?>" data-package="<?php echo esc_attr($package['id']); ?>">
                                    Select
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($recommendations)): ?>
                <div class="sbha-related-services">
                    <h2>You May Also Like</h2>
                    <div class="sbha-services-grid sbha-cols-3">
                        <?php foreach ($recommendations as $rec): ?>
                            <?php echo $this->render_service_card($rec); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render quote form
     */
    public function render_quote_form($atts) {
        $atts = shortcode_atts(array(
            'service' => '',
            'title' => 'Request a Quote'
        ), $atts);

        $services = SBHA()->get_service_catalog()->get_services();

        ob_start();
        ?>
        <div class="sbha-quote-form-standalone">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <form id="sbha-quote-form-standalone" class="sbha-form">
                <div class="sbha-form-row">
                    <label for="sbha-qs-service">Service *</label>
                    <select id="sbha-qs-service" name="service_id" required>
                        <option value="">Select a service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo esc_attr($service['id']); ?>" <?php selected($atts['service'], $service['id']); ?>>
                                <?php echo esc_html($service['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sbha-form-row-inline">
                    <div class="sbha-form-row">
                        <label for="sbha-qs-name">Your Name *</label>
                        <input type="text" id="sbha-qs-name" name="name" required>
                    </div>
                    <div class="sbha-form-row">
                        <label for="sbha-qs-email">Email *</label>
                        <input type="email" id="sbha-qs-email" name="email" required>
                    </div>
                </div>

                <div class="sbha-form-row-inline">
                    <div class="sbha-form-row">
                        <label for="sbha-qs-phone">Phone</label>
                        <input type="tel" id="sbha-qs-phone" name="phone">
                    </div>
                    <div class="sbha-form-row">
                        <label for="sbha-qs-company">Company</label>
                        <input type="text" id="sbha-qs-company" name="company">
                    </div>
                </div>

                <div class="sbha-form-row">
                    <label for="sbha-qs-title">Project Title *</label>
                    <input type="text" id="sbha-qs-title" name="title" required>
                </div>

                <div class="sbha-form-row">
                    <label for="sbha-qs-description">Project Details *</label>
                    <textarea id="sbha-qs-description" name="description" rows="5" required></textarea>
                </div>

                <div class="sbha-form-actions">
                    <button type="submit" class="sbha-btn sbha-btn-primary sbha-btn-large">Submit Request</button>
                </div>

                <div id="sbha-qs-message" class="sbha-form-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render job tracker
     */
    public function render_job_tracker($atts) {
        ob_start();
        ?>
        <div class="sbha-job-tracker">
            <h2>Track Your Order</h2>
            <form id="sbha-track-form" class="sbha-form">
                <div class="sbha-form-row">
                    <label for="sbha-job-number">Job Number</label>
                    <input type="text" id="sbha-job-number" name="job_number" placeholder="e.g., SBH2501-0001" required>
                </div>
                <button type="submit" class="sbha-btn sbha-btn-primary">Track Order</button>
            </form>
            <div id="sbha-track-result" class="sbha-track-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render welcome section only
     */
    public function render_welcome($atts) {
        ob_start();
        ?>
        <div class="sbha-welcome-standalone">
            <div class="sbha-welcome-content">
                <h2><?php echo esc_html(get_option('sbha_welcome_message', 'Welcome! How can we help you today?')); ?></h2>
                <div class="sbha-smart-search">
                    <input type="text" id="sbha-query-input" placeholder="Tell us what you need...">
                    <button id="sbha-search-btn" class="sbha-btn sbha-btn-primary">Find Services</button>
                </div>
                <div id="sbha-search-results" class="sbha-search-results"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render categories grid
     */
    public function render_categories($atts) {
        $atts = shortcode_atts(array(
            'show_count' => 'yes'
        ), $atts);

        $categories = SBHA()->get_service_catalog()->get_categories();
        $services = SBHA()->get_service_catalog()->get_services();

        ob_start();
        ?>
        <div class="sbha-categories-grid">
            <?php foreach ($categories as $slug => $name): ?>
                <a href="#" class="sbha-category-card" data-category="<?php echo esc_attr($slug); ?>">
                    <div class="sbha-category-icon sbha-icon-<?php echo esc_attr($slug); ?>"></div>
                    <h3><?php echo esc_html($name); ?></h3>
                    <?php if ($atts['show_count'] === 'yes'): ?>
                        <span class="sbha-category-count">
                            <?php
                            $count = count(array_filter($services, function($s) use ($slug) {
                                return $s['category'] === $slug;
                            }));
                            echo $count . ' ' . _n('service', 'services', $count, 'switch-business-hub');
                            ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
