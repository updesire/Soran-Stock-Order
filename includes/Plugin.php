<?php
namespace SSO;

defined('ABSPATH') || exit;

final class Plugin {
	public const OPTION_GROUP = 'soran_stock_order';
	public const PAGE_SLUG = 'soran-stock-order';

	public const OPT_ENABLE = 'soran_stock_order_enable';
	public const OPT_OUTOFSTOCK_LAST = 'soran_stock_order_outofstock_last';
	public const OPT_RESPECT_ORDERBY = 'soran_stock_order_respect_orderby';
	public const OPT_APPLY_SHOP = 'soran_stock_order_apply_shop';
	public const OPT_APPLY_TAX = 'soran_stock_order_apply_tax';
	public const OPT_APPLY_TAG = 'soran_stock_order_apply_tag';
	public const OPT_APPLY_ALL = 'soran_stock_order_apply_all';
	public const OPT_ONLY_MAIN_QUERY = 'soran_stock_order_only_main_query';
	public const OPT_STOCK_PRIORITY = 'soran_stock_order_stock_priority';
	public const OPT_RULES_MODE = 'soran_stock_order_rules_mode';
	public const OPT_EXCLUDE_PRODUCT_IDS = 'soran_stock_order_exclude_product_ids';
	public const OPT_EXCLUDE_CAT_IDS = 'soran_stock_order_exclude_cat_ids';
	public const OPT_EXCLUDE_TAG_IDS = 'soran_stock_order_exclude_tag_ids';
	public const OPT_EXCLUDE_CUSTOM_TAX = 'soran_stock_order_exclude_custom_taxonomy';
	public const OPT_EXCLUDE_CUSTOM_TAX_TERM_IDS = 'soran_stock_order_exclude_custom_taxonomy_term_ids';

	private static $instance;
	private $admin;
	private $sorter;

	public static function instance(): self {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action('init', [$this, 'load_textdomain']);

		$this->admin = new Admin($this);
		$this->sorter = new Sorter($this);

		$this->admin->init();
		$this->sorter->init();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain('soran-stock-order', false, dirname(plugin_basename(SSO_FILE)) . '/languages');
	}

	public function woocommerce_available(): bool {
		return class_exists('WooCommerce') && function_exists('WC') && post_type_exists('product');
	}

	public function capability(): string {
		return function_exists('wc_admin_connect_page') ? 'manage_woocommerce' : 'manage_options';
	}

	public function get_bool_option(string $key, int $default): bool {
		return (int) get_option($key, $default) === 1;
	}
}
