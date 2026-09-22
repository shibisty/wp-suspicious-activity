<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $filters_view */
/** @var array $sort_view */
/** @var array $debug_view */
/** @var array $results_view */
/** @var int $total_view */
/** @var int $total_pages_view */
/** @var int $paged_view */

$base_url = admin_url('admin.php');
$page_slug = WP_SAD_Admin_Menu::SLUG_ACTIVITY;
$query_args = [
    'page'      => $page_slug,
    'date_from' => $filters_view['date_from'],
    'date_to'   => $filters_view['date_to'],
    'min_ips'   => $filters_view['min_ips'],
    'email'     => $filters_view['email'],
    'user_type' => $filters_view['user_type'],
];
?>
<div class="wrap wp-sad-wrapper">
    <h1 class="wp-heading-inline"><?php esc_html_e('Детектор підозрілої активності', 'sharing-activity-detector'); ?></h1>

    <div class="wp-sad-info">
        <strong><?php esc_html_e('Логіка підрахунку часу (сесійний метод):', 'sharing-activity-detector'); ?></strong>
        <ul>
            <li><?php esc_html_e('Запити сортуються по часу. Якщо розрив між сусідніми запитами ≤ 5 хв — це одна сесія, час додається.', 'sharing-activity-detector'); ?></li>
            <li><?php esc_html_e('Якщо розрив > 5 хв — користувач вийшов, розрив НЕ рахується.', 'sharing-activity-detector'); ?></li>
            <li><?php esc_html_e('Heartbeat-запити забезпечують точний підрахунок часу, поки вкладка відкрита.', 'sharing-activity-detector'); ?></li>
            <li><?php esc_html_e('Діапазон = сума всіх активних проміжків (реальний час на сайті).', 'sharing-activity-detector'); ?></li>
            <li><?php esc_html_e('Сумарно по пристроях = сума активного часу кожного пристрою окремо.', 'sharing-activity-detector'); ?></li>
            <li><?php esc_html_e('Паралельний час = Сумарно − Діапазон. Якщо > 0, пристрої працювали одночасно.', 'sharing-activity-detector'); ?></li>
        </ul>
    </div>

    <div class="wp-sad-debug">
        <strong>🔍 <?php esc_html_e('Діагностика:', 'sharing-activity-detector'); ?></strong><br>
        • <?php esc_html_e('Таблиця:', 'sharing-activity-detector'); ?> <code><?php echo esc_html(WP_SAD_DB::table_name()); ?></code><br>
        • <?php esc_html_e('Всього записів:', 'sharing-activity-detector'); ?> <strong><?php echo esc_html(number_format_i18n($debug_view['total_in_table'])); ?></strong><br>
        • <?php esc_html_e('За період:', 'sharing-activity-detector'); ?> <strong><?php echo esc_html(number_format_i18n($debug_view['total_in_period'])); ?></strong><br>
        • <?php esc_html_e('Унікальних user_id:', 'sharing-activity-detector'); ?> <strong><?php echo esc_html(number_format_i18n($debug_view['unique_users'])); ?></strong><br>
        • <?php esc_html_e('Результатів запиту:', 'sharing-activity-detector'); ?> <strong><?php echo esc_html(count($results_view)); ?></strong>
        <?php if ($debug_view['sql_error']): ?>
            <br><span class="error">⚠️ <?php esc_html_e('Помилка SQL:', 'sharing-activity-detector'); ?> <?php echo esc_html($debug_view['sql_error']); ?></span>
        <?php endif; ?>
    </div>

    <form method="get" class="wp-sad-filters" data-sad-filters-form style="margin-top: 20px;">
        <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
        <input type="hidden" name="orderby" value="<?php echo esc_attr($sort_view['orderby']); ?>">
        <input type="hidden" name="order" value="<?php echo esc_attr($sort_view['order']); ?>">

        <div class="wp-sad-filter-group">
            <label for="sad-email"><?php esc_html_e('Email користувача:', 'sharing-activity-detector'); ?></label>
            <input type="email" id="sad-email" name="email" value="<?php echo esc_attr($filters_view['email']); ?>" placeholder="user@example.com">
        </div>

        <div class="wp-sad-filter-group">
            <label for="sad-date-from"><?php esc_html_e('Дата від:', 'sharing-activity-detector'); ?></label>
            <input type="date" id="sad-date-from" name="date_from" value="<?php echo esc_attr($filters_view['date_from']); ?>">
        </div>

        <div class="wp-sad-filter-group">
            <label for="sad-date-to"><?php esc_html_e('Дата до:', 'sharing-activity-detector'); ?></label>
            <input type="date" id="sad-date-to" name="date_to" value="<?php echo esc_attr($filters_view['date_to']); ?>">
        </div>

        <div class="wp-sad-filter-group">
            <label for="sad-min-ips"><?php esc_html_e('Мін. унікальних IP:', 'sharing-activity-detector'); ?></label>
            <input type="number" id="sad-min-ips" name="min_ips" value="<?php echo esc_attr($filters_view['min_ips']); ?>" min="1" max="100">
        </div>

        <div class="wp-sad-filter-group">
            <label for="sad-user-type"><?php esc_html_e('Тип користувача:', 'sharing-activity-detector'); ?></label>
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_user_type_filter() escapes every dynamic value internally before returning. ?>
            <?php echo WP_SAD_View_Helpers::render_user_type_filter($filters_view['user_type']); ?>
        </div>

        <button type="submit" class="button"><?php esc_html_e('Застосувати фільтри', 'sharing-activity-detector'); ?></button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug)); ?>" class="button wp-sad-reset-filters"><?php esc_html_e('Скинути', 'sharing-activity-detector'); ?></a>
    </form>

    <?php if (empty($results_view)): ?>
        <div class="wp-sad-empty">
            <strong><?php esc_html_e('За вказаними критеріями даних не знайдено.', 'sharing-activity-detector'); ?></strong><br><br>
            <?php esc_html_e('Перевірте діагностику вище.', 'sharing-activity-detector'); ?>
        </div>
    <?php else: ?>
        <p style="margin: 10px 0; color: #666;">
            <?php esc_html_e('Знайдено користувачів:', 'sharing-activity-detector'); ?> <strong><?php echo esc_html(number_format_i18n($total_view)); ?></strong>
        </p>

        <table class="wp-sad-table">
            <thead>
                <tr>
                    <th class="col-user"><?php esc_html_e('Користувач', 'sharing-activity-detector'); ?></th>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally (esc_html()/esc_url()) before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('unique_ips', esc_html__('IP', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally (esc_html()/esc_url()) before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('unique_agents', esc_html__('Пристрої', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally (esc_html()/esc_url()) before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('total_requests', esc_html__('Запити', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally (esc_html()/esc_url()) before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('course_requests', esc_html__('Курси', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    ?>
                    <th class="col-time"><?php esc_html_e('Діапазон (хв)', 'sharing-activity-detector'); ?></th>
                    <th class="col-time"><?php esc_html_e('Сумарно (хв)', 'sharing-activity-detector'); ?></th>
                    <th class="col-time"><?php esc_html_e('Паралельно (хв)', 'sharing-activity-detector'); ?></th>
                    <th><?php esc_html_e('Ризик', 'sharing-activity-detector'); ?></th>
                    <th><?php esc_html_e('Дії', 'sharing-activity-detector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results_view as $row):
                    $stats = $row->session_stats;
                    $risk = $row->risk;
                    $parallel_time = round(max(0, $stats['total_device_session_mins'] - $stats['total_session_mins']), 2);
                    $has_parallel = $parallel_time > 0;

                    $activity_view_url = add_query_arg([
                        'page'      => WP_SAD_Admin_Menu::SLUG_ACTIVITY_VIEW,
                        'user_id'   => $row->user_id,
                        'date_from' => $filters_view['date_from'],
                        'date_to'   => $filters_view['date_to'],
                    ], admin_url('admin.php'));

                    $logs_url = add_query_arg([
                        'page'      => WP_SAD_Admin_Menu::SLUG_REQUEST_LOGS,
                        'email'     => $row->user_email,
                        'date_from' => $filters_view['date_from'],
                        'date_to'   => $filters_view['date_to'],
                    ], admin_url('admin.php'));
                ?>
                    <tr>
                        <td>
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_user_link() escapes every dynamic value internally (esc_html()/esc_url()) before returning. ?>
                            <?php echo WP_SAD_View_Helpers::render_user_link($row->user_id, $row->user_email, $row->display_name); ?>
                            <?php if (!empty($stats['timeline_segments'])): ?>
                                <div class="timeline-container">
                                    <div class="timeline-header">
                                        <span><?php esc_html_e('Часова шкала', 'sharing-activity-detector'); ?></span>
                                        <div>
                                            <span class="timeline-zoom-level">100%</span>
                                            <span class="timeline-toggle"><?php esc_html_e('Показати ▼', 'sharing-activity-detector'); ?></span>
                                        </div>
                                    </div>
                                    <div class="timeline-content active">
                                        <div class="timeline-wrapper">
                                            <div class="timeline-zoom-controls">
                                                <button class="timeline-zoom-btn zoom-out" title="<?php esc_attr_e('Зменшити', 'sharing-activity-detector'); ?>">−</button>
                                                <button class="timeline-zoom-btn zoom-reset" title="<?php esc_attr_e('Скинути', 'sharing-activity-detector'); ?>">⌂</button>
                                                <button class="timeline-zoom-btn zoom-in" title="<?php esc_attr_e('Збільшити', 'sharing-activity-detector'); ?>">+</button>
                                            </div>
                                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_timeline() escapes every dynamic value internally (esc_html()/esc_attr()) before returning. ?>
                                            <?php echo WP_SAD_View_Helpers::render_timeline($stats); ?>
                                        </div>
                                        <div class="timeline-time-labels">
                                            <span class="time-start"><?php echo esc_html($stats['timeline_start']); ?></span>
                                            <span class="time-end"><?php echo esc_html($stats['timeline_end']); ?></span>
                                        </div>
                                    </div>
                                    <div class="session-info">
                                        <?php
                                        // translators: %1$d: number of sessions; %2$d: number of gaps longer than 5 minutes.
                                        echo esc_html(sprintf(__('Сесій: %1$d | Розривів > 5 хв: %2$d', 'sharing-activity-detector'), $stats['session_count'], $stats['general_gaps']));
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="col-num"><?php echo intval($row->unique_ips); ?></td>
                        <td class="col-num"><?php echo intval($row->unique_agents); ?></td>
                        <td class="col-num"><?php echo esc_html(number_format_i18n($row->total_requests)); ?></td>
                        <td class="col-num"><strong><?php echo esc_html(number_format_i18n($row->course_requests)); ?></strong></td>
                        <td class="col-time"><?php echo $stats['total_session_mins'] > 0 ? esc_html($stats['total_session_mins']) : '&lt; 1'; ?></td>
                        <td class="col-time"><strong><?php echo $stats['total_device_session_mins'] > 0 ? esc_html($stats['total_device_session_mins']) : '&lt; 1'; ?></strong></td>
                        <td class="col-time">
                            <?php if ($has_parallel): ?>
                                <strong style="color: #d63638;"><?php echo esc_html($parallel_time); ?></strong>
                                <span class="parallel-badge" title="<?php esc_attr_e('Пристрої працювали одночасно', 'sharing-activity-detector'); ?>">🟡</span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="risk-badge risk-<?php echo esc_attr($risk['class']); ?>" title="<?php echo esc_attr($risk['reason']); ?>">
                                <?php echo esc_html($risk['label']); ?>
                            </span>
                            <?php if ($risk['class'] !== 'low'): ?>
                                <div style="font-size: 10px; color: #666; margin-top: 3px;"><?php echo esc_html($risk['reason']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($activity_view_url); ?>" class="button button-small button-view-logs" target="_blank"><?php esc_html_e('Перегляд', 'sharing-activity-detector'); ?></a>
                            <a href="<?php echo esc_url($logs_url); ?>" class="button button-small button-view-logs" target="_blank"><?php esc_html_e('Логи', 'sharing-activity-detector'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php WP_SAD_View_Helpers::render_pagination($paged_view, $total_pages_view, $query_args, $base_url); ?>
    <?php endif; ?>
</div>

<div id="timeline-tooltip" class="timeline-tooltip"></div>
