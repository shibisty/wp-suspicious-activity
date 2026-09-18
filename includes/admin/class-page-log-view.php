<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Контролер сторінки перегляду ОДНОГО запису логу (з "Лог запитів").
 */
class WP_SAD_Page_Log_View {

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Недостатньо прав', 'wp-suspicious-activity'));
        }

        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $id = intval($_GET['id'] ?? 0);

        $log = $id ? $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, u.user_email, u.display_name
             FROM {$table} l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.id = %d",
            $id
        )) : null;

        $neighbors = [];
        if ($log) {
            $neighbors = $wpdb->get_results($wpdb->prepare(
                "SELECT id, created_at, path, request_type
                 FROM {$table}
                 WHERE user_id = %d
                   AND ABS(TIMESTAMPDIFF(SECOND, created_at, %s)) <= 900
                   AND id != %d
                 ORDER BY created_at ASC
                 LIMIT 30",
                $log->user_id, $log->created_at, $log->id
            ));
        }

        $log_view = $log;
        $neighbors_view = $neighbors;

        require WP_SAD_PLUGIN_DIR . 'views/log-single.php';
    }
}
