<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Чиста логіка підрахунку сесій/таймлайну — без SQL для інших сутностей
 * і без HTML. Отримує сирі записи з wp_request_logs і рахує:
 *  - скільки часу користувач реально провів на сайті (сесійний метод,
 *    розрив > 5 хв = вихід),
 *  - скільки пристроїв працювало одночасно (для ризику "паралельна робота").
 */
class WP_SAD_Session_Analyzer {

    private $session_threshold_minutes = 5;

    /** Типи запитів, які вважаються реальним "сигналом" присутності. */
    private $signal_types = ['page', 'api', 'heartbeat'];

    public function get_user_sessions($user_id, $date_from, $date_to) {
        global $wpdb;
        $table = WP_SAD_DB::table_name();

        $sql = "SELECT id, created_at, user_agent, ip, path, params, request_type
                FROM {$table}
                WHERE user_id = %d
                  AND DATE(created_at) BETWEEN %s AND %s
                ORDER BY created_at ASC";

        $all_records = $wpdb->get_results($wpdb->prepare($sql, $user_id, $date_from, $date_to)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} comes from WP_SAD_DB::table_name() ($wpdb->prefix), not user input; every real value is bound via $wpdb->prepare() placeholders above.

        if (empty($all_records)) {
            return $this->empty_stats();
        }

        $records = $this->filter_signal_records($all_records);

        if (empty($records)) {
            return $this->empty_stats();
        }

        $by_agent = [];
        foreach ($records as $r) {
            $by_agent[$r->user_agent][] = $r;
        }

        $total_session_seconds = $this->calculate_session_time($records, $this->session_threshold_minutes);

        $device_session_seconds = 0;
        $device_stats = [];
        foreach ($by_agent as $agent => $agent_records) {
            $agent_seconds = $this->calculate_session_time($agent_records, $this->session_threshold_minutes);
            $device_session_seconds += $agent_seconds;
            $device_stats[$agent] = [
                'seconds' => $agent_seconds,
                'records' => $agent_records,
                'label'   => $this->shorten_user_agent($agent),
            ];
        }

        $timeline = $this->build_timeline($by_agent, $this->session_threshold_minutes, $date_from, $date_to);

        return [
            'total_session_mins'        => round($total_session_seconds / 60, 2),
            'total_device_session_mins' => round($device_session_seconds / 60, 2),
            'session_count'             => $timeline['session_count'],
            'general_gaps'              => $timeline['general_gaps'],
            'timeline_segments'         => $timeline['segments'],
            'timeline_start'            => $timeline['start_time'],
            'timeline_end'              => $timeline['end_time'],
            'range_start'               => $timeline['range_start'],
            'range_end'                 => $timeline['range_end'],
            'device_stats'              => $device_stats,
        ];
    }

    /**
     * ВИПРАВЛЕНО (баг з "маленькими відрізками часу" на таймлайні):
     * раніше тут використовувався array_filter($all_records,
     * 'is_wordpress_system_request') — ця функція повертала true для
     * СИСТЕМНИХ запитів (heartbeat/cron/update-actions), а array_filter
     * ЗАЛИШАЄ елементи, для яких колбек true. Тобто фільтр працював
     * навпаки до задуму: він залишав тільки системні/heartbeat-записи і
     * викидав майже всі реальні перегляди сторінок та API-запити. Звідси
     * й малі, розірвані відрізки на таймлайні при великій кількості
     * реальних запитів користувача.
     *
     * Тепер тип запиту явний (колонка request_type, проставляється в
     * WP_SAD_Request_Logger на запису), тож просто залишаємо реальний
     * сигнал присутності (page/api/heartbeat) і відкидаємо адмінку/шум.
     * Історичні рядки без явного типу мають DEFAULT 'page' — це
     * найближча коректна інтерпретація старих даних.
     */
    private function filter_signal_records($records) {
        return array_values(array_filter($records, function ($r) {
            $type = !empty($r->request_type) ? $r->request_type : 'page';
            return in_array($type, $this->signal_types, true);
        }));
    }

    public function calculate_session_time($records, $threshold_minutes) {
        if (count($records) < 2) {
            return 0;
        }

        $total_seconds = 0;
        $threshold_seconds = $threshold_minutes * 60;

        for ($i = 1; $i < count($records); $i++) {
            $prev_time = strtotime($records[$i - 1]->created_at);
            $curr_time = strtotime($records[$i]->created_at);
            $diff = $curr_time - $prev_time;

            if ($diff <= $threshold_seconds) {
                $total_seconds += $diff;
            }
        }

        return $total_seconds;
    }

    public function build_timeline($by_agent, $threshold_minutes, $date_from, $date_to) {
        if (empty($by_agent)) {
            return [
                'segments' => [], 'session_count' => 0, 'general_gaps' => 0,
                'start_time' => $date_from, 'end_time' => $date_to,
                'range_start' => 0, 'range_end' => 0,
            ];
        }

        $threshold_seconds = $threshold_minutes * 60;

        $range_start = strtotime($date_from . ' 00:00:00');
        $range_end = strtotime($date_to . ' 23:59:59');
        $total_span = $range_end - $range_start;

        if ($total_span <= 0) {
            return [
                'segments' => [], 'session_count' => 0, 'general_gaps' => 0,
                'start_time' => $date_from, 'end_time' => $date_to,
                'range_start' => $range_start, 'range_end' => $range_end,
            ];
        }

        $device_sessions = [];
        foreach ($by_agent as $agent => $agent_records) {
            $sessions = [];
            $current_session_start = strtotime($agent_records[0]->created_at);
            $current_session_end = $current_session_start;

            for ($i = 1; $i < count($agent_records); $i++) {
                $prev_time = strtotime($agent_records[$i - 1]->created_at);
                $curr_time = strtotime($agent_records[$i]->created_at);
                $diff = $curr_time - $prev_time;

                if ($diff <= $threshold_seconds) {
                    $current_session_end = $curr_time;
                } else {
                    $sessions[] = ['start' => $current_session_start, 'end' => $current_session_end, 'agent' => $agent];
                    $current_session_start = $curr_time;
                    $current_session_end = $curr_time;
                }
            }
            $sessions[] = ['start' => $current_session_start, 'end' => $current_session_end, 'agent' => $agent];

            $device_sessions[$agent] = $sessions;
        }

        $events = [];
        foreach ($device_sessions as $agent => $sessions) {
            foreach ($sessions as $session) {
                $events[] = ['time' => $session['start'], 'type' => 'start', 'agent' => $agent];
                $events[] = ['time' => $session['end'] + 1, 'type' => 'end', 'agent' => $agent];
            }
        }

        usort($events, function ($a, $b) {
            if ($a['time'] == $b['time']) {
                return $a['type'] == 'start' ? -1 : 1;
            }
            return $a['time'] - $b['time'];
        });

        $segments = [];
        $active_devices = [];
        $prev_time = $range_start;
        $session_count = 0;
        $general_gaps = 0;

        foreach ($events as $event) {
            if ($event['time'] < $range_start) {
                if ($event['type'] == 'start') {
                    $active_devices[] = $event['agent'];
                } else {
                    $active_devices = array_values(array_diff($active_devices, [$event['agent']]));
                }
                continue;
            }

            if ($event['time'] > $range_end) {
                break;
            }

            if ($event['time'] > $prev_time) {
                $segment_duration = $event['time'] - $prev_time;
                $width_percent = ($segment_duration / $total_span) * 100;

                if (!empty($active_devices)) {
                    $agent_list = implode(', ', array_map([$this, 'shorten_user_agent'], $active_devices));

                    $segments[] = [
                        'start_percent' => (($prev_time - $range_start) / $total_span) * 100,
                        'width_percent' => min($width_percent, 100),
                        'device_count'  => count($active_devices),
                        'time_range'    => gmdate('Y-m-d H:i', $prev_time) . ' - ' . gmdate('H:i', $event['time'] - 1),
                        'agent_list'    => $agent_list,
                        'is_gap'        => false,
                    ];
                } else {
                    $segments[] = [
                        'start_percent' => (($prev_time - $range_start) / $total_span) * 100,
                        'width_percent' => min($width_percent, 100),
                        'device_count'  => 0,
                        'time_range'    => gmdate('Y-m-d H:i', $prev_time) . ' - ' . gmdate('H:i', $event['time'] - 1),
                        'agent_list'    => __('Неактивний', 'suspicious-activity'),
                        'is_gap'        => true,
                    ];
                    $general_gaps++;
                }
            }

            if ($event['type'] == 'start') {
                if (empty($active_devices)) {
                    $session_count++;
                }
                $active_devices[] = $event['agent'];
            } else {
                $active_devices = array_values(array_diff($active_devices, [$event['agent']]));
            }

            $prev_time = $event['time'];
        }

        if ($prev_time < $range_end && $prev_time >= $range_start) {
            $segment_duration = $range_end - $prev_time;
            $width_percent = ($segment_duration / $total_span) * 100;

            if (!empty($active_devices)) {
                $agent_list = implode(', ', array_map([$this, 'shorten_user_agent'], $active_devices));

                $segments[] = [
                    'start_percent' => (($prev_time - $range_start) / $total_span) * 100,
                    'width_percent' => min($width_percent, 100),
                    'device_count'  => count($active_devices),
                    'time_range'    => gmdate('Y-m-d H:i', $prev_time) . ' - ' . gmdate('Y-m-d H:i', $range_end),
                    'agent_list'    => $agent_list,
                    'is_gap'        => false,
                ];
            } else {
                $segments[] = [
                    'start_percent' => (($prev_time - $range_start) / $total_span) * 100,
                    'width_percent' => min($width_percent, 100),
                    'device_count'  => 0,
                    'time_range'    => gmdate('Y-m-d H:i', $prev_time) . ' - ' . gmdate('Y-m-d H:i', $range_end),
                    'agent_list'    => __('Неактивний', 'suspicious-activity'),
                    'is_gap'        => true,
                ];
                $general_gaps++;
            }
        }

        return [
            'segments'      => $segments,
            'session_count' => $session_count,
            'general_gaps'  => $general_gaps,
            'start_time'    => $date_from,
            'end_time'      => $date_to,
            'range_start'   => $range_start,
            'range_end'     => $range_end,
        ];
    }

    public function calculate_risk($stats) {
        $parallel_time = round(max(0, $stats['total_device_session_mins'] - $stats['total_session_mins']), 2);

        if ($parallel_time >= 60) {
            return [
                'class'  => 'high',
                'label'  => __('Високий', 'suspicious-activity'),
                // translators: %s: number of minutes of parallel device activity.
                'reason' => sprintf(__('Паралельна робота пристроїв: %s хв', 'suspicious-activity'), $parallel_time),
            ];
        }

        if ($parallel_time >= 15) {
            return [
                'class'  => 'medium',
                'label'  => __('Середній', 'suspicious-activity'),
                // translators: %s: number of minutes of parallel device activity.
                'reason' => sprintf(__('Паралельна робота пристроїв: %s хв', 'suspicious-activity'), $parallel_time),
            ];
        }

        return [
            'class'  => 'low',
            'label'  => __('Низький', 'suspicious-activity'),
            'reason' => $parallel_time > 0
                // translators: %s: number of minutes of short parallel-device overlap.
                ? sprintf(__('Коротке перетинання: %s хв', 'suspicious-activity'), $parallel_time)
                : __('Без паралельної активності', 'suspicious-activity'),
        ];
    }

    public function shorten_user_agent($agent) {
        if (strpos($agent, 'Chrome') !== false) return 'Chrome';
        if (strpos($agent, 'Firefox') !== false) return 'Firefox';
        if (strpos($agent, 'Safari') !== false && strpos($agent, 'Chrome') === false) return 'Safari';
        if (strpos($agent, 'Mobile') !== false) return 'Mobile';
        if (strpos($agent, 'Android') !== false) return 'Android';
        if (strpos($agent, 'iPhone') !== false) return 'iPhone';
        if (strpos($agent, 'Windows') !== false) return 'Windows';
        if (strpos($agent, 'Mac') !== false) return 'Mac';
        if (strpos($agent, 'Linux') !== false) return 'Linux';

        return mb_substr($agent, 0, 25) . (mb_strlen($agent) > 25 ? '...' : '');
    }

    public function empty_stats() {
        return [
            'total_session_mins'        => 0,
            'total_device_session_mins' => 0,
            'session_count'             => 0,
            'general_gaps'              => 0,
            'timeline_segments'         => [],
            'timeline_start'            => '',
            'timeline_end'              => '',
            'range_start'               => 0,
            'range_end'                 => 0,
            'device_stats'              => [],
        ];
    }
}
