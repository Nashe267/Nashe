<?php
/**
 * AI Engine Class
 *
 * Core AI functionality for learning, predictions, and insights
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_AI_Engine {

    /**
     * Service keywords mapping for intent detection
     */
    private $service_keywords = array();

    /**
     * Sentiment keywords
     */
    private $sentiment_keywords = array(
        'positive' => array('great', 'excellent', 'love', 'perfect', 'amazing', 'wonderful', 'fantastic', 'best', 'awesome', 'happy', 'pleased', 'thanks', 'thank'),
        'negative' => array('bad', 'terrible', 'awful', 'hate', 'worst', 'disappointed', 'frustrated', 'angry', 'problem', 'issue', 'complaint', 'refund', 'cancel'),
        'urgent' => array('urgent', 'asap', 'immediately', 'rush', 'emergency', 'today', 'tomorrow', 'deadline', 'quickly', 'fast', 'hurry')
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_service_keywords();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('sbha_daily_insights_generation', array($this, 'generate_daily_insights'));
        add_action('sbha_hourly_analytics', array($this, 'aggregate_hourly_analytics'));
        add_action('sbha_weekly_reports', array($this, 'generate_weekly_report'));
        add_action('sbha_monthly_ai_training', array($this, 'train_ai_models'));
    }

    /**
     * Load service keywords from database
     */
    private function load_service_keywords() {
        $services = SBHA_Database::get_results('services', array('status' => 'active'));

        foreach ($services as $service) {
            $keywords = array();
            $keywords[] = strtolower($service['name']);
            $keywords[] = strtolower($service['slug']);

            // Extract words from name
            $name_words = explode(' ', strtolower($service['name']));
            $keywords = array_merge($keywords, $name_words);

            // Add common variations
            $variations = $this->get_keyword_variations($service['name']);
            $keywords = array_merge($keywords, $variations);

            $this->service_keywords[$service['id']] = array_unique(array_filter($keywords));
        }
    }

    /**
     * Get keyword variations
     */
    private function get_keyword_variations($name) {
        $variations = array();
        $name_lower = strtolower($name);

        // Common abbreviations and variations
        $mappings = array(
            'business card' => array('biz card', 'b-card', 'visiting card', 'name card'),
            'flyer' => array('flier', 'leaflet', 'pamphlet', 'handbill'),
            'banner' => array('signage', 'sign', 'billboard'),
            'logo' => array('logotype', 'brand mark', 'emblem', 'symbol'),
            't-shirt' => array('tshirt', 'tee', 'shirt', 't shirt'),
            'website' => array('web site', 'site', 'webpage', 'web page'),
            'branding' => array('brand', 'identity', 'corporate identity'),
            'printing' => array('print', 'prints'),
            'design' => array('designing', 'designer'),
            '3d rendering' => array('3d render', 'render', 'visualization', 'viz'),
            'floor plan' => array('floorplan', 'layout', 'blueprint'),
            'poster' => array('placard'),
            'stationery' => array('stationary', 'letterhead'),
            'welcome board' => array('wedding board', 'event board', 'reception board')
        );

        foreach ($mappings as $key => $values) {
            if (strpos($name_lower, $key) !== false) {
                $variations = array_merge($variations, $values);
            }
        }

        return $variations;
    }

    /**
     * Analyze customer query and detect intent
     */
    public function analyze_query($query, $session_id = null, $customer_id = null) {
        $query_lower = strtolower(trim($query));
        $words = preg_split('/\s+/', $query_lower);

        // Detect matched services
        $matched_services = $this->detect_services($query_lower, $words);

        // Analyze sentiment
        $sentiment = $this->analyze_sentiment($words);

        // Extract keywords
        $keywords = $this->extract_keywords($words);

        // Determine primary intent
        $intent = $this->determine_intent($query_lower, $matched_services);

        // Calculate confidence
        $confidence = $this->calculate_confidence($matched_services, $keywords);

        // Log the intention
        $intention_data = array(
            'session_id' => $session_id ?: $this->generate_session_id(),
            'customer_id' => $customer_id,
            'raw_query' => $query,
            'interpreted_intent' => $intent,
            'matched_services' => json_encode($matched_services),
            'confidence_score' => $confidence,
            'sentiment' => $sentiment['type'],
            'sentiment_score' => $sentiment['score'],
            'keywords' => json_encode($keywords),
            'device_type' => $this->detect_device_type(),
            'browser' => $this->detect_browser(),
            'ip_address' => $this->get_client_ip(),
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? sanitize_url($_SERVER['HTTP_REFERER']) : '',
            'page_url' => isset($_SERVER['REQUEST_URI']) ? sanitize_url($_SERVER['REQUEST_URI']) : ''
        );

        $intention_id = SBHA_Database::insert('ai_customer_intentions', $intention_data);

        // Check for service gaps
        if (empty($matched_services) && !empty($keywords)) {
            $this->record_service_gap($keywords, $query);
        }

        return array(
            'intention_id' => $intention_id,
            'intent' => $intent,
            'matched_services' => $matched_services,
            'confidence' => $confidence,
            'sentiment' => $sentiment,
            'keywords' => $keywords,
            'suggestions' => $this->get_query_suggestions($matched_services, $keywords)
        );
    }

    /**
     * Detect services from query
     */
    private function detect_services($query, $words) {
        $matched = array();
        $services = SBHA_Database::get_results('services', array('status' => 'active'));

        foreach ($services as $service) {
            $score = 0;
            $keywords = isset($this->service_keywords[$service['id']]) ? $this->service_keywords[$service['id']] : array();

            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    $score += strlen($keyword); // Longer matches get higher scores
                }
            }

            if ($score > 0) {
                $matched[] = array(
                    'service_id' => $service['id'],
                    'service_name' => $service['name'],
                    'category' => $service['category'],
                    'score' => $score
                );
            }
        }

        // Sort by score descending
        usort($matched, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($matched, 0, 5); // Return top 5 matches
    }

    /**
     * Analyze sentiment
     */
    private function analyze_sentiment($words) {
        $positive_count = 0;
        $negative_count = 0;
        $urgent_count = 0;

        foreach ($words as $word) {
            if (in_array($word, $this->sentiment_keywords['positive'])) {
                $positive_count++;
            }
            if (in_array($word, $this->sentiment_keywords['negative'])) {
                $negative_count++;
            }
            if (in_array($word, $this->sentiment_keywords['urgent'])) {
                $urgent_count++;
            }
        }

        $total = $positive_count + $negative_count;
        if ($total === 0) {
            return array('type' => 'neutral', 'score' => 0.5, 'urgent' => $urgent_count > 0);
        }

        $score = $positive_count / $total;
        $type = $score > 0.6 ? 'positive' : ($score < 0.4 ? 'negative' : 'neutral');

        return array(
            'type' => $type,
            'score' => $score,
            'urgent' => $urgent_count > 0
        );
    }

    /**
     * Extract keywords
     */
    private function extract_keywords($words) {
        // Stop words to filter out
        $stop_words = array(
            'i', 'me', 'my', 'we', 'our', 'you', 'your', 'the', 'a', 'an', 'and', 'or', 'but',
            'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were',
            'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'can', 'need', 'want', 'looking', 'like', 'just',
            'some', 'any', 'this', 'that', 'these', 'those', 'it', 'its', 'get', 'got', 'please',
            'hi', 'hello', 'hey', 'thanks', 'thank', 'help', 'need', 'want', 'looking'
        );

        $keywords = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 2 && !in_array($word, $stop_words);
        });

        return array_values($keywords);
    }

    /**
     * Determine primary intent
     */
    private function determine_intent($query, $matched_services) {
        // Intent patterns
        $patterns = array(
            'inquiry' => array('how much', 'price', 'cost', 'quote', 'rate', 'pricing'),
            'order' => array('order', 'buy', 'purchase', 'get', 'book', 'place order'),
            'custom' => array('custom', 'customize', 'personalize', 'bespoke', 'unique', 'special'),
            'rush' => array('urgent', 'rush', 'asap', 'emergency', 'quickly', 'fast', 'today', 'tomorrow'),
            'bulk' => array('bulk', 'wholesale', 'large quantity', 'many', 'lot of', 'multiple'),
            'support' => array('help', 'support', 'issue', 'problem', 'question', 'status', 'update')
        );

        foreach ($patterns as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        if (!empty($matched_services)) {
            return 'service_inquiry';
        }

        return 'general_inquiry';
    }

    /**
     * Calculate confidence score
     */
    private function calculate_confidence($matched_services, $keywords) {
        if (empty($matched_services) && empty($keywords)) {
            return 0.1;
        }

        $score = 0;

        // Score based on matched services
        if (!empty($matched_services)) {
            $top_score = $matched_services[0]['score'];
            $score += min(0.5, $top_score * 0.1);
        }

        // Score based on keyword count
        $keyword_count = count($keywords);
        $score += min(0.3, $keyword_count * 0.05);

        // Bonus for multiple service matches
        if (count($matched_services) > 1) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * Get suggestions based on query
     */
    private function get_query_suggestions($matched_services, $keywords) {
        $suggestions = array();

        if (!empty($matched_services)) {
            $primary_service_id = $matched_services[0]['service_id'];

            // Get related services
            $related = $this->get_related_services($primary_service_id, 3);
            foreach ($related as $service) {
                $suggestions[] = array(
                    'type' => 'related_service',
                    'service_id' => $service['id'],
                    'service_name' => $service['name'],
                    'message' => sprintf('Customers who order %s often also get %s',
                        $matched_services[0]['service_name'],
                        $service['name']
                    )
                );
            }
        }

        return $suggestions;
    }

    /**
     * Get related services based on patterns
     */
    public function get_related_services($service_id, $limit = 5) {
        global $wpdb;

        $patterns_table = SBHA_Database::get_table('ai_service_patterns');
        $services_table = SBHA_Database::get_table('services');

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT s.*, p.pairing_confidence, p.pairing_count
            FROM $patterns_table p
            JOIN $services_table s ON p.paired_service_id = s.id
            WHERE p.service_id = %d AND s.status = 'active'
            ORDER BY p.pairing_confidence DESC, p.pairing_count DESC
            LIMIT %d
        ", $service_id, $limit), ARRAY_A);

        return $results;
    }

    /**
     * Record service gap
     */
    private function record_service_gap($keywords, $query) {
        global $wpdb;

        $keyword_string = implode(' ', $keywords);
        $gaps_table = SBHA_Database::get_table('service_gaps');

        // Check if gap already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $gaps_table WHERE keyword = %s",
            $keyword_string
        ));

        if ($existing) {
            // Update count and add sample query
            $sample_queries = json_decode($existing->sample_queries, true) ?: array();
            if (count($sample_queries) < 10) {
                $sample_queries[] = $query;
            }

            $wpdb->update($gaps_table,
                array(
                    'request_count' => $existing->request_count + 1,
                    'sample_queries' => json_encode($sample_queries)
                ),
                array('id' => $existing->id)
            );
        } else {
            // Create new gap record
            SBHA_Database::insert('service_gaps', array(
                'keyword' => $keyword_string,
                'request_count' => 1,
                'sample_queries' => json_encode(array($query)),
                'estimated_demand_score' => 1.0
            ));
        }
    }

    /**
     * Update service pairing patterns
     */
    public function record_service_pairing($service_ids) {
        if (count($service_ids) < 2) {
            return;
        }

        // Record all pairs
        for ($i = 0; $i < count($service_ids); $i++) {
            for ($j = $i + 1; $j < count($service_ids); $j++) {
                $this->update_pairing($service_ids[$i], $service_ids[$j]);
                $this->update_pairing($service_ids[$j], $service_ids[$i]);
            }
        }
    }

    /**
     * Update pairing record
     */
    private function update_pairing($service_id, $paired_id) {
        global $wpdb;
        $table = SBHA_Database::get_table('ai_service_patterns');

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE service_id = %d AND paired_service_id = %d",
            $service_id, $paired_id
        ));

        if ($existing) {
            $wpdb->update($table,
                array('pairing_count' => $existing->pairing_count + 1),
                array('id' => $existing->id)
            );
        } else {
            SBHA_Database::insert('ai_service_patterns', array(
                'service_id' => $service_id,
                'paired_service_id' => $paired_id,
                'pairing_count' => 1
            ));
        }

        // Recalculate confidence
        $this->recalculate_pairing_confidence($service_id);
    }

    /**
     * Recalculate pairing confidence
     */
    private function recalculate_pairing_confidence($service_id) {
        global $wpdb;
        $table = SBHA_Database::get_table('ai_service_patterns');

        // Get total pairings for this service
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pairing_count) FROM $table WHERE service_id = %d",
            $service_id
        ));

        if ($total > 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET pairing_confidence = pairing_count / %f WHERE service_id = %d",
                $total, $service_id
            ));
        }
    }

    /**
     * Predict job completion time
     */
    public function predict_job_completion($service_id, $complexity = 'normal') {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');

        // Get average completion time for similar jobs
        $avg_time = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, actual_completion))
            FROM $jobs_table
            WHERE service_id = %d
              AND job_status = 'completed'
              AND actual_completion IS NOT NULL
        ", $service_id));

        // Default times by complexity if no data
        $default_times = array(
            'simple' => 24,
            'normal' => 48,
            'complex' => 96
        );

        $base_hours = $avg_time ?: ($default_times[$complexity] ?? 48);

        // Apply complexity multiplier
        $multipliers = array(
            'simple' => 0.5,
            'normal' => 1.0,
            'complex' => 2.0
        );

        $predicted_hours = $base_hours * ($multipliers[$complexity] ?? 1.0);

        return array(
            'hours' => round($predicted_hours),
            'date' => date('Y-m-d H:i:s', strtotime("+$predicted_hours hours")),
            'confidence' => $avg_time ? 0.8 : 0.5
        );
    }

    /**
     * Predict job issues
     */
    public function predict_job_issues($service_id, $job_data = array()) {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $issues = array();

        // Check revision rate for similar jobs
        $avg_revisions = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(revision_count)
            FROM $jobs_table
            WHERE service_id = %d AND job_status = 'completed'
        ", $service_id));

        if ($avg_revisions > 1.5) {
            $issues[] = array(
                'type' => 'high_revision_rate',
                'severity' => 'warning',
                'message' => sprintf('This service type typically requires %.1f revisions on average', $avg_revisions),
                'suggestion' => 'Consider clarifying requirements upfront'
            );
        }

        // Check for file format issues (if category is architectural)
        $service = SBHA_Database::get_by_id('services', $service_id);
        if ($service && $service['category'] === 'architectural') {
            $issues[] = array(
                'type' => 'file_format',
                'severity' => 'info',
                'message' => '70% of architectural jobs require file format conversion',
                'suggestion' => 'Offer file conversion service as an add-on'
            );
        }

        return $issues;
    }

    /**
     * Generate daily insights
     */
    public function generate_daily_insights() {
        $insights = array();

        // Top services insight
        $top_services = $this->get_top_services_today();
        if (!empty($top_services)) {
            $insights[] = array(
                'type' => 'trending_services',
                'title' => 'Top Requested Services Today',
                'data' => $top_services,
                'severity' => 'info'
            );
        }

        // Service gaps
        $gaps = $this->get_significant_gaps();
        foreach ($gaps as $gap) {
            $insights[] = array(
                'type' => 'service_gap',
                'title' => sprintf('New Service Opportunity: %s', $gap['keyword']),
                'data' => $gap,
                'severity' => 'medium',
                'action_required' => true
            );
        }

        // Price optimization suggestions
        $price_suggestions = $this->get_price_suggestions();
        foreach ($price_suggestions as $suggestion) {
            $insights[] = array(
                'type' => 'price_optimization',
                'title' => $suggestion['title'],
                'data' => $suggestion,
                'severity' => 'low',
                'action_required' => false
            );
        }

        // Seasonal trends
        $seasonal = $this->detect_seasonal_trends();
        if (!empty($seasonal)) {
            $insights[] = array(
                'type' => 'seasonal_trend',
                'title' => 'Seasonal Trend Detected',
                'data' => $seasonal,
                'severity' => 'info'
            );
        }

        // Save insights
        foreach ($insights as $insight) {
            SBHA_Database::insert('ai_business_insights', array(
                'insight_type' => $insight['type'],
                'insight_title' => $insight['title'],
                'insight_data' => json_encode($insight['data']),
                'severity' => $insight['severity'],
                'action_required' => isset($insight['action_required']) ? $insight['action_required'] : 0
            ));
        }

        return $insights;
    }

    /**
     * Get top services today
     */
    private function get_top_services_today() {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');

        $results = $wpdb->get_results("
            SELECT interpreted_intent, COUNT(*) as count
            FROM $intentions_table
            WHERE DATE(created_at) = CURDATE()
            GROUP BY interpreted_intent
            ORDER BY count DESC
            LIMIT 5
        ", ARRAY_A);

        return $results;
    }

    /**
     * Get significant service gaps
     */
    private function get_significant_gaps() {
        return SBHA_Database::get_results('service_gaps',
            array('status' => 'identified'),
            'request_count',
            'DESC',
            5
        );
    }

    /**
     * Get price suggestions
     */
    private function get_price_suggestions() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        // Find high-demand services with high conversion
        $suggestions = $wpdb->get_results("
            SELECT s.id, s.name, s.base_price,
                   COUNT(j.id) as order_count,
                   AVG(j.total) as avg_order_value
            FROM $services_table s
            LEFT JOIN $jobs_table j ON s.id = j.service_id
            WHERE j.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY s.id
            HAVING order_count > 5 AND avg_order_value > s.base_price * 1.2
            ORDER BY order_count DESC
            LIMIT 3
        ", ARRAY_A);

        return array_map(function($s) {
            return array(
                'service_id' => $s['id'],
                'service_name' => $s['name'],
                'current_price' => $s['base_price'],
                'suggested_price' => round($s['avg_order_value'] * 0.9, 2),
                'title' => sprintf('Consider raising %s base price', $s['name'])
            );
        }, $suggestions);
    }

    /**
     * Detect seasonal trends
     */
    private function detect_seasonal_trends() {
        global $wpdb;

        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');

        // Compare this week to same week last month
        $current = $wpdb->get_results("
            SELECT interpreted_intent, COUNT(*) as count
            FROM $intentions_table
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY interpreted_intent
        ", ARRAY_A);

        $previous = $wpdb->get_results("
            SELECT interpreted_intent, COUNT(*) as count
            FROM $intentions_table
            WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 37 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY interpreted_intent
        ", ARRAY_A);

        // Calculate growth
        $previous_map = array();
        foreach ($previous as $p) {
            $previous_map[$p['interpreted_intent']] = $p['count'];
        }

        $trends = array();
        foreach ($current as $c) {
            $prev_count = isset($previous_map[$c['interpreted_intent']]) ? $previous_map[$c['interpreted_intent']] : 0;
            if ($prev_count > 0) {
                $growth = (($c['count'] - $prev_count) / $prev_count) * 100;
                if (abs($growth) > 25) {
                    $trends[] = array(
                        'intent' => $c['interpreted_intent'],
                        'current_count' => $c['count'],
                        'previous_count' => $prev_count,
                        'growth' => round($growth, 1)
                    );
                }
            }
        }

        return $trends;
    }

    /**
     * Aggregate hourly analytics
     */
    public function aggregate_hourly_analytics() {
        $date = current_time('Y-m-d');

        // Aggregate various metrics
        $metrics = array(
            'page_views' => $this->count_page_views_today(),
            'inquiries' => SBHA_Database::get_count('ai_customer_intentions', array()),
            'jobs_created' => SBHA_Database::get_count('jobs', array()),
            'revenue' => SBHA_Database::get_sum('jobs', 'total', array('payment_status' => 'paid'))
        );

        foreach ($metrics as $type => $value) {
            $this->update_daily_metric($date, $type, $value);
        }
    }

    /**
     * Count page views today
     */
    private function count_page_views_today() {
        global $wpdb;
        $table = SBHA_Database::get_table('ai_conversion_funnel');
        return (int) $wpdb->get_var("
            SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()
        ");
    }

    /**
     * Update daily metric
     */
    private function update_daily_metric($date, $type, $value) {
        global $wpdb;
        $table = SBHA_Database::get_table('analytics_daily');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE date = %s AND metric_type = %s",
            $date, $type
        ));

        if ($existing) {
            $wpdb->update($table,
                array('metric_value' => $value),
                array('id' => $existing)
            );
        } else {
            SBHA_Database::insert('analytics_daily', array(
                'date' => $date,
                'metric_type' => $type,
                'metric_value' => $value
            ));
        }
    }

    /**
     * Generate weekly report
     */
    public function generate_weekly_report() {
        $report = array(
            'period' => array(
                'start' => date('Y-m-d', strtotime('-7 days')),
                'end' => date('Y-m-d')
            ),
            'summary' => $this->get_weekly_summary(),
            'top_services' => $this->get_top_services_week(),
            'customer_insights' => $this->get_customer_insights_week(),
            'recommendations' => $this->generate_recommendations()
        );

        // Store report as insight
        SBHA_Database::insert('ai_business_insights', array(
            'insight_type' => 'weekly_report',
            'insight_title' => sprintf('Weekly Report: %s to %s', $report['period']['start'], $report['period']['end']),
            'insight_data' => json_encode($report),
            'severity' => 'info',
            'category' => 'report'
        ));

        // Send email notification if enabled
        if (get_option('sbha_email_notifications')) {
            $this->send_weekly_report_email($report);
        }

        return $report;
    }

    /**
     * Get weekly summary
     */
    private function get_weekly_summary() {
        global $wpdb;
        $jobs_table = SBHA_Database::get_table('jobs');

        $summary = $wpdb->get_row("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
                AVG(total) as avg_order_value
            FROM $jobs_table
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", ARRAY_A);

        return $summary;
    }

    /**
     * Get top services for the week
     */
    private function get_top_services_week() {
        global $wpdb;
        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        return $wpdb->get_results("
            SELECT s.name, COUNT(j.id) as count, SUM(j.total) as revenue
            FROM $jobs_table j
            JOIN $services_table s ON j.service_id = s.id
            WHERE j.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY s.id
            ORDER BY count DESC
            LIMIT 5
        ", ARRAY_A);
    }

    /**
     * Get customer insights for the week
     */
    private function get_customer_insights_week() {
        global $wpdb;
        $customers_table = SBHA_Database::get_table('customers');

        return array(
            'new_customers' => $wpdb->get_var("
                SELECT COUNT(*) FROM $customers_table
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            "),
            'repeat_customers' => $wpdb->get_var("
                SELECT COUNT(*) FROM $customers_table
                WHERE total_orders > 1 AND last_order_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ")
        );
    }

    /**
     * Generate recommendations
     */
    private function generate_recommendations() {
        $recommendations = array();

        // Check service gaps
        $gaps = SBHA_Database::get_results('service_gaps',
            array('status' => 'identified'),
            'request_count',
            'DESC',
            3
        );

        foreach ($gaps as $gap) {
            if ($gap['request_count'] >= 5) {
                $recommendations[] = array(
                    'type' => 'new_service',
                    'priority' => 'high',
                    'message' => sprintf('%d customers have asked for "%s"', $gap['request_count'], $gap['keyword']),
                    'action' => 'Consider adding this service to your offerings'
                );
            }
        }

        return $recommendations;
    }

    /**
     * Send weekly report email
     */
    private function send_weekly_report_email($report) {
        $to = get_option('sbha_business_email');
        $subject = sprintf('[Switch Business Hub] Weekly Report - %s', $report['period']['end']);

        $message = $this->format_report_email($report);

        wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }

    /**
     * Format report email
     */
    private function format_report_email($report) {
        ob_start();
        ?>
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h1 style="color: #FF6600;">Weekly Business Report</h1>
            <p>Period: <?php echo esc_html($report['period']['start']); ?> to <?php echo esc_html($report['period']['end']); ?></p>

            <h2>Summary</h2>
            <ul>
                <li>Total Jobs: <?php echo esc_html($report['summary']['total_jobs']); ?></li>
                <li>Completed: <?php echo esc_html($report['summary']['completed_jobs']); ?></li>
                <li>Revenue: $<?php echo number_format($report['summary']['total_revenue'], 2); ?></li>
                <li>Avg Order: $<?php echo number_format($report['summary']['avg_order_value'], 2); ?></li>
            </ul>

            <?php if (!empty($report['recommendations'])): ?>
            <h2>Recommendations</h2>
            <ul>
                <?php foreach ($report['recommendations'] as $rec): ?>
                <li><strong><?php echo esc_html($rec['message']); ?></strong><br>
                    <?php echo esc_html($rec['action']); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <p style="color: #666; font-size: 12px;">
                This report was generated by Switch Business Hub AI
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Train AI models (monthly task)
     */
    public function train_ai_models() {
        // Recalculate all pairing confidences
        $this->recalculate_all_pairings();

        // Update service popularity scores
        $this->update_popularity_scores();

        // Recalculate customer scores
        $this->recalculate_customer_scores();

        // Update demand forecasts
        $this->update_demand_forecasts();

        // Log training completion
        SBHA_Database::insert('ai_business_insights', array(
            'insight_type' => 'ai_training',
            'insight_title' => 'Monthly AI Model Training Completed',
            'insight_data' => json_encode(array(
                'date' => current_time('Y-m-d H:i:s'),
                'status' => 'completed'
            )),
            'severity' => 'info'
        ));
    }

    /**
     * Recalculate all pairings
     */
    private function recalculate_all_pairings() {
        $services = SBHA_Database::get_results('services', array('status' => 'active'));
        foreach ($services as $service) {
            $this->recalculate_pairing_confidence($service['id']);
        }
    }

    /**
     * Update popularity scores
     */
    private function update_popularity_scores() {
        global $wpdb;

        $jobs_table = SBHA_Database::get_table('jobs');
        $services_table = SBHA_Database::get_table('services');

        $wpdb->query("
            UPDATE $services_table s
            SET popularity_score = (
                SELECT COUNT(*)
                FROM $jobs_table j
                WHERE j.service_id = s.id
                  AND j.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
            )
        ");
    }

    /**
     * Recalculate customer scores
     */
    private function recalculate_customer_scores() {
        global $wpdb;

        $customers_table = SBHA_Database::get_table('customers');
        $jobs_table = SBHA_Database::get_table('jobs');

        // Update customer scores based on lifetime value and frequency
        $wpdb->query("
            UPDATE $customers_table c
            SET customer_score = (
                SELECT COALESCE(
                    (COUNT(j.id) * 10) +
                    (SUM(j.total) / 100) +
                    (CASE WHEN MAX(j.created_at) > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 20 ELSE 0 END),
                    0
                )
                FROM $jobs_table j
                WHERE j.customer_id = c.id
            )
        ");
    }

    /**
     * Update demand forecasts
     */
    private function update_demand_forecasts() {
        // Calculate demand trends for service gaps
        $gaps = SBHA_Database::get_results('service_gaps', array('status' => 'identified'));

        foreach ($gaps as $gap) {
            $demand_score = $this->calculate_demand_score($gap);
            SBHA_Database::update('service_gaps',
                array('estimated_demand_score' => $demand_score),
                array('id' => $gap['id'])
            );
        }
    }

    /**
     * Calculate demand score for a service gap
     */
    private function calculate_demand_score($gap) {
        // Score based on request count, recency, and growth
        $base_score = min(100, $gap['request_count'] * 5);

        // Check growth trend
        $days_old = max(1, (time() - strtotime($gap['created_at'])) / 86400);
        $daily_rate = $gap['request_count'] / $days_old;

        $growth_factor = min(2, 1 + ($daily_rate * 0.1));

        return round($base_score * $growth_factor, 2);
    }

    /**
     * Helper functions
     */
    private function generate_session_id() {
        return 'sess_' . bin2hex(random_bytes(16));
    }

    private function detect_device_type() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return 'unknown';
        }

        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false) {
            return 'mobile';
        }
        if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
            return 'tablet';
        }

        return 'desktop';
    }

    private function detect_browser() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return 'unknown';
        }

        $ua = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($ua, 'Firefox') !== false) return 'Firefox';
        if (strpos($ua, 'Chrome') !== false) return 'Chrome';
        if (strpos($ua, 'Safari') !== false) return 'Safari';
        if (strpos($ua, 'Edge') !== false) return 'Edge';
        if (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) return 'IE';

        return 'Other';
    }

    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Get customer lifetime value prediction
     */
    public function predict_customer_ltv($customer_id) {
        $customer = SBHA_Database::get_by_id('customers', $customer_id);
        if (!$customer) {
            return 0;
        }

        // Simple LTV prediction based on:
        // - Current lifetime value
        // - Order frequency
        // - Recency
        $ltv = $customer['lifetime_value'];
        $order_count = $customer['total_orders'];
        $avg_order = $customer['average_order_value'];

        if ($order_count < 2) {
            // New customer - predict based on first order
            return $avg_order * 3; // Assume 3 orders over lifetime
        }

        // Calculate average days between orders
        $first_order = SBHA_Database::query(
            "SELECT MIN(created_at) as first_date FROM " . SBHA_Database::get_table('jobs') . " WHERE customer_id = %d",
            array($customer_id)
        );

        if (!empty($first_order) && $first_order[0]['first_date']) {
            $days_active = max(1, (time() - strtotime($first_order[0]['first_date'])) / 86400);
            $order_frequency = $order_count / $days_active;

            // Project forward 2 years
            $predicted_orders = $order_frequency * 730;
            return round($avg_order * $predicted_orders, 2);
        }

        return $ltv * 2; // Default: double current LTV
    }

    /**
     * Get churn risk for customer
     */
    public function calculate_churn_risk($customer_id) {
        $customer = SBHA_Database::get_by_id('customers', $customer_id);
        if (!$customer) {
            return 1.0; // High risk if not found
        }

        $last_order = $customer['last_order_date'];
        if (!$last_order) {
            return 0.5; // Medium risk for never-ordered
        }

        $days_since_order = (time() - strtotime($last_order)) / 86400;

        // Risk increases with time since last order
        if ($days_since_order < 30) return 0.1;
        if ($days_since_order < 60) return 0.2;
        if ($days_since_order < 90) return 0.4;
        if ($days_since_order < 180) return 0.6;
        if ($days_since_order < 365) return 0.8;

        return 0.95;
    }

    /**
     * Get bundle recommendations
     */
    public function get_bundle_recommendations($service_id, $limit = 3) {
        $related = $this->get_related_services($service_id, $limit);

        return array_map(function($service) {
            return array(
                'service_id' => $service['id'],
                'service_name' => $service['name'],
                'base_price' => $service['base_price'],
                'confidence' => $service['pairing_confidence'],
                'suggested_discount' => $this->calculate_bundle_discount($service['pairing_confidence'])
            );
        }, $related);
    }

    /**
     * Calculate bundle discount based on pairing confidence
     */
    private function calculate_bundle_discount($confidence) {
        // Higher confidence = better discount
        if ($confidence > 0.7) return 25;
        if ($confidence > 0.5) return 20;
        if ($confidence > 0.3) return 15;
        return 10;
    }
}
