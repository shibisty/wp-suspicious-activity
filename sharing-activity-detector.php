<?php
/**
 * Plugin Name: Sharing Activity Detector by Shibisty
 * Description: Detection of suspicious activity with an interactive timeline, request log, and heartbeat.
 * Version: 5.0.0
 * Author: Alexander Shibisty
 * Text Domain: sharing-activity-detector
 * Domain Path: /languages
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_SAD_VERSION', '5.0.0');
define('WP_SAD_PLUGIN_FILE', __FILE__);
define('WP_SAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SAD_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Довідково: історичний DDL, з якого була створена таблиця wp_request_logs.
 * Фактичне створення/оновлення таблиці виконує WP_SAD_DB::install() —
 * дивись includes/class-db.php. Ніколи не запускати цей блок вручну,
 * він лишений тут лише як документація структури.
 *
 * CREATE TABLE IF NOT EXISTS `wp_request_logs` (
 *   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 *   `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
 *   `user_agent` varchar(512) DEFAULT '',
 *   `ip` varchar(45) NOT NULL DEFAULT '',
 *   `session_id` varchar(255) DEFAULT '',
 *   `request_type` varchar(20) NOT NULL DEFAULT 'page',
 *   `path` text,
 *   `params` longtext,
 *   `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   PRIMARY KEY (`id`),
 *   KEY `user_id` (`user_id`),
 *   KEY `ip` (`ip`),
 *   KEY `created_at` (`created_at`),
 *   KEY `request_type` (`request_type`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 */

require_once WP_SAD_PLUGIN_DIR . 'includes/class-db.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-settings.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-session-analyzer.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-request-logger.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-heartbeat.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-view-helpers.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-query-helpers.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-admin-menu.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/admin/class-page-activity.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/admin/class-page-request-logs.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/admin/class-page-log-view.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/admin/class-page-activity-view.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/admin/class-page-settings.php';
require_once WP_SAD_PLUGIN_DIR . 'includes/class-plugin.php';

new WP_SAD_Plugin();
