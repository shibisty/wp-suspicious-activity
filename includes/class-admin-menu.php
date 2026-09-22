<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Реєстрація пунктів меню адмінки. "Перегляд логу" і "Перегляд
 * активності" — приховані сторінки (parent_slug = null): доступні за
 * прямим посиланням, але не показуються в меню.
 */
class WP_SAD_Admin_Menu {

    const CAP = 'manage_options';

    const SLUG_ACTIVITY      = 'wp-suspicious-activity';
    const SLUG_REQUEST_LOGS  = 'wp-sad-request-logs';
    const SLUG_SETTINGS      = 'wp-sad-settings';
    const SLUG_LOG_VIEW      = 'wp-sad-log-view';
    const SLUG_ACTIVITY_VIEW = 'wp-sad-activity-view';

    private $session_analyzer;

    public function __construct(WP_SAD_Session_Analyzer $session_analyzer) {
        $this->session_analyzer = $session_analyzer;
    }

    public function register() {
        add_menu_page(
            __('Підозріла активність', 'sharing-activity-detector'),
            __('Підозріла активність', 'sharing-activity-detector'),
            self::CAP,
            self::SLUG_ACTIVITY,
            [$this, 'render_activity'],
            'dashicons-warning',
            82
        );

        add_submenu_page(
            self::SLUG_ACTIVITY,
            __('Активність', 'sharing-activity-detector'),
            __('Активність', 'sharing-activity-detector'),
            self::CAP,
            self::SLUG_ACTIVITY,
            [$this, 'render_activity']
        );

        add_submenu_page(
            self::SLUG_ACTIVITY,
            __('Лог запитів', 'sharing-activity-detector'),
            __('Лог запитів', 'sharing-activity-detector'),
            self::CAP,
            self::SLUG_REQUEST_LOGS,
            [$this, 'render_request_logs']
        );

        add_submenu_page(
            self::SLUG_ACTIVITY,
            __('Налаштування', 'sharing-activity-detector'),
            __('Налаштування', 'sharing-activity-detector'),
            self::CAP,
            self::SLUG_SETTINGS,
            [$this, 'render_settings']
        );

        add_submenu_page(null, __('Перегляд логу', 'sharing-activity-detector'), '', self::CAP, self::SLUG_LOG_VIEW, [$this, 'render_log_view']);
        add_submenu_page(null, __('Перегляд активності', 'sharing-activity-detector'), '', self::CAP, self::SLUG_ACTIVITY_VIEW, [$this, 'render_activity_view']);
    }

    public function enqueue_assets($hook) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which admin screen is rendering, used only to decide whether to enqueue our own assets; not a state-changing action.
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $our_pages = [
            self::SLUG_ACTIVITY,
            self::SLUG_REQUEST_LOGS,
            self::SLUG_SETTINGS,
            self::SLUG_LOG_VIEW,
            self::SLUG_ACTIVITY_VIEW,
        ];

        if (!in_array($page, $our_pages, true)) {
            return;
        }

        wp_enqueue_style('wp-sad-admin', WP_SAD_PLUGIN_URL . 'assets/css/admin.css', [], WP_SAD_VERSION);
        wp_enqueue_script('wp-sad-timeline', WP_SAD_PLUGIN_URL . 'assets/js/admin-timeline.js', [], WP_SAD_VERSION, true);
        wp_enqueue_script('wp-sad-filters', WP_SAD_PLUGIN_URL . 'assets/js/admin-filters.js', [], WP_SAD_VERSION, true);
        wp_localize_script('wp-sad-filters', 'WP_SAD_FiltersConfig', [
            'storageKey' => 'wp_sad_filters_' . $page,
        ]);
    }

    public function render_activity() {
        (new WP_SAD_Page_Activity($this->session_analyzer))->render();
    }

    public function render_request_logs() {
        (new WP_SAD_Page_Request_Logs())->render();
    }

    public function render_settings() {
        (new WP_SAD_Page_Settings())->render();
    }

    public function render_log_view() {
        (new WP_SAD_Page_Log_View())->render();
    }

    public function render_activity_view() {
        (new WP_SAD_Page_Activity_View($this->session_analyzer))->render();
    }
}
