<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Контролер сторінки перегляду активності ОДНОГО користувача окремо
 * (той самий таймлайн, що й у списку "Активність", але на всю сторінку).
 */
class WP_SAD_Page_Activity_View {

    private $session_analyzer;

    public function __construct(WP_SAD_Session_Analyzer $session_analyzer) {
        $this->session_analyzer = $session_analyzer;
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Недостатньо прав', 'wp-suspicious-activity'));
        }

        $user_id = intval($_GET['user_id'] ?? 0);

        $default_to = current_time('Y-m-d');
        $default_from = date('Y-m-d', strtotime('-30 days', strtotime($default_to)));
        $date_from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? $default_from));
        $date_to = sanitize_text_field(wp_unslash($_GET['date_to'] ?? $default_to));

        $user = $user_id ? get_userdata($user_id) : false;
        $stats = $user_id ? $this->session_analyzer->get_user_sessions($user_id, $date_from, $date_to) : $this->session_analyzer->empty_stats();
        $risk = $this->session_analyzer->calculate_risk($stats);

        $user_view = $user;
        $user_id_view = $user_id;
        $date_from_view = $date_from;
        $date_to_view = $date_to;
        $stats_view = $stats;
        $risk_view = $risk;

        require WP_SAD_PLUGIN_DIR . 'views/activity-single.php';
    }
}
