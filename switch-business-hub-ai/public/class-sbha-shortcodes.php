<?php
/**
 * Shortcodes - Premium Single Page App
 */

if (!defined('ABSPATH')) exit;

class SBHA_Shortcodes {

    public function __construct() {
        add_shortcode('switch_hub', array($this, 'render'));
    }

    public function render($atts) {
        global $wpdb;

        $atts = shortcode_atts(array(
            'primary' => get_option('sbha_primary_color', '#FF6600'),
            'secondary' => get_option('sbha_secondary_color', '#1a1a2e'),
        ), $atts);

        $business = get_option('sbha_business_name', 'Switch Hub');
        $phone = get_option('sbha_business_phone', '');
        $whatsapp = get_option('sbha_whatsapp', '');
        $email = get_option('sbha_business_email', '');
        $currency = get_option('sbha_currency_symbol', 'R');

        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbha_services WHERE status = 'active' ORDER BY display_order ASC", ARRAY_A);

        $categories = array(
            'graphics' => 'Graphics Design',
            'printing' => 'Printing',
            'web' => 'Web Services',
            'branding' => 'Branding',
            'architectural' => 'Architecture'
        );

        $customer = $this->get_customer();

        ob_start();
        ?>
        <div class="sh-app" id="switch-hub-app"
             data-ajax="<?php echo admin_url('admin-ajax.php'); ?>"
             data-nonce="<?php echo wp_create_nonce('sbha_nonce'); ?>"
             style="--primary: <?php echo esc_attr($atts['primary']); ?>; --secondary: <?php echo esc_attr($atts['secondary']); ?>;">

            <!-- Header -->
            <header class="sh-header">
                <div class="sh-brand"><h1><?php echo esc_html($business); ?></h1></div>
                <div class="sh-header-right">
                    <?php if ($customer): ?>
                        <div class="sh-user-menu">
                            <button class="sh-user-btn" id="user-menu-btn">
                                <span class="sh-avatar"><?php echo strtoupper(substr($customer['first_name'], 0, 1)); ?></span>
                                <span><?php echo esc_html($customer['first_name']); ?></span>
                            </button>
                            <div class="sh-dropdown" id="user-dropdown">
                                <a href="#" data-panel="track">My Orders</a>
                                <a href="#" data-panel="documents">Documents</a>
                                <a href="#" id="logout-btn">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <button class="sh-btn sh-btn-outline" id="login-btn">Login</button>
                    <?php endif; ?>
                    <span class="sh-notif-icon" id="notif-btn">🔔<span class="sh-badge" id="notif-badge" style="display:none;">0</span></span>
                </div>
            </header>

            <!-- AI Section -->
            <section class="sh-ai">
                <div class="sh-ai-box">
                    <div class="sh-ai-head">
                        <div class="sh-ai-avatar">🤖</div>
                        <div><h2>Hi, I'm Switch!</h2><p>Welcome to <?php echo esc_html($business); ?></p></div>
                    </div>
                    <div class="sh-ai-chat" id="ai-chat">
                        <div class="sh-msg sh-msg-bot">
                            <p>What can I help you with today?</p>
                            <div class="sh-quick-btns">
                                <button data-q="I need business cards">Business Cards</button>
                                <button data-q="I need a logo">Logo Design</button>
                                <button data-q="I need a website">Website</button>
                                <button data-q="I need flyers">Flyers</button>
                                <button data-q="I need banners">Banners</button>
                                <button data-q="Track my order">Track Order</button>
                            </div>
                        </div>
                    </div>
                    <div class="sh-ai-input">
                        <input type="text" id="ai-input" placeholder="Type what you need..." autocomplete="off">
                        <button id="ai-send" class="sh-btn sh-btn-primary">Send</button>
                    </div>
                </div>
            </section>

            <!-- Panels -->
            <main class="sh-main">
                <!-- Services -->
                <section class="sh-panel active" id="panel-services">
                    <div class="sh-section-head">
                        <h2>Our Services</h2>
                        <div class="sh-filters">
                            <button class="sh-filter active" data-cat="all">All</button>
                            <?php foreach ($categories as $slug => $name): ?>
                                <button class="sh-filter" data-cat="<?php echo $slug; ?>"><?php echo $name; ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sh-grid">
                        <?php foreach ($services as $s): echo $this->card($s, $currency); endforeach; ?>
                    </div>
                </section>

                <!-- Quote -->
                <section class="sh-panel" id="panel-quote">
                    <div class="sh-card">
                        <h2>📝 Request Quote</h2>
                        <form id="quote-form" class="sh-form">
                            <?php if (!$customer): ?>
                            <div class="sh-form-section">
                                <h3>Your Details</h3>
                                <div class="sh-row">
                                    <div class="sh-field"><label>Name *</label><input type="text" name="customer_name" required></div>
                                    <div class="sh-field"><label>Email *</label><input type="email" name="customer_email" required></div>
                                </div>
                                <div class="sh-row">
                                    <div class="sh-field"><label>Phone *</label><input type="tel" name="customer_phone" required></div>
                                    <div class="sh-field"><label>Company</label><input type="text" name="customer_company"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="sh-form-section">
                                <h3>Service</h3>
                                <div class="sh-field">
                                    <label>Service *</label>
                                    <select name="service_type" id="quote-service" required>
                                        <option value="">Select...</option>
                                        <?php foreach ($services as $s): ?>
                                            <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['base_price']; ?>"><?php echo $s['name']; ?> - From <?php echo $currency . number_format($s['base_price'], 2); ?></option>
                                        <?php endforeach; ?>
                                        <option value="custom">Custom Request</option>
                                    </select>
                                </div>
                                <div class="sh-field sh-custom-field" style="display:none;"><label>Custom</label><input type="text" name="custom_service"></div>
                                <div class="sh-row">
                                    <div class="sh-field"><label>Qty</label><input type="number" name="quantity" value="1" min="1" id="quote-qty"></div>
                                    <div class="sh-field"><label>Urgency</label><select name="urgency" id="quote-urg"><option value="standard">Standard</option><option value="express">Express +25%</option><option value="rush">Rush +50%</option></select></div>
                                </div>
                                <div class="sh-field"><label>Title</label><input type="text" name="project_title"></div>
                                <div class="sh-field"><label>Description *</label><textarea name="description" rows="3" required></textarea></div>
                                <div class="sh-field"><label>Files</label><input type="file" name="files[]" multiple></div>
                            </div>
                            <div class="sh-form-section">
                                <h3>Your Budget (Optional)</h3>
                                <p style="font-size:13px;color:#666;margin-bottom:15px;">Let us know your budget and we'll try to work with you</p>
                                <div class="sh-field">
                                    <label>My Budget / What I Can Afford (<?php echo $currency; ?>)</label>
                                    <input type="number" name="client_budget" id="client-budget" placeholder="Enter your budget amount" min="0" step="0.01">
                                </div>
                                <div class="sh-field">
                                    <label>Budget Notes</label>
                                    <textarea name="budget_notes" rows="2" placeholder="Any notes about your budget or payment preferences..."></textarea>
                                </div>
                            </div>
                            <div class="sh-preview">
                                <div class="sh-preview-row"><span>Service:</span><span id="pv-svc">-</span></div>
                                <div class="sh-preview-row"><span>Qty:</span><span id="pv-qty">1</span></div>
                                <div class="sh-preview-row sh-preview-total"><span>Est. Total:</span><span id="pv-total"><?php echo $currency; ?>0.00</span></div>
                            </div>
                            <button type="submit" class="sh-btn sh-btn-primary sh-btn-lg sh-btn-block"><span class="btn-txt">Submit</span><span class="btn-load" style="display:none;">...</span></button>
                        </form>
                        <div id="quote-msg"></div>
                    </div>
                </section>

                <!-- Track -->
                <section class="sh-panel" id="panel-track">
                    <div class="sh-card">
                        <h2>📦 Track Order</h2>
                        <form id="track-form"><div class="sh-track-row"><input type="text" id="track-input" placeholder="Order # or Email" required><button type="submit" class="sh-btn sh-btn-primary">Track</button></div></form>
                        <div id="track-results"></div>
                        <?php if ($customer): ?><div class="sh-my-orders"><h3>My Orders</h3><div id="my-orders"></div></div><?php endif; ?>
                    </div>
                </section>

                <!-- Documents -->
                <section class="sh-panel" id="panel-documents">
                    <div class="sh-card">
                        <h2>📄 Documents</h2>
                        <?php if ($customer): ?>
                            <div id="docs-list"></div>
                        <?php else: ?>
                            <form id="docs-form"><div class="sh-track-row"><input type="email" name="email" placeholder="Your Email" required><button type="submit" class="sh-btn sh-btn-primary">Find</button></div></form>
                            <div id="docs-results"></div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Contact -->
                <section class="sh-panel" id="panel-contact">
                    <div class="sh-contact">
                        <div class="sh-contact-info">
                            <h2>📞 Contact</h2>
                            <?php if ($phone): ?><a href="tel:<?php echo $phone; ?>" class="sh-contact-card"><span>📱</span><span>Call</span><span><?php echo $phone; ?></span></a><?php endif; ?>
                            <?php if ($whatsapp): ?><a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>" class="sh-contact-card sh-wa" target="_blank"><span>💬</span><span>WhatsApp</span><span>Chat</span></a><?php endif; ?>
                            <?php if ($email): ?><a href="mailto:<?php echo $email; ?>" class="sh-contact-card"><span>✉️</span><span>Email</span><span><?php echo $email; ?></span></a><?php endif; ?>
                        </div>
                        <div class="sh-card">
                            <h3>Message</h3>
                            <form id="contact-form" class="sh-form">
                                <div class="sh-field"><input type="text" name="name" placeholder="Name *" required></div>
                                <div class="sh-field"><input type="email" name="email" placeholder="Email *" required></div>
                                <div class="sh-field"><input type="tel" name="phone" placeholder="Phone"></div>
                                <div class="sh-field"><textarea name="message" rows="3" placeholder="Message *" required></textarea></div>
                                <button type="submit" class="sh-btn sh-btn-primary sh-btn-block">Send</button>
                            </form>
                            <div id="contact-msg"></div>
                        </div>
                    </div>
                </section>
            </main>

            <!-- Auth Modal -->
            <div class="sh-modal" id="auth-modal">
                <div class="sh-modal-bg"></div>
                <div class="sh-modal-box">
                    <button class="sh-modal-close">&times;</button>
                    <div class="sh-auth-tabs">
                        <button class="sh-auth-tab active" data-tab="login">Login</button>
                        <button class="sh-auth-tab" data-tab="register">Register</button>
                        <button class="sh-auth-tab" data-tab="reset">Reset</button>
                    </div>
                    <form id="login-form" class="sh-auth-form">
                        <div class="sh-field"><label>Email</label><input type="email" name="email" required></div>
                        <div class="sh-field"><label>Password</label><input type="password" name="password" required></div>
                        <button type="submit" class="sh-btn sh-btn-primary sh-btn-block">Login</button>
                        <div id="login-msg"></div>
                    </form>
                    <form id="register-form" class="sh-auth-form" style="display:none;">
                        <div class="sh-row"><div class="sh-field"><label>First Name *</label><input type="text" name="first_name" required></div><div class="sh-field"><label>Last Name *</label><input type="text" name="last_name" required></div></div>
                        <div class="sh-field"><label>Business Name</label><input type="text" name="business_name"></div>
                        <div class="sh-field"><label>Cell Number *</label><input type="tel" name="cell_number" required></div>
                        <div class="sh-field"><label>WhatsApp</label><input type="tel" name="whatsapp_number"></div>
                        <div class="sh-field"><label>Email *</label><input type="email" name="email" required></div>
                        <div class="sh-field"><label>Password *</label><input type="password" name="password" required minlength="6"></div>
                        <button type="submit" class="sh-btn sh-btn-primary sh-btn-block">Register</button>
                        <div id="register-msg"></div>
                    </form>
                    <form id="reset-form" class="sh-auth-form" style="display:none;">
                        <p>Enter email and new password</p>
                        <div class="sh-field"><label>Email</label><input type="email" name="email" required></div>
                        <div class="sh-field"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
                        <button type="submit" class="sh-btn sh-btn-primary sh-btn-block">Reset</button>
                        <div id="reset-msg"></div>
                    </form>
                </div>
            </div>

            <!-- Notif Panel -->
            <div class="sh-notif-panel" id="notif-panel"><h4>Notifications</h4><div id="notif-list"></div></div>

            <!-- Toast -->
            <div class="sh-toasts" id="toasts"></div>

            <!-- Bottom Nav -->
            <nav class="sh-nav">
                <button class="sh-nav-btn active" data-panel="services"><span>🏠</span><span>Services</span></button>
                <button class="sh-nav-btn" data-panel="quote"><span>📝</span><span>Quote</span></button>
                <button class="sh-nav-btn sh-nav-ai" id="nav-ai"><span>🤖</span></button>
                <button class="sh-nav-btn" data-panel="track"><span>📦</span><span>Track</span></button>
                <button class="sh-nav-btn" data-panel="contact"><span>📞</span><span>Contact</span></button>
            </nav>

        </div>
        <?php
        return ob_get_clean();
    }

    private function card($s, $c) {
        $img = $s['image_url'] ?: '';
        $ph = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200"%3E%3Crect fill="%23eee" width="300" height="200"/%3E%3C/svg%3E';
        ob_start(); ?>
        <div class="sh-card sh-service" data-id="<?php echo $s['id']; ?>" data-cat="<?php echo $s['category']; ?>" data-price="<?php echo $s['base_price']; ?>">
            <?php if ($s['is_featured']): ?><span class="sh-feat">⭐ Featured</span><?php endif; ?>
            <div class="sh-img" style="background-image:url('<?php echo $img ?: $ph; ?>');"><?php if (!$img): ?><span><?php echo $this->emoji($s['category']); ?></span><?php endif; ?></div>
            <div class="sh-body">
                <h3><?php echo esc_html($s['name']); ?></h3>
                <p><?php echo esc_html($s['short_description']); ?></p>
                <div class="sh-price"><span>From</span><strong><?php echo $c . number_format($s['base_price'], 2); ?></strong></div>
                <button class="sh-btn sh-btn-primary sh-btn-sm sh-get-quote">Get Quote</button>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private function emoji($c) {
        return array('graphics'=>'🎨','printing'=>'🖨️','web'=>'💻','branding'=>'⭐','architectural'=>'🏛️')[$c] ?? '📦';
    }

    private function get_customer() {
        global $wpdb;
        $t = $_COOKIE['sbha_token'] ?? '';
        if (!$t) return null;
        $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_sessions WHERE session_token=%s AND expires_at>NOW()", $t), ARRAY_A);
        return $s ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_customers WHERE id=%d AND status='active'", $s['customer_id']), ARRAY_A) : null;
    }
}

new SBHA_Shortcodes();
