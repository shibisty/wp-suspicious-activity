<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Свій" request logger. Пише кожен прийнятний запит у wp_request_logs,
 * проставляючи request_type (page/api/admin/heartbeat) і застосовуючи
 * налаштування (аудиторія/діапазон/часове вікно) з WP_SAD_Settings.
 */
class WP_SAD_Request_Logger {

    const GUEST_COOKIE = 'wp_sad_sid';

    public function __construct() {
        add_action('init', [$this, 'maybe_log_current_request'], 5);
    }

    public function maybe_log_current_request() {
        if (WP_SAD_Settings::get('log_scope') === WP_SAD_Settings::SCOPE_OFF) {
            return;
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 'WordPress/') !== false) {
            return; // внутрішній WP-to-WP трафік (loopback wp-cron тощо)
        }

        if (isset($_SERVER['REQUEST_URI']) && strpos(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), 'wp-cron.php') !== false) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        if (!WP_SAD_Settings::is_logging_active_now()) {
            return;
        }

        $request_type = $this->detect_request_type();

        if (!$this->passes_scope_gate($request_type)) {
            return;
        }

        if ($request_type === 'heartbeat' && !WP_SAD_Settings::get('heartbeat_enabled')) {
            return;
        }

        $user_id = get_current_user_id();

        if (!$this->passes_audience_gate($user_id)) {
            return;
        }

        $this->insert_log($user_id, $request_type);
    }

    /**
     * Визначає тип запиту ДО будь-яких ранніх return'ів по is_admin().
     * Важливо: WordPress завжди встановлює is_admin()=true для будь-якого
     * запиту через admin-ajax.php, навіть якщо той ініційований з
     * фронтенду — тож "справжня" адмінка визначається як is_admin()
     * ЯКЩО це не admin-ajax.php і не наш heartbeat REST-роут.
     */
    private function detect_request_type() {
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (strpos($uri, '/wp-json/wp-sad/v1/heartbeat') !== false) {
            return 'heartbeat';
        }

        if (is_admin() && strpos($uri, 'admin-ajax.php') === false) {
            return 'admin';
        }

        if (strpos($uri, 'admin-ajax.php') !== false || strpos($uri, '/wp-json/') !== false) {
            return 'api';
        }

        return 'page';
    }

    private function passes_scope_gate($request_type) {
        switch (WP_SAD_Settings::get('log_scope')) {
            case WP_SAD_Settings::SCOPE_ALL:
                // Справді "все": сторінки, api/ajax, адмінка й heartbeat.
                return in_array($request_type, ['page', 'api', 'admin', 'heartbeat'], true);

            case WP_SAD_Settings::SCOPE_PAGES_HEARTBEAT:
                return in_array($request_type, ['page', 'heartbeat'], true);

            case WP_SAD_Settings::SCOPE_SITE_NO_ADMIN_HEARTBEAT:
                return in_array($request_type, ['page', 'api', 'heartbeat'], true);

            case WP_SAD_Settings::SCOPE_ADMIN_HEARTBEAT:
                return in_array($request_type, ['admin', 'heartbeat'], true);

            case WP_SAD_Settings::SCOPE_OFF:
                return false;

            default:
                return true;
        }
    }

    private function passes_audience_gate($user_id) {
        $audience = WP_SAD_Settings::get('log_audience');

        if ($audience === WP_SAD_Settings::AUDIENCE_ALL) {
            return true;
        }

        if (!$user_id) {
            return false; // "лише зареєстровані"/"лише адміни" ніколи не логують гостей
        }

        if ($audience === WP_SAD_Settings::AUDIENCE_REGISTERED) {
            return true;
        }

        if ($audience === WP_SAD_Settings::AUDIENCE_ADMINS) {
            return user_can($user_id, 'manage_options');
        }

        return true;
    }

    private function insert_log($user_id, $request_type) {
        global $wpdb;

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 512) : '';
        $ip         = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $path       = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $params     = wp_json_encode(array_merge($_GET, $_POST)); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- this is the plugin's own passive audit log: by design it records the raw parameter set of EVERY request (not a privileged form submission), so a nonce check does not apply here; the merged params are stored as an opaque JSON blob for later admin review, never executed or output unescaped (see views/log-single.php, which escapes it on display).

        $session_id = $user_id
            ? (function_exists('wp_get_session_token') ? wp_get_session_token() : '')
            : $this->get_or_set_guest_session_id();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert() is the WordPress-recommended API for writing a row; caching does not apply to an INSERT.
        $wpdb->insert(
            WP_SAD_DB::table_name(),
            [
                'user_id'      => $user_id,
                'user_agent'   => $user_agent,
                'ip'           => $ip,
                'session_id'   => $session_id,
                'request_type' => $request_type,
                'path'         => $path,
                'params'       => $params,
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Для аудиторії "всі" гостей теж потрібно рахувати як окремі сесії —
     * видаємо легкий first-party cookie замість user_id.
     */
    private function get_or_set_guest_session_id() {
        if (!empty($_COOKIE[self::GUEST_COOKIE])) {
            return sanitize_text_field(wp_unslash($_COOKIE[self::GUEST_COOKIE]));
        }

        if (headers_sent()) {
            return '';
        }

        $sid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : md5(uniqid('', true));

        setcookie(self::GUEST_COOKIE, $sid, [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::GUEST_COOKIE] = $sid;

        return $sid;
    }
}
