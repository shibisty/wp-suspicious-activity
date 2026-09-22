<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Робота зі схемою БД. maybe_upgrade() викликається на init (а не лише
 * на activation), тому таблиця автоматично створюється/доповнюється,
 * навіть якщо була видалена вручну або плагін оновили без реактивації.
 */
class WP_SAD_DB {

    const DB_VERSION = '1.1';
    const DB_VERSION_OPTION = 'wp_sad_db_version';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'request_logs';
    }

    public static function table_exists() {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema-check query (SHOW TABLES), not user data; must run uncached to reflect the live schema state.
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    public static function maybe_upgrade() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '');

        if ($installed_version === self::DB_VERSION && self::table_exists()) {
            return;
        }

        self::install();
    }

    public static function install() {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT '0',
            user_agent varchar(512) DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            session_id varchar(255) DEFAULT '',
            request_type varchar(20) NOT NULL DEFAULT 'page',
            path text,
            params longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY ip (ip),
            KEY created_at (created_at),
            KEY path (path(100)),
            KEY request_type (request_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        update_option('wp_sad_db_version', self::DB_VERSION); // сумісність зі старою назвою опції
    }
}
