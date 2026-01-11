<?php
/**
 * Job Manager Class
 *
 * Manages jobs/orders and their lifecycle
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Job_Manager {

    /**
     * Job statuses
     */
    private $statuses = array(
        'inquiry' => 'Inquiry',
        'quoted' => 'Quoted',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In Progress',
        'review' => 'In Review',
        'revision' => 'Revision Required',
        'completed' => 'Completed',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    );

    /**
     * Payment statuses
     */
    private $payment_statuses = array(
        'pending' => 'Pending',
        'partial' => 'Partially Paid',
        'paid' => 'Paid',
        'refunded' => 'Refunded'
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize
    }

    /**
     * Get all statuses
     */
    public function get_statuses() {
        return $this->statuses;
    }

    /**
     * Get payment statuses
     */
    public function get_payment_statuses() {
        return $this->payment_statuses;
    }

    /**
     * Get jobs with filters
     */
    public function get_jobs($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'payment_status' => '',
            'customer_id' => 0,
            'service_id' => 0,
            'assigned_to' => 0,
            'priority' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $table = SBHA_Database::get_table('jobs');
        $sql = "SELECT * FROM $table WHERE 1=1";
        $values = array();

        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $sql .= " AND job_status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $sql .= " AND job_status = %s";
                $values[] = $args['status'];
            }
        }

        if (!empty($args['payment_status'])) {
            $sql .= " AND payment_status = %s";
            $values[] = $args['payment_status'];
        }

        if ($args['customer_id'] > 0) {
            $sql .= " AND customer_id = %d";
            $values[] = $args['customer_id'];
        }

        if ($args['service_id'] > 0) {
            $sql .= " AND service_id = %d";
            $values[] = $args['service_id'];
        }

        if ($args['assigned_to'] > 0) {
            $sql .= " AND assigned_to = %d";
            $values[] = $args['assigned_to'];
        }

        if (!empty($args['priority'])) {
            $sql .= " AND priority = %s";
            $values[] = $args['priority'];
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $sql .= " AND (job_number LIKE %s OR title LIKE %s)";
            $values[] = $search;
            $values[] = $search;
        }

        if (!empty($args['date_from'])) {
            $sql .= " AND DATE(created_at) >= %s";
            $values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $sql .= " AND DATE(created_at) <= %s";
            $values[] = $args['date_to'];
        }

        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get single job
     */
    public function get_job($id) {
        $job = SBHA_Database::get_by_id('jobs', $id);
        if ($job) {
            $job['requirements'] = json_decode($job['requirements'], true) ?: array();
            $job['files'] = json_decode($job['files'], true) ?: array();
            $job['ai_suggested_upsells'] = json_decode($job['ai_suggested_upsells'], true) ?: array();
            $job['timeline'] = $this->get_job_timeline($id);
        }
        return $job;
    }

    /**
     * Get job by number
     */
    public function get_job_by_number($job_number) {
        $jobs = SBHA_Database::get_results('jobs', array('job_number' => $job_number), 'id', 'ASC', 1);
        if (!empty($jobs)) {
            return $this->get_job($jobs[0]['id']);
        }
        return null;
    }

    /**
     * Create job
     */
    public function create_job($data) {
        $defaults = array(
            'job_number' => $this->generate_job_number(),
            'customer_id' => 0,
            'service_id' => null,
            'package_id' => null,
            'title' => '',
            'description' => '',
            'requirements' => array(),
            'files' => array(),
            'quantity' => 1,
            'unit_price' => 0,
            'subtotal' => 0,
            'discount' => 0,
            'discount_type' => 'fixed',
            'tax' => 0,
            'total' => 0,
            'payment_status' => 'pending',
            'payment_method' => '',
            'job_status' => 'inquiry',
            'priority' => 'normal',
            'max_revisions' => 2,
            'notes' => '',
            'internal_notes' => '',
            'assigned_to' => null
        );

        $data = wp_parse_args($data, $defaults);

        // Calculate totals if not provided
        if ($data['subtotal'] == 0 && $data['unit_price'] > 0) {
            $data['subtotal'] = $data['unit_price'] * $data['quantity'];
        }

        if ($data['total'] == 0) {
            $data['total'] = $this->calculate_total($data);
        }

        // JSON encode arrays
        $data['requirements'] = json_encode($data['requirements']);
        $data['files'] = json_encode($data['files']);

        // Get AI predictions if service is set
        if (!empty($data['service_id']) && function_exists('SBHA')) {
            $ai = SBHA()->get_ai_engine();

            // Predict completion time
            $prediction = $ai->predict_job_completion($data['service_id']);
            $data['ai_predicted_completion'] = $prediction['date'];

            // Predict issues
            $issues = $ai->predict_job_issues($data['service_id'], $data);
            $data['ai_risk_score'] = !empty($issues) ? 0.5 : 0.1;

            // Get upsell suggestions
            $upsells = $ai->get_bundle_recommendations($data['service_id']);
            $data['ai_suggested_upsells'] = json_encode($upsells);
        }

        $job_id = SBHA_Database::insert('jobs', $data);

        if ($job_id) {
            // Record timeline entry
            $this->add_timeline_entry($job_id, 'created', 'Job created');

            // Update customer stats
            if ($data['customer_id']) {
                $this->update_customer_stats($data['customer_id']);
            }

            // Record service pairing if multiple services
            if (!empty($data['service_ids']) && function_exists('SBHA')) {
                SBHA()->get_ai_engine()->record_service_pairing($data['service_ids']);
            }

            do_action('sbha_job_created', $job_id, $data);
        }

        return $job_id;
    }

    /**
     * Update job
     */
    public function update_job($id, $data) {
        $old_job = $this->get_job($id);
        if (!$old_job) {
            return false;
        }

        // Track status changes
        if (isset($data['job_status']) && $data['job_status'] !== $old_job['job_status']) {
            $this->add_timeline_entry($id, 'status_changed',
                sprintf('Status changed from %s to %s',
                    $this->statuses[$old_job['job_status']],
                    $this->statuses[$data['job_status']]
                ),
                $old_job['job_status'],
                $data['job_status']
            );

            // Handle completion
            if ($data['job_status'] === 'completed' && !$old_job['actual_completion']) {
                $data['actual_completion'] = current_time('mysql');
            }
        }

        // Track payment changes
        if (isset($data['payment_status']) && $data['payment_status'] !== $old_job['payment_status']) {
            $this->add_timeline_entry($id, 'payment_updated',
                sprintf('Payment status changed to %s', $this->payment_statuses[$data['payment_status']])
            );
        }

        // JSON encode arrays if present
        if (isset($data['requirements']) && is_array($data['requirements'])) {
            $data['requirements'] = json_encode($data['requirements']);
        }
        if (isset($data['files']) && is_array($data['files'])) {
            $data['files'] = json_encode($data['files']);
        }

        $result = SBHA_Database::update('jobs', $data, array('id' => $id));

        if ($result !== false) {
            do_action('sbha_job_updated', $id, $data, $old_job);
        }

        return $result;
    }

    /**
     * Update job status
     */
    public function update_status($id, $status, $note = '') {
        if (!isset($this->statuses[$status])) {
            return false;
        }

        $data = array('job_status' => $status);
        if (!empty($note)) {
            $job = $this->get_job($id);
            $data['notes'] = $job['notes'] . "\n\n" . date('Y-m-d H:i') . ": " . $note;
        }

        return $this->update_job($id, $data);
    }

    /**
     * Request revision
     */
    public function request_revision($id, $revision_notes) {
        $job = $this->get_job($id);
        if (!$job) {
            return false;
        }

        $data = array(
            'job_status' => 'revision',
            'revision_count' => $job['revision_count'] + 1
        );

        $this->add_timeline_entry($id, 'revision_requested',
            'Revision #' . $data['revision_count'] . ' requested: ' . $revision_notes
        );

        return $this->update_job($id, $data);
    }

    /**
     * Delete job
     */
    public function delete_job($id) {
        // Delete timeline entries
        SBHA_Database::delete('job_timeline', array('job_id' => $id));

        return SBHA_Database::delete('jobs', array('id' => $id));
    }

    /**
     * Get job timeline
     */
    public function get_job_timeline($job_id) {
        return SBHA_Database::get_results(
            'job_timeline',
            array('job_id' => $job_id),
            'created_at',
            'DESC'
        );
    }

    /**
     * Add timeline entry
     */
    public function add_timeline_entry($job_id, $action, $description, $old_value = null, $new_value = null) {
        return SBHA_Database::insert('job_timeline', array(
            'job_id' => $job_id,
            'action' => $action,
            'description' => $description,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'user_id' => get_current_user_id()
        ));
    }

    /**
     * Generate job number
     */
    private function generate_job_number() {
        $prefix = get_option('sbha_job_number_prefix', 'SBH');
        $year = date('y');
        $month = date('m');

        // Get last job number for this month
        global $wpdb;
        $table = SBHA_Database::get_table('jobs');

        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT job_number FROM $table WHERE job_number LIKE %s ORDER BY id DESC LIMIT 1",
            $prefix . $year . $month . '%'
        ));

        if ($last) {
            $last_num = (int) substr($last, -4);
            $next_num = $last_num + 1;
        } else {
            $next_num = 1;
        }

        return $prefix . $year . $month . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate total
     */
    private function calculate_total($data) {
        $subtotal = floatval($data['subtotal']);
        $discount = floatval($data['discount']);
        $tax = floatval($data['tax']);

        if ($data['discount_type'] === 'percentage') {
            $discount = $subtotal * ($discount / 100);
        }

        $total = $subtotal - $discount;

        // Add tax
        if ($tax > 0) {
            $tax_rate = get_option('sbha_tax_rate', 0);
            $total += $total * ($tax_rate / 100);
        }

        return round($total, 2);
    }

    /**
     * Update customer stats
     */
    private function update_customer_stats($customer_id) {
        global $wpdb;
        $jobs_table = SBHA_Database::get_table('jobs');
        $customers_table = SBHA_Database::get_table('customers');

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as lifetime_value,
                COALESCE(AVG(total), 0) as average_order_value,
                MAX(created_at) as last_order_date
            FROM $jobs_table
            WHERE customer_id = %d AND job_status != 'cancelled'
        ", $customer_id), ARRAY_A);

        if ($stats) {
            $wpdb->update($customers_table, $stats, array('id' => $customer_id));
        }
    }

    /**
     * Add file to job
     */
    public function add_file($job_id, $file_data) {
        $job = $this->get_job($job_id);
        if (!$job) {
            return false;
        }

        $files = $job['files'];
        $files[] = array(
            'id' => uniqid('file_'),
            'name' => $file_data['name'],
            'url' => $file_data['url'],
            'type' => $file_data['type'],
            'size' => $file_data['size'],
            'uploaded_at' => current_time('mysql'),
            'uploaded_by' => get_current_user_id()
        );

        $this->add_timeline_entry($job_id, 'file_uploaded',
            'File uploaded: ' . $file_data['name']
        );

        return $this->update_job($job_id, array('files' => $files));
    }

    /**
     * Remove file from job
     */
    public function remove_file($job_id, $file_id) {
        $job = $this->get_job($job_id);
        if (!$job) {
            return false;
        }

        $files = array_filter($job['files'], function($file) use ($file_id) {
            return $file['id'] !== $file_id;
        });

        return $this->update_job($job_id, array('files' => array_values($files)));
    }

    /**
     * Get job counts by status
     */
    public function get_status_counts() {
        return SBHA_Database::get_grouped_stats('jobs', 'job_status');
    }

    /**
     * Get jobs due today
     */
    public function get_jobs_due_today() {
        global $wpdb;
        $table = SBHA_Database::get_table('jobs');

        return $wpdb->get_results("
            SELECT * FROM $table
            WHERE DATE(estimated_completion) = CURDATE()
              AND job_status NOT IN ('completed', 'delivered', 'cancelled')
            ORDER BY priority DESC, estimated_completion ASC
        ", ARRAY_A);
    }

    /**
     * Get overdue jobs
     */
    public function get_overdue_jobs() {
        global $wpdb;
        $table = SBHA_Database::get_table('jobs');

        return $wpdb->get_results("
            SELECT * FROM $table
            WHERE estimated_completion < NOW()
              AND job_status NOT IN ('completed', 'delivered', 'cancelled')
            ORDER BY estimated_completion ASC
        ", ARRAY_A);
    }

    /**
     * Get jobs for customer
     */
    public function get_customer_jobs($customer_id, $limit = 10) {
        return $this->get_jobs(array(
            'customer_id' => $customer_id,
            'limit' => $limit
        ));
    }

    /**
     * Get revenue stats
     */
    public function get_revenue_stats($period = 'month') {
        global $wpdb;
        $table = SBHA_Database::get_table('jobs');

        $date_filter = '';
        switch ($period) {
            case 'today':
                $date_filter = "DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                $date_filter = "1=1";
        }

        return $wpdb->get_row("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
                SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as pending_revenue,
                AVG(total) as avg_order_value,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs
            FROM $table
            WHERE $date_filter AND job_status != 'cancelled'
        ", ARRAY_A);
    }

    /**
     * Assign job to user
     */
    public function assign_job($job_id, $user_id) {
        $user = get_user_by('id', $user_id);
        $this->add_timeline_entry($job_id, 'assigned',
            'Job assigned to ' . ($user ? $user->display_name : 'User #' . $user_id)
        );

        return $this->update_job($job_id, array('assigned_to' => $user_id));
    }

    /**
     * Set job priority
     */
    public function set_priority($job_id, $priority) {
        $priorities = array('low', 'normal', 'high', 'urgent');
        if (!in_array($priority, $priorities)) {
            return false;
        }

        $this->add_timeline_entry($job_id, 'priority_changed',
            'Priority set to ' . ucfirst($priority)
        );

        return $this->update_job($job_id, array('priority' => $priority));
    }
}
