<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Контролер сторінки "Налаштування". Використовує звичайний
 * POST-обробник (з nonce), а не Settings API register_setting, щоб не
 * плодити глобальні опції під час рефакторингу невеликого плагіна.
 */
class WP_SAD_Page_Settings {

    const NONCE_ACTION = 'wp_sad_save_settings';

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Недостатньо прав', 'suspicious-activity'));
        }

        $saved = false;

        if (isset($_POST['wp_sad_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wp_sad_settings_nonce'])), self::NONCE_ACTION)) {
            WP_SAD_Settings::update([
                'log_audience'       => sanitize_text_field(wp_unslash($_POST['log_audience'] ?? '')),
                'log_scope'          => sanitize_text_field(wp_unslash($_POST['log_scope'] ?? '')),
                'log_window_mode'    => sanitize_text_field(wp_unslash($_POST['log_window_mode'] ?? '')),
                'log_window_from'    => sanitize_text_field(wp_unslash($_POST['log_window_from'] ?? '')),
                'log_window_to'      => sanitize_text_field(wp_unslash($_POST['log_window_to'] ?? '')),
                'heartbeat_enabled'  => !empty($_POST['heartbeat_enabled']),
                'heartbeat_interval' => intval($_POST['heartbeat_interval'] ?? 60),
                'admin_language'     => sanitize_text_field(wp_unslash($_POST['admin_language'] ?? '')),
            ]);
            $saved = true;
        }

        $settings_view = WP_SAD_Settings::get_all();
        $languages_view = WP_SAD_Settings::available_languages_for_select();
        $saved_view = $saved;
        $nonce_action_view = self::NONCE_ACTION;

        require WP_SAD_PLUGIN_DIR . 'views/settings-page.php';
    }
}
