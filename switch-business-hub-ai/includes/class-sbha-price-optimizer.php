<?php
/**
 * Price Optimizer Class
 *
 * AI-powered pricing optimization and analysis
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Price_Optimizer {

    /**
     * Get pricing recommendations for a service
     */
    public function get_price_recommendations($service_id) {
        $service = SBHA_Database::get_by_id('services', $service_id);
        if (!$service) {
            return array();
        }

        $current_price = floatval($service['base_price']);
        $analysis = $this->analyze_service_pricing($service_id);

        $recommendations = array();

        // Check if price is too low
        if ($analysis['avg_order_value'] > $current_price * 1.3) {
            $suggested = round($analysis['avg_order_value'] * 0.9, 2);
            $recommendations[] = array(
                'type' => 'increase',
                'current_price' => $current_price,
                'suggested_price' => $suggested,
                'potential_increase' => round((($suggested - $current_price) / $current_price) * 100, 1),
                'reason' => 'Customers are paying significantly more than base price on average',
                'confidence' => $this->calculate_confidence($analysis)
            );
        }

        // Check for high demand
        if ($analysis['demand_score'] > 0.7 && $analysis['conversion_rate'] > 0.5) {
            $suggested = round($current_price * 1.1, 2);
            $recommendations[] = array(
                'type' => 'increase',
                'current_price' => $current_price,
                'suggested_price' => $suggested,
                'potential_increase' => 10,
                'reason' => 'High demand and conversion rate suggest price increase opportunity',
                'confidence' => 0.7
            );
        }

        // Check for low conversion
        if ($analysis['conversion_rate'] < 0.2 && $analysis['view_count'] > 20) {
            $suggested = round($current_price * 0.9, 2);
            $recommendations[] = array(
                'type' => 'decrease',
                'current_price' => $current_price,
                'suggested_price' => $suggested,
                'potential_increase' => -10,
                'reason' => 'Low conversion rate may indicate price sensitivity',
                'confidence' => 0.5
            );
        }

        return $recommendations;
    }

    /**
     * Analyze service pricing
     */
    public function analyze_service_pricing($service_id) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');

        // Get order statistics
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as order_count,
                AVG(total) as avg_order_value,
                MIN(total) as min_order,
                MAX(total) as max_order,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
            FROM $jobs_table
            WHERE service_id = %d AND job_status != 'cancelled'
        ", $service_id), ARRAY_A);

        // Get view/inquiry count
        $view_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $intentions_table
            WHERE matched_services LIKE %s
        ", '%"service_id":' . $service_id . '%'));

        // Calculate metrics
        $order_count = intval($stats['order_count']);
        $conversion_rate = $view_count > 0 ? $order_count / $view_count : 0;

        // Calculate demand score (normalized between 0-1)
        $demand_score = min(1, $order_count / 100); // 100 orders = max demand

        return array(
            'order_count' => $order_count,
            'avg_order_value' => floatval($stats['avg_order_value']),
            'min_order' => floatval($stats['min_order']),
            'max_order' => floatval($stats['max_order']),
            'view_count' => intval($view_count),
            'conversion_rate' => $conversion_rate,
            'demand_score' => $demand_score,
            'price_variance' => $stats['max_order'] > 0 ?
                ($stats['max_order'] - $stats['min_order']) / $stats['max_order'] : 0
        );
    }

    /**
     * Calculate confidence score
     */
    private function calculate_confidence($analysis) {
        $confidence = 0.5;

        // More orders = higher confidence
        if ($analysis['order_count'] > 50) {
            $confidence += 0.2;
        } elseif ($analysis['order_count'] > 20) {
            $confidence += 0.1;
        }

        // More views = higher confidence
        if ($analysis['view_count'] > 100) {
            $confidence += 0.1;
        }

        // Lower price variance = higher confidence
        if ($analysis['price_variance'] < 0.3) {
            $confidence += 0.1;
        }

        return min(0.95, $confidence);
    }

    /**
     * Get bundle pricing suggestions
     */
    public function get_bundle_suggestions() {
        global $wpdb;

        $patterns_table = SBHA_Database::get_table('ai_service_patterns');
        $services_table = SBHA_Database::get_table('services');

        // Find highly paired services
        $pairings = $wpdb->get_results("
            SELECT
                p.service_id,
                p.paired_service_id,
                p.pairing_count,
                p.pairing_confidence,
                s1.name as service_name,
                s1.base_price as service_price,
                s2.name as paired_name,
                s2.base_price as paired_price
            FROM $patterns_table p
            JOIN $services_table s1 ON p.service_id = s1.id
            JOIN $services_table s2 ON p.paired_service_id = s2.id
            WHERE p.pairing_confidence > 0.3
              AND p.pairing_count >= 5
            ORDER BY p.pairing_confidence DESC
            LIMIT 10
        ", ARRAY_A);

        return array_map(function($p) {
            $total_price = floatval($p['service_price']) + floatval($p['paired_price']);
            $discount = $this->calculate_optimal_bundle_discount($p['pairing_confidence']);

            return array(
                'services' => array(
                    array('id' => $p['service_id'], 'name' => $p['service_name'], 'price' => $p['service_price']),
                    array('id' => $p['paired_service_id'], 'name' => $p['paired_name'], 'price' => $p['paired_price'])
                ),
                'original_total' => $total_price,
                'suggested_discount' => $discount,
                'bundle_price' => round($total_price * (1 - $discount / 100), 2),
                'pairing_count' => $p['pairing_count'],
                'confidence' => round($p['pairing_confidence'] * 100, 1)
            );
        }, $pairings);
    }

    /**
     * Calculate optimal bundle discount
     */
    private function calculate_optimal_bundle_discount($confidence) {
        // Higher confidence = customers already buy together, so less discount needed
        // Lower confidence = need more incentive
        if ($confidence >= 0.7) return 10;
        if ($confidence >= 0.5) return 15;
        if ($confidence >= 0.3) return 20;
        return 25;
    }

    /**
     * Get price elasticity estimate
     */
    public function estimate_price_elasticity($service_id) {
        global $wpdb;

        $history_table = SBHA_Database::get_table('price_history');
        $jobs_table = SBHA_Database::get_table('jobs');

        // Get price changes with conversion data
        $changes = $wpdb->get_results($wpdb->prepare("
            SELECT
                h.*,
                (SELECT COUNT(*) FROM $jobs_table
                 WHERE service_id = h.service_id
                   AND created_at BETWEEN DATE_SUB(h.created_at, INTERVAL 30 DAY) AND h.created_at
                ) as orders_before,
                (SELECT COUNT(*) FROM $jobs_table
                 WHERE service_id = h.service_id
                   AND created_at BETWEEN h.created_at AND DATE_ADD(h.created_at, INTERVAL 30 DAY)
                ) as orders_after
            FROM $history_table h
            WHERE h.service_id = %d
            ORDER BY h.created_at DESC
            LIMIT 5
        ", $service_id), ARRAY_A);

        if (empty($changes)) {
            return array(
                'elasticity' => 'unknown',
                'message' => 'Not enough price history data to estimate elasticity'
            );
        }

        // Calculate average elasticity
        $elasticities = array();
        foreach ($changes as $change) {
            $price_change = ($change['new_price'] - $change['old_price']) / $change['old_price'];
            $demand_change = $change['orders_before'] > 0 ?
                ($change['orders_after'] - $change['orders_before']) / $change['orders_before'] : 0;

            if ($price_change != 0) {
                $elasticities[] = abs($demand_change / $price_change);
            }
        }

        $avg_elasticity = !empty($elasticities) ? array_sum($elasticities) / count($elasticities) : 0;

        if ($avg_elasticity > 1.5) {
            $category = 'elastic';
            $message = 'Demand is highly sensitive to price changes. Consider smaller price adjustments.';
        } elseif ($avg_elasticity > 0.5) {
            $category = 'moderate';
            $message = 'Demand has moderate sensitivity to price. Price changes may affect sales moderately.';
        } else {
            $category = 'inelastic';
            $message = 'Demand is relatively stable despite price changes. More flexibility in pricing.';
        }

        return array(
            'elasticity_score' => round($avg_elasticity, 2),
            'category' => $category,
            'message' => $message,
            'data_points' => count($changes)
        );
    }

    /**
     * Get seasonal pricing recommendations
     */
    public function get_seasonal_recommendations() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        // Analyze monthly patterns
        $patterns = $wpdb->get_results("
            SELECT
                s.id as service_id,
                s.name as service_name,
                s.category,
                MONTH(j.created_at) as month,
                COUNT(*) as order_count,
                SUM(j.total) as revenue
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE j.created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
              AND j.job_status != 'cancelled'
            GROUP BY s.id, MONTH(j.created_at)
            ORDER BY s.id, month
        ", ARRAY_A);

        // Group by service
        $service_patterns = array();
        foreach ($patterns as $p) {
            $service_id = $p['service_id'];
            if (!isset($service_patterns[$service_id])) {
                $service_patterns[$service_id] = array(
                    'name' => $p['service_name'],
                    'category' => $p['category'],
                    'months' => array()
                );
            }
            $service_patterns[$service_id]['months'][$p['month']] = array(
                'orders' => $p['order_count'],
                'revenue' => $p['revenue']
            );
        }

        // Generate recommendations
        $recommendations = array();
        $current_month = intval(date('n'));

        foreach ($service_patterns as $service_id => $data) {
            $avg_orders = array_sum(array_column($data['months'], 'orders')) / max(1, count($data['months']));

            // Check next month
            $next_month = ($current_month % 12) + 1;
            if (isset($data['months'][$next_month])) {
                $next_orders = $data['months'][$next_month]['orders'];

                if ($next_orders > $avg_orders * 1.3) {
                    $recommendations[] = array(
                        'service_id' => $service_id,
                        'service_name' => $data['name'],
                        'type' => 'increase',
                        'month' => date('F', mktime(0, 0, 0, $next_month, 1)),
                        'expected_demand' => 'high',
                        'suggestion' => 'Consider raising prices - historically high demand period',
                        'historical_increase' => round((($next_orders / $avg_orders) - 1) * 100, 1)
                    );
                } elseif ($next_orders < $avg_orders * 0.7) {
                    $recommendations[] = array(
                        'service_id' => $service_id,
                        'service_name' => $data['name'],
                        'type' => 'decrease',
                        'month' => date('F', mktime(0, 0, 0, $next_month, 1)),
                        'expected_demand' => 'low',
                        'suggestion' => 'Consider promotional pricing - historically low demand period',
                        'historical_decrease' => round((1 - ($next_orders / $avg_orders)) * 100, 1)
                    );
                }
            }
        }

        return $recommendations;
    }

    /**
     * Record price change
     */
    public function record_price_change($service_id, $old_price, $new_price, $reason = '') {
        // Get current conversion rate for comparison later
        $analysis = $this->analyze_service_pricing($service_id);

        return SBHA_Database::insert('price_history', array(
            'service_id' => $service_id,
            'old_price' => $old_price,
            'new_price' => $new_price,
            'change_reason' => $reason,
            'conversion_before' => $analysis['conversion_rate'],
            'changed_by' => get_current_user_id()
        ));
    }

    /**
     * Get price history for service
     */
    public function get_price_history($service_id, $limit = 10) {
        return SBHA_Database::get_results(
            'price_history',
            array('service_id' => $service_id),
            'created_at',
            'DESC',
            $limit
        );
    }

    /**
     * Get overall pricing health
     */
    public function get_pricing_health() {
        global $wpdb;

        $services_table = SBHA_Database::get_table('services');
        $jobs_table = SBHA_Database::get_table('jobs');

        // Get services with pricing issues
        $underpriced = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.base_price,
                AVG(j.total) as avg_order
            FROM $services_table s
            JOIN $jobs_table j ON s.id = j.service_id
            WHERE s.status = 'active' AND j.job_status != 'cancelled'
            GROUP BY s.id
            HAVING avg_order > s.base_price * 1.3
        ", ARRAY_A);

        $low_conversion = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.base_price,
                s.conversion_rate
            FROM $services_table s
            WHERE s.status = 'active'
              AND s.conversion_rate < 0.15
              AND s.popularity_score > 10
        ", ARRAY_A);

        return array(
            'underpriced_services' => $underpriced,
            'low_conversion_services' => $low_conversion,
            'health_score' => $this->calculate_pricing_health_score(count($underpriced), count($low_conversion))
        );
    }

    /**
     * Calculate pricing health score
     */
    private function calculate_pricing_health_score($underpriced_count, $low_conversion_count) {
        $total_services = SBHA_Database::get_count('services', array('status' => 'active'));

        if ($total_services == 0) {
            return 100;
        }

        $issue_rate = ($underpriced_count + $low_conversion_count) / $total_services;

        return max(0, round(100 - ($issue_rate * 100)));
    }
}
