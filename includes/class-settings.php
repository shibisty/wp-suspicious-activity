<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Налаштування логування: хто (аудиторія), що (діапазон/scope),
 * коли (часове вікно), heartbeat, мова інтерфейсу. Зберігається одним
 * автозавантажуваним option wp_sad_settings.
 */
class WP_SAD_Settings {

    const OPTION_KEY = 'wp_sad_settings';

    const SCOPE_ALL                     = 'all';
    const SCOPE_PAGES_HEARTBEAT         = 'pages_heartbeat';
    const SCOPE_SITE_NO_ADMIN_HEARTBEAT = 'site_no_admin_heartbeat';
    const SCOPE_ADMIN_HEARTBEAT         = 'admin_heartbeat';
    const SCOPE_OFF                     = 'off';

    const AUDIENCE_ALL        = 'all';
    const AUDIENCE_REGISTERED = 'registered';
    const AUDIENCE_ADMINS     = 'admins';

    /**
     * Мови, для яких плагін постачає ВЛАСНІ .po/.mo у своїй папці
     * /languages (незалежно від того, чи встановлений відповідний
     * мовний пакет самого WordPress). Ключ — WP-локаль (файл
     * wp-suspicious-activity-{locale}.mo), значення — назва мови
     * рідним написанням для випадаючого списку.
     */
    const BUNDLED_LANGUAGES = [
        'en_US' => 'English (United States)',
        'ru_RU' => 'Русский',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'es_ES' => 'Español',
        'it_IT' => 'Italiano',
        'pt_BR' => 'Português do Brasil',
        'pt_PT' => 'Português',
        'pl_PL' => 'Polski',
        'nl_NL' => 'Nederlands',
        'ro_RO' => 'Română',
        'cs_CZ' => 'Čeština',
        'hu_HU' => 'Magyar',
        'bg_BG' => 'Български',
        'el'    => 'Ελληνικά',
        'tr_TR' => 'Türkçe',
        'sv_SE' => 'Svenska',
        'fi'    => 'Suomi',
        'lt_LT' => 'Lietuvių kalba',
        'hr'    => 'Hrvatski',
        'sr_RS' => 'Српски језик',
        'ka_GE' => 'ქართული',
        'az'    => 'Azərbaycan dili',
        'kk'    => 'Қазақ тілі',
        'bel'   => 'Беларуская мова',
        'ar'    => 'العربية',
        'he_IL' => 'עברית',
        'hi_IN' => 'हिन्दी',
        'zh_CN' => '简体中文',
        'ja'    => '日本語',
    ];

    public static function defaults() {
        return [
            'log_audience'       => self::AUDIENCE_ALL,
            'log_scope'          => self::SCOPE_ALL,
            'log_window_mode'    => 'always', // always|scheduled
            'log_window_from'    => '00:00',
            'log_window_to'      => '23:59',
            'heartbeat_enabled'  => false,
            'heartbeat_interval' => 60,
            'admin_language'     => '',
        ];
    }

    public static function get_all() {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return wp_parse_args($stored, self::defaults());
    }

    public static function get($key, $default = null) {
        $all = self::get_all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function update(array $input) {
        $sanitized = self::sanitize($input);
        $merged = wp_parse_args($sanitized, self::get_all());
        update_option(self::OPTION_KEY, $merged);
        return $merged;
    }

    public static function sanitize(array $input) {
        $defaults = self::defaults();
        $out = [];

        $audiences = [self::AUDIENCE_ALL, self::AUDIENCE_REGISTERED, self::AUDIENCE_ADMINS];
        $out['log_audience'] = in_array($input['log_audience'] ?? '', $audiences, true)
            ? $input['log_audience']
            : $defaults['log_audience'];

        $scopes = [
            self::SCOPE_ALL,
            self::SCOPE_PAGES_HEARTBEAT,
            self::SCOPE_SITE_NO_ADMIN_HEARTBEAT,
            self::SCOPE_ADMIN_HEARTBEAT,
            self::SCOPE_OFF,
        ];
        $out['log_scope'] = in_array($input['log_scope'] ?? '', $scopes, true)
            ? $input['log_scope']
            : $defaults['log_scope'];

        $out['log_window_mode'] = (($input['log_window_mode'] ?? '') === 'scheduled') ? 'scheduled' : 'always';

        $out['log_window_from'] = self::sanitize_time($input['log_window_from'] ?? '', $defaults['log_window_from']);
        $out['log_window_to']   = self::sanitize_time($input['log_window_to'] ?? '', $defaults['log_window_to']);

        $out['heartbeat_enabled'] = !empty($input['heartbeat_enabled']);

        $interval = intval($input['heartbeat_interval'] ?? $defaults['heartbeat_interval']);
        $out['heartbeat_interval'] = max(15, min(600, $interval ?: $defaults['heartbeat_interval']));

        $lang = isset($input['admin_language']) ? sanitize_text_field($input['admin_language']) : '';
        $allowed = array_merge(
            [''],
            array_values(get_available_languages()),
            array_keys(self::BUNDLED_LANGUAGES)
        );
        $out['admin_language'] = in_array($lang, $allowed, true) ? $lang : '';

        return $out;
    }

    private static function sanitize_time($value, $fallback) {
        if (is_string($value) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim($value))) {
            return trim($value);
        }
        return $fallback;
    }

    /**
     * Чи активне логування зараз, з урахуванням часового вікна (з/по).
     * Підтримує вікна, що перетинають північ (напр. 22:00–06:00).
     */
    public static function is_logging_active_now() {
        if (self::get('log_window_mode', 'always') !== 'scheduled') {
            return true;
        }

        $from = self::get('log_window_from', '00:00');
        $to   = self::get('log_window_to', '23:59');
        $now  = current_time('H:i');

        if ($from === $to) {
            return true;
        }

        if ($from < $to) {
            return ($now >= $from && $now <= $to);
        }

        return ($now >= $from || $now <= $to);
    }

    /**
     * Список мов для випадаючого списку в налаштуваннях. Об'єднує:
     * (1) мовні пакети, встановлені в самому WordPress, і
     * (2) мови, для яких цей плагін постачає ВЛАСНІ .po/.mo у своїй
     *     папці /languages (self::BUNDLED_LANGUAGES) — вони працюють
     *     незалежно від того, чи стоїть відповідний пакет ядра WP,
     *     бо load_plugin_textdomain() шукає файл прямо в папці плагіна.
     */
    public static function available_languages_for_select() {
        $installed = get_available_languages();
        $choices = ['' => __('— мова сайту за замовчуванням —', 'wp-suspicious-activity')];

        if (!function_exists('wp_get_available_translations')) {
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        }
        $translations = function_exists('wp_get_available_translations')
            ? wp_get_available_translations()
            : [];

        $all_locales = array_unique(array_merge($installed, array_keys(self::BUNDLED_LANGUAGES)));
        sort($all_locales);

        foreach ($all_locales as $locale) {
            if ($locale === 'en_US') {
                $choices[$locale] = 'English (United States)';
                continue;
            }
            if (isset(self::BUNDLED_LANGUAGES[$locale])) {
                // Власний переклад плагіна — надійне джерело назви,
                // не залежить від доступності api.wordpress.org.
                $choices[$locale] = self::BUNDLED_LANGUAGES[$locale];
                continue;
            }
            $choices[$locale] = isset($translations[$locale]['native_name'])
                ? $translations[$locale]['native_name']
                : $locale;
        }

        return $choices;
    }
}
