<?php
/**
 * Customer Class
 *
 * Manages customer data and interactions
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Customer {

    /**
     * Get customers with filters
     */
    public static function get_customers($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'customer_type' => '',
            'search' => '',
            'min_orders' => 0,
            'min_value' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $table = SBHA_Database::get_table('customers');
        $sql = "SELECT * FROM $table WHERE 1=1";
        $values = array();

        if (!empty($args['status'])) {
            $sql .= " AND status = %s";
            $values[] = $args['status'];
        }

        if (!empty($args['customer_type'])) {
            $sql .= " AND customer_type = %s";
            $values[] = $args['customer_type'];
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $sql .= " AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR company LIKE %s OR phone LIKE %s)";
            $values = array_merge($values, array($search, $search, $search, $search, $search));
        }

        if ($args['min_orders'] > 0) {
            $sql .= " AND total_orders >= %d";
            $values[] = $args['min_orders'];
        }

        if ($args['min_value'] > 0) {
            $sql .= " AND lifetime_value >= %f";
            $values[] = $args['min_value'];
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
     * Get single customer
     */
    public static function get_customer($id) {
        $customer = SBHA_Database::get_by_id('customers', $id);
        if ($customer) {
            $customer['preferred_services'] = json_decode($customer['preferred_services'], true) ?: array();
        }
        return $customer;
    }

    /**
     * Get customer by email
     */
    public static function get_by_email($email) {
        $customers = SBHA_Database::get_results('customers', array('email' => $email), 'id', 'ASC', 1);
        if (!empty($customers)) {
            return self::get_customer($customers[0]['id']);
        }
        return null;
    }

    /**
     * Get customer by WP user ID
     */
    public static function get_by_user_id($user_id) {
        $customers = SBHA_Database::get_results('customers', array('wp_user_id' => $user_id), 'id', 'ASC', 1);
        if (!empty($customers)) {
            return self::get_customer($customers[0]['id']);
        }
        return null;
    }

    /**
     * Create customer
     */
    public static function create_customer($data) {
        $defaults = array(
            'wp_user_id' => null,
            'email' => '',
            'phone' => '',
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'postal_code' => '',
            'customer_type' => 'individual',
            'preferred_services' => array(),
            'notes' => '',
            'status' => 'active'
        );

        $data = wp_parse_args($data, $defaults);

        // Check if email already exists
        $existing = self::get_by_email($data['email']);
        if ($existing) {
            return new WP_Error('email_exists', 'A customer with this email already exists');
        }

        // JSON encode arrays
        $data['preferred_services'] = json_encode($data['preferred_services']);

        $customer_id = SBHA_Database::insert('customers', $data);

        if ($customer_id) {
            do_action('sbha_customer_created', $customer_id, $data);
        }

        return $customer_id;
    }

    /**
     * Update customer
     */
    public static function update_customer($id, $data) {
        if (isset($data['preferred_services']) && is_array($data['preferred_services'])) {
            $data['preferred_services'] = json_encode($data['preferred_services']);
        }

        $result = SBHA_Database::update('customers', $data, array('id' => $id));

        if ($result !== false) {
            do_action('sbha_customer_updated', $id, $data);
        }

        return $result;
    }

    /**
     * Delete customer
     */
    public static function delete_customer($id) {
        return SBHA_Database::delete('customers', array('id' => $id));
    }

    /**
     * Get or create customer from data
     */
    public static function get_or_create($email, $data = array()) {
        $existing = self::get_by_email($email);
        if ($existing) {
            return $existing['id'];
        }

        $data['email'] = $email;
        return self::create_customer($data);
    }

    /**
     * Get customer's full name
     */
    public static function get_full_name($customer) {
        if (is_numeric($customer)) {
            $customer = self::get_customer($customer);
        }

        if (!$customer) {
            return '';
        }

        $name = trim($customer['first_name'] . ' ' . $customer['last_name']);
        return !empty($name) ? $name : $customer['email'];
    }

    /**
     * Get customer display name
     */
    public static function get_display_name($customer) {
        if (is_numeric($customer)) {
            $customer = self::get_customer($customer);
        }

        if (!$customer) {
            return '';
        }

        if (!empty($customer['company'])) {
            return $customer['company'];
        }

        return self::get_full_name($customer);
    }

    /**
     * Get customer orders
     */
    public static function get_customer_jobs($customer_id, $limit = 10) {
        if (function_exists('SBHA')) {
            return SBHA()->get_job_manager()->get_customer_jobs($customer_id, $limit);
        }
        return array();
    }

    /**
     * Get customer quotes
     */
    public static function get_customer_quotes($customer_id, $limit = 10) {
        return SBHA_Database::get_results(
            'quotes',
            array('customer_id' => $customer_id),
            'created_at',
            'DESC',
            $limit
        );
    }

    /**
     * Get customer stats
     */
    public static function get_customer_stats($customer_id) {
        global $wpdb;
        $jobs_table = SBHA_Database::get_table('jobs');

        return $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as total_pending,
                AVG(total) as avg_order_value,
                MIN(created_at) as first_order,
                MAX(created_at) as last_order
            FROM $jobs_table
            WHERE customer_id = %d
        ", $customer_id), ARRAY_A);
    }

    /**
     * Get customer's preferred services
     */
    public static function get_preferred_services($customer_id) {
        global $wpdb;
        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        return $wpdb->get_results($wpdb->prepare("
            SELECT s.id, s.name, s.category, COUNT(j.id) as order_count
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE j.customer_id = %d
            GROUP BY s.id
            ORDER BY order_count DESC
            LIMIT 5
        ", $customer_id), ARRAY_A);
    }

    /**
     * Update customer preferred services
     */
    public static function update_preferred_services($customer_id) {
        $preferred = self::get_preferred_services($customer_id);
        $service_ids = array_column($preferred, 'id');

        return self::update_customer($customer_id, array(
            'preferred_services' => $service_ids
        ));
    }

    /**
     * Get AI predictions for customer
     */
    public static function get_customer_predictions($customer_id) {
        if (!function_exists('SBHA')) {
            return array();
        }

        $ai = SBHA()->get_ai_engine();

        return array(
            'predicted_ltv' => $ai->predict_customer_ltv($customer_id),
            'churn_risk' => $ai->calculate_churn_risk($customer_id)
        );
    }

    /**
     * Get high value customers
     */
    public static function get_high_value_customers($limit = 10) {
        return self::get_customers(array(
            'orderby' => 'lifetime_value',
            'order' => 'DESC',
            'limit' => $limit,
            'status' => 'active'
        ));
    }

    /**
     * Get at-risk customers (high churn risk)
     */
    public static function get_at_risk_customers($limit = 10) {
        global $wpdb;
        $table = SBHA_Database::get_table('customers');

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE status = 'active'
              AND total_orders > 0
              AND last_order_date < DATE_SUB(NOW(), INTERVAL 60 DAY)
            ORDER BY lifetime_value DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }

    /**
     * Get new customers in period
     */
    public static function get_new_customers($days = 30) {
        global $wpdb;
        $table = SBHA_Database::get_table('customers');

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
        ", $days), ARRAY_A);
    }

    /**
     * Get repeat customers
     */
    public static function get_repeat_customers($limit = 20) {
        return self::get_customers(array(
            'min_orders' => 2,
            'orderby' => 'total_orders',
            'order' => 'DESC',
            'limit' => $limit
        ));
    }

    /**
     * Merge customers (combine two customer records)
     */
    public static function merge_customers($primary_id, $secondary_id) {
        global $wpdb;

        $primary = self::get_customer($primary_id);
        $secondary = self::get_customer($secondary_id);

        if (!$primary || !$secondary) {
            return new WP_Error('not_found', 'One or both customers not found');
        }

        // Update all jobs to primary customer
        $jobs_table = SBHA_Database::get_table('jobs');
        $wpdb->update($jobs_table,
            array('customer_id' => $primary_id),
            array('customer_id' => $secondary_id)
        );

        // Update all quotes to primary customer
        $quotes_table = SBHA_Database::get_table('quotes');
        $wpdb->update($quotes_table,
            array('customer_id' => $primary_id),
            array('customer_id' => $secondary_id)
        );

        // Update all intentions to primary customer
        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');
        $wpdb->update($intentions_table,
            array('customer_id' => $primary_id),
            array('customer_id' => $secondary_id)
        );

        // Merge notes
        $notes = $primary['notes'];
        if (!empty($secondary['notes'])) {
            $notes .= "\n\n--- Merged from customer #{$secondary_id} ---\n" . $secondary['notes'];
        }

        // Update primary with merged data
        $merge_data = array(
            'notes' => $notes,
            'lifetime_value' => $primary['lifetime_value'] + $secondary['lifetime_value'],
            'total_orders' => $primary['total_orders'] + $secondary['total_orders']
        );

        // Fill in missing data
        $fields = array('phone', 'company', 'address', 'city', 'state', 'country', 'postal_code');
        foreach ($fields as $field) {
            if (empty($primary[$field]) && !empty($secondary[$field])) {
                $merge_data[$field] = $secondary[$field];
            }
        }

        self::update_customer($primary_id, $merge_data);

        // Delete secondary customer
        self::delete_customer($secondary_id);

        return true;
    }

    /**
     * Export customers to CSV
     */
    public static function export_to_csv($args = array()) {
        $customers = self::get_customers($args);

        $headers = array(
            'ID', 'Email', 'First Name', 'Last Name', 'Company', 'Phone',
            'Address', 'City', 'State', 'Country', 'Postal Code',
            'Customer Type', 'Total Orders', 'Lifetime Value', 'Average Order',
            'Last Order Date', 'Status', 'Created'
        );

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($customers as $customer) {
            fputcsv($output, array(
                $customer['id'],
                $customer['email'],
                $customer['first_name'],
                $customer['last_name'],
                $customer['company'],
                $customer['phone'],
                $customer['address'],
                $customer['city'],
                $customer['state'],
                $customer['country'],
                $customer['postal_code'],
                $customer['customer_type'],
                $customer['total_orders'],
                $customer['lifetime_value'],
                $customer['average_order_value'],
                $customer['last_order_date'],
                $customer['status'],
                $customer['created_at']
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Get customer segment
     */
    public static function get_customer_segment($customer) {
        if (is_numeric($customer)) {
            $customer = self::get_customer($customer);
        }

        if (!$customer) {
            return 'unknown';
        }

        $ltv = floatval($customer['lifetime_value']);
        $orders = intval($customer['total_orders']);

        if ($ltv >= 1000 && $orders >= 5) {
            return 'vip';
        } elseif ($ltv >= 500 || $orders >= 3) {
            return 'loyal';
        } elseif ($orders >= 1) {
            return 'active';
        } else {
            return 'new';
        }
    }
}
