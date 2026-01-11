<?php
/**
 * Database Helper Class
 *
 * Provides database operations and utilities
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Database {

    /**
     * Table names
     */
    public static function get_table($name) {
        global $wpdb;
        return $wpdb->prefix . 'sbha_' . $name;
    }

    /**
     * Insert record
     */
    public static function insert($table, $data) {
        global $wpdb;
        $table_name = self::get_table($table);
        $result = $wpdb->insert($table_name, $data);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update record
     */
    public static function update($table, $data, $where) {
        global $wpdb;
        $table_name = self::get_table($table);
        return $wpdb->update($table_name, $data, $where);
    }

    /**
     * Delete record
     */
    public static function delete($table, $where) {
        global $wpdb;
        $table_name = self::get_table($table);
        return $wpdb->delete($table_name, $where);
    }

    /**
     * Get single record by ID
     */
    public static function get_by_id($table, $id) {
        global $wpdb;
        $table_name = self::get_table($table);
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Get records with conditions
     */
    public static function get_results($table, $conditions = array(), $orderby = 'id', $order = 'DESC', $limit = 0, $offset = 0) {
        global $wpdb;
        $table_name = self::get_table($table);

        $sql = "SELECT * FROM $table_name";
        $where_clauses = array();
        $values = array();

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '%s'));
                    $where_clauses[] = "$key IN ($placeholders)";
                    $values = array_merge($values, $value);
                } else {
                    $where_clauses[] = "$key = %s";
                    $values[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $sql .= " ORDER BY $orderby $order";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get count
     */
    public static function get_count($table, $conditions = array()) {
        global $wpdb;
        $table_name = self::get_table($table);

        $sql = "SELECT COUNT(*) FROM $table_name";
        $where_clauses = array();
        $values = array();

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                $where_clauses[] = "$key = %s";
                $values[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get sum
     */
    public static function get_sum($table, $column, $conditions = array()) {
        global $wpdb;
        $table_name = self::get_table($table);

        $sql = "SELECT COALESCE(SUM($column), 0) FROM $table_name";
        $where_clauses = array();
        $values = array();

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                $where_clauses[] = "$key = %s";
                $values[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return (float) $wpdb->get_var($sql);
    }

    /**
     * Execute custom query
     */
    public static function query($sql, $params = array()) {
        global $wpdb;
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get last error
     */
    public static function get_last_error() {
        global $wpdb;
        return $wpdb->last_error;
    }

    /**
     * Begin transaction
     */
    public static function begin_transaction() {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
    }

    /**
     * Commit transaction
     */
    public static function commit() {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    /**
     * Rollback transaction
     */
    public static function rollback() {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    /**
     * Get date range stats
     */
    public static function get_date_range_stats($table, $column, $date_column, $start_date, $end_date, $conditions = array()) {
        global $wpdb;
        $table_name = self::get_table($table);

        $sql = "SELECT
                    DATE($date_column) as date,
                    COUNT(*) as count,
                    COALESCE(SUM($column), 0) as total
                FROM $table_name
                WHERE $date_column BETWEEN %s AND %s";

        $values = array($start_date, $end_date);

        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                $sql .= " AND $key = %s";
                $values[] = $value;
            }
        }

        $sql .= " GROUP BY DATE($date_column) ORDER BY date ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * Get grouped stats
     */
    public static function get_grouped_stats($table, $group_column, $count_column = '*', $conditions = array(), $limit = 10) {
        global $wpdb;
        $table_name = self::get_table($table);

        $count_expr = $count_column === '*' ? 'COUNT(*)' : "COUNT($count_column)";

        $sql = "SELECT $group_column, $count_expr as count FROM $table_name";
        $values = array();

        if (!empty($conditions)) {
            $where_clauses = array();
            foreach ($conditions as $key => $value) {
                $where_clauses[] = "$key = %s";
                $values[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $sql .= " GROUP BY $group_column ORDER BY count DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }
}
