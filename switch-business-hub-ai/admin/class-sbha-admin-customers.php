<?php
/**
 * Admin Customers
 *
 * Customer management admin page
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin_Customers {

    /**
     * Render page
     */
    public function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'view':
                $this->render_view($id);
                break;
            case 'new':
            case 'edit':
                $this->render_form($id);
                break;
            default:
                $this->render_list();
        }
    }

    /**
     * Render customers list
     */
    private function render_list() {
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $customers = SBHA_Customer::get_customers(array(
            'search' => $search,
            'limit' => 50
        ));
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                Customers
                <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=new'); ?>" class="page-title-action">Add New</a>
            </h1>

            <form method="get" class="sbha-search-form">
                <input type="hidden" name="page" value="sbha-customers">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search customers...">
                    <input type="submit" class="button" value="Search">
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped sbha-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Orders</th>
                        <th>Lifetime Value</th>
                        <th>Status</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="7">No customers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=view&id=' . $customer['id']); ?>">
                                        <strong><?php echo esc_html(SBHA_Customer::get_display_name($customer)); ?></strong>
                                    </a>
                                    <?php
                                    $segment = SBHA_Customer::get_customer_segment($customer);
                                    if ($segment === 'vip'):
                                    ?>
                                        <span class="sbha-badge vip">VIP</span>
                                    <?php elseif ($segment === 'loyal'): ?>
                                        <span class="sbha-badge loyal">Loyal</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($customer['email']); ?></td>
                                <td><?php echo esc_html($customer['phone'] ?: '-'); ?></td>
                                <td><?php echo esc_html($customer['total_orders']); ?></td>
                                <td><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($customer['lifetime_value'], 2); ?></td>
                                <td>
                                    <span class="sbha-status-badge status-<?php echo esc_attr($customer['status']); ?>">
                                        <?php echo esc_html(ucfirst($customer['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=view&id=' . $customer['id']); ?>" class="button button-small">View</a>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=edit&id=' . $customer['id']); ?>" class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render customer view
     */
    private function render_view($id) {
        $customer = SBHA_Customer::get_customer($id);
        if (!$customer) {
            wp_die('Customer not found');
        }

        $stats = SBHA_Customer::get_customer_stats($id);
        $jobs = SBHA_Customer::get_customer_jobs($id, 10);
        $predictions = SBHA_Customer::get_customer_predictions($id);
        $preferred = SBHA_Customer::get_preferred_services($id);
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                <?php echo esc_html(SBHA_Customer::get_display_name($customer)); ?>
                <a href="<?php echo admin_url('admin.php?page=sbha-customers'); ?>" class="page-title-action">Back to Customers</a>
                <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=edit&id=' . $id); ?>" class="page-title-action">Edit</a>
            </h1>

            <div class="sbha-customer-view-grid">
                <div class="sbha-customer-main">
                    <!-- Stats Cards -->
                    <div class="sbha-stats-row">
                        <div class="sbha-stat-card small">
                            <span class="sbha-stat-value"><?php echo esc_html($stats['total_orders']); ?></span>
                            <span class="sbha-stat-label">Total Orders</span>
                        </div>
                        <div class="sbha-stat-card small">
                            <span class="sbha-stat-value"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($stats['total_paid'], 2); ?></span>
                            <span class="sbha-stat-label">Total Paid</span>
                        </div>
                        <div class="sbha-stat-card small">
                            <span class="sbha-stat-value"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($stats['avg_order_value'], 2); ?></span>
                            <span class="sbha-stat-label">Avg Order</span>
                        </div>
                        <div class="sbha-stat-card small">
                            <span class="sbha-stat-value"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($stats['total_pending'], 2); ?></span>
                            <span class="sbha-stat-label">Pending</span>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h2>Recent Orders</h2>
                            <a href="<?php echo admin_url('admin.php?page=sbha-jobs&customer=' . $id); ?>" class="sbha-view-all">View All</a>
                        </div>
                        <div class="sbha-panel-content">
                            <?php if (empty($jobs)): ?>
                                <p>No orders yet.</p>
                            <?php else: ?>
                                <table class="sbha-table">
                                    <thead>
                                        <tr>
                                            <th>Job #</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jobs as $job): ?>
                                            <tr>
                                                <td><a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=view&id=' . $job['id']); ?>"><?php echo esc_html($job['job_number']); ?></a></td>
                                                <td><?php echo esc_html(substr($job['title'], 0, 30)); ?></td>
                                                <td><span class="sbha-status-badge status-<?php echo esc_attr($job['job_status']); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $job['job_status']))); ?></span></td>
                                                <td><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['total'], 2); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Preferred Services -->
                    <?php if (!empty($preferred)): ?>
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h2>Preferred Services</h2>
                        </div>
                        <div class="sbha-panel-content">
                            <ul class="sbha-preferred-list">
                                <?php foreach ($preferred as $service): ?>
                                    <li>
                                        <strong><?php echo esc_html($service['name']); ?></strong>
                                        <span><?php echo esc_html($service['order_count']); ?> orders</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sbha-customer-sidebar">
                    <!-- Contact Info -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Contact Information</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($customer['email']); ?>"><?php echo esc_html($customer['email']); ?></a></p>
                            <?php if ($customer['phone']): ?>
                                <p><strong>Phone:</strong> <a href="tel:<?php echo esc_attr($customer['phone']); ?>"><?php echo esc_html($customer['phone']); ?></a></p>
                            <?php endif; ?>
                            <?php if ($customer['company']): ?>
                                <p><strong>Company:</strong> <?php echo esc_html($customer['company']); ?></p>
                            <?php endif; ?>
                            <?php if ($customer['address']): ?>
                                <p><strong>Address:</strong><br>
                                    <?php echo esc_html($customer['address']); ?><br>
                                    <?php echo esc_html(implode(', ', array_filter(array($customer['city'], $customer['state'], $customer['postal_code'])))); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AI Predictions -->
                    <div class="sbha-panel sbha-ai-panel">
                        <div class="sbha-panel-header">
                            <h3>AI Predictions</h3>
                            <span class="sbha-badge ai">AI</span>
                        </div>
                        <div class="sbha-panel-content">
                            <p>
                                <strong>Predicted LTV:</strong>
                                <?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($predictions['predicted_ltv'], 2); ?>
                            </p>
                            <p>
                                <strong>Churn Risk:</strong>
                                <span class="sbha-risk-level <?php echo $predictions['churn_risk'] > 0.5 ? 'high' : ($predictions['churn_risk'] > 0.3 ? 'medium' : 'low'); ?>">
                                    <?php echo round($predictions['churn_risk'] * 100); ?>%
                                </span>
                            </p>
                            <?php if ($predictions['churn_risk'] > 0.5): ?>
                                <p class="sbha-ai-warning">
                                    <span class="dashicons dashicons-warning"></span>
                                    Customer at risk of churning. Consider reaching out.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Account Details</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <p><strong>Segment:</strong> <?php echo esc_html(ucfirst(SBHA_Customer::get_customer_segment($customer))); ?></p>
                            <p><strong>Type:</strong> <?php echo esc_html(ucfirst($customer['customer_type'])); ?></p>
                            <p><strong>Since:</strong> <?php echo date('M j, Y', strtotime($customer['created_at'])); ?></p>
                            <?php if ($customer['last_order_date']): ?>
                                <p><strong>Last Order:</strong> <?php echo date('M j, Y', strtotime($customer['last_order_date'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($customer['notes'])): ?>
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Notes</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <p><?php echo nl2br(esc_html($customer['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Actions</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=new&customer=' . $id); ?>" class="button">Create New Job</a>
                            <a href="mailto:<?php echo esc_attr($customer['email']); ?>" class="button">Send Email</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render customer form
     */
    private function render_form($id = 0) {
        $customer = $id ? SBHA_Customer::get_customer($id) : null;
        $is_new = !$customer;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sbha_customer_nonce'])) {
            if (wp_verify_nonce($_POST['sbha_customer_nonce'], 'sbha_save_customer')) {
                $data = array(
                    'email' => sanitize_email($_POST['email']),
                    'first_name' => sanitize_text_field($_POST['first_name']),
                    'last_name' => sanitize_text_field($_POST['last_name']),
                    'phone' => sanitize_text_field($_POST['phone']),
                    'company' => sanitize_text_field($_POST['company']),
                    'address' => sanitize_textarea_field($_POST['address']),
                    'city' => sanitize_text_field($_POST['city']),
                    'state' => sanitize_text_field($_POST['state']),
                    'country' => sanitize_text_field($_POST['country']),
                    'postal_code' => sanitize_text_field($_POST['postal_code']),
                    'customer_type' => sanitize_text_field($_POST['customer_type']),
                    'status' => sanitize_text_field($_POST['status']),
                    'notes' => sanitize_textarea_field($_POST['notes'])
                );

                if ($is_new) {
                    $result = SBHA_Customer::create_customer($data);
                    if (!is_wp_error($result)) {
                        wp_redirect(admin_url('admin.php?page=sbha-customers&action=view&id=' . $result . '&saved=1'));
                        exit;
                    }
                } else {
                    SBHA_Customer::update_customer($id, $data);
                    wp_redirect(admin_url('admin.php?page=sbha-customers&action=view&id=' . $id . '&saved=1'));
                    exit;
                }
            }
        }
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                <?php echo $is_new ? 'Add New Customer' : 'Edit Customer'; ?>
                <a href="<?php echo admin_url('admin.php?page=sbha-customers'); ?>" class="page-title-action">Back to Customers</a>
            </h1>

            <form method="post" class="sbha-customer-form">
                <?php wp_nonce_field('sbha_save_customer', 'sbha_customer_nonce'); ?>

                <div class="sbha-form-grid">
                    <div class="sbha-form-main">
                        <div class="sbha-form-section">
                            <h3>Contact Information</h3>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($customer['first_name'] ?? ''); ?>">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($customer['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="sbha-form-row">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?php echo esc_attr($customer['email'] ?? ''); ?>" required>
                            </div>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($customer['phone'] ?? ''); ?>">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="company">Company</label>
                                    <input type="text" id="company" name="company" value="<?php echo esc_attr($customer['company'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Address</h3>

                            <div class="sbha-form-row">
                                <label for="address">Street Address</label>
                                <textarea id="address" name="address" rows="2"><?php echo esc_textarea($customer['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo esc_attr($customer['city'] ?? ''); ?>">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="state">State/Province</label>
                                    <input type="text" id="state" name="state" value="<?php echo esc_attr($customer['state'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" value="<?php echo esc_attr($customer['postal_code'] ?? ''); ?>">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country" value="<?php echo esc_attr($customer['country'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Notes</h3>
                            <div class="sbha-form-row">
                                <label for="notes">Internal Notes</label>
                                <textarea id="notes" name="notes" rows="4"><?php echo esc_textarea($customer['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="sbha-form-sidebar">
                        <div class="sbha-form-section">
                            <h3>Customer Type</h3>
                            <select id="customer_type" name="customer_type">
                                <option value="individual" <?php selected($customer['customer_type'] ?? 'individual', 'individual'); ?>>Individual</option>
                                <option value="business" <?php selected($customer['customer_type'] ?? '', 'business'); ?>>Business</option>
                            </select>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Status</h3>
                            <select id="status" name="status">
                                <option value="active" <?php selected($customer['status'] ?? 'active', 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($customer['status'] ?? '', 'inactive'); ?>>Inactive</option>
                                <option value="blocked" <?php selected($customer['status'] ?? '', 'blocked'); ?>>Blocked</option>
                            </select>
                        </div>

                        <div class="sbha-form-section">
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $is_new ? 'Create Customer' : 'Update Customer'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
