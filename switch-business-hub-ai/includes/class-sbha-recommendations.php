<?php
/**
 * Recommendations Engine Class
 *
 * AI-powered product and service recommendations
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Recommendations {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize
    }

    /**
     * Get recommendations for a service
     */
    public function get_service_recommendations($service_id, $limit = 4) {
        $recommendations = array();

        // Get AI-based pairings
        $ai_recommendations = $this->get_ai_recommendations($service_id, $limit);
        $recommendations = array_merge($recommendations, $ai_recommendations);

        // Get category-based recommendations if not enough
        if (count($recommendations) < $limit) {
            $category_recs = $this->get_category_recommendations($service_id, $limit - count($recommendations));
            $recommendations = array_merge($recommendations, $category_recs);
        }

        // Get popular services if still not enough
        if (count($recommendations) < $limit) {
            $popular_recs = $this->get_popular_recommendations($service_id, $limit - count($recommendations));
            $recommendations = array_merge($recommendations, $popular_recs);
        }

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Get AI-based recommendations
     */
    private function get_ai_recommendations($service_id, $limit) {
        global $wpdb;

        $patterns_table = SBHA_Database::get_table('ai_service_patterns');
        $services_table = SBHA_Database::get_table('services');

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.*,
                p.pairing_confidence,
                p.pairing_count,
                'ai' as recommendation_type,
                ROUND(p.pairing_confidence * 100) as match_score
            FROM $patterns_table p
            JOIN $services_table s ON p.paired_service_id = s.id
            WHERE p.service_id = %d
              AND s.status = 'active'
              AND p.pairing_confidence > 0.1
            ORDER BY p.pairing_confidence DESC, p.pairing_count DESC
            LIMIT %d
        ", $service_id, $limit), ARRAY_A);

        return array_map(function($r) {
            $r['reason'] = sprintf(
                '%d%% of customers also ordered this (%d orders together)',
                $r['match_score'],
                $r['pairing_count']
            );
            return $r;
        }, $results);
    }

    /**
     * Get category-based recommendations
     */
    private function get_category_recommendations($service_id, $limit) {
        $service = SBHA_Database::get_by_id('services', $service_id);
        if (!$service) {
            return array();
        }

        global $wpdb;
        $table = SBHA_Database::get_table('services');

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT *,
                   'category' as recommendation_type,
                   50 as match_score
            FROM $table
            WHERE category = %s
              AND id != %d
              AND status = 'active'
            ORDER BY popularity_score DESC
            LIMIT %d
        ", $service['category'], $service_id, $limit), ARRAY_A);

        return array_map(function($r) use ($service) {
            $r['reason'] = 'Related ' . ucfirst($service['category']) . ' service';
            return $r;
        }, $results);
    }

    /**
     * Get popular recommendations
     */
    private function get_popular_recommendations($exclude_service_id, $limit) {
        global $wpdb;
        $table = SBHA_Database::get_table('services');

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT *,
                   'popular' as recommendation_type,
                   30 as match_score
            FROM $table
            WHERE id != %d
              AND status = 'active'
              AND is_popular = 1
            ORDER BY popularity_score DESC
            LIMIT %d
        ", $exclude_service_id, $limit), ARRAY_A);

        return array_map(function($r) {
            $r['reason'] = 'Popular choice among customers';
            return $r;
        }, $results);
    }

    /**
     * Get customer recommendations
     */
    public function get_customer_recommendations($customer_id, $limit = 4) {
        $recommendations = array();

        // Get recommendations based on order history
        $history_recs = $this->get_history_based_recommendations($customer_id, $limit);
        $recommendations = array_merge($recommendations, $history_recs);

        // Get recommendations based on similar customers
        if (count($recommendations) < $limit) {
            $similar_recs = $this->get_similar_customer_recommendations($customer_id, $limit - count($recommendations));
            $recommendations = array_merge($recommendations, $similar_recs);
        }

        // Add popular services if not enough
        if (count($recommendations) < $limit) {
            $popular = $this->get_popular_recommendations(0, $limit - count($recommendations));
            $recommendations = array_merge($recommendations, $popular);
        }

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Get recommendations based on customer order history
     */
    private function get_history_based_recommendations($customer_id, $limit) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $patterns_table = SBHA_Database::get_table('ai_service_patterns');
        $services_table = SBHA_Database::get_table('services');

        // Get services the customer has ordered
        $ordered_services = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT service_id FROM $jobs_table WHERE customer_id = %d AND service_id IS NOT NULL",
            $customer_id
        ));

        if (empty($ordered_services)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ordered_services), '%d'));

        // Find services commonly paired with ordered services that customer hasn't ordered
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.*,
                SUM(p.pairing_confidence) as total_confidence,
                COUNT(*) as connection_count,
                'history' as recommendation_type
            FROM $patterns_table p
            JOIN $services_table s ON p.paired_service_id = s.id
            WHERE p.service_id IN ($placeholders)
              AND p.paired_service_id NOT IN ($placeholders)
              AND s.status = 'active'
            GROUP BY s.id
            ORDER BY total_confidence DESC
            LIMIT %d
        ", array_merge($ordered_services, $ordered_services, array($limit))), ARRAY_A);

        return array_map(function($r) {
            $r['reason'] = 'Based on your order history';
            $r['match_score'] = min(95, round(floatval($r['total_confidence']) * 100));
            return $r;
        }, $results);
    }

    /**
     * Get recommendations based on similar customers
     */
    private function get_similar_customer_recommendations($customer_id, $limit) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        // Get customer's service IDs
        $customer_services = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT service_id FROM $jobs_table WHERE customer_id = %d AND service_id IS NOT NULL",
            $customer_id
        ));

        if (empty($customer_services)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($customer_services), '%d'));

        // Find customers with similar orders
        $similar_customers = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT customer_id
            FROM $jobs_table
            WHERE service_id IN ($placeholders)
              AND customer_id != %d
            GROUP BY customer_id
            HAVING COUNT(DISTINCT service_id) >= 2
        ", array_merge($customer_services, array($customer_id))));

        if (empty($similar_customers)) {
            return array();
        }

        $customer_placeholders = implode(',', array_fill(0, count($similar_customers), '%d'));
        $service_placeholders = implode(',', array_fill(0, count($customer_services), '%d'));

        // Find services those customers ordered that this customer hasn't
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.*,
                COUNT(DISTINCT j.customer_id) as customer_count,
                'similar_customers' as recommendation_type
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE j.customer_id IN ($customer_placeholders)
              AND j.service_id NOT IN ($service_placeholders)
              AND s.status = 'active'
            GROUP BY s.id
            ORDER BY customer_count DESC
            LIMIT %d
        ", array_merge($similar_customers, $customer_services, array($limit))), ARRAY_A);

        return array_map(function($r) {
            $r['reason'] = 'Similar customers also ordered this';
            $r['match_score'] = min(80, 40 + intval($r['customer_count']) * 10);
            return $r;
        }, $results);
    }

    /**
     * Get bundle recommendations
     */
    public function get_bundle_recommendations($service_ids, $max_suggestions = 3) {
        if (!is_array($service_ids)) {
            $service_ids = array($service_ids);
        }

        $bundles = array();

        foreach ($service_ids as $service_id) {
            $related = $this->get_ai_recommendations($service_id, 2);
            foreach ($related as $rec) {
                if (!in_array($rec['id'], $service_ids)) {
                    $bundles[$rec['id']] = array(
                        'service' => $rec,
                        'discount' => $this->calculate_bundle_discount($rec['pairing_confidence'] ?? 0.3),
                        'message' => sprintf('Add %s and save %d%%!', $rec['name'], $this->calculate_bundle_discount($rec['pairing_confidence'] ?? 0.3))
                    );
                }
            }
        }

        return array_slice(array_values($bundles), 0, $max_suggestions);
    }

    /**
     * Calculate bundle discount based on confidence
     */
    private function calculate_bundle_discount($confidence) {
        $confidence = floatval($confidence);

        if ($confidence >= 0.7) return 25;
        if ($confidence >= 0.5) return 20;
        if ($confidence >= 0.3) return 15;
        return 10;
    }

    /**
     * Get upsell recommendations for a job
     */
    public function get_job_upsells($job_id) {
        $job = SBHA_Database::get_by_id('jobs', $job_id);
        if (!$job || empty($job['service_id'])) {
            return array();
        }

        $recommendations = $this->get_service_recommendations($job['service_id'], 3);

        return array_map(function($rec) {
            return array(
                'service_id' => $rec['id'],
                'service_name' => $rec['name'],
                'price' => $rec['base_price'],
                'reason' => $rec['reason'],
                'discount' => $this->calculate_bundle_discount($rec['pairing_confidence'] ?? 0.3)
            );
        }, $recommendations);
    }

    /**
     * Get cross-sell recommendations during checkout
     */
    public function get_checkout_recommendations($cart_service_ids) {
        if (empty($cart_service_ids)) {
            return $this->get_popular_recommendations(0, 3);
        }

        $all_recommendations = array();

        foreach ($cart_service_ids as $service_id) {
            $recs = $this->get_ai_recommendations($service_id, 2);
            foreach ($recs as $rec) {
                if (!in_array($rec['id'], $cart_service_ids)) {
                    $key = $rec['id'];
                    if (!isset($all_recommendations[$key])) {
                        $all_recommendations[$key] = $rec;
                        $all_recommendations[$key]['connection_count'] = 0;
                    }
                    $all_recommendations[$key]['connection_count']++;
                }
            }
        }

        // Sort by connection count
        usort($all_recommendations, function($a, $b) {
            return $b['connection_count'] - $a['connection_count'];
        });

        return array_slice($all_recommendations, 0, 3);
    }

    /**
     * Get personalized recommendations for homepage
     */
    public function get_homepage_recommendations($customer_id = null) {
        // If customer is logged in, get personalized recommendations
        if ($customer_id) {
            $personal = $this->get_customer_recommendations($customer_id, 4);
            if (!empty($personal)) {
                return array(
                    'type' => 'personalized',
                    'title' => 'Recommended for You',
                    'items' => $personal
                );
            }
        }

        // Fall back to popular/featured services
        $featured = SBHA_Database::get_results('services',
            array('status' => 'active', 'is_featured' => 1),
            'popularity_score',
            'DESC',
            4
        );

        if (!empty($featured)) {
            return array(
                'type' => 'featured',
                'title' => 'Featured Services',
                'items' => $featured
            );
        }

        $popular = SBHA_Database::get_results('services',
            array('status' => 'active', 'is_popular' => 1),
            'popularity_score',
            'DESC',
            4
        );

        return array(
            'type' => 'popular',
            'title' => 'Popular Services',
            'items' => $popular
        );
    }

    /**
     * Track recommendation interaction
     */
    public function track_recommendation_click($service_id, $source, $session_id = null) {
        global $wpdb;

        $table = SBHA_Database::get_table('ai_conversion_funnel');

        return SBHA_Database::insert('ai_conversion_funnel', array(
            'session_id' => $session_id ?: $this->generate_session_id(),
            'funnel_stage' => 'service_view',
            'service_id' => $service_id,
            'page_data' => json_encode(array(
                'source' => $source,
                'type' => 'recommendation_click',
                'timestamp' => current_time('mysql')
            ))
        ));
    }

    /**
     * Get recommendation performance stats
     */
    public function get_recommendation_stats($days = 30) {
        global $wpdb;

        $funnel_table = SBHA_Database::get_table('ai_conversion_funnel');
        $jobs_table = SBHA_Database::get_table('jobs');

        // Count recommendation clicks
        $clicks = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $funnel_table
            WHERE page_data LIKE '%recommendation_click%'
              AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));

        // Count conversions from recommendations
        // This is simplified - in production you'd track the full funnel
        $conversions = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT j.id)
            FROM $jobs_table j
            JOIN $funnel_table f ON f.service_id = j.service_id
                AND f.created_at < j.created_at
                AND f.created_at >= DATE_SUB(j.created_at, INTERVAL 1 DAY)
            WHERE f.page_data LIKE '%recommendation_click%'
              AND j.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));

        return array(
            'total_clicks' => intval($clicks),
            'conversions' => intval($conversions),
            'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0
        );
    }

    /**
     * Generate session ID
     */
    private function generate_session_id() {
        if (isset($_COOKIE['sbha_session'])) {
            return sanitize_text_field($_COOKIE['sbha_session']);
        }

        return 'sess_' . bin2hex(random_bytes(16));
    }
}
