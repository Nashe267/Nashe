<?php
/**
 * Reports Class
 *
 * Business reports and export functionality
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Reports {

    /**
     * Generate comprehensive business report
     */
    public static function generate_business_report($period = 'month') {
        $report = array(
            'generated_at' => current_time('mysql'),
            'period' => $period,
            'date_range' => self::get_date_range($period),
            'sections' => array()
        );

        // Executive Summary
        $report['sections']['summary'] = self::get_executive_summary($period);

        // Revenue Report
        $report['sections']['revenue'] = self::get_revenue_report($period);

        // Services Performance
        $report['sections']['services'] = self::get_services_report($period);

        // Customer Report
        $report['sections']['customers'] = self::get_customer_report($period);

        // Operational Metrics
        $report['sections']['operations'] = self::get_operations_report($period);

        // AI Insights
        $report['sections']['insights'] = self::get_insights_report();

        return $report;
    }

    /**
     * Get date range for period
     */
    private static function get_date_range($period) {
        switch ($period) {
            case 'week':
                return array(
                    'start' => date('Y-m-d', strtotime('-7 days')),
                    'end' => date('Y-m-d')
                );
            case 'month':
                return array(
                    'start' => date('Y-m-d', strtotime('-30 days')),
                    'end' => date('Y-m-d')
                );
            case 'quarter':
                return array(
                    'start' => date('Y-m-d', strtotime('-90 days')),
                    'end' => date('Y-m-d')
                );
            case 'year':
                return array(
                    'start' => date('Y-m-d', strtotime('-1 year')),
                    'end' => date('Y-m-d')
                );
            default:
                return array(
                    'start' => date('Y-m-d'),
                    'end' => date('Y-m-d')
                );
        }
    }

    /**
     * Get executive summary
     */
    private static function get_executive_summary($period) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $customers_table = SBHA_Database::get_table('customers');

        $date_range = self::get_date_range($period);

        // Current period stats
        $current = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
                AVG(total) as avg_order
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
              AND job_status != 'cancelled'
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        // New customers
        $new_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $customers_table
            WHERE DATE(created_at) BETWEEN %s AND %s
        ", $date_range['start'], $date_range['end']));

        // Repeat customers
        $repeat_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
              AND customer_id IN (
                  SELECT customer_id FROM $jobs_table
                  WHERE DATE(created_at) < %s
              )
        ", $date_range['start'], $date_range['end'], $date_range['start']));

        return array(
            'total_jobs' => intval($current['total_jobs']),
            'completed_jobs' => intval($current['completed_jobs']),
            'completion_rate' => $current['total_jobs'] > 0 ?
                round(($current['completed_jobs'] / $current['total_jobs']) * 100, 1) : 0,
            'revenue' => floatval($current['revenue']),
            'average_order_value' => floatval($current['avg_order']),
            'new_customers' => intval($new_customers),
            'repeat_customers' => intval($repeat_customers),
            'customer_retention_rate' => ($new_customers + $repeat_customers) > 0 ?
                round(($repeat_customers / ($new_customers + $repeat_customers)) * 100, 1) : 0
        );
    }

    /**
     * Get revenue report
     */
    private static function get_revenue_report($period) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $date_range = self::get_date_range($period);

        // Daily revenue
        $daily_revenue = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(created_at) as date,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue,
                COUNT(*) as orders
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
              AND job_status != 'cancelled'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        // Revenue by category
        $services_table = SBHA_Database::get_table('services');
        $category_revenue = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.category,
                SUM(j.total) as revenue,
                COUNT(*) as orders
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE DATE(j.created_at) BETWEEN %s AND %s
              AND j.job_status != 'cancelled'
              AND j.payment_status = 'paid'
            GROUP BY s.category
            ORDER BY revenue DESC
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        // Payment status breakdown
        $payment_breakdown = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as paid,
                SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as pending,
                SUM(CASE WHEN payment_status = 'partial' THEN total ELSE 0 END) as partial
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
              AND job_status != 'cancelled'
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        return array(
            'daily' => $daily_revenue,
            'by_category' => $category_revenue,
            'payment_status' => $payment_breakdown,
            'total_revenue' => floatval($payment_breakdown['paid']),
            'outstanding' => floatval($payment_breakdown['pending']) + floatval($payment_breakdown['partial'])
        );
    }

    /**
     * Get services report
     */
    private static function get_services_report($period) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');
        $date_range = self::get_date_range($period);

        // Top services
        $top_services = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.id,
                s.name,
                s.category,
                COUNT(j.id) as order_count,
                SUM(j.total) as revenue,
                AVG(j.total) as avg_order,
                SUM(CASE WHEN j.job_status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM $services_table s
            LEFT JOIN $jobs_table j ON s.id = j.service_id
                AND DATE(j.created_at) BETWEEN %s AND %s
                AND j.job_status != 'cancelled'
            WHERE s.status = 'active'
            GROUP BY s.id
            ORDER BY order_count DESC
            LIMIT 10
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        // Service pairings
        $pairings = $wpdb->get_results("
            SELECT
                service_id,
                paired_service_id,
                pairing_count,
                pairing_confidence
            FROM " . SBHA_Database::get_table('ai_service_patterns') . "
            ORDER BY pairing_count DESC
            LIMIT 10
        ", ARRAY_A);

        return array(
            'top_services' => $top_services,
            'popular_pairings' => $pairings
        );
    }

    /**
     * Get customer report
     */
    private static function get_customer_report($period) {
        global $wpdb;

        $customers_table = SBHA_Database::get_table('customers');
        $jobs_table = SBHA_Database::get_table('jobs');
        $date_range = self::get_date_range($period);

        // Customer acquisition
        $acquisition = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as new_customers
            FROM $customers_table
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $date_range['start'], $date_range['end']), ARRAY_A);

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
                SUM(lifetime_value) as total_value
            FROM $customers_table
            WHERE status = 'active'
            GROUP BY segment
            ORDER BY total_value DESC
        ", ARRAY_A);

        // Top customers
        $top_customers = $wpdb->get_results($wpdb->prepare("
            SELECT
                c.id,
                c.email,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                c.company,
                COUNT(j.id) as orders_in_period,
                SUM(j.total) as revenue_in_period
            FROM $customers_table c
            JOIN $jobs_table j ON c.id = j.customer_id
            WHERE DATE(j.created_at) BETWEEN %s AND %s
              AND j.job_status != 'cancelled'
            GROUP BY c.id
            ORDER BY revenue_in_period DESC
            LIMIT 10
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        return array(
            'acquisition' => $acquisition,
            'segments' => $segments,
            'top_customers' => $top_customers
        );
    }

    /**
     * Get operations report
     */
    private static function get_operations_report($period) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $date_range = self::get_date_range($period);

        // Job status breakdown
        $status_breakdown = $wpdb->get_results($wpdb->prepare("
            SELECT
                job_status,
                COUNT(*) as count
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY job_status
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        // Average completion time
        $completion_time = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, actual_completion))
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
              AND job_status = 'completed'
              AND actual_completion IS NOT NULL
        ", $date_range['start'], $date_range['end']));

        // Revision statistics
        $revisions = $wpdb->get_row($wpdb->prepare("
            SELECT
                AVG(revision_count) as avg_revisions,
                MAX(revision_count) as max_revisions,
                SUM(CASE WHEN revision_count > max_revisions THEN 1 ELSE 0 END) as exceeded_limit
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
              AND job_status IN ('completed', 'delivered')
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        // Priority breakdown
        $priority_breakdown = $wpdb->get_results($wpdb->prepare("
            SELECT
                priority,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(actual_completion, NOW()))) as avg_time
            FROM $jobs_table
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY priority
        ", $date_range['start'], $date_range['end']), ARRAY_A);

        return array(
            'status_breakdown' => $status_breakdown,
            'avg_completion_hours' => round(floatval($completion_time), 1),
            'revisions' => $revisions,
            'priority_breakdown' => $priority_breakdown
        );
    }

    /**
     * Get insights report
     */
    private static function get_insights_report() {
        // Get recent insights
        $insights = SBHA_Insights::get_active_insights(10);

        // Get service gaps
        $gaps = SBHA_Insights::get_service_gaps(3);

        // Get recommendations
        $recommendations = array();

        foreach ($gaps as $gap) {
            if ($gap['request_count'] >= 5) {
                $recommendations[] = array(
                    'type' => 'service_gap',
                    'title' => sprintf('Consider adding "%s"', $gap['keyword']),
                    'details' => sprintf('%d customer requests identified', $gap['request_count']),
                    'priority' => $gap['request_count'] >= 10 ? 'high' : 'medium'
                );
            }
        }

        return array(
            'active_insights' => $insights,
            'service_gaps' => $gaps,
            'recommendations' => $recommendations
        );
    }

    /**
     * Export report to PDF
     */
    public static function export_to_pdf($report) {
        // For PDF generation, you would typically use a library like TCPDF or Dompdf
        // This is a placeholder that returns HTML
        return self::generate_html_report($report);
    }

    /**
     * Generate HTML report
     */
    public static function generate_html_report($report) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Business Report - <?php echo esc_html($report['date_range']['start']); ?> to <?php echo esc_html($report['date_range']['end']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
                h1 { color: #FF6600; border-bottom: 2px solid #FF6600; padding-bottom: 10px; }
                h2 { color: #333; margin-top: 30px; }
                .stat-box { display: inline-block; background: #f5f5f5; padding: 15px 25px; margin: 10px; border-radius: 8px; }
                .stat-value { font-size: 24px; font-weight: bold; color: #FF6600; }
                .stat-label { font-size: 12px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background: #FF6600; color: white; }
                tr:nth-child(even) { background: #f9f9f9; }
                .insight { background: #fff3e0; padding: 10px; margin: 10px 0; border-left: 4px solid #FF6600; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <h1>Switch Business Hub - Business Report</h1>
            <p><strong>Period:</strong> <?php echo esc_html($report['date_range']['start']); ?> to <?php echo esc_html($report['date_range']['end']); ?></p>
            <p><strong>Generated:</strong> <?php echo esc_html($report['generated_at']); ?></p>

            <h2>Executive Summary</h2>
            <?php $summary = $report['sections']['summary']; ?>
            <div class="stat-box">
                <div class="stat-value"><?php echo esc_html($summary['total_jobs']); ?></div>
                <div class="stat-label">Total Jobs</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">$<?php echo number_format($summary['revenue'], 2); ?></div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">$<?php echo number_format($summary['average_order_value'], 2); ?></div>
                <div class="stat-label">Avg Order</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo esc_html($summary['new_customers']); ?></div>
                <div class="stat-label">New Customers</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo esc_html($summary['completion_rate']); ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>

            <h2>Top Services</h2>
            <table>
                <tr>
                    <th>Service</th>
                    <th>Orders</th>
                    <th>Revenue</th>
                    <th>Avg Order</th>
                </tr>
                <?php foreach ($report['sections']['services']['top_services'] as $service): ?>
                <tr>
                    <td><?php echo esc_html($service['name']); ?></td>
                    <td><?php echo esc_html($service['order_count']); ?></td>
                    <td>$<?php echo number_format($service['revenue'], 2); ?></td>
                    <td>$<?php echo number_format($service['avg_order'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h2>Top Customers</h2>
            <table>
                <tr>
                    <th>Customer</th>
                    <th>Orders</th>
                    <th>Revenue</th>
                </tr>
                <?php foreach ($report['sections']['customers']['top_customers'] as $customer): ?>
                <tr>
                    <td><?php echo esc_html($customer['name'] ?: $customer['email']); ?></td>
                    <td><?php echo esc_html($customer['orders_in_period']); ?></td>
                    <td>$<?php echo number_format($customer['revenue_in_period'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php if (!empty($report['sections']['insights']['recommendations'])): ?>
            <h2>Recommendations</h2>
            <?php foreach ($report['sections']['insights']['recommendations'] as $rec): ?>
            <div class="insight">
                <strong><?php echo esc_html($rec['title']); ?></strong><br>
                <?php echo esc_html($rec['details']); ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <div class="footer">
                <p>This report was generated by Switch Business Hub AI</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Export report to CSV
     */
    public static function export_to_csv($report, $section = 'all') {
        $output = fopen('php://temp', 'r+');

        // Export summary
        if ($section === 'all' || $section === 'summary') {
            fputcsv($output, array('--- EXECUTIVE SUMMARY ---'));
            fputcsv($output, array('Metric', 'Value'));
            foreach ($report['sections']['summary'] as $key => $value) {
                fputcsv($output, array(ucwords(str_replace('_', ' ', $key)), $value));
            }
            fputcsv($output, array(''));
        }

        // Export services
        if ($section === 'all' || $section === 'services') {
            fputcsv($output, array('--- TOP SERVICES ---'));
            fputcsv($output, array('Service', 'Category', 'Orders', 'Revenue', 'Avg Order', 'Completed'));
            foreach ($report['sections']['services']['top_services'] as $service) {
                fputcsv($output, array(
                    $service['name'],
                    $service['category'],
                    $service['order_count'],
                    $service['revenue'],
                    $service['avg_order'],
                    $service['completed']
                ));
            }
            fputcsv($output, array(''));
        }

        // Export customers
        if ($section === 'all' || $section === 'customers') {
            fputcsv($output, array('--- TOP CUSTOMERS ---'));
            fputcsv($output, array('Name', 'Email', 'Company', 'Orders', 'Revenue'));
            foreach ($report['sections']['customers']['top_customers'] as $customer) {
                fputcsv($output, array(
                    $customer['name'],
                    $customer['email'],
                    $customer['company'],
                    $customer['orders_in_period'],
                    $customer['revenue_in_period']
                ));
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Schedule automated reports
     */
    public static function schedule_report($frequency, $email, $report_type = 'business') {
        $schedules = get_option('sbha_scheduled_reports', array());

        $schedules[] = array(
            'id' => uniqid('report_'),
            'frequency' => $frequency,
            'email' => $email,
            'report_type' => $report_type,
            'created_at' => current_time('mysql'),
            'last_sent' => null
        );

        update_option('sbha_scheduled_reports', $schedules);

        return true;
    }

    /**
     * Send scheduled reports
     */
    public static function send_scheduled_reports() {
        $schedules = get_option('sbha_scheduled_reports', array());

        foreach ($schedules as $key => $schedule) {
            if (self::should_send_report($schedule)) {
                $period = $schedule['frequency'] === 'weekly' ? 'week' : 'month';
                $report = self::generate_business_report($period);
                $html = self::generate_html_report($report);

                $subject = sprintf(
                    '[Switch Business Hub] %s Report - %s',
                    ucfirst($schedule['frequency']),
                    date('M d, Y')
                );

                wp_mail(
                    $schedule['email'],
                    $subject,
                    $html,
                    array('Content-Type: text/html; charset=UTF-8')
                );

                $schedules[$key]['last_sent'] = current_time('mysql');
            }
        }

        update_option('sbha_scheduled_reports', $schedules);
    }

    /**
     * Check if report should be sent
     */
    private static function should_send_report($schedule) {
        if (empty($schedule['last_sent'])) {
            return true;
        }

        $last_sent = strtotime($schedule['last_sent']);
        $now = current_time('timestamp');

        switch ($schedule['frequency']) {
            case 'daily':
                return ($now - $last_sent) >= DAY_IN_SECONDS;
            case 'weekly':
                return ($now - $last_sent) >= WEEK_IN_SECONDS;
            case 'monthly':
                return ($now - $last_sent) >= (30 * DAY_IN_SECONDS);
            default:
                return false;
        }
    }
}
