<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $settings_view */
/** @var array $languages_view */
/** @var bool $saved_view */
/** @var string $nonce_action_view */
?>
<div class="wrap wp-sad-wrapper">
    <h1 class="wp-heading-inline"><?php esc_html_e('Налаштування підозрілої активності', 'wp-suspicious-activity'); ?></h1>

    <?php if ($saved_view): ?>
        <div class="notice notice-success" style="margin-top:15px;"><p><?php esc_html_e('Налаштування збережено.', 'wp-suspicious-activity'); ?></p></div>
    <?php endif; ?>

    <form method="post" style="max-width:720px; margin-top:20px;">
        <?php wp_nonce_field($nonce_action_view, 'wp_sad_settings_nonce'); ?>

        <h2><?php esc_html_e('Хто підпадає під логування', 'wp-suspicious-activity'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Аудиторія', 'wp-suspicious-activity'); ?></th>
                <td>
                    <label><input type="radio" name="log_audience" value="all" <?php checked($settings_view['log_audience'], 'all'); ?>> <?php esc_html_e('Логувати для всіх (включно з гостями)', 'wp-suspicious-activity'); ?></label><br>
                    <label><input type="radio" name="log_audience" value="registered" <?php checked($settings_view['log_audience'], 'registered'); ?>> <?php esc_html_e('Тільки для зареєстрованих користувачів', 'wp-suspicious-activity'); ?></label><br>
                    <label><input type="radio" name="log_audience" value="admins" <?php checked($settings_view['log_audience'], 'admins'); ?>> <?php esc_html_e('Тільки для адміністраторів', 'wp-suspicious-activity'); ?></label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Що логувати', 'wp-suspicious-activity'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Діапазон логування', 'wp-suspicious-activity'); ?></th>
                <td>
                    <label><input type="radio" name="log_scope" value="all" <?php checked($settings_view['log_scope'], 'all'); ?>> <?php esc_html_e('Всі сторінки, API та адмінка (максимальна видимість)', 'wp-suspicious-activity'); ?></label><br>
                    <label><input type="radio" name="log_scope" value="pages_heartbeat" <?php checked($settings_view['log_scope'], 'pages_heartbeat'); ?>> <?php esc_html_e('Тільки сторінки та heartbeat', 'wp-suspicious-activity'); ?></label><br>
                    <label><input type="radio" name="log_scope" value="site_no_admin_heartbeat" <?php checked($settings_view['log_scope'], 'site_no_admin_heartbeat'); ?>> <?php esc_html_e('Тільки сайт без адмінки та heartbeat', 'wp-suspicious-activity'); ?></label><br>
                    <label><input type="radio" name="log_scope" value="admin_heartbeat" <?php checked($settings_view['log_scope'], 'admin_heartbeat'); ?>> <?php esc_html_e('Тільки адмінка та heartbeat', 'wp-suspicious-activity'); ?></label><br>
                    <label><input type="radio" name="log_scope" value="off" <?php checked($settings_view['log_scope'], 'off'); ?>> <?php esc_html_e('Перестати логувати', 'wp-suspicious-activity'); ?></label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Коли логувати', 'wp-suspicious-activity'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Проміжок часу', 'wp-suspicious-activity'); ?></th>
                <td>
                    <label><input type="radio" name="log_window_mode" value="always" <?php checked($settings_view['log_window_mode'], 'always'); ?>> <?php esc_html_e('Завжди', 'wp-suspicious-activity'); ?></label><br>
                    <label>
                        <input type="radio" name="log_window_mode" value="scheduled" <?php checked($settings_view['log_window_mode'], 'scheduled'); ?>>
                        <?php esc_html_e('З', 'wp-suspicious-activity'); ?>
                        <input type="time" name="log_window_from" value="<?php echo esc_attr($settings_view['log_window_from']); ?>">
                        <?php esc_html_e('по', 'wp-suspicious-activity'); ?>
                        <input type="time" name="log_window_to" value="<?php echo esc_attr($settings_view['log_window_to']); ?>">
                    </label>
                    <p class="description"><?php esc_html_e('Час доби сайту. Проміжок, що перетинає північ (напр. 22:00–06:00), підтримується.', 'wp-suspicious-activity'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Heartbeat', 'wp-suspicious-activity'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Стан', 'wp-suspicious-activity'); ?></th>
                <td>
                    <label><input type="checkbox" name="heartbeat_enabled" value="1" <?php checked(!empty($settings_view['heartbeat_enabled'])); ?>> <?php esc_html_e('Увімкнути heartbeat (пінг, поки вкладка відкрита)', 'wp-suspicious-activity'); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Інтервал (сек)', 'wp-suspicious-activity'); ?></th>
                <td><input type="number" name="heartbeat_interval" min="15" max="600" value="<?php echo esc_attr($settings_view['heartbeat_interval']); ?>"></td>
            </tr>
        </table>

        <h2><?php esc_html_e('Мова інтерфейсу плагіна', 'wp-suspicious-activity'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Мова', 'wp-suspicious-activity'); ?></th>
                <td>
                    <select name="admin_language">
                        <?php foreach ($languages_view as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($settings_view['admin_language'], $code); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('До списку входять мовні пакети WordPress, встановлені на сайті, а також переклади, вбудовані в цей плагін.', 'wp-suspicious-activity'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Зберегти налаштування', 'wp-suspicious-activity')); ?>
    </form>
</div>
