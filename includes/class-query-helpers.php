<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Спільний SQL-шматок для фільтра "Всі / Зареєстровані / Адміністратори",
 * що використовується і на сторінці Активності, і на сторінці Логів.
 */
class WP_SAD_Query_Helpers {

    /**
     * @param string $user_type  all|registered|admins
     * @param string $user_id_col  повний вираз колонки user_id у запиті, напр. "l.user_id"
     * @return array{join:string, where:string}
     */
    public static function user_type_sql($user_type, $user_id_col) {
        global $wpdb;

        switch ($user_type) {
            case 'registered':
                return [
                    'join'  => '',
                    'where' => "{$user_id_col} > 0",
                ];

            case 'admins':
                $capabilities_key = $wpdb->prefix . 'capabilities';
                return [
                    'join'  => $wpdb->prepare(
                        "INNER JOIN {$wpdb->usermeta} sad_um ON sad_um.user_id = {$user_id_col} AND sad_um.meta_key = %s",
                        $capabilities_key
                    ),
                    'where' => "sad_um.meta_value LIKE '%\"administrator\"%'",
                ];

            case 'all':
            default:
                return ['join' => '', 'where' => ''];
        }
    }

    public static function where_or_and($existing_where_sql) {
        return $existing_where_sql ? 'AND' : 'WHERE';
    }
}
