<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Оркестратор: реєстрація хуків активації/деактивації, textdomain,
 * логера, heartbeat та меню адмінки. Уся конкретна логіка живе в
 * окремих класах — цей файл лише "з'єднує" їх.
 */
class WP_SAD_Plugin {

    public function __construct() {
        register_activation_hook(WP_SAD_PLUGIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(WP_SAD_PLUGIN_FILE, [__CLASS__, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_filter('plugin_locale', [__CLASS__, 'filter_plugin_locale'], 10, 2);

        // Перевірка/створення таблиці при кожній ініціалізації, а не
        // лише при активації плагіна.
        add_action('init', ['WP_SAD_DB', 'maybe_upgrade'], 1);

        new WP_SAD_Request_Logger();
        new WP_SAD_Heartbeat();

        $session_analyzer = new WP_SAD_Session_Analyzer();
        $admin_menu = new WP_SAD_Admin_Menu($session_analyzer);

        add_action('admin_menu', [$admin_menu, 'register']);
        add_action('admin_enqueue_scripts', [$admin_menu, 'enqueue_assets']);
    }

    public static function activate() {
        WP_SAD_DB::install();
    }

    public static function deactivate() {
        // Дані навмисно НЕ видаляються при деактивації плагіна.
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-suspicious-activity',
            false,
            dirname(plugin_basename(WP_SAD_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Дозволяє в налаштуваннях плагіна форсувати мову ІНТЕРФЕЙСУ ЦЬОГО
     * ПЛАГІНА окремо від загальної мови сайту.
     */
    public static function filter_plugin_locale($locale, $domain) {
        if ($domain !== 'wp-suspicious-activity') {
            return $locale;
        }

        if (!class_exists('WP_SAD_Settings')) {
            return $locale;
        }

        $forced = WP_SAD_Settings::get('admin_language');
        return $forced ? $forced : $locale;
    }
}
