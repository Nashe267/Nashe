<?php
/**
 * Shortcodes Class
 *
 * Single tabbed shortcode for the frontend
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
        add_shortcode('switch_hub', array($this, 'render_tabbed_hub'));
    }

    /**
     * Main tabbed hub - single shortcode with all functionality
     * Usage: [switch_hub]
     */
    public function render_tabbed_hub($atts) {
        $atts = shortcode_atts(array(
            'primary_color' => '#FF6600',
            'secondary_color' => '#333333'
        ), $atts);

        $services = SBHA()->get_service_catalog()->get_services();
        $categories = SBHA()->get_service_catalog()->get_categories();
        $currency = get_option('sbha_currency_symbol', '$');
        $business_name = get_option('sbha_business_name', 'Switch Business Hub');

        ob_start();
        ?>
        <div class="sbha-tabbed-hub" style="--sbha-primary: <?php echo esc_attr($atts['primary_color']); ?>; --sbha-secondary: <?php echo esc_attr($atts['secondary_color']); ?>;">

            <!-- Tab Navigation -->
            <div class="sbha-tabs-nav">
                <button class="sbha-tab-btn active" data-tab="services">
                    <span class="sbha-tab-icon">&#128736;</span>
                    <span class="sbha-tab-text">Our Services</span>
                </button>
                <button class="sbha-tab-btn" data-tab="quote">
                    <span class="sbha-tab-icon">&#128221;</span>
                    <span class="sbha-tab-text">Request Quote</span>
                </button>
                <button class="sbha-tab-btn" data-tab="track">
                    <span class="sbha-tab-icon">&#128269;</span>
                    <span class="sbha-tab-text">Track Order</span>
                </button>
                <button class="sbha-tab-btn" data-tab="contact">
                    <span class="sbha-tab-icon">&#128222;</span>
                    <span class="sbha-tab-text">Contact Us</span>
                </button>
            </div>

            <!-- Tab Content -->
            <div class="sbha-tabs-content">

                <!-- Services Tab -->
                <div class="sbha-tab-panel active" id="sbha-tab-services">
                    <div class="sbha-services-header">
                        <h2>Our Services</h2>
                        <p>Professional Graphics Design, Printing, Web Services, Branding & Architectural Drawings</p>

                        <!-- Category Filter -->
                        <div class="sbha-category-filter">
                            <button class="sbha-filter-btn active" data-category="all">All</button>
                            <?php foreach ($categories as $slug => $name): ?>
                                <button class="sbha-filter-btn" data-category="<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="sbha-services-grid" id="sbha-services-grid">
                        <?php foreach ($services as $service): ?>
                            <?php echo $this->render_service_card($service, $currency); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quote Request Tab -->
                <div class="sbha-tab-panel" id="sbha-tab-quote">
                    <div class="sbha-quote-container">
                        <h2>Request a Quote</h2>
                        <p>Fill out the form below and we'll get back to you within 24 hours.</p>

                        <form id="sbha-quote-form" class="sbha-form">
                            <?php wp_nonce_field('sbha_quote_nonce', 'sbha_nonce'); ?>

                            <div class="sbha-form-grid">
                                <div class="sbha-form-group">
                                    <label for="sbha-service">Service Required *</label>
                                    <select id="sbha-service" name="service_id" required>
                                        <option value="">Select a service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo esc_attr($service['id']); ?>">
                                                <?php echo esc_html($service['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sbha-form-group">
                                    <label for="sbha-name">Your Name *</label>
                                    <input type="text" id="sbha-name" name="name" required>
                                </div>

                                <div class="sbha-form-group">
                                    <label for="sbha-email">Email Address *</label>
                                    <input type="email" id="sbha-email" name="email" required>
                                </div>

                                <div class="sbha-form-group">
                                    <label for="sbha-phone">Phone Number</label>
                                    <input type="tel" id="sbha-phone" name="phone">
                                </div>

                                <div class="sbha-form-group">
                                    <label for="sbha-company">Company/Organization</label>
                                    <input type="text" id="sbha-company" name="company">
                                </div>

                                <div class="sbha-form-group">
                                    <label for="sbha-quantity">Quantity</label>
                                    <input type="number" id="sbha-quantity" name="quantity" value="1" min="1">
                                </div>
                            </div>

                            <div class="sbha-form-group sbha-full-width">
                                <label for="sbha-project-title">Project Title *</label>
                                <input type="text" id="sbha-project-title" name="project_title" required>
                            </div>

                            <div class="sbha-form-group sbha-full-width">
                                <label for="sbha-description">Project Description *</label>
                                <textarea id="sbha-description" name="description" rows="5" required placeholder="Please describe your project requirements, specifications, and any special instructions..."></textarea>
                            </div>

                            <div class="sbha-form-group sbha-full-width">
                                <label for="sbha-files">Attach Files (optional)</label>
                                <input type="file" id="sbha-files" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.ai,.psd,.doc,.docx">
                                <small>Accepted: JPG, PNG, PDF, AI, PSD, DOC, DOCX (Max 10MB each)</small>
                            </div>

                            <div class="sbha-form-actions">
                                <button type="submit" class="sbha-btn sbha-btn-primary">
                                    <span class="sbha-btn-text">Submit Quote Request</span>
                                    <span class="sbha-btn-loading" style="display:none;">Submitting...</span>
                                </button>
                            </div>

                            <div id="sbha-quote-message" class="sbha-message"></div>
                        </form>
                    </div>
                </div>

                <!-- Track Order Tab -->
                <div class="sbha-tab-panel" id="sbha-tab-track">
                    <div class="sbha-track-container">
                        <h2>Track Your Order</h2>
                        <p>Enter your job number to check the status of your order.</p>

                        <form id="sbha-track-form" class="sbha-form">
                            <?php wp_nonce_field('sbha_track_nonce', 'sbha_track_nonce'); ?>

                            <div class="sbha-track-input">
                                <input type="text" id="sbha-job-number" name="job_number" placeholder="Enter Job Number (e.g., SBH-2025-0001)" required>
                                <button type="submit" class="sbha-btn sbha-btn-primary">Track</button>
                            </div>
                        </form>

                        <div id="sbha-track-result" class="sbha-track-result"></div>

                        <div class="sbha-track-help">
                            <h4>Where to find your Job Number?</h4>
                            <p>Your job number was sent to you via email when your order was confirmed. It's also available in your order confirmation receipt.</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Tab -->
                <div class="sbha-tab-panel" id="sbha-tab-contact">
                    <div class="sbha-contact-container">
                        <h2>Contact Us</h2>
                        <p>Have questions? We're here to help!</p>

                        <div class="sbha-contact-grid">
                            <div class="sbha-contact-info">
                                <div class="sbha-contact-item">
                                    <span class="sbha-contact-icon">&#128205;</span>
                                    <div>
                                        <strong>Address</strong>
                                        <p><?php echo esc_html(get_option('sbha_business_address', 'Your Business Address')); ?></p>
                                    </div>
                                </div>

                                <div class="sbha-contact-item">
                                    <span class="sbha-contact-icon">&#128222;</span>
                                    <div>
                                        <strong>Phone</strong>
                                        <p><?php echo esc_html(get_option('sbha_business_phone', '+1 234 567 8900')); ?></p>
                                    </div>
                                </div>

                                <div class="sbha-contact-item">
                                    <span class="sbha-contact-icon">&#128231;</span>
                                    <div>
                                        <strong>Email</strong>
                                        <p><?php echo esc_html(get_option('sbha_business_email', 'info@example.com')); ?></p>
                                    </div>
                                </div>

                                <div class="sbha-contact-item">
                                    <span class="sbha-contact-icon">&#128337;</span>
                                    <div>
                                        <strong>Business Hours</strong>
                                        <p><?php echo esc_html(get_option('sbha_business_hours', 'Mon - Fri: 9AM - 6PM')); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="sbha-contact-form">
                                <form id="sbha-contact-form" class="sbha-form">
                                    <?php wp_nonce_field('sbha_contact_nonce', 'sbha_contact_nonce'); ?>

                                    <div class="sbha-form-group">
                                        <label for="sbha-contact-name">Your Name *</label>
                                        <input type="text" id="sbha-contact-name" name="name" required>
                                    </div>

                                    <div class="sbha-form-group">
                                        <label for="sbha-contact-email">Email *</label>
                                        <input type="email" id="sbha-contact-email" name="email" required>
                                    </div>

                                    <div class="sbha-form-group">
                                        <label for="sbha-contact-subject">Subject *</label>
                                        <input type="text" id="sbha-contact-subject" name="subject" required>
                                    </div>

                                    <div class="sbha-form-group">
                                        <label for="sbha-contact-message">Message *</label>
                                        <textarea id="sbha-contact-message" name="message" rows="4" required></textarea>
                                    </div>

                                    <button type="submit" class="sbha-btn sbha-btn-primary">Send Message</button>

                                    <div id="sbha-contact-message-result" class="sbha-message"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Detail Modal -->
            <div id="sbha-modal" class="sbha-modal">
                <div class="sbha-modal-overlay"></div>
                <div class="sbha-modal-content">
                    <button class="sbha-modal-close">&times;</button>
                    <div id="sbha-modal-body"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render service card
     */
    private function render_service_card($service, $currency = '$') {
        $features = is_string($service['features']) ? json_decode($service['features'], true) : $service['features'];

        ob_start();
        ?>
        <div class="sbha-service-card" data-id="<?php echo esc_attr($service['id']); ?>" data-category="<?php echo esc_attr($service['category']); ?>">
            <?php if (!empty($service['is_featured'])): ?>
                <span class="sbha-badge sbha-badge-featured">Featured</span>
            <?php endif; ?>
            <?php if (!empty($service['is_popular'])): ?>
                <span class="sbha-badge sbha-badge-popular">Popular</span>
            <?php endif; ?>

            <div class="sbha-service-icon">
                <?php echo $this->get_category_icon($service['category']); ?>
            </div>

            <h3 class="sbha-service-title"><?php echo esc_html($service['name']); ?></h3>

            <?php if (!empty($service['short_description'])): ?>
                <p class="sbha-service-desc"><?php echo esc_html($service['short_description']); ?></p>
            <?php endif; ?>

            <div class="sbha-service-price">
                <?php if (!empty($service['price_type']) && $service['price_type'] === 'starting_from'): ?>
                    <span class="sbha-price-label">From</span>
                <?php endif; ?>
                <span class="sbha-price-amount"><?php echo esc_html($currency . number_format($service['base_price'], 2)); ?></span>
            </div>

            <?php if (!empty($features) && is_array($features)): ?>
                <ul class="sbha-service-features">
                    <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="sbha-service-actions">
                <button class="sbha-btn sbha-btn-outline sbha-view-details" data-id="<?php echo esc_attr($service['id']); ?>">Details</button>
                <button class="sbha-btn sbha-btn-primary sbha-get-quote" data-id="<?php echo esc_attr($service['id']); ?>" data-name="<?php echo esc_attr($service['name']); ?>">Get Quote</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get category icon
     */
    private function get_category_icon($category) {
        $icons = array(
            'graphics_design' => '&#127912;',
            'printing' => '&#128424;',
            'web_services' => '&#128187;',
            'branding' => '&#127775;',
            'architectural' => '&#127970;'
        );
        return isset($icons[$category]) ? $icons[$category] : '&#128736;';
    }
}
