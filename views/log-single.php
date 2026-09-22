<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var object|null $log_view */
/** @var array $neighbors_view */

$back_url = admin_url('admin.php?page=' . WP_SAD_Admin_Menu::SLUG_REQUEST_LOGS);
?>
<div class="wrap wp-sad-wrapper">
    <h1 class="wp-heading-inline"><?php esc_html_e('Перегляд логу', 'sharing-activity-detector'); ?></h1>
    <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php esc_html_e('← До списку логів', 'sharing-activity-detector'); ?></a>

    <?php if (!$log_view): ?>
        <div class="wp-sad-empty" style="margin-top:20px;">
            <strong><?php esc_html_e('Запис не знайдено.', 'sharing-activity-detector'); ?></strong>
        </div>
    <?php else: ?>
        <table class="wp-sad-table" style="margin-top:20px; max-width:900px;">
            <tbody>
                <tr><th style="width:200px;"><?php esc_html_e('ID запису', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($log_view->id); ?></td></tr>
                <tr><th><?php esc_html_e('Дата й час', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($log_view->created_at); ?></td></tr>
                <tr><th><?php esc_html_e('Користувач', 'sharing-activity-detector'); ?></th><td><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_user_link() escapes every dynamic value internally before returning. ?><?php echo WP_SAD_View_Helpers::render_user_link($log_view->user_id, $log_view->user_email, $log_view->display_name); ?></td></tr>
                <tr><th><?php esc_html_e('Тип запиту', 'sharing-activity-detector'); ?></th><td><span class="risk-badge risk-low"><?php echo esc_html($log_view->request_type); ?></span></td></tr>
                <tr><th><?php esc_html_e('IP-адреса', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($log_view->ip); ?></td></tr>
                <tr><th><?php esc_html_e('Session ID', 'sharing-activity-detector'); ?></th><td><code><?php echo esc_html($log_view->session_id); ?></code></td></tr>
                <tr><th><?php esc_html_e('User-Agent', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($log_view->user_agent); ?></td></tr>
                <tr><th><?php esc_html_e('Шлях', 'sharing-activity-detector'); ?></th><td><code><?php echo esc_html($log_view->path); ?></code></td></tr>
                <tr>
                    <th><?php esc_html_e('Параметри', 'sharing-activity-detector'); ?></th>
                    <td><pre style="white-space:pre-wrap; word-break:break-all; margin:0;"><?php
                        $decoded = json_decode($log_view->params, true);
                        echo esc_html($decoded !== null ? wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $log_view->params);
                    ?></pre></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($neighbors_view)): ?>
            <h2 style="margin-top:30px;"><?php esc_html_e('Сусідні запити цього користувача (±15 хв)', 'sharing-activity-detector'); ?></h2>
            <table class="wp-sad-table" style="max-width:900px;">
                <thead>
                    <tr>
                        <th class="col-time"><?php esc_html_e('Час', 'sharing-activity-detector'); ?></th>
                        <th><?php esc_html_e('Тип', 'sharing-activity-detector'); ?></th>
                        <th><?php esc_html_e('Шлях', 'sharing-activity-detector'); ?></th>
                        <th><?php esc_html_e('Дії', 'sharing-activity-detector'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($neighbors_view as $n):
                        $n_url = add_query_arg(['page' => WP_SAD_Admin_Menu::SLUG_LOG_VIEW, 'id' => $n->id], admin_url('admin.php'));
                    ?>
                        <tr>
                            <td class="col-time"><?php echo esc_html($n->created_at); ?></td>
                            <td><span class="risk-badge risk-low"><?php echo esc_html($n->request_type); ?></span></td>
                            <td><?php echo esc_html($n->path); ?></td>
                            <td><a href="<?php echo esc_url($n_url); ?>" class="button button-small"><?php esc_html_e('Переглянути', 'sharing-activity-detector'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
