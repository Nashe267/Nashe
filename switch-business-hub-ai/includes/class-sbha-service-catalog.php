<?php
/**
 * Service Catalog Class
 *
 * Manages services and packages
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Service_Catalog {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize
    }

    /**
     * Get all services
     */
    public function get_services($args = array()) {
        $defaults = array(
            'status' => 'active',
            'category' => '',
            'featured' => null,
            'popular' => null,
            'orderby' => 'display_order',
            'order' => 'ASC',
            'limit' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $conditions = array();
        if (!empty($args['status'])) {
            $conditions['status'] = $args['status'];
        }
        if (!empty($args['category'])) {
            $conditions['category'] = $args['category'];
        }
        if ($args['featured'] !== null) {
            $conditions['is_featured'] = $args['featured'] ? 1 : 0;
        }
        if ($args['popular'] !== null) {
            $conditions['is_popular'] = $args['popular'] ? 1 : 0;
        }

        return SBHA_Database::get_results(
            'services',
            $conditions,
            $args['orderby'],
            $args['order'],
            $args['limit']
        );
    }

    /**
     * Get single service
     */
    public function get_service($id) {
        $service = SBHA_Database::get_by_id('services', $id);
        if ($service) {
            $service['features'] = json_decode($service['features'], true) ?: array();
            $service['gallery'] = json_decode($service['gallery'], true) ?: array();
            $service['packages'] = $this->get_service_packages($id);
        }
        return $service;
    }

    /**
     * Get service by slug
     */
    public function get_service_by_slug($slug) {
        $services = SBHA_Database::get_results('services', array('slug' => $slug), 'id', 'ASC', 1);
        if (!empty($services)) {
            return $this->get_service($services[0]['id']);
        }
        return null;
    }

    /**
     * Get service packages
     */
    public function get_service_packages($service_id) {
        $packages = SBHA_Database::get_results(
            'service_packages',
            array('service_id' => $service_id),
            'display_order',
            'ASC'
        );

        return array_map(function($package) {
            $package['features'] = json_decode($package['features'], true) ?: array();
            return $package;
        }, $packages);
    }

    /**
     * Create service
     */
    public function create_service($data) {
        $defaults = array(
            'name' => '',
            'slug' => '',
            'category' => '',
            'description' => '',
            'short_description' => '',
            'base_price' => 0,
            'price_type' => 'fixed',
            'features' => array(),
            'image_url' => '',
            'gallery' => array(),
            'is_popular' => 0,
            'is_featured' => 0,
            'display_order' => 0,
            'status' => 'active'
        );

        $data = wp_parse_args($data, $defaults);

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }

        // Ensure unique slug
        $data['slug'] = $this->ensure_unique_slug($data['slug']);

        // JSON encode arrays
        $data['features'] = json_encode($data['features']);
        $data['gallery'] = json_encode($data['gallery']);

        return SBHA_Database::insert('services', $data);
    }

    /**
     * Update service
     */
    public function update_service($id, $data) {
        // JSON encode arrays if present
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        if (isset($data['gallery']) && is_array($data['gallery'])) {
            $data['gallery'] = json_encode($data['gallery']);
        }

        return SBHA_Database::update('services', $data, array('id' => $id));
    }

    /**
     * Delete service
     */
    public function delete_service($id) {
        // Also delete packages
        SBHA_Database::delete('service_packages', array('service_id' => $id));
        return SBHA_Database::delete('services', array('id' => $id));
    }

    /**
     * Create package
     */
    public function create_package($service_id, $data) {
        $defaults = array(
            'name' => '',
            'description' => '',
            'price' => 0,
            'features' => array(),
            'is_popular' => 0,
            'display_order' => 0
        );

        $data = wp_parse_args($data, $defaults);
        $data['service_id'] = $service_id;
        $data['features'] = json_encode($data['features']);

        return SBHA_Database::insert('service_packages', $data);
    }

    /**
     * Update package
     */
    public function update_package($id, $data) {
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        return SBHA_Database::update('service_packages', $data, array('id' => $id));
    }

    /**
     * Delete package
     */
    public function delete_package($id) {
        return SBHA_Database::delete('service_packages', array('id' => $id));
    }

    /**
     * Get categories
     */
    public function get_categories() {
        $categories = get_option('sbha_service_categories', '{}');
        return json_decode($categories, true) ?: array();
    }

    /**
     * Get services by category
     */
    public function get_services_by_category($category) {
        return $this->get_services(array('category' => $category));
    }

    /**
     * Get featured services
     */
    public function get_featured_services($limit = 6) {
        return $this->get_services(array(
            'featured' => true,
            'limit' => $limit,
            'orderby' => 'popularity_score',
            'order' => 'DESC'
        ));
    }

    /**
     * Get popular services
     */
    public function get_popular_services($limit = 6) {
        return $this->get_services(array(
            'popular' => true,
            'limit' => $limit,
            'orderby' => 'popularity_score',
            'order' => 'DESC'
        ));
    }

    /**
     * Search services
     */
    public function search_services($query) {
        global $wpdb;
        $table = SBHA_Database::get_table('services');

        $query = '%' . $wpdb->esc_like($query) . '%';

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE status = 'active'
              AND (name LIKE %s OR description LIKE %s OR short_description LIKE %s)
            ORDER BY popularity_score DESC
        ", $query, $query, $query), ARRAY_A);
    }

    /**
     * Increment popularity score
     */
    public function increment_popularity($service_id) {
        global $wpdb;
        $table = SBHA_Database::get_table('services');

        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET popularity_score = popularity_score + 1 WHERE id = %d",
            $service_id
        ));
    }

    /**
     * Update conversion rate
     */
    public function update_conversion_rate($service_id) {
        global $wpdb;
        $intentions_table = SBHA_Database::get_table('ai_customer_intentions');
        $services_table = SBHA_Database::get_table('services');

        // Calculate conversion rate
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_views,
                SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions
            FROM $intentions_table
            WHERE matched_services LIKE %s
        ", '%"service_id":' . $service_id . '%'), ARRAY_A);

        if ($stats && $stats['total_views'] > 0) {
            $rate = ($stats['conversions'] / $stats['total_views']) * 100;
            $wpdb->update($services_table,
                array('conversion_rate' => $rate),
                array('id' => $service_id)
            );
        }
    }

    /**
     * Ensure unique slug
     */
    private function ensure_unique_slug($slug, $id = 0) {
        global $wpdb;
        $table = SBHA_Database::get_table('services');

        $original_slug = $slug;
        $counter = 1;

        while (true) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s AND id != %d",
                $slug, $id
            ));

            if (!$existing) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get service statistics
     */
    public function get_service_stats($service_id) {
        global $wpdb;
        $jobs_table = SBHA_Database::get_table('jobs');

        return $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN job_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(total) as total_revenue,
                AVG(total) as avg_order_value,
                AVG(revision_count) as avg_revisions
            FROM $jobs_table
            WHERE service_id = %d
        ", $service_id), ARRAY_A);
    }

    /**
     * Reorder services
     */
    public function reorder_services($order_data) {
        foreach ($order_data as $position => $service_id) {
            SBHA_Database::update('services',
                array('display_order' => $position),
                array('id' => $service_id)
            );
        }
        return true;
    }

    /**
     * Duplicate service
     */
    public function duplicate_service($id) {
        $service = $this->get_service($id);
        if (!$service) {
            return false;
        }

        $new_data = $service;
        unset($new_data['id'], $new_data['packages']);
        $new_data['name'] = $service['name'] . ' (Copy)';
        $new_data['slug'] = $this->ensure_unique_slug($service['slug'] . '-copy');
        $new_data['status'] = 'draft';

        $new_id = $this->create_service($new_data);

        if ($new_id && !empty($service['packages'])) {
            foreach ($service['packages'] as $package) {
                unset($package['id'], $package['service_id']);
                $this->create_package($new_id, $package);
            }
        }

        return $new_id;
    }

    /**
     * Get related services (manual + AI)
     */
    public function get_related_services($service_id, $limit = 4) {
        // First try AI recommendations
        if (function_exists('SBHA')) {
            $ai_related = SBHA()->get_ai_engine()->get_related_services($service_id, $limit);
            if (!empty($ai_related)) {
                return $ai_related;
            }
        }

        // Fallback to same category
        $service = $this->get_service($service_id);
        if (!$service) {
            return array();
        }

        return SBHA_Database::get_results('services',
            array('category' => $service['category'], 'status' => 'active'),
            'popularity_score',
            'DESC',
            $limit + 1
        );
    }

    /**
     * Get bundle suggestions for a service
     */
    public function get_bundle_suggestions($service_id) {
        if (!function_exists('SBHA')) {
            return array();
        }

        return SBHA()->get_ai_engine()->get_bundle_recommendations($service_id);
    }
}
