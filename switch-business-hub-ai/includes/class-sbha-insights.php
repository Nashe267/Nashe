<?php
/**
 * Business Insights Class
 *
 * AI-generated business insights and recommendations
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Insights {

    /**
     * Get all active insights
     */
    public static function get_active_insights($limit = 20) {
        global $wpdb;
        $table = SBHA_Database::get_table('ai_business_insights');

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE (expires_at IS NULL OR expires_at > NOW())
            ORDER BY
                CASE severity
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 5
                END,
                action_required DESC,
                created_at DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }

    /**
     * Get insights by type
     */
    public static function get_insights_by_type($type, $limit = 10) {
        return SBHA_Database::get_results(
            'ai_business_insights',
            array('insight_type' => $type),
            'created_at',
            'DESC',
            $limit
        );
    }

    /**
     * Get actionable insights
     */
    public static function get_actionable_insights() {
        return SBHA_Database::get_results(
            'ai_business_insights',
            array('action_required' => 1, 'action_taken' => 0),
            'severity',
            'ASC'
        );
    }

    /**
     * Create insight
     */
    public static function create_insight($data) {
        $defaults = array(
            'insight_type' => 'general',
            'insight_title' => '',
            'insight_data' => '{}',
            'severity' => 'info',
            'category' => 'general',
            'action_required' => 0,
            'expires_at' => null
        );

        $data = wp_parse_args($data, $defaults);

        if (is_array($data['insight_data'])) {
            $data['insight_data'] = json_encode($data['insight_data']);
        }

        return SBHA_Database::insert('ai_business_insights', $data);
    }

    /**
     * Mark insight as acted upon
     */
    public static function mark_action_taken($insight_id, $notes = '') {
        return SBHA_Database::update('ai_business_insights',
            array(
                'action_taken' => 1,
                'action_taken_date' => current_time('mysql'),
                'action_notes' => $notes
            ),
            array('id' => $insight_id)
        );
    }

    /**
     * Dismiss insight
     */
    public static function dismiss_insight($insight_id) {
        return SBHA_Database::update('ai_business_insights',
            array('expires_at' => current_time('mysql')),
            array('id' => $insight_id)
        );
    }

    /**
     * Get service gap insights
     */
    public static function get_service_gaps($min_requests = 3) {
        global $wpdb;
        $table = SBHA_Database::get_table('service_gaps');

        return $wpdb->get_results($wpdb->prepare("
            SELECT *,
                CASE
                    WHEN request_count >= 15 THEN 'high'
                    WHEN request_count >= 8 THEN 'medium'
                    ELSE 'low'
                END as priority
            FROM $table
            WHERE status = 'identified'
              AND request_count >= %d
            ORDER BY estimated_demand_score DESC, request_count DESC
        ", $min_requests), ARRAY_A);
    }

    /**
     * Get business expansion opportunities
     */
    public static function get_expansion_opportunities() {
        $gaps = self::get_service_gaps(5);
        $opportunities = array();

        foreach ($gaps as $gap) {
            $opportunity = array(
                'keyword' => $gap['keyword'],
                'request_count' => $gap['request_count'],
                'demand_score' => $gap['estimated_demand_score'],
                'sample_queries' => json_decode($gap['sample_queries'], true),
                'analysis' => self::analyze_opportunity($gap)
            );

            $opportunities[] = $opportunity;
        }

        return $opportunities;
    }

    /**
     * Analyze expansion opportunity
     */
    private static function analyze_opportunity($gap) {
        $request_count = intval($gap['request_count']);
        $demand_score = floatval($gap['estimated_demand_score']);

        // Estimate potential revenue
        $avg_order_value = floatval(get_option('sbha_avg_order_value', 100));
        $estimated_monthly_orders = max(1, $request_count / 3);
        $estimated_revenue = $avg_order_value * $estimated_monthly_orders;

        // Calculate ROI score
        $roi_score = min(100, $demand_score * 10);

        // Generate recommendations
        $recommendations = array();

        if ($request_count >= 10) {
            $recommendations[] = 'Strong demand signals - consider prioritizing this service addition';
        }

        if ($demand_score > 50) {
            $recommendations[] = 'Growing trend detected - demand is increasing over time';
        }

        return array(
            'estimated_monthly_revenue' => round($estimated_revenue, 2),
            'estimated_monthly_orders' => $estimated_monthly_orders,
            'roi_score' => $roi_score,
            'recommendations' => $recommendations,
            'suggested_action' => $roi_score > 70 ? 'Add this service' : 'Monitor for more data'
        );
    }

    /**
     * Get trending services
     */
    public static function get_trending_services($period = 'week') {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');
        $services_table = SBHA_Database::get_table('services');

        $date_filter = $period === 'week' ? 7 : 30;

        // This is a simplified trending calculation
        // In production, you'd compare current period to previous
        $trending = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.id,
                s.name,
                s.category,
                COUNT(*) as inquiry_count
            FROM $intentions_table i
            JOIN $services_table s ON i.matched_services LIKE CONCAT('%%\"service_id\":', s.id, '%%')
            WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY s.id
            ORDER BY inquiry_count DESC
            LIMIT 10
        ", $date_filter), ARRAY_A);

        return $trending;
    }

    /**
     * Get customer behavior insights
     */
    public static function get_customer_insights() {
        global $wpdb;

        $customers_table = SBHA_Database::get_table('customers');
        $jobs_table = SBHA_Database::get_table('jobs');

        $insights = array();

        // At-risk high-value customers
        $at_risk = $wpdb->get_results("
            SELECT c.*, DATEDIFF(NOW(), c.last_order_date) as days_inactive
            FROM $customers_table c
            WHERE c.status = 'active'
              AND c.lifetime_value > 500
              AND c.last_order_date < DATE_SUB(NOW(), INTERVAL 60 DAY)
            ORDER BY c.lifetime_value DESC
            LIMIT 5
        ", ARRAY_A);

        if (!empty($at_risk)) {
            $insights[] = array(
                'type' => 'at_risk_customers',
                'title' => 'High-Value Customers at Risk',
                'severity' => 'high',
                'message' => sprintf('%d high-value customers haven\'t ordered in over 60 days', count($at_risk)),
                'data' => $at_risk,
                'action' => 'Consider reaching out with special offers'
            );
        }

        // Customers ready for upsell
        $upsell_ready = $wpdb->get_results("
            SELECT c.*
            FROM $customers_table c
            WHERE c.status = 'active'
              AND c.total_orders >= 2
              AND c.last_order_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY c.average_order_value DESC
            LIMIT 10
        ", ARRAY_A);

        if (!empty($upsell_ready)) {
            $insights[] = array(
                'type' => 'upsell_opportunities',
                'title' => 'Upsell Opportunities',
                'severity' => 'medium',
                'message' => sprintf('%d active customers may be ready for additional services', count($upsell_ready)),
                'data' => $upsell_ready,
                'action' => 'Review their order history and suggest complementary services'
            );
        }

        return $insights;
    }

    /**
     * Get operational insights
     */
    public static function get_operational_insights() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $insights = array();

        // Overdue jobs
        $overdue = $wpdb->get_results("
            SELECT * FROM $jobs_table
            WHERE estimated_completion < NOW()
              AND job_status NOT IN ('completed', 'delivered', 'cancelled')
            ORDER BY estimated_completion ASC
        ", ARRAY_A);

        if (!empty($overdue)) {
            $insights[] = array(
                'type' => 'overdue_jobs',
                'title' => 'Overdue Jobs',
                'severity' => 'critical',
                'message' => sprintf('%d jobs are past their estimated completion date', count($overdue)),
                'data' => $overdue,
                'action' => 'Review and update job statuses immediately'
            );
        }

        // Jobs requiring revision (high revision rate)
        $high_revision = $wpdb->get_results("
            SELECT * FROM $jobs_table
            WHERE revision_count >= max_revisions
              AND job_status IN ('revision', 'in_progress')
        ", ARRAY_A);

        if (!empty($high_revision)) {
            $insights[] = array(
                'type' => 'high_revision_jobs',
                'title' => 'Jobs with High Revision Count',
                'severity' => 'medium',
                'message' => sprintf('%d jobs have reached or exceeded revision limits', count($high_revision)),
                'data' => $high_revision,
                'action' => 'Review requirements clarification process'
            );
        }

        // Unpaid jobs
        $unpaid = $wpdb->get_results("
            SELECT * FROM $jobs_table
            WHERE payment_status = 'pending'
              AND job_status IN ('completed', 'delivered')
              AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", ARRAY_A);

        if (!empty($unpaid)) {
            $total_unpaid = array_sum(array_column($unpaid, 'total'));
            $insights[] = array(
                'type' => 'unpaid_jobs',
                'title' => 'Unpaid Completed Jobs',
                'severity' => 'high',
                'message' => sprintf('%d completed jobs totaling $%.2f are unpaid', count($unpaid), $total_unpaid),
                'data' => $unpaid,
                'action' => 'Follow up on payment collection'
            );
        }

        return $insights;
    }

    /**
     * Get market trend insights
     */
    public static function get_market_insights() {
        $insights = array();

        // Seasonal trends
        $seasonal = self::detect_seasonal_patterns();
        if (!empty($seasonal)) {
            $insights[] = array(
                'type' => 'seasonal_trend',
                'title' => 'Seasonal Pattern Detected',
                'severity' => 'info',
                'data' => $seasonal,
                'action' => 'Adjust marketing and inventory accordingly'
            );
        }

        // Rising categories
        $rising = self::detect_rising_categories();
        if (!empty($rising)) {
            foreach ($rising as $category) {
                $insights[] = array(
                    'type' => 'rising_category',
                    'title' => sprintf('%s Services Trending Up', ucfirst($category['category'])),
                    'severity' => 'info',
                    'message' => sprintf('Demand increased by %.1f%% compared to last month', $category['growth']),
                    'data' => $category,
                    'action' => 'Consider expanding offerings in this category'
                );
            }
        }

        return $insights;
    }

    /**
     * Detect seasonal patterns
     */
    private static function detect_seasonal_patterns() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');

        // Get monthly patterns
        $patterns = $wpdb->get_results("
            SELECT
                MONTH(created_at) as month,
                COUNT(*) as order_count,
                SUM(total) as revenue
            FROM $jobs_table
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
              AND job_status != 'cancelled'
            GROUP BY MONTH(created_at)
            ORDER BY month
        ", ARRAY_A);

        if (count($patterns) < 6) {
            return null;
        }

        $avg_orders = array_sum(array_column($patterns, 'order_count')) / count($patterns);

        // Find peak months
        $peak_months = array();
        $low_months = array();

        foreach ($patterns as $p) {
            if ($p['order_count'] > $avg_orders * 1.3) {
                $peak_months[] = date('F', mktime(0, 0, 0, $p['month'], 1));
            } elseif ($p['order_count'] < $avg_orders * 0.7) {
                $low_months[] = date('F', mktime(0, 0, 0, $p['month'], 1));
            }
        }

        if (empty($peak_months) && empty($low_months)) {
            return null;
        }

        return array(
            'peak_months' => $peak_months,
            'low_months' => $low_months,
            'average_monthly_orders' => round($avg_orders)
        );
    }

    /**
     * Detect rising categories
     */
    private static function detect_rising_categories() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        $current = $wpdb->get_results("
            SELECT s.category, COUNT(*) as count
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE j.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND j.job_status != 'cancelled'
            GROUP BY s.category
        ", ARRAY_A);

        $previous = $wpdb->get_results("
            SELECT s.category, COUNT(*) as count
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE j.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND j.job_status != 'cancelled'
            GROUP BY s.category
        ", ARRAY_A);

        $prev_map = array();
        foreach ($previous as $p) {
            $prev_map[$p['category']] = $p['count'];
        }

        $rising = array();
        foreach ($current as $c) {
            $prev_count = isset($prev_map[$c['category']]) ? $prev_map[$c['category']] : 0;
            if ($prev_count > 0) {
                $growth = (($c['count'] - $prev_count) / $prev_count) * 100;
                if ($growth > 20) {
                    $rising[] = array(
                        'category' => $c['category'],
                        'current_count' => $c['count'],
                        'previous_count' => $prev_count,
                        'growth' => round($growth, 1)
                    );
                }
            }
        }

        return $rising;
    }

    /**
     * Generate daily summary
     */
    public static function generate_daily_summary() {
        $summary = array(
            'date' => current_time('Y-m-d'),
            'stats' => array(),
            'highlights' => array(),
            'alerts' => array()
        );

        // Get today's stats
        if (function_exists('SBHA') && SBHA()->get_analytics()) {
            $summary['stats'] = SBHA()->get_analytics()->get_period_stats('today');
        }

        // Get alerts
        $summary['alerts'] = array_merge(
            self::get_operational_insights(),
            self::get_customer_insights()
        );

        // Filter to only critical/high severity
        $summary['alerts'] = array_filter($summary['alerts'], function($alert) {
            return in_array($alert['severity'], array('critical', 'high'));
        });

        return $summary;
    }
}
