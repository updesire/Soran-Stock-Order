<?php
/*
Plugin Name: مرتب کردن لیست محصولات
Description: انتقال محصولات ناموجود به انتهای لیست با تنظیمات قابل مدیریت.
Version: 1.3.0
Author: سوران
Author URI: https://soraun.com/
Requires at least: 5.6
Requires PHP: 7.2
Text Domain: soran-stock-order
*/

defined('ABSPATH') || exit;

define('SSO_VERSION', '1.3.0');
define('SSO_FILE', __FILE__);
define('SSO_DIR', plugin_dir_path(__FILE__));
define('SSO_URL', plugin_dir_url(__FILE__));

require_once SSO_DIR . 'includes/Plugin.php';
require_once SSO_DIR . 'includes/Admin.php';
require_once SSO_DIR . 'includes/Sorter.php';

add_action('plugins_loaded', static function () {
	SSO\Plugin::instance()->init();
});
