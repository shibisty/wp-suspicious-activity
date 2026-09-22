<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Спільні шматки розмітки, які використовують кілька сторінок
 * адмінки (посилання на користувача, сортовані заголовки, пагінація,
 * таймлайн). Тут немає SQL і немає бізнес-логіки — лише форматування.
 */
class WP_SAD_View_Helpers {

    public static function render_user_link($user_id, $email, $display_name = '') {
        $user_id = intval($user_id);
        $label = esc_html($email ?: ($user_id ? ('ID: ' . $user_id) : __('Гість', 'sharing-activity-detector')));

        if ($user_id > 0) {
            $edit_link = get_edit_user_link($user_id);
            if ($edit_link) {
                $label = '<a href="' . esc_url($edit_link) . '" target="_blank">' . $label . '</a>';
            }
        }

        $html = '<strong>' . $label . '</strong>';
        if ($display_name) {
            $html .= '<br><span style="color:#666; font-size:11px;">' . esc_html($display_name) . '</span>';
        }

        return $html;
    }

    public static function render_sortable_header($column, $label, $sort, $query_args, $base_url) {
        $is_current = ($sort['orderby'] === $column);
        $order = $is_current && $sort['order'] === 'ASC' ? 'DESC' : 'ASC';
        $arrow = '';

        if ($is_current) {
            $arrow = $sort['order'] === 'ASC' ? ' ↑' : ' ↓';
        }

        $url = add_query_arg(array_merge($query_args, [
            'orderby' => $column,
            'order'   => $order,
        ]), $base_url);

        return '<th class="col-num sortable" onclick="window.location.href=\'' . esc_url($url) . '\'">'
             . esc_html($label) . '<span class="sort-arrow">' . $arrow . '</span></th>';
    }

    public static function render_pagination($current, $total, $query_args, $base_url) {
        if ($total <= 1) {
            return;
        }

        $max_links = 5;
        $start = max(1, $current - (int) floor($max_links / 2));
        $end = min($total, $start + $max_links - 1);
        $start = max(1, $end - $max_links + 1);

        echo '<div class="tablenav-pages">';

        if ($current > 1) {
            $url = add_query_arg(array_merge($query_args, ['paged' => $current - 1]), $base_url);
            echo '<a class="prev page-numbers" href="' . esc_url($url) . '">' . esc_html__('‹ Назад', 'sharing-activity-detector') . '</a>';
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $current) {
                echo '<span aria-current="page" class="page-numbers current">' . esc_html($i) . '</span>';
            } else {
                $url = add_query_arg(array_merge($query_args, ['paged' => $i]), $base_url);
                echo '<a class="page-numbers" href="' . esc_url($url) . '">' . esc_html($i) . '</a>';
            }
        }

        if ($current < $total) {
            $url = add_query_arg(array_merge($query_args, ['paged' => $current + 1]), $base_url);
            echo '<a class="next page-numbers" href="' . esc_url($url) . '">' . esc_html__('Вперед ›', 'sharing-activity-detector') . '</a>';
        }

        echo '</div>';
    }

    public static function render_user_type_filter($selected) {
        $options = [
            'all'        => __('Всі користувачі', 'sharing-activity-detector'),
            'registered' => __('Зареєстровані', 'sharing-activity-detector'),
            'admins'     => __('Адміністратори', 'sharing-activity-detector'),
        ];

        $html = '<select id="sad-user-type" name="user_type">';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    public static function render_timeline($stats) {
        if (empty($stats['timeline_segments'])) {
            return '<p style="font-size: 11px; color: #666;">' . esc_html__('Недостатньо даних', 'sharing-activity-detector') . '</p>';
        }

        $html = '<div class="timeline-bar" data-range-start="' . intval($stats['range_start']) . '" data-range-end="' . intval($stats['range_end']) . '">';

        foreach ($stats['timeline_segments'] as $segment) {
            if ($segment['is_gap']) {
                // translators: %s: the gap's time range, e.g. "2026-09-20 14:03 - 14:05".
                $gap_title = sprintf(__('Розрив: %s', 'sharing-activity-detector'), $segment['time_range']);
                $html .= '<div class="timeline-gap" style="left: ' . esc_attr($segment['start_percent']) . '%; width: ' . esc_attr($segment['width_percent']) . '%;" '
                       . 'data-original-left="' . esc_attr($segment['start_percent']) . '" '
                       . 'data-original-width="' . esc_attr($segment['width_percent']) . '" '
                       . 'title="' . esc_attr($gap_title) . '"></div>';
            } else {
                $color = WP_SAD_Session_Analyzer_Color::for_device_count($segment['device_count']);
                $html .= '<div class="timeline-segment" style="left: ' . esc_attr($segment['start_percent']) . '%; width: ' . esc_attr($segment['width_percent']) . '%; background: ' . esc_attr($color) . ';" '
                       . 'data-original-left="' . esc_attr($segment['start_percent']) . '" '
                       . 'data-original-width="' . esc_attr($segment['width_percent']) . '" '
                       . 'data-devices="' . esc_attr($segment['device_count']) . '" '
                       . 'data-time="' . esc_attr($segment['time_range']) . '" '
                       . 'data-agent_list="' . esc_attr($segment['agent_list']) . '"></div>';
            }
        }

        $html .= '</div>';

        $html .= '<div class="timeline-legend">';
        $html .= '<div class="legend-item"><div class="legend-color" style="background: #4a90e2;"></div>' . esc_html__('1 пристрій', 'sharing-activity-detector') . '</div>';
        $html .= '<div class="legend-item"><div class="legend-color" style="background: #f5a623;"></div>' . esc_html__('2 пристрої', 'sharing-activity-detector') . '</div>';
        $html .= '<div class="legend-item"><div class="legend-color" style="background: #d0021b;"></div>' . esc_html__('3+ пристрої', 'sharing-activity-detector') . '</div>';
        $html .= '<div class="legend-item"><div class="legend-color" style="background: repeating-linear-gradient(45deg, #ddd, #ddd 5px, #f5f5f5 5px, #f5f5f5 10px);"></div>' . esc_html__('Розрив (користувач вийшов)', 'sharing-activity-detector') . '</div>';
        $html .= '</div>';

        return $html;
    }
}

/**
 * Дрібний хелпер лише для кольору сегмента таймлайна (щоб не тягнути
 * сюди весь Session_Analyzer заради однієї статичної функції).
 */
class WP_SAD_Session_Analyzer_Color {
    public static function for_device_count($device_count) {
        if ($device_count <= 1) return '#4a90e2';
        if ($device_count == 2) return '#f5a623';
        if ($device_count == 3) return '#d0021b';
        return '#8b0000';
    }
}
