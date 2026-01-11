<?php
/**
 * Analytics Class
 *
 * Business analytics and reporting
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Analytics {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize
    }

    /**
     * Get dashboard stats
     */
    public function get_dashboard_stats() {
        return array(
            'today' => $this->get_period_stats('today'),
            'week' => $this->get_period_stats('week'),
            'month' => $this->get_period_stats('month'),
            'year' => $this->get_period_stats('year')
        );
    }

    /**
     * Get stats for a period
     */
    public function get_period_stats($period) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $customers_table = SBHA_Database::get_table('customers');
        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');

        $date_filter = $this->get_date_filter($period);

        // Jobs stats
        $jobs = $wpdb->get_row("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN job_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_jobs,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
                SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as pending_revenue,
                AVG(total) as avg_order_value
            FROM $jobs_table
            WHERE $date_filter
        ", ARRAY_A);

        // New customers
        $new_customers = $wpdb->get_var("
            SELECT COUNT(*) FROM $customers_table WHERE $date_filter
        ");

        // Inquiries
        $inquiries = $wpdb->get_var("
            SELECT COUNT(*) FROM $intentions_table WHERE $date_filter
        ");

        // Conversion rate
        $converted = $wpdb->get_var("
            SELECT COUNT(*) FROM $intentions_table WHERE $date_filter AND converted = 1
        ");

        return array(
            'total_jobs' => intval($jobs['total_jobs']),
            'completed_jobs' => intval($jobs['completed_jobs']),
            'cancelled_jobs' => intval($jobs['cancelled_jobs']),
            'revenue' => floatval($jobs['revenue']),
            'pending_revenue' => floatval($jobs['pending_revenue']),
            'avg_order_value' => floatval($jobs['avg_order_value']),
            'new_customers' => intval($new_customers),
            'inquiries' => intval($inquiries),
            'conversion_rate' => $inquiries > 0 ? round(($converted / $inquiries) * 100, 2) : 0
        );
    }

    /**
     * Get date filter SQL
     */
    private function get_date_filter($period, $column = 'created_at') {
        switch ($period) {
            case 'today':
                return "DATE($column) = CURDATE()";
            case 'yesterday':
                return "DATE($column) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'week':
                return "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'quarter':
                return "$column >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'year':
                return "$column >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "1=1";
        }
    }

    /**
     * Get revenue chart data
     */
    public function get_revenue_chart($period = 'month', $group_by = 'day') {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $date_filter = $this->get_date_filter($period);

        $date_format = $group_by === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $results = $wpdb->get_results("
            SELECT
                DATE_FORMAT(created_at, '$date_format') as date,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
                COUNT(*) as orders
            FROM $jobs_table
            WHERE $date_filter AND job_status != 'cancelled'
            GROUP BY DATE_FORMAT(created_at, '$date_format')
            ORDER BY date ASC
        ", ARRAY_A);

        return $results;
    }

    /**
     * Get service performance
     */
    public function get_service_performance($limit = 10) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                s.id,
                s.name,
                s.category,
                s.base_price,
                COUNT(j.id) as order_count,
                SUM(j.total) as total_revenue,
                AVG(j.total) as avg_order_value,
                SUM(CASE WHEN j.job_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN j.job_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM $services_table s
            LEFT JOIN $jobs_table j ON s.id = j.service_id
            WHERE s.status = 'active'
            GROUP BY s.id
            ORDER BY order_count DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }

    /**
     * Get category performance
     */
    public function get_category_performance() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        return $wpdb->get_results("
            SELECT
                s.category,
                COUNT(j.id) as order_count,
                SUM(j.total) as total_revenue,
                AVG(j.total) as avg_order_value,
                COUNT(DISTINCT j.customer_id) as unique_customers
            FROM $services_table s
            LEFT JOIN $jobs_table j ON s.id = j.service_id
            WHERE s.status = 'active' AND j.job_status != 'cancelled'
            GROUP BY s.category
            ORDER BY total_revenue DESC
        ", ARRAY_A);
    }

    /**
     * Get customer analytics
     */
    public function get_customer_analytics() {
        global $wpdb;

        $customers_table = SBHA_Database::get_table('customers');
        $jobs_table = SBHA_Database::get_table('jobs');

        // Customer segments
        $segments = $wpdb->get_results("
            SELECT
                CASE
                    WHEN lifetime_value >= 1000 AND total_orders >= 5 THEN 'VIP'
                    WHEN lifetime_value >= 500 OR total_orders >= 3 THEN 'Loyal'
                    WHEN total_orders >= 1 THEN 'Active'
                    ELSE 'New'
                END as segment,
                COUNT(*) as count,
                SUM(lifetime_value) as total_value,
                AVG(lifetime_value) as avg_value
            FROM $customers_table
            WHERE status = 'active'
            GROUP BY segment
        ", ARRAY_A);

        // Customer acquisition
        $acquisition = $wpdb->get_results("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_customers
            FROM $customers_table
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ", ARRAY_A);

        // Retention (customers with repeat orders)
        $retention = $wpdb->get_row("
            SELECT
                COUNT(CASE WHEN total_orders = 1 THEN 1 END) as one_time,
                COUNT(CASE WHEN total_orders = 2 THEN 1 END) as two_orders,
                COUNT(CASE WHEN total_orders >= 3 THEN 1 END) as three_plus
            FROM $customers_table
            WHERE status = 'active'
        ", ARRAY_A);

        return array(
            'segments' => $segments,
            'acquisition' => $acquisition,
            'retention' => $retention
        );
    }

    /**
     * Get funnel analytics
     */
    public function get_funnel_analytics($period = 'month') {
        global $wpdb;

        $funnel_table = SBHA_Database::get_table('ai_conversion_funnel');
        $date_filter = $this->get_date_filter($period);

        $stages = array('landing', 'service_view', 'inquiry', 'quote', 'confirmed', 'completed');
        $funnel = array();

        foreach ($stages as $stage) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT session_id)
                FROM $funnel_table
                WHERE funnel_stage = %s AND $date_filter
            ", $stage));

            $funnel[$stage] = intval($count);
        }

        // Calculate drop-off rates
        $prev_count = null;
        foreach ($funnel as $stage => $count) {
            if ($prev_count !== null && $prev_count > 0) {
                $funnel[$stage . '_dropoff'] = round((1 - ($count / $prev_count)) * 100, 2);
            }
            $prev_count = $count;
        }

        return $funnel;
    }

    /**
     * Get keyword analytics
     */
    public function get_keyword_analytics($limit = 20) {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');

        // Get all keywords from recent intentions
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT keywords
            FROM $intentions_table
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND keywords IS NOT NULL
            LIMIT %d
        ", 1000), ARRAY_A);

        $keyword_counts = array();
        foreach ($results as $row) {
            $keywords = json_decode($row['keywords'], true);
            if (is_array($keywords)) {
                foreach ($keywords as $keyword) {
                    $keyword = strtolower(trim($keyword));
                    if (strlen($keyword) > 2) {
                        if (!isset($keyword_counts[$keyword])) {
                            $keyword_counts[$keyword] = 0;
                        }
                        $keyword_counts[$keyword]++;
                    }
                }
            }
        }

        arsort($keyword_counts);
        return array_slice($keyword_counts, 0, $limit, true);
    }

    /**
     * Get intent analytics
     */
    public function get_intent_analytics($period = 'month') {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');
        $date_filter = $this->get_date_filter($period);

        return $wpdb->get_results("
            SELECT
                interpreted_intent,
                COUNT(*) as count,
                SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
                AVG(confidence_score) as avg_confidence,
                SUM(conversion_value) as total_value
            FROM $intentions_table
            WHERE $date_filter
            GROUP BY interpreted_intent
            ORDER BY count DESC
        ", ARRAY_A);
    }

    /**
     * Get device analytics
     */
    public function get_device_analytics($period = 'month') {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');
        $date_filter = $this->get_date_filter($period);

        return $wpdb->get_results("
            SELECT
                device_type,
                COUNT(*) as sessions,
                SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
                SUM(conversion_value) as revenue
            FROM $intentions_table
            WHERE $date_filter
            GROUP BY device_type
            ORDER BY sessions DESC
        ", ARRAY_A);
    }

    /**
     * Get time-based analytics (hourly patterns)
     */
    public function get_hourly_patterns($period = 'month') {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');
        $date_filter = $this->get_date_filter($period);

        return $wpdb->get_results("
            SELECT
                HOUR(created_at) as hour,
                COUNT(*) as inquiries,
                SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions
            FROM $intentions_table
            WHERE $date_filter
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ", ARRAY_A);
    }

    /**
     * Get day of week patterns
     */
    public function get_weekly_patterns($period = 'month') {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $date_filter = $this->get_date_filter($period);

        return $wpdb->get_results("
            SELECT
                DAYNAME(created_at) as day_name,
                DAYOFWEEK(created_at) as day_num,
                COUNT(*) as orders,
                SUM(total) as revenue
            FROM $jobs_table
            WHERE $date_filter AND job_status != 'cancelled'
            GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
            ORDER BY day_num ASC
        ", ARRAY_A);
    }

    /**
     * Get comparison stats (current vs previous period)
     */
    public function get_comparison_stats($period = 'month') {
        $current = $this->get_period_stats($period);

        // Get previous period stats
        $previous = $this->get_previous_period_stats($period);

        // Calculate changes
        $comparison = array();
        foreach ($current as $key => $value) {
            $prev_value = isset($previous[$key]) ? $previous[$key] : 0;
            $comparison[$key] = array(
                'current' => $value,
                'previous' => $prev_value,
                'change' => $prev_value > 0 ? round((($value - $prev_value) / $prev_value) * 100, 2) : 0,
                'trend' => $value > $prev_value ? 'up' : ($value < $prev_value ? 'down' : 'stable')
            );
        }

        return $comparison;
    }

    /**
     * Get previous period stats
     */
    private function get_previous_period_stats($period) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $customers_table = SBHA_Database::get_table('customers');

        $date_range = $this->get_previous_date_range($period);

        $jobs = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
                AVG(total) as avg_order_value
            FROM $jobs_table
            WHERE created_at BETWEEN %s AND %s
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        $new_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $customers_table
            WHERE created_at BETWEEN %s AND %s
        ", $date_range['start'], $date_range['end']));

        return array(
            'total_jobs' => intval($jobs['total_jobs']),
            'completed_jobs' => intval($jobs['completed_jobs']),
            'revenue' => floatval($jobs['revenue']),
            'avg_order_value' => floatval($jobs['avg_order_value']),
            'new_customers' => intval($new_customers)
        );
    }

    /**
     * Get previous date range
     */
    private function get_previous_date_range($period) {
        switch ($period) {
            case 'week':
                return array(
                    'start' => date('Y-m-d', strtotime('-14 days')),
                    'end' => date('Y-m-d', strtotime('-7 days'))
                );
            case 'month':
                return array(
                    'start' => date('Y-m-d', strtotime('-60 days')),
                    'end' => date('Y-m-d', strtotime('-30 days'))
                );
            case 'year':
                return array(
                    'start' => date('Y-m-d', strtotime('-2 years')),
                    'end' => date('Y-m-d', strtotime('-1 year'))
                );
            default:
                return array(
                    'start' => date('Y-m-d', strtotime('yesterday')),
                    'end' => date('Y-m-d', strtotime('yesterday'))
                );
        }
    }

    /**
     * Export analytics to CSV
     */
    public function export_report($type, $period = 'month') {
        switch ($type) {
            case 'revenue':
                return $this->export_revenue_report($period);
            case 'services':
                return $this->export_services_report();
            case 'customers':
                return $this->export_customers_report();
            default:
                return '';
        }
    }

    /**
     * Export revenue report
     */
    private function export_revenue_report($period) {
        $data = $this->get_revenue_chart($period);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array('Date', 'Revenue', 'Orders'));

        foreach ($data as $row) {
            fputcsv($output, array($row['date'], $row['revenue'], $row['orders']));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export services report
     */
    private function export_services_report() {
        $data = $this->get_service_performance(100);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array('Service', 'Category', 'Base Price', 'Orders', 'Revenue', 'Avg Order', 'Completed', 'Cancelled'));

        foreach ($data as $row) {
            fputcsv($output, array(
                $row['name'],
                $row['category'],
                $row['base_price'],
                $row['order_count'],
                $row['total_revenue'],
                $row['avg_order_value'],
                $row['completed'],
                $row['cancelled']
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export customers report
     */
    private function export_customers_report() {
        $data = SBHA_Customer::get_customers(array('limit' => 0));

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array('ID', 'Email', 'Name', 'Company', 'Orders', 'Lifetime Value', 'Last Order', 'Status'));

        foreach ($data as $customer) {
            fputcsv($output, array(
                $customer['id'],
                $customer['email'],
                $customer['first_name'] . ' ' . $customer['last_name'],
                $customer['company'],
                $customer['total_orders'],
                $customer['lifetime_value'],
                $customer['last_order_date'],
                $customer['status']
            ));
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
