<?php
/**
 * Shortcodes Class
 *
 * Single-page business hub with AI assistant
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Shortcodes {

    public function __construct() {
        add_shortcode('switch_hub', array($this, 'render_hub'));
    }

    /**
     * Main hub - Complete single-page application
     */
    public function render_hub($atts) {
        $atts = shortcode_atts(array(
            'primary_color' => '#FF6600',
            'secondary_color' => '#333333',
            'business_name' => get_option('sbha_business_name', 'Switch Business Hub'),
            'whatsapp' => get_option('sbha_whatsapp', ''),
            'phone' => get_option('sbha_business_phone', ''),
            'email' => get_option('sbha_business_email', ''),
        ), $atts);

        $services = SBHA()->get_service_catalog()->get_services();
        $categories = SBHA()->get_service_catalog()->get_categories();
        $currency = get_option('sbha_currency_symbol', '$');

        // Check if user is logged in (customer)
        $current_user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();

        ob_start();
        ?>
        <div class="sbha-hub-app"
             data-ajax="<?php echo admin_url('admin-ajax.php'); ?>"
             data-nonce="<?php echo wp_create_nonce('sbha_nonce'); ?>"
             style="--primary: <?php echo esc_attr($atts['primary_color']); ?>; --secondary: <?php echo esc_attr($atts['secondary_color']); ?>;">

            <!-- Header -->
            <header class="sbha-header">
                <div class="sbha-logo">
                    <h1><?php echo esc_html($atts['business_name']); ?></h1>
                    <span class="sbha-tagline">Graphics • Printing • Web • Branding • Architecture</span>
                </div>
                <div class="sbha-header-actions">
                    <?php if ($is_logged_in): ?>
                        <button class="sbha-user-btn" data-action="my-orders">
                            <span class="sbha-icon">👤</span>
                            <span><?php echo esc_html($current_user->display_name); ?></span>
                        </button>
                    <?php else: ?>
                        <button class="sbha-btn sbha-btn-outline sbha-login-btn">Login</button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="sbha-main-content">

                <!-- AI Assistant Section - Always visible at top -->
                <section class="sbha-ai-assistant" id="sbha-ai-section">
                    <div class="sbha-ai-container">
                        <div class="sbha-ai-header">
                            <span class="sbha-ai-avatar">🤖</span>
                            <div class="sbha-ai-intro">
                                <h2>Hi! I'm your AI Assistant</h2>
                                <p>Tell me what you need - I'll help you find the right service or create a custom quote</p>
                            </div>
                        </div>

                        <div class="sbha-ai-chat" id="sbha-chat-messages">
                            <div class="sbha-chat-message sbha-bot-message">
                                <p>👋 Welcome! What can I help you with today?</p>
                                <div class="sbha-quick-options">
                                    <button class="sbha-quick-btn" data-query="I need business cards">Business Cards</button>
                                    <button class="sbha-quick-btn" data-query="I need a logo design">Logo Design</button>
                                    <button class="sbha-quick-btn" data-query="I need a website">Website</button>
                                    <button class="sbha-quick-btn" data-query="I need flyers printed">Flyers</button>
                                    <button class="sbha-quick-btn" data-query="I need architectural drawings">Architecture</button>
                                    <button class="sbha-quick-btn" data-query="Track my order">Track Order</button>
                                </div>
                            </div>
                        </div>

                        <div class="sbha-ai-input-area">
                            <input type="text" id="sbha-ai-input" placeholder="Type what you need... (e.g., 'I need 500 flyers for my event')" autocomplete="off">
                            <button id="sbha-ai-send" class="sbha-btn sbha-btn-primary">
                                <span>Send</span>
                                <span class="sbha-send-icon">➤</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Panels Container -->
                <div class="sbha-panels">

                    <!-- Services Panel -->
                    <section class="sbha-panel sbha-panel-active" id="sbha-panel-services">
                        <div class="sbha-panel-header">
                            <h2>Our Services</h2>
                            <div class="sbha-category-filters">
                                <button class="sbha-cat-btn active" data-cat="all">All</button>
                                <?php foreach ($categories as $slug => $name): ?>
                                    <button class="sbha-cat-btn" data-cat="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="sbha-services-grid">
                            <?php foreach ($services as $service): ?>
                                <?php echo $this->render_service_card($service, $currency); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- Quote/Order Panel -->
                    <section class="sbha-panel" id="sbha-panel-quote">
                        <div class="sbha-quote-builder">
                            <h2>📝 Request a Quote</h2>
                            <p>Fill in your details and get an instant quote you can download</p>

                            <form id="sbha-quote-form" class="sbha-form">
                                <?php wp_nonce_field('sbha_quote_nonce', 'quote_nonce'); ?>
                                <input type="hidden" name="service_id" id="quote-service-id" value="">

                                <div class="sbha-form-section">
                                    <h3>Your Information</h3>
                                    <div class="sbha-form-row">
                                        <div class="sbha-field">
                                            <label>Full Name *</label>
                                            <input type="text" name="customer_name" required>
                                        </div>
                                        <div class="sbha-field">
                                            <label>Email *</label>
                                            <input type="email" name="customer_email" required>
                                        </div>
                                    </div>
                                    <div class="sbha-form-row">
                                        <div class="sbha-field">
                                            <label>Phone *</label>
                                            <input type="tel" name="customer_phone" required>
                                        </div>
                                        <div class="sbha-field">
                                            <label>Company/Organization</label>
                                            <input type="text" name="customer_company">
                                        </div>
                                    </div>
                                </div>

                                <div class="sbha-form-section">
                                    <h3>Service Details</h3>
                                    <div class="sbha-field">
                                        <label>Service Type *</label>
                                        <select name="service_type" id="quote-service-select" required>
                                            <option value="">Select a service...</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?php echo esc_attr($service['id']); ?>" data-price="<?php echo esc_attr($service['base_price']); ?>">
                                                    <?php echo esc_html($service['name']); ?> - From <?php echo $currency . number_format($service['base_price'], 2); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="custom">Custom Request (Describe Below)</option>
                                        </select>
                                    </div>
                                    <div class="sbha-form-row">
                                        <div class="sbha-field">
                                            <label>Quantity</label>
                                            <input type="number" name="quantity" value="1" min="1" id="quote-quantity">
                                        </div>
                                        <div class="sbha-field">
                                            <label>Urgency</label>
                                            <select name="urgency">
                                                <option value="standard">Standard (5-7 days)</option>
                                                <option value="express">Express (2-3 days) +25%</option>
                                                <option value="rush">Rush (24 hours) +50%</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="sbha-field">
                                        <label>Project Description *</label>
                                        <textarea name="description" rows="4" required placeholder="Describe what you need in detail..."></textarea>
                                    </div>
                                    <div class="sbha-field">
                                        <label>Upload Reference Files</label>
                                        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.ai,.psd,.doc,.docx">
                                        <small>Accepted: Images, PDF, AI, PSD, DOC (Max 10MB each)</small>
                                    </div>
                                </div>

                                <!-- Instant Quote Preview -->
                                <div class="sbha-quote-preview" id="quote-preview">
                                    <h3>💰 Estimated Quote</h3>
                                    <div class="sbha-quote-summary">
                                        <div class="sbha-quote-line">
                                            <span>Service:</span>
                                            <span id="preview-service">-</span>
                                        </div>
                                        <div class="sbha-quote-line">
                                            <span>Quantity:</span>
                                            <span id="preview-qty">1</span>
                                        </div>
                                        <div class="sbha-quote-line">
                                            <span>Base Price:</span>
                                            <span id="preview-base"><?php echo $currency; ?>0.00</span>
                                        </div>
                                        <div class="sbha-quote-line sbha-quote-total">
                                            <span>Estimated Total:</span>
                                            <span id="preview-total"><?php echo $currency; ?>0.00</span>
                                        </div>
                                    </div>
                                    <p class="sbha-quote-note">* Final price may vary based on specifications</p>
                                </div>

                                <div class="sbha-form-actions">
                                    <button type="submit" class="sbha-btn sbha-btn-primary sbha-btn-large">
                                        <span class="btn-text">Submit & Get Quote PDF</span>
                                        <span class="btn-loading" style="display:none;">Processing...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- Track Order Panel -->
                    <section class="sbha-panel" id="sbha-panel-track">
                        <div class="sbha-track-container">
                            <h2>🔍 Track Your Order</h2>
                            <p>Enter your order number or email to check status</p>

                            <form id="sbha-track-form" class="sbha-form">
                                <div class="sbha-track-input-group">
                                    <input type="text" id="track-input" placeholder="Order # or Email address" required>
                                    <button type="submit" class="sbha-btn sbha-btn-primary">Track</button>
                                </div>
                            </form>

                            <div id="sbha-track-results"></div>

                            <!-- Customer Orders (if logged in) -->
                            <?php if ($is_logged_in): ?>
                            <div class="sbha-my-orders">
                                <h3>My Recent Orders</h3>
                                <div id="sbha-customer-orders">
                                    <p class="sbha-loading">Loading your orders...</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- My Quotes & Invoices Panel -->
                    <section class="sbha-panel" id="sbha-panel-documents">
                        <div class="sbha-documents-container">
                            <h2>📄 My Quotes & Invoices</h2>

                            <?php if ($is_logged_in): ?>
                                <div class="sbha-doc-tabs">
                                    <button class="sbha-doc-tab active" data-doc="quotes">Quotes</button>
                                    <button class="sbha-doc-tab" data-doc="invoices">Invoices</button>
                                </div>
                                <div id="sbha-documents-list">
                                    <p class="sbha-loading">Loading...</p>
                                </div>
                            <?php else: ?>
                                <div class="sbha-login-prompt">
                                    <p>Please login or enter your email to view your documents</p>
                                    <form id="sbha-doc-lookup-form">
                                        <input type="email" name="email" placeholder="Your email address" required>
                                        <button type="submit" class="sbha-btn sbha-btn-primary">Find My Documents</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Contact Panel -->
                    <section class="sbha-panel" id="sbha-panel-contact">
                        <div class="sbha-contact-container">
                            <h2>📞 Contact Us</h2>

                            <div class="sbha-contact-grid">
                                <div class="sbha-contact-info">
                                    <?php if ($atts['phone']): ?>
                                    <a href="tel:<?php echo esc_attr($atts['phone']); ?>" class="sbha-contact-card">
                                        <span class="sbha-contact-icon">📱</span>
                                        <span>Call Us</span>
                                        <span class="sbha-contact-value"><?php echo esc_html($atts['phone']); ?></span>
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($atts['whatsapp']): ?>
                                    <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $atts['whatsapp'])); ?>" class="sbha-contact-card sbha-whatsapp" target="_blank">
                                        <span class="sbha-contact-icon">💬</span>
                                        <span>WhatsApp</span>
                                        <span class="sbha-contact-value">Chat Now</span>
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($atts['email']): ?>
                                    <a href="mailto:<?php echo esc_attr($atts['email']); ?>" class="sbha-contact-card">
                                        <span class="sbha-contact-icon">✉️</span>
                                        <span>Email</span>
                                        <span class="sbha-contact-value"><?php echo esc_html($atts['email']); ?></span>
                                    </a>
                                    <?php endif; ?>
                                </div>

                                <div class="sbha-contact-form-wrap">
                                    <h3>Send us a message</h3>
                                    <form id="sbha-contact-form" class="sbha-form">
                                        <?php wp_nonce_field('sbha_contact_nonce', 'contact_nonce'); ?>
                                        <div class="sbha-field">
                                            <input type="text" name="name" placeholder="Your Name *" required>
                                        </div>
                                        <div class="sbha-field">
                                            <input type="email" name="email" placeholder="Email *" required>
                                        </div>
                                        <div class="sbha-field">
                                            <input type="tel" name="phone" placeholder="Phone">
                                        </div>
                                        <div class="sbha-field">
                                            <textarea name="message" rows="4" placeholder="Your Message *" required></textarea>
                                        </div>
                                        <button type="submit" class="sbha-btn sbha-btn-primary">Send Message</button>
                                    </form>
                                    <div id="contact-message"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                </div><!-- .sbha-panels -->
            </main>

            <!-- Login/Register Modal -->
            <div class="sbha-modal" id="sbha-auth-modal">
                <div class="sbha-modal-overlay"></div>
                <div class="sbha-modal-box">
                    <button class="sbha-modal-close">&times;</button>
                    <div class="sbha-auth-tabs">
                        <button class="sbha-auth-tab active" data-auth="login">Login</button>
                        <button class="sbha-auth-tab" data-auth="register">Register</button>
                    </div>

                    <form id="sbha-login-form" class="sbha-auth-form">
                        <?php wp_nonce_field('sbha_login_nonce', 'login_nonce'); ?>
                        <div class="sbha-field">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="sbha-field">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" class="sbha-btn sbha-btn-primary sbha-btn-block">Login</button>
                        <div id="login-message"></div>
                    </form>

                    <form id="sbha-register-form" class="sbha-auth-form" style="display:none;">
                        <?php wp_nonce_field('sbha_register_nonce', 'register_nonce'); ?>
                        <div class="sbha-field">
                            <label>Full Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="sbha-field">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="sbha-field">
                            <label>Phone</label>
                            <input type="tel" name="phone" required>
                        </div>
                        <div class="sbha-field">
                            <label>Password</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                        <button type="submit" class="sbha-btn sbha-btn-primary sbha-btn-block">Create Account</button>
                        <div id="register-message"></div>
                    </form>
                </div>
            </div>

            <!-- Quote/Invoice View Modal -->
            <div class="sbha-modal" id="sbha-doc-modal">
                <div class="sbha-modal-overlay"></div>
                <div class="sbha-modal-box sbha-modal-large">
                    <button class="sbha-modal-close">&times;</button>
                    <div id="sbha-doc-content"></div>
                </div>
            </div>

            <!-- Notification Toast -->
            <div class="sbha-notifications" id="sbha-notifications"></div>

            <!-- Sticky Bottom Navigation -->
            <nav class="sbha-bottom-nav">
                <button class="sbha-nav-item active" data-panel="services">
                    <span class="sbha-nav-icon">🛍️</span>
                    <span class="sbha-nav-label">Services</span>
                </button>
                <button class="sbha-nav-item" data-panel="quote">
                    <span class="sbha-nav-icon">📝</span>
                    <span class="sbha-nav-label">Quote</span>
                </button>
                <button class="sbha-nav-item sbha-nav-ai" data-action="focus-ai">
                    <span class="sbha-nav-icon">🤖</span>
                    <span class="sbha-nav-label">AI Help</span>
                </button>
                <button class="sbha-nav-item" data-panel="track">
                    <span class="sbha-nav-icon">📦</span>
                    <span class="sbha-nav-label">Track</span>
                    <span class="sbha-nav-badge" id="orders-badge" style="display:none;">0</span>
                </button>
                <button class="sbha-nav-item" data-panel="documents">
                    <span class="sbha-nav-icon">📄</span>
                    <span class="sbha-nav-label">Docs</span>
                </button>
                <button class="sbha-nav-item" data-panel="contact">
                    <span class="sbha-nav-icon">📞</span>
                    <span class="sbha-nav-label">Contact</span>
                </button>
            </nav>

        </div><!-- .sbha-hub-app -->
        <?php
        return ob_get_clean();
    }

    /**
     * Render service card
     */
    private function render_service_card($service, $currency = '$') {
        $features = is_string($service['features']) ? json_decode($service['features'], true) : $service['features'];
        if (!is_array($features)) $features = array();

        ob_start();
        ?>
        <div class="sbha-service-card"
             data-id="<?php echo esc_attr($service['id']); ?>"
             data-category="<?php echo esc_attr($service['category']); ?>"
             data-name="<?php echo esc_attr($service['name']); ?>"
             data-price="<?php echo esc_attr($service['base_price']); ?>">

            <?php if (!empty($service['is_featured'])): ?>
                <span class="sbha-badge sbha-badge-featured">⭐ Featured</span>
            <?php endif; ?>

            <div class="sbha-service-icon">
                <?php echo $this->get_category_icon($service['category']); ?>
            </div>

            <h3><?php echo esc_html($service['name']); ?></h3>

            <?php if (!empty($service['short_description'])): ?>
                <p class="sbha-service-desc"><?php echo esc_html($service['short_description']); ?></p>
            <?php endif; ?>

            <div class="sbha-service-price">
                <span class="sbha-price-label">From</span>
                <span class="sbha-price-amount"><?php echo $currency . number_format($service['base_price'], 2); ?></span>
            </div>

            <?php if (!empty($features)): ?>
            <ul class="sbha-service-features">
                <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                    <li>✓ <?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <div class="sbha-service-actions">
                <button class="sbha-btn sbha-btn-primary sbha-order-btn">Get Quote</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_category_icon($category) {
        $icons = array(
            'graphics_design' => '🎨',
            'printing' => '🖨️',
            'web_services' => '💻',
            'branding' => '⭐',
            'architectural' => '🏛️'
        );
        return isset($icons[$category]) ? $icons[$category] : '📦';
    }
}
