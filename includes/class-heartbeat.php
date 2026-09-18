<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Власний легкий heart-bit: REST-роут (не admin-ajax.php, щоб не
 * впиратись в те, що WordPress завжди виставляє is_admin()=true для
 * admin-ajax.php) + фронтенд-пінгер, який стукає туди, поки вкладка
 * активна. Фактичне логування виконує WP_SAD_Request_Logger на init —
 * REST-запити теж проходять через init до диспетчеризації роуту.
 */
class WP_SAD_Heartbeat {

    const ROUTE_NAMESPACE = 'wp-sad/v1';
    const ROUTE = '/heartbeat';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_pinger']);
    }

    public function register_routes() {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_ping'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_ping($request) {
        return rest_ensure_response(['ok' => true]);
    }

    public function maybe_enqueue_pinger() {
        if (is_admin()) {
            return;
        }

        if (!WP_SAD_Settings::get('heartbeat_enabled')) {
            return;
        }

        if (WP_SAD_Settings::get('log_scope') === WP_SAD_Settings::SCOPE_OFF) {
            return;
        }

        if (!WP_SAD_Settings::is_logging_active_now()) {
            return;
        }

        $user_id = get_current_user_id();
        $audience = WP_SAD_Settings::get('log_audience');

        if ($audience !== WP_SAD_Settings::AUDIENCE_ALL) {
            if (!$user_id) {
                return;
            }
            if ($audience === WP_SAD_Settings::AUDIENCE_ADMINS && !user_can($user_id, 'manage_options')) {
                return;
            }
        }

        $handle = 'wp-sad-heartbeat-ping';

        wp_enqueue_script(
            $handle,
            WP_SAD_PLUGIN_URL . 'assets/js/heartbeat-ping.js',
            [],
            WP_SAD_VERSION,
            true
        );

        wp_localize_script($handle, 'WP_SAD_Heartbeat', [
            'endpoint' => esc_url_raw(rest_url(self::ROUTE_NAMESPACE . self::ROUTE)),
            'interval' => max(15, intval(WP_SAD_Settings::get('heartbeat_interval', 60))) * 1000,
        ]);
    }
}
