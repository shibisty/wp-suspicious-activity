<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Контролер сторінки "Лог запитів": пагінований список усіх сирих
 * записів wp_request_logs з фільтрами й сортуванням.
 */
class WP_SAD_Page_Request_Logs {

    private $per_page = 50;

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Недостатньо прав', 'sharing-activity-detector'));
        }

        $filters = $this->get_filters();
        $sort = $this->get_sort_params();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param on a GET-based list screen, not a state-changing action.
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($paged - 1) * $this->per_page;

        $results = $this->get_logs($filters, $sort, $this->per_page, $offset);
        $total = $this->get_total($filters);
        $total_pages = max(1, (int) ceil($total / $this->per_page));

        $filters_view = $filters;
        $sort_view = $sort;
        $results_view = $results;
        $total_view = $total;
        $total_pages_view = $total_pages;
        $paged_view = $paged;

        require WP_SAD_PLUGIN_DIR . 'views/request-logs-list.php';
    }

    private function get_filters() {
        $default_to = current_time('Y-m-d');
        $default_from = gmdate('Y-m-d', strtotime('-30 days', strtotime($default_to)));

        $email        = sanitize_email(wp_unslash($_GET['email'] ?? ($_GET['emails'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $date_from    = sanitize_text_field(wp_unslash($_GET['date_from'] ?? $default_from)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $date_to      = sanitize_text_field(wp_unslash($_GET['date_to'] ?? $default_to)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $device       = sanitize_text_field(wp_unslash($_GET['device'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $request_type = sanitize_text_field(wp_unslash($_GET['request_type'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.
        $user_type    = sanitize_text_field(wp_unslash($_GET['user_type'] ?? 'all')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter param on a GET-based list screen (bookmarkable URLs), not a state-changing action.

        if (!in_array($user_type, ['all', 'registered', 'admins'], true)) {
            $user_type = 'all';
        }

        $allowed_types = ['', 'page', 'api', 'admin', 'heartbeat'];
        if (!in_array($request_type, $allowed_types, true)) {
            $request_type = '';
        }

        return [
            'email'        => $email,
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'device'       => $device,
            'request_type' => $request_type,
            'user_type'    => $user_type,
        ];
    }

    private function get_sort_params() {
        $orderby = sanitize_text_field(wp_unslash($_GET['orderby'] ?? 'created_at')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort param on a GET-based list screen, not a state-changing action.
        $order = strtoupper(sanitize_text_field(wp_unslash($_GET['order'] ?? 'DESC'))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort param on a GET-based list screen, not a state-changing action.

        $allowed = ['created_at', 'ip', 'request_type', 'user_email'];
        if (!in_array($orderby, $allowed, true)) {
            $orderby = 'created_at';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        return ['orderby' => $orderby, 'order' => $order];
    }

    private function build_where($filters) {
        global $wpdb;

        $where = ['DATE(l.created_at) BETWEEN %s AND %s'];
        $params = [$filters['date_from'], $filters['date_to']];

        if (!empty($filters['email'])) {
            $where[] = 'u.user_email = %s';
            $params[] = $filters['email'];
        }

        if (!empty($filters['device'])) {
            $where[] = 'l.user_agent LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['device']) . '%';
        }

        if (!empty($filters['request_type'])) {
            $where[] = 'l.request_type = %s';
            $params[] = $filters['request_type'];
        }

        $user_type_sql = WP_SAD_Query_Helpers::user_type_sql($filters['user_type'], 'l.user_id');
        if ($user_type_sql['where']) {
            $where[] = $user_type_sql['where'];
        }

        return [
            'where_sql' => implode(' AND ', $where),
            'join_sql'  => $user_type_sql['join'],
            'params'    => $params,
        ];
    }

    private function get_logs($filters, $sort, $per_page, $offset) {
        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $parts = $this->build_where($filters);
        $order_sql = "{$sort['orderby']} {$sort['order']}";

        $sql = "SELECT l.id, l.user_id, l.user_agent, l.ip, l.session_id, l.request_type, l.path, l.params, l.created_at,
                       u.user_email, u.display_name
                FROM {$table} l
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                {$parts['join_sql']}
                WHERE {$parts['where_sql']}
                ORDER BY {$order_sql}
                LIMIT %d OFFSET %d";

        $params = array_merge($parts['params'], [$per_page, $offset]);

        return $wpdb->get_results($wpdb->prepare($sql, $params)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table}/{$wpdb->users} are hardcoded/prefix-derived; {$parts['join_sql']}/{$parts['where_sql']} come from build_where(), built only from hardcoded fragments and allow-listed values; {$order_sql} uses $sort['orderby']/$sort['order'] validated against a fixed allow-list; every real user-supplied value is bound via prepare() placeholders.
    }

    private function get_total($filters) {
        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $parts = $this->build_where($filters);

        $sql = "SELECT COUNT(*)
                FROM {$table} l
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                {$parts['join_sql']}
                WHERE {$parts['where_sql']}";

        return intval($wpdb->get_var($wpdb->prepare($sql, $parts['params']))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- {$table}/{$wpdb->users} are hardcoded/prefix-derived; {$parts['join_sql']}/{$parts['where_sql']} come from build_where(), built only from hardcoded fragments and allow-listed values; every real user-supplied value is bound via prepare() placeholders.
    }
}
