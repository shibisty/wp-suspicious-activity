<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var array $filters_view */
/** @var array $sort_view */
/** @var array $results_view */
/** @var int $total_view */
/** @var int $total_pages_view */
/** @var int $paged_view */

$base_url = admin_url('admin.php');
$page_slug = WP_SAD_Admin_Menu::SLUG_REQUEST_LOGS;
$query_args = [
    'page'         => $page_slug,
    'email'        => $filters_view['email'],
    'date_from'    => $filters_view['date_from'],
    'date_to'      => $filters_view['date_to'],
    'device'       => $filters_view['device'],
    'request_type' => $filters_view['request_type'],
    'user_type'    => $filters_view['user_type'],
];

$request_type_labels = [
    ''          => __('Всі типи', 'sharing-activity-detector'),
    'page'      => __('Сторінка', 'sharing-activity-detector'),
    'api'       => __('API', 'sharing-activity-detector'),
    'admin'     => __('Адмінка', 'sharing-activity-detector'),
    'heartbeat' => __('Heartbeat', 'sharing-activity-detector'),
];
?>
<div class="wrap wp-sad-wrapper">
    <h1 class="wp-heading-inline"><?php esc_html_e('Лог запитів', 'sharing-activity-detector'); ?></h1>

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
            <label for="sad-device"><?php esc_html_e('Пристрій (User-Agent містить):', 'sharing-activity-detector'); ?></label>
            <input type="text" id="sad-device" name="device" value="<?php echo esc_attr($filters_view['device']); ?>" placeholder="Chrome, iPhone...">
        </div>

        <div class="wp-sad-filter-group">
            <label for="sad-request-type"><?php esc_html_e('Тип запиту:', 'sharing-activity-detector'); ?></label>
            <select id="sad-request-type" name="request_type">
                <?php foreach ($request_type_labels as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters_view['request_type'], $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
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
            <strong><?php esc_html_e('За вказаними критеріями записів не знайдено.', 'sharing-activity-detector'); ?></strong>
        </div>
    <?php else: ?>
        <p style="margin: 10px 0; color: #666;">
            <?php esc_html_e('Знайдено записів:', 'sharing-activity-detector'); ?> <strong><?php echo esc_html(number_format_i18n($total_view)); ?></strong>
        </p>

        <table class="wp-sad-table">
            <thead>
                <tr>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('created_at', esc_html__('Час', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('user_email', esc_html__('Користувач', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally before returning.
                    echo WP_SAD_View_Helpers::render_sortable_header('ip', esc_html__('IP', 'sharing-activity-detector'), $sort_view, $query_args, $base_url);
                    ?>
                    <th><?php esc_html_e('Пристрій', 'sharing-activity-detector'); ?></th>
                    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_sortable_header() escapes the label and every dynamic value internally before returning. ?>
                    <?php echo WP_SAD_View_Helpers::render_sortable_header('request_type', esc_html__('Тип', 'sharing-activity-detector'), $sort_view, $query_args, $base_url); ?>
                    <th><?php esc_html_e('Шлях', 'sharing-activity-detector'); ?></th>
                    <th><?php esc_html_e('Дії', 'sharing-activity-detector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results_view as $row):
                    $view_url = add_query_arg([
                        'page' => WP_SAD_Admin_Menu::SLUG_LOG_VIEW,
                        'id'   => $row->id,
                    ], admin_url('admin.php'));
                ?>
                    <tr>
                        <td class="col-time"><?php echo esc_html($row->created_at); ?></td>
                        <td><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP_SAD_View_Helpers::render_user_link() escapes every dynamic value internally before returning. ?><?php echo WP_SAD_View_Helpers::render_user_link($row->user_id, $row->user_email, $row->display_name); ?></td>
                        <td><?php echo esc_html($row->ip); ?></td>
                        <td><?php echo esc_html(mb_substr($row->user_agent, 0, 40)); ?></td>
                        <td><span class="risk-badge risk-low"><?php echo esc_html($row->request_type); ?></span></td>
                        <td style="max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($row->path); ?></td>
                        <td><a href="<?php echo esc_url($view_url); ?>" class="button button-small button-view-logs" target="_blank"><?php esc_html_e('Переглянути', 'sharing-activity-detector'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php WP_SAD_View_Helpers::render_pagination($paged_view, $total_pages_view, $query_args, $base_url); ?>
    <?php endif; ?>
</div>
