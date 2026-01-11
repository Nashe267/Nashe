<?php
/**
 * Admin Settings
 *
 * Plugin settings page
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin_Settings {

    /**
     * Render page
     */
    public function render() {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sbha_settings_nonce'])) {
            if (wp_verify_nonce($_POST['sbha_settings_nonce'], 'sbha_save_settings')) {
                $this->save_settings();
                echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
            }
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">Settings</h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=sbha-settings&tab=general'); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="<?php echo admin_url('admin.php?page=sbha-settings&tab=business'); ?>" class="nav-tab <?php echo $tab === 'business' ? 'nav-tab-active' : ''; ?>">Business</a>
                <a href="<?php echo admin_url('admin.php?page=sbha-settings&tab=ai'); ?>" class="nav-tab <?php echo $tab === 'ai' ? 'nav-tab-active' : ''; ?>">AI Settings</a>
                <a href="<?php echo admin_url('admin.php?page=sbha-settings&tab=notifications'); ?>" class="nav-tab <?php echo $tab === 'notifications' ? 'nav-tab-active' : ''; ?>">Notifications</a>
                <a href="<?php echo admin_url('admin.php?page=sbha-settings&tab=appearance'); ?>" class="nav-tab <?php echo $tab === 'appearance' ? 'nav-tab-active' : ''; ?>">Appearance</a>
            </nav>

            <form method="post" class="sbha-settings-form">
                <?php wp_nonce_field('sbha_save_settings', 'sbha_settings_nonce'); ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">

                <?php
                switch ($tab) {
                    case 'business':
                        $this->render_business_settings();
                        break;
                    case 'ai':
                        $this->render_ai_settings();
                        break;
                    case 'notifications':
                        $this->render_notification_settings();
                        break;
                    case 'appearance':
                        $this->render_appearance_settings();
                        break;
                    default:
                        $this->render_general_settings();
                }
                ?>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="sbha_business_name">Business Name</label></th>
                <td>
                    <input type="text" id="sbha_business_name" name="sbha_business_name" value="<?php echo esc_attr(get_option('sbha_business_name')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_business_email">Business Email</label></th>
                <td>
                    <input type="email" id="sbha_business_email" name="sbha_business_email" value="<?php echo esc_attr(get_option('sbha_business_email')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_business_phone">Business Phone</label></th>
                <td>
                    <input type="text" id="sbha_business_phone" name="sbha_business_phone" value="<?php echo esc_attr(get_option('sbha_business_phone')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_business_address">Business Address</label></th>
                <td>
                    <textarea id="sbha_business_address" name="sbha_business_address" rows="3" class="regular-text"><?php echo esc_textarea(get_option('sbha_business_address')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_welcome_message">Welcome Message</label></th>
                <td>
                    <textarea id="sbha_welcome_message" name="sbha_welcome_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('sbha_welcome_message')); ?></textarea>
                    <p class="description">This message is shown to customers when they first visit.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render business settings
     */
    private function render_business_settings() {
        $categories = json_decode(get_option('sbha_service_categories', '{}'), true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="sbha_currency">Currency</label></th>
                <td>
                    <select id="sbha_currency" name="sbha_currency">
                        <option value="USD" <?php selected(get_option('sbha_currency'), 'USD'); ?>>USD ($)</option>
                        <option value="EUR" <?php selected(get_option('sbha_currency'), 'EUR'); ?>>EUR (€)</option>
                        <option value="GBP" <?php selected(get_option('sbha_currency'), 'GBP'); ?>>GBP (£)</option>
                        <option value="ZAR" <?php selected(get_option('sbha_currency'), 'ZAR'); ?>>ZAR (R)</option>
                        <option value="NGN" <?php selected(get_option('sbha_currency'), 'NGN'); ?>>NGN (₦)</option>
                        <option value="KES" <?php selected(get_option('sbha_currency'), 'KES'); ?>>KES (KSh)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_currency_symbol">Currency Symbol</label></th>
                <td>
                    <input type="text" id="sbha_currency_symbol" name="sbha_currency_symbol" value="<?php echo esc_attr(get_option('sbha_currency_symbol', '$')); ?>" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_tax_rate">Tax Rate (%)</label></th>
                <td>
                    <input type="number" id="sbha_tax_rate" name="sbha_tax_rate" value="<?php echo esc_attr(get_option('sbha_tax_rate', 0)); ?>" step="0.01" min="0" max="100" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_tax_label">Tax Label</label></th>
                <td>
                    <input type="text" id="sbha_tax_label" name="sbha_tax_label" value="<?php echo esc_attr(get_option('sbha_tax_label', 'VAT')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_job_number_prefix">Job Number Prefix</label></th>
                <td>
                    <input type="text" id="sbha_job_number_prefix" name="sbha_job_number_prefix" value="<?php echo esc_attr(get_option('sbha_job_number_prefix', 'SBH')); ?>" class="small-text">
                    <p class="description">Example: SBH2501-0001</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_quote_validity_days">Quote Validity (days)</label></th>
                <td>
                    <input type="number" id="sbha_quote_validity_days" name="sbha_quote_validity_days" value="<?php echo esc_attr(get_option('sbha_quote_validity_days', 30)); ?>" min="1" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row">Service Categories</th>
                <td>
                    <div id="sbha-categories-list">
                        <?php foreach ($categories as $slug => $name): ?>
                            <div class="sbha-category-row">
                                <input type="text" name="category_slugs[]" value="<?php echo esc_attr($slug); ?>" placeholder="Slug" class="small-text">
                                <input type="text" name="category_names[]" value="<?php echo esc_attr($name); ?>" placeholder="Name" class="regular-text">
                                <button type="button" class="button sbha-remove-category">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="sbha-add-category" class="button">+ Add Category</button>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('#sbha-add-category').on('click', function() {
                $('#sbha-categories-list').append(
                    '<div class="sbha-category-row">' +
                    '<input type="text" name="category_slugs[]" placeholder="Slug" class="small-text">' +
                    '<input type="text" name="category_names[]" placeholder="Name" class="regular-text">' +
                    '<button type="button" class="button sbha-remove-category">&times;</button>' +
                    '</div>'
                );
            });

            $(document).on('click', '.sbha-remove-category', function() {
                $(this).parent().remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Render AI settings
     */
    private function render_ai_settings() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">AI Features</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sbha_ai_enabled" value="1" <?php checked(get_option('sbha_ai_enabled', 1)); ?>>
                            Enable AI Features
                        </label><br>
                        <label>
                            <input type="checkbox" name="sbha_ai_learning_enabled" value="1" <?php checked(get_option('sbha_ai_learning_enabled', 1)); ?>>
                            Enable AI Learning (collect data from customer interactions)
                        </label><br>
                        <label>
                            <input type="checkbox" name="sbha_ai_recommendations_enabled" value="1" <?php checked(get_option('sbha_ai_recommendations_enabled', 1)); ?>>
                            Enable AI Recommendations
                        </label><br>
                        <label>
                            <input type="checkbox" name="sbha_ai_insights_enabled" value="1" <?php checked(get_option('sbha_ai_insights_enabled', 1)); ?>>
                            Enable AI Business Insights
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_ai_provider">AI Provider</label></th>
                <td>
                    <select id="sbha_ai_provider" name="sbha_ai_provider">
                        <option value="local" <?php selected(get_option('sbha_ai_provider'), 'local'); ?>>Local (Rule-based)</option>
                        <option value="openai" <?php selected(get_option('sbha_ai_provider'), 'openai'); ?>>OpenAI</option>
                    </select>
                    <p class="description">Local uses built-in AI. OpenAI provides more advanced analysis.</p>
                </td>
            </tr>
            <tr class="sbha-openai-settings" style="<?php echo get_option('sbha_ai_provider') !== 'openai' ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="sbha_ai_api_key">OpenAI API Key</label></th>
                <td>
                    <input type="password" id="sbha_ai_api_key" name="sbha_ai_api_key" value="<?php echo esc_attr(get_option('sbha_ai_api_key')); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <h3>AI Insights Schedule</h3>
        <table class="form-table">
            <tr>
                <th scope="row">Daily Insights</th>
                <td>
                    <p>Generated automatically at 6:00 AM daily</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Weekly Reports</th>
                <td>
                    <p>Sent every Monday at 8:00 AM</p>
                </td>
            </tr>
            <tr>
                <th scope="row">AI Model Training</th>
                <td>
                    <p>Runs on the 1st of each month</p>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('#sbha_ai_provider').on('change', function() {
                if ($(this).val() === 'openai') {
                    $('.sbha-openai-settings').show();
                } else {
                    $('.sbha-openai-settings').hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render notification settings
     */
    private function render_notification_settings() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Email Notifications</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sbha_email_notifications" value="1" <?php checked(get_option('sbha_email_notifications', 1)); ?>>
                            Enable Email Notifications
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row">Notification Events</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sbha_notify_new_inquiry" value="1" <?php checked(get_option('sbha_notify_new_inquiry', 1)); ?>>
                            New Inquiry/Quote Request
                        </label><br>
                        <label>
                            <input type="checkbox" name="sbha_notify_job_status" value="1" <?php checked(get_option('sbha_notify_job_status', 1)); ?>>
                            Job Status Changes
                        </label><br>
                        <label>
                            <input type="checkbox" name="sbha_notify_payment" value="1" <?php checked(get_option('sbha_notify_payment', 1)); ?>>
                            Payment Received
                        </label><br>
                        <label>
                            <input type="checkbox" name="sbha_notify_weekly_report" value="1" <?php checked(get_option('sbha_notify_weekly_report', 1)); ?>>
                            Weekly Business Report
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_notification_email">Notification Email</label></th>
                <td>
                    <input type="email" id="sbha_notification_email" name="sbha_notification_email" value="<?php echo esc_attr(get_option('sbha_notification_email', get_option('admin_email'))); ?>" class="regular-text">
                    <p class="description">Admin notifications will be sent to this email.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render appearance settings
     */
    private function render_appearance_settings() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="sbha_primary_color">Primary Color</label></th>
                <td>
                    <input type="color" id="sbha_primary_color" name="sbha_primary_color" value="<?php echo esc_attr(get_option('sbha_primary_color', '#FF6600')); ?>">
                    <span class="description">Orange (#FF6600) by default</span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_secondary_color">Secondary Color</label></th>
                <td>
                    <input type="color" id="sbha_secondary_color" name="sbha_secondary_color" value="<?php echo esc_attr(get_option('sbha_secondary_color', '#000000')); ?>">
                    <span class="description">Black (#000000) by default</span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sbha_accent_color">Accent Color</label></th>
                <td>
                    <input type="color" id="sbha_accent_color" name="sbha_accent_color" value="<?php echo esc_attr(get_option('sbha_accent_color', '#FFFFFF')); ?>">
                    <span class="description">White (#FFFFFF) by default</span>
                </td>
            </tr>
        </table>

        <h3>Preview</h3>
        <div class="sbha-color-preview" style="padding: 20px; background: <?php echo esc_attr(get_option('sbha_secondary_color', '#000000')); ?>;">
            <h2 style="color: <?php echo esc_attr(get_option('sbha_accent_color', '#FFFFFF')); ?>; margin: 0;">Switch Business Hub</h2>
            <p style="color: <?php echo esc_attr(get_option('sbha_accent_color', '#FFFFFF')); ?>;">Your professional design partner</p>
            <button style="background: <?php echo esc_attr(get_option('sbha_primary_color', '#FF6600')); ?>; color: white; border: none; padding: 10px 20px; cursor: pointer;">Get Quote</button>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'general';

        $settings = array();

        switch ($tab) {
            case 'general':
                $settings = array(
                    'sbha_business_name',
                    'sbha_business_email',
                    'sbha_business_phone',
                    'sbha_business_address',
                    'sbha_welcome_message'
                );
                break;

            case 'business':
                $settings = array(
                    'sbha_currency',
                    'sbha_currency_symbol',
                    'sbha_tax_rate',
                    'sbha_tax_label',
                    'sbha_job_number_prefix',
                    'sbha_quote_validity_days'
                );

                // Handle categories
                if (isset($_POST['category_slugs']) && isset($_POST['category_names'])) {
                    $categories = array();
                    foreach ($_POST['category_slugs'] as $i => $slug) {
                        $slug = sanitize_title($slug);
                        $name = sanitize_text_field($_POST['category_names'][$i]);
                        if (!empty($slug) && !empty($name)) {
                            $categories[$slug] = $name;
                        }
                    }
                    update_option('sbha_service_categories', json_encode($categories));
                }
                break;

            case 'ai':
                $checkboxes = array('sbha_ai_enabled', 'sbha_ai_learning_enabled', 'sbha_ai_recommendations_enabled', 'sbha_ai_insights_enabled');
                foreach ($checkboxes as $cb) {
                    update_option($cb, isset($_POST[$cb]) ? 1 : 0);
                }
                $settings = array('sbha_ai_provider', 'sbha_ai_api_key');
                break;

            case 'notifications':
                $checkboxes = array('sbha_email_notifications', 'sbha_notify_new_inquiry', 'sbha_notify_job_status', 'sbha_notify_payment', 'sbha_notify_weekly_report');
                foreach ($checkboxes as $cb) {
                    update_option($cb, isset($_POST[$cb]) ? 1 : 0);
                }
                $settings = array('sbha_notification_email');
                break;

            case 'appearance':
                $settings = array('sbha_primary_color', 'sbha_secondary_color', 'sbha_accent_color');
                break;
        }

        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
    }
}
