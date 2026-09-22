<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Контролер сторінки "Активність": список користувачів з підозрілими
 * ознаками + таймлайн. Уся розмітка — у views/activity-list.php.
 */
class WP_SAD_Page_Activity {

    private $per_page = 20;
    private $session_analyzer;

    public function __construct(WP_SAD_Session_Analyzer $session_analyzer) {
        $this->session_analyzer = $session_analyzer;
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Недостатньо прав', 'suspicious-activity'));
        }

        $filters = $this->get_filters();
        $sort = $this->get_sort_params();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param on a GET-based list screen, not a state-changing action.
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($paged - 1) * $this->per_page;

        $debug = $this->debug_info($filters);
        $results = $this->get_suspicious_users($filters, $sort, $this->per_page, $offset);

        foreach ($results as &$row) {
            $stats = $this->session_analyzer->get_user_sessions($row->user_id, $filters['date_from'], $filters['date_to']);
            $row->session_stats = $stats;
            $row->risk = $this->session_analyzer->calculate_risk($stats);
        }
        unset($row);

        $total = $this->get_total_suspicious_count($filters);
        $total_pages = max(1, (int) ceil($total / $this->per_page));

        $filters_view = $filters;
        $sort_view = $sort;
        $debug_view = $debug;
        $results_view = $results;
        $total_view = $total;
        $total_pages_view = $total_pages;
        $paged_view = $paged;

        require WP_SAD_PLUGIN_DIR . 'views/activity-list.php';
    }

    private function get_filters() {
        $default_to = current_time('Y-m-d');
        $default_from = gmdate('Y-m-d', strtotime('-30 days', strtotime($default_to)));

        $date_from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? $default_from)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $date_to   = sanitize_text_field(wp_unslash($_GET['date_to'] ?? $default_to)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $min_ips   = max(1, intval($_GET['min_ips'] ?? 1)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $email     = sanitize_email(wp_unslash($_GET['email'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $user_type = sanitize_text_field(wp_unslash($_GET['user_type'] ?? 'all')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.

        if (!in_array($user_type, ['all', 'registered', 'admins'], true)) {
            $user_type = 'all';
        }

        return [
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'min_ips'   => $min_ips,
            'email'     => $email,
            'user_type' => $user_type,
        ];
    }

    private function get_sort_params() {
        $orderby = sanitize_text_field(wp_unslash($_GET['orderby'] ?? 'course_requests')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort param on a GET-based list screen, not a state-changing action.
        $order = strtoupper(sanitize_text_field(wp_unslash($_GET['order'] ?? 'DESC'))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort param on a GET-based list screen, not a state-changing action.

        $allowed = ['unique_ips', 'unique_agents', 'total_requests', 'course_requests'];
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'course_requests';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        return ['orderby' => $orderby, 'order' => $order];
    }

    private function debug_info($filters) {
        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $total_in_table = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}")); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is WP_SAD_DB::table_name() ($wpdb->prefix), not user input; a simple diagnostic count with no user-supplied values.

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is WP_SAD_DB::table_name() ($wpdb->prefix), not user input; user-supplied values are bound via prepare() placeholders below.
        $total_in_period = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $filters['date_from'], $filters['date_to']
        )));
        $unique_users = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE DATE(created_at) BETWEEN %s AND %s",
            $filters['date_from'], $filters['date_to']
        )));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return [
            'total_in_table'  => $total_in_table,
            'total_in_period' => $total_in_period,
            'unique_users'    => $unique_users,
            'sql_error'       => $wpdb->last_error ?: '',
        ];
    }

    private function get_suspicious_users($filters, $sort, $per_page, $offset) {
        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $inner_where = ['DATE(l.created_at) BETWEEN %s AND %s'];
        $params = [$filters['date_from'], $filters['date_to']];

        $user_type_sql = WP_SAD_Query_Helpers::user_type_sql($filters['user_type'], 'l.user_id');
        if ($user_type_sql['where']) {
            $inner_where[] = $user_type_sql['where'];
        }

        $outer_where = [];
        if (!empty($filters['email'])) {
            $outer_where[] = 's.user_email = %s';
            $params[] = $filters['email'];
        }

        $inner_where_sql = implode(' AND ', $inner_where);
        $outer_where_sql = !empty($outer_where) ? 'WHERE ' . implode(' AND ', $outer_where) : '';
        $order_sql = "s.{$sort['orderby']} {$sort['order']}";

        $sql = "SELECT
                    s.user_id, s.user_email, s.display_name,
                    s.unique_ips, s.unique_agents, s.total_requests, s.course_requests
                FROM (
                    SELECT
                        l.user_id,
                        u.user_email,
                        u.display_name,
                        COUNT(DISTINCT l.ip) as unique_ips,
                        COUNT(DISTINCT l.user_agent) as unique_agents,
                        COUNT(l.id) as total_requests,
                        SUM(CASE WHEN l.path LIKE '/courses/%%' THEN 1 ELSE 0 END) as course_requests
                    FROM {$table} l
                    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                    {$user_type_sql['join']}
                    WHERE {$inner_where_sql}
                    GROUP BY l.user_id
                ) s
                {$outer_where_sql}
                ORDER BY {$order_sql}
                LIMIT %d OFFSET %d";

        $params[] = $per_page;
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table}/{$wpdb->users} are hardcoded/prefix-derived; {$user_type_sql['join']} comes from WP_SAD_Query_Helpers::user_type_sql() (hardcoded fragments only); {$order_sql} is built from $sort['orderby']/$sort['order'], both validated against fixed allow-lists in get_sort_params(); every real user-supplied value is bound via prepare() placeholders.

        if ($filters['min_ips'] > 1 && !empty($results)) {
            $results = array_values(array_filter($results, function ($row) use ($filters) {
                return $row->unique_ips >= $filters['min_ips'];
            }));
        }

        return $results;
    }

    private function get_total_suspicious_count($filters) {
        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $inner_where = ['DATE(l.created_at) BETWEEN %s AND %s'];
        $params = [$filters['date_from'], $filters['date_to']];

        $user_type_sql = WP_SAD_Query_Helpers::user_type_sql($filters['user_type'], 'l.user_id');
        if ($user_type_sql['where']) {
            $inner_where[] = $user_type_sql['where'];
        }

        $outer_where = [];
        if (!empty($filters['email'])) {
            $outer_where[] = 's.user_email = %s';
            $params[] = $filters['email'];
        }

        $inner_where_sql = implode(' AND ', $inner_where);
        $outer_where_sql = !empty($outer_where) ? 'WHERE ' . implode(' AND ', $outer_where) : '';

        if ($filters['min_ips'] > 1) {
            $connector = WP_SAD_Query_Helpers::where_or_and($outer_where_sql);
            $sql = "SELECT COUNT(*) FROM (
                SELECT s.user_id, s.unique_ips
                FROM (
                    SELECT l.user_id, u.user_email, COUNT(DISTINCT l.ip) as unique_ips
                    FROM {$table} l
                    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                    {$user_type_sql['join']}
                    WHERE {$inner_where_sql}
                    GROUP BY l.user_id
                ) s
                {$outer_where_sql}
                {$connector} s.unique_ips >= %d
            ) as subquery";
            $params[] = $filters['min_ips'];
        } else {
            $sql = "SELECT COUNT(*) FROM (
                SELECT s.user_id
                FROM (
                    SELECT l.user_id, u.user_email
                    FROM {$table} l
                    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                    {$user_type_sql['join']}
                    WHERE {$inner_where_sql}
                    GROUP BY l.user_id
                ) s
                {$outer_where_sql}
            ) as subquery";
        }

        return intval($wpdb->get_var($wpdb->prepare($sql, $params))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table}/{$wpdb->users} are hardcoded/prefix-derived; {$user_type_sql['join']}/{$connector} come from WP_SAD_Query_Helpers helpers (hardcoded fragments only); every real user-supplied value is bound via prepare() placeholders.
    }
}
