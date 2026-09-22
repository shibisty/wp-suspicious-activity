<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var WP_User|false $user_view */
/** @var int $user_id_view */
/** @var string $date_from_view */
/** @var string $date_to_view */
/** @var array $stats_view */
/** @var array $risk_view */

$back_url = admin_url('admin.php?page=' . WP_SAD_Admin_Menu::SLUG_ACTIVITY);
$logs_url = add_query_arg([
    'page'      => WP_SAD_Admin_Menu::SLUG_REQUEST_LOGS,
    'email'     => $user_view ? $user_view->user_email : '',
    'date_from' => $date_from_view,
    'date_to'   => $date_to_view,
], admin_url('admin.php'));

$parallel_time = round(max(0, $stats_view['total_device_session_mins'] - $stats_view['total_session_mins']), 2);
?>
<div class="wrap wp-sad-wrapper">
    <h1 class="wp-heading-inline"><?php esc_html_e('Перегляд активності', 'sharing-activity-detector'); ?></h1>
    <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php esc_html_e('← До списку активності', 'sharing-activity-detector'); ?></a>

    <?php if (!$user_view && !$user_id_view): ?>
        <div class="wp-sad-empty" style="margin-top:20px;">
            <strong><?php esc_html_e('Користувача не вказано.', 'sharing-activity-detector'); ?></strong>
        </div>
    <?php else: ?>
        <form method="get" style="margin: 15px 0;">
            <input type="hidden" name="page" value="<?php echo esc_attr(WP_SAD_Admin_Menu::SLUG_ACTIVITY_VIEW); ?>">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id_view); ?>">
            <div class="wp-sad-filters" style="margin-top:0;">
                <div class="wp-sad-filter-group">
                    <label><?php esc_html_e('Дата від:', 'sharing-activity-detector'); ?></label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from_view); ?>">
                </div>
                <div class="wp-sad-filter-group">
                    <label><?php esc_html_e('Дата до:', 'sharing-activity-detector'); ?></label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to_view); ?>">
                </div>
                <button type="submit" class="button"><?php esc_html_e('Застосувати фільтри', 'sharing-activity-detector'); ?></button>
            </div>
        </form>

        <table class="wp-sad-table" style="max-width:900px;">
            <tbody>
                <tr><th style="width:220px;"><?php esc_html_e('Користувач', 'sharing-activity-detector'); ?></th><td><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_user_link() escapes every dynamic value internally before returning. ?><?php echo WP_SAD_View_Helpers::render_user_link($user_id_view, $user_view ? $user_view->user_email : '', $user_view ? $user_view->display_name : ''); ?></td></tr>
                <tr><th><?php esc_html_e('Період', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($date_from_view . ' — ' . $date_to_view); ?></td></tr>
                <tr><th><?php esc_html_e('Сесій', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($stats_view['session_count']); ?></td></tr>
                <tr><th><?php esc_html_e('Розривів > 5 хв', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($stats_view['general_gaps']); ?></td></tr>
                <tr><th><?php esc_html_e('Діапазон (хв)', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($stats_view['total_session_mins']); ?></td></tr>
                <tr><th><?php esc_html_e('Сумарно по пристроях (хв)', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($stats_view['total_device_session_mins']); ?></td></tr>
                <tr><th><?php esc_html_e('Паралельно (хв)', 'sharing-activity-detector'); ?></th><td><?php echo esc_html($parallel_time); ?></td></tr>
                <tr>
                    <th><?php esc_html_e('Ризик', 'sharing-activity-detector'); ?></th>
                    <td>
                        <span class="risk-badge risk-<?php echo esc_attr($risk_view['class']); ?>"><?php echo esc_html($risk_view['label']); ?></span>
                        <span style="margin-left:8px; color:#666; font-size:12px;"><?php echo esc_html($risk_view['reason']); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Дії', 'sharing-activity-detector'); ?></th>
                    <td><a href="<?php echo esc_url($logs_url); ?>" class="button" target="_blank"><?php esc_html_e('Показати всі логи цього користувача', 'sharing-activity-detector'); ?></a></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($stats_view['timeline_segments'])): ?>
            <div class="timeline-container" style="max-width:1100px; margin-top:20px;">
                <div class="timeline-header">
                    <span><?php esc_html_e('Часова шкала', 'sharing-activity-detector'); ?></span>
                    <div><span class="timeline-zoom-level">100%</span></div>
                </div>
                <div class="timeline-content active">
                    <div class="timeline-wrapper">
                        <div class="timeline-zoom-controls">
                            <button class="timeline-zoom-btn zoom-out" title="<?php esc_attr_e('Зменшити', 'sharing-activity-detector'); ?>">−</button>
                            <button class="timeline-zoom-btn zoom-reset" title="<?php esc_attr_e('Скинути', 'sharing-activity-detector'); ?>">⌂</button>
                            <button class="timeline-zoom-btn zoom-in" title="<?php esc_attr_e('Збільшити', 'sharing-activity-detector'); ?>">+</button>
                        </div>
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_timeline() escapes every dynamic value internally before returning. ?>
                        <?php echo WP_SAD_View_Helpers::render_timeline($stats_view); ?>
                    </div>
                    <div class="timeline-time-labels">
                        <span class="time-start"><?php echo esc_html($stats_view['timeline_start']); ?></span>
                        <span class="time-end"><?php echo esc_html($stats_view['timeline_end']); ?></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p style="margin-top:20px; color:#666;"><?php esc_html_e('Немає даних для побудови таймлайну за обраний період.', 'sharing-activity-detector'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div id="timeline-tooltip" class="timeline-tooltip"></div>
