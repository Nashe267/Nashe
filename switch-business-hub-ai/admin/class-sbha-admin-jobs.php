<?php
/**
 * Admin Jobs
 *
 * Job management admin page
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin_Jobs {

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
                $this->render_form(0);
                break;
            case 'edit':
                $this->render_form($id);
                break;
            default:
                $this->render_list();
        }
    }

    /**
     * Render jobs list
     */
    private function render_list() {
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $jobs = SBHA()->get_job_manager()->get_jobs(array('status' => $status, 'limit' => 50));
        $statuses = SBHA()->get_job_manager()->get_statuses();
        $status_counts = SBHA()->get_job_manager()->get_status_counts();

        // Create count map
        $count_map = array();
        foreach ($status_counts as $count) {
            $count_map[$count['job_status']] = $count['count'];
        }
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                Jobs
                <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=new'); ?>" class="page-title-action">New Job</a>
            </h1>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo admin_url('admin.php?page=sbha-jobs'); ?>" <?php echo empty($status) ? 'class="current"' : ''; ?>>
                        All <span class="count">(<?php echo array_sum($count_map); ?>)</span>
                    </a> |
                </li>
                <?php
                $i = 0;
                foreach ($statuses as $key => $label):
                    $count = isset($count_map[$key]) ? $count_map[$key] : 0;
                    $i++;
                ?>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=sbha-jobs&status=' . $key); ?>" <?php echo $status === $key ? 'class="current"' : ''; ?>>
                            <?php echo esc_html($label); ?> <span class="count">(<?php echo esc_html($count); ?>)</span>
                        </a><?php echo $i < count($statuses) ? ' |' : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <table class="wp-list-table widefat fixed striped sbha-table">
                <thead>
                    <tr>
                        <th>Job #</th>
                        <th>Title</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr>
                            <td colspan="8">No jobs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $job):
                            $customer = SBHA_Customer::get_customer($job['customer_id']);
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=view&id=' . $job['id']); ?>">
                                        <strong><?php echo esc_html($job['job_number']); ?></strong>
                                    </a>
                                    <?php if ($job['priority'] === 'urgent'): ?>
                                        <span class="sbha-badge urgent">Urgent</span>
                                    <?php elseif ($job['priority'] === 'high'): ?>
                                        <span class="sbha-badge high">High</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(substr($job['title'], 0, 40)); ?></td>
                                <td>
                                    <?php if ($customer): ?>
                                        <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=view&id=' . $customer['id']); ?>">
                                            <?php echo esc_html(SBHA_Customer::get_display_name($customer)); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['total'], 2); ?></td>
                                <td>
                                    <select class="sbha-status-select" data-job-id="<?php echo esc_attr($job['id']); ?>">
                                        <?php foreach ($statuses as $key => $label): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($job['job_status'], $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <span class="sbha-payment-badge payment-<?php echo esc_attr($job['payment_status']); ?>">
                                        <?php echo esc_html(ucfirst($job['payment_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=view&id=' . $job['id']); ?>" class="button button-small">View</a>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=edit&id=' . $job['id']); ?>" class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.sbha-status-select').on('change', function() {
                var $select = $(this);
                var jobId = $select.data('job-id');
                var status = $select.val();

                $.ajax({
                    url: sbhaAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'sbha_admin_action',
                        sbha_action: 'update_job_status',
                        nonce: sbhaAdmin.nonce,
                        job_id: jobId,
                        status: status
                    },
                    success: function(response) {
                        if (response.success) {
                            $select.addClass('updated');
                            setTimeout(function() { $select.removeClass('updated'); }, 1000);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render job view
     */
    private function render_view($id) {
        $job = SBHA()->get_job_manager()->get_job($id);
        if (!$job) {
            wp_die('Job not found');
        }

        $customer = SBHA_Customer::get_customer($job['customer_id']);
        $service = $job['service_id'] ? SBHA()->get_service_catalog()->get_service($job['service_id']) : null;
        $statuses = SBHA()->get_job_manager()->get_statuses();
        $payment_statuses = SBHA()->get_job_manager()->get_payment_statuses();
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                Job <?php echo esc_html($job['job_number']); ?>
                <a href="<?php echo admin_url('admin.php?page=sbha-jobs'); ?>" class="page-title-action">Back to Jobs</a>
                <a href="<?php echo admin_url('admin.php?page=sbha-jobs&action=edit&id=' . $id); ?>" class="page-title-action">Edit</a>
            </h1>

            <div class="sbha-job-view-grid">
                <div class="sbha-job-main">
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h2><?php echo esc_html($job['title']); ?></h2>
                            <span class="sbha-status-badge status-<?php echo esc_attr($job['job_status']); ?>">
                                <?php echo esc_html($statuses[$job['job_status']]); ?>
                            </span>
                        </div>
                        <div class="sbha-panel-content">
                            <div class="sbha-job-meta">
                                <div class="sbha-meta-item">
                                    <span class="sbha-meta-label">Service</span>
                                    <span class="sbha-meta-value"><?php echo $service ? esc_html($service['name']) : '-'; ?></span>
                                </div>
                                <div class="sbha-meta-item">
                                    <span class="sbha-meta-label">Quantity</span>
                                    <span class="sbha-meta-value"><?php echo esc_html($job['quantity']); ?></span>
                                </div>
                                <div class="sbha-meta-item">
                                    <span class="sbha-meta-label">Priority</span>
                                    <span class="sbha-meta-value sbha-priority-<?php echo esc_attr($job['priority']); ?>"><?php echo esc_html(ucfirst($job['priority'])); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($job['description'])): ?>
                                <div class="sbha-job-description">
                                    <h4>Description</h4>
                                    <p><?php echo nl2br(esc_html($job['description'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($job['requirements'])): ?>
                                <div class="sbha-job-requirements">
                                    <h4>Requirements</h4>
                                    <ul>
                                        <?php foreach ($job['requirements'] as $req): ?>
                                            <li><?php echo esc_html($req); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($job['files'])): ?>
                                <div class="sbha-job-files">
                                    <h4>Attached Files</h4>
                                    <ul class="sbha-files-list">
                                        <?php foreach ($job['files'] as $file): ?>
                                            <li>
                                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank">
                                                    <?php echo esc_html($file['name']); ?>
                                                </a>
                                                <small>(<?php echo esc_html(size_format($file['size'])); ?>)</small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AI Insights -->
                    <?php if (!empty($job['ai_suggested_upsells']) || $job['ai_risk_score'] > 0): ?>
                    <div class="sbha-panel sbha-ai-panel">
                        <div class="sbha-panel-header">
                            <h2>AI Insights</h2>
                            <span class="sbha-badge ai">AI</span>
                        </div>
                        <div class="sbha-panel-content">
                            <?php if ($job['ai_risk_score'] > 0.5): ?>
                                <div class="sbha-ai-warning">
                                    <span class="dashicons dashicons-warning"></span>
                                    <p>This job has a higher risk score (<?php echo round($job['ai_risk_score'] * 100); ?>%). Consider reviewing requirements carefully.</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($job['ai_suggested_upsells'])): ?>
                                <div class="sbha-ai-upsells">
                                    <h4>Suggested Upsells</h4>
                                    <ul>
                                        <?php foreach ($job['ai_suggested_upsells'] as $upsell): ?>
                                            <li>
                                                <strong><?php echo esc_html($upsell['service_name']); ?></strong>
                                                <span class="sbha-discount"><?php echo esc_html($upsell['suggested_discount']); ?>% off if bundled</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h2>Timeline</h2>
                        </div>
                        <div class="sbha-panel-content">
                            <ul class="sbha-timeline">
                                <?php foreach ($job['timeline'] as $entry): ?>
                                    <li class="sbha-timeline-item">
                                        <span class="sbha-timeline-date"><?php echo date('M j, g:i a', strtotime($entry['created_at'])); ?></span>
                                        <span class="sbha-timeline-action"><?php echo esc_html($entry['description']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="sbha-job-sidebar">
                    <!-- Customer -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Customer</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <?php if ($customer): ?>
                                <p><strong><?php echo esc_html(SBHA_Customer::get_display_name($customer)); ?></strong></p>
                                <p><?php echo esc_html($customer['email']); ?></p>
                                <?php if ($customer['phone']): ?>
                                    <p><?php echo esc_html($customer['phone']); ?></p>
                                <?php endif; ?>
                                <a href="<?php echo admin_url('admin.php?page=sbha-customers&action=view&id=' . $customer['id']); ?>" class="button button-small">View Profile</a>
                            <?php else: ?>
                                <p>No customer assigned</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Payment</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <table class="sbha-payment-table">
                                <tr>
                                    <td>Subtotal</td>
                                    <td><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['subtotal'], 2); ?></td>
                                </tr>
                                <?php if ($job['discount'] > 0): ?>
                                <tr>
                                    <td>Discount</td>
                                    <td>-<?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['discount'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($job['tax'] > 0): ?>
                                <tr>
                                    <td>Tax</td>
                                    <td><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['tax'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="sbha-total-row">
                                    <td><strong>Total</strong></td>
                                    <td><strong><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($job['total'], 2); ?></strong></td>
                                </tr>
                            </table>
                            <p class="sbha-payment-status">
                                <span class="sbha-payment-badge payment-<?php echo esc_attr($job['payment_status']); ?>">
                                    <?php echo esc_html($payment_statuses[$job['payment_status']]); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Dates</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <p><strong>Created:</strong> <?php echo date('M j, Y g:i a', strtotime($job['created_at'])); ?></p>
                            <?php if ($job['estimated_completion']): ?>
                                <p><strong>Est. Completion:</strong> <?php echo date('M j, Y', strtotime($job['estimated_completion'])); ?></p>
                            <?php endif; ?>
                            <?php if ($job['actual_completion']): ?>
                                <p><strong>Completed:</strong> <?php echo date('M j, Y g:i a', strtotime($job['actual_completion'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($job['notes']) || !empty($job['internal_notes'])): ?>
                    <div class="sbha-panel">
                        <div class="sbha-panel-header">
                            <h3>Notes</h3>
                        </div>
                        <div class="sbha-panel-content">
                            <?php if (!empty($job['notes'])): ?>
                                <p><?php echo nl2br(esc_html($job['notes'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($job['internal_notes'])): ?>
                                <h4>Internal Notes</h4>
                                <p><?php echo nl2br(esc_html($job['internal_notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render job form
     */
    private function render_form($id = 0) {
        $job = $id ? SBHA()->get_job_manager()->get_job($id) : null;
        $is_new = !$job;
        $services = SBHA()->get_service_catalog()->get_services();
        $statuses = SBHA()->get_job_manager()->get_statuses();
        $customers = SBHA_Customer::get_customers(array('limit' => 100));

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sbha_job_nonce'])) {
            if (wp_verify_nonce($_POST['sbha_job_nonce'], 'sbha_save_job')) {
                $data = array(
                    'customer_id' => intval($_POST['customer_id']),
                    'service_id' => intval($_POST['service_id']) ?: null,
                    'title' => sanitize_text_field($_POST['title']),
                    'description' => sanitize_textarea_field($_POST['description']),
                    'quantity' => intval($_POST['quantity']) ?: 1,
                    'unit_price' => floatval($_POST['unit_price']),
                    'subtotal' => floatval($_POST['subtotal']),
                    'discount' => floatval($_POST['discount']),
                    'total' => floatval($_POST['total']),
                    'job_status' => sanitize_text_field($_POST['job_status']),
                    'payment_status' => sanitize_text_field($_POST['payment_status']),
                    'priority' => sanitize_text_field($_POST['priority']),
                    'estimated_completion' => !empty($_POST['estimated_completion']) ? sanitize_text_field($_POST['estimated_completion']) : null,
                    'notes' => sanitize_textarea_field($_POST['notes']),
                    'internal_notes' => sanitize_textarea_field($_POST['internal_notes'])
                );

                if ($is_new) {
                    $id = SBHA()->get_job_manager()->create_job($data);
                    if ($id) {
                        wp_redirect(admin_url('admin.php?page=sbha-jobs&action=view&id=' . $id . '&saved=1'));
                        exit;
                    }
                } else {
                    SBHA()->get_job_manager()->update_job($id, $data);
                    wp_redirect(admin_url('admin.php?page=sbha-jobs&action=view&id=' . $id . '&saved=1'));
                    exit;
                }
            }
        }
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                <?php echo $is_new ? 'New Job' : 'Edit Job'; ?>
                <a href="<?php echo admin_url('admin.php?page=sbha-jobs'); ?>" class="page-title-action">Back to Jobs</a>
            </h1>

            <form method="post" class="sbha-job-form">
                <?php wp_nonce_field('sbha_save_job', 'sbha_job_nonce'); ?>

                <div class="sbha-form-grid">
                    <div class="sbha-form-main">
                        <div class="sbha-form-section">
                            <h3>Job Details</h3>

                            <div class="sbha-form-row">
                                <label for="title">Job Title *</label>
                                <input type="text" id="title" name="title" value="<?php echo esc_attr($job['title'] ?? ''); ?>" required>
                            </div>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="customer_id">Customer *</label>
                                    <select id="customer_id" name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo esc_attr($customer['id']); ?>" <?php selected($job['customer_id'] ?? '', $customer['id']); ?>>
                                                <?php echo esc_html(SBHA_Customer::get_display_name($customer)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sbha-form-row">
                                    <label for="service_id">Service</label>
                                    <select id="service_id" name="service_id">
                                        <option value="">Select Service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo esc_attr($service['id']); ?>" <?php selected($job['service_id'] ?? '', $service['id']); ?> data-price="<?php echo esc_attr($service['base_price']); ?>">
                                                <?php echo esc_html($service['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="sbha-form-row">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"><?php echo esc_textarea($job['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Pricing</h3>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="quantity">Quantity</label>
                                    <input type="number" id="quantity" name="quantity" value="<?php echo esc_attr($job['quantity'] ?? 1); ?>" min="1">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="unit_price">Unit Price</label>
                                    <input type="number" id="unit_price" name="unit_price" value="<?php echo esc_attr($job['unit_price'] ?? 0); ?>" step="0.01">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="subtotal">Subtotal</label>
                                    <input type="number" id="subtotal" name="subtotal" value="<?php echo esc_attr($job['subtotal'] ?? 0); ?>" step="0.01">
                                </div>
                            </div>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="discount">Discount</label>
                                    <input type="number" id="discount" name="discount" value="<?php echo esc_attr($job['discount'] ?? 0); ?>" step="0.01">
                                </div>
                                <div class="sbha-form-row">
                                    <label for="total">Total</label>
                                    <input type="number" id="total" name="total" value="<?php echo esc_attr($job['total'] ?? 0); ?>" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Notes</h3>
                            <div class="sbha-form-row">
                                <label for="notes">Customer Notes</label>
                                <textarea id="notes" name="notes" rows="3"><?php echo esc_textarea($job['notes'] ?? ''); ?></textarea>
                            </div>
                            <div class="sbha-form-row">
                                <label for="internal_notes">Internal Notes (not visible to customer)</label>
                                <textarea id="internal_notes" name="internal_notes" rows="3"><?php echo esc_textarea($job['internal_notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="sbha-form-sidebar">
                        <div class="sbha-form-section">
                            <h3>Status</h3>
                            <div class="sbha-form-row">
                                <label for="job_status">Job Status</label>
                                <select id="job_status" name="job_status">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($job['job_status'] ?? 'inquiry', $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sbha-form-row">
                                <label for="payment_status">Payment Status</label>
                                <select id="payment_status" name="payment_status">
                                    <option value="pending" <?php selected($job['payment_status'] ?? 'pending', 'pending'); ?>>Pending</option>
                                    <option value="partial" <?php selected($job['payment_status'] ?? '', 'partial'); ?>>Partial</option>
                                    <option value="paid" <?php selected($job['payment_status'] ?? '', 'paid'); ?>>Paid</option>
                                    <option value="refunded" <?php selected($job['payment_status'] ?? '', 'refunded'); ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="sbha-form-row">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority">
                                    <option value="low" <?php selected($job['priority'] ?? '', 'low'); ?>>Low</option>
                                    <option value="normal" <?php selected($job['priority'] ?? 'normal', 'normal'); ?>>Normal</option>
                                    <option value="high" <?php selected($job['priority'] ?? '', 'high'); ?>>High</option>
                                    <option value="urgent" <?php selected($job['priority'] ?? '', 'urgent'); ?>>Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Deadline</h3>
                            <div class="sbha-form-row">
                                <label for="estimated_completion">Estimated Completion</label>
                                <input type="date" id="estimated_completion" name="estimated_completion" value="<?php echo $job['estimated_completion'] ? date('Y-m-d', strtotime($job['estimated_completion'])) : ''; ?>">
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $is_new ? 'Create Job' : 'Update Job'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Auto-fill price from service
            $('#service_id').on('change', function() {
                var price = $(this).find(':selected').data('price');
                if (price) {
                    $('#unit_price').val(price);
                    calculateTotal();
                }
            });

            // Calculate totals
            function calculateTotal() {
                var qty = parseFloat($('#quantity').val()) || 1;
                var price = parseFloat($('#unit_price').val()) || 0;
                var discount = parseFloat($('#discount').val()) || 0;

                var subtotal = qty * price;
                var total = subtotal - discount;

                $('#subtotal').val(subtotal.toFixed(2));
                $('#total').val(total.toFixed(2));
            }

            $('#quantity, #unit_price, #discount').on('input', calculateTotal);
        });
        </script>
        <?php
    }
}
