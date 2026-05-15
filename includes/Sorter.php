<?php
namespace SSO;

defined('ABSPATH') || exit;

final class Sorter {
	private $plugin;

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function init(): void {
		add_filter('posts_clauses', [$this, 'filter_posts_clauses'], 999, 2);
	}

	private function should_apply(\WP_Query $query): bool {
		if (!$this->plugin->woocommerce_available()) {
			return false;
		}
		if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
			return false;
		}
		if (!$this->plugin->get_bool_option(Plugin::OPT_ENABLE, 1)) {
			return false;
		}
		if ($this->plugin->get_bool_option(Plugin::OPT_ONLY_MAIN_QUERY, 1) && !$query->is_main_query()) {
			return false;
		}

		if ($this->plugin->get_bool_option(Plugin::OPT_RESPECT_ORDERBY, 1)) {
			$q_orderby = $query->get('orderby');
			if (!empty($q_orderby)) {
				return false;
			}
			if (isset($_GET['orderby']) && (string) $_GET['orderby'] !== '') {
				return false;
			}
		}

		$apply_all = $this->plugin->get_bool_option(Plugin::OPT_APPLY_ALL, 0);
		$post_type = $query->get('post_type');
		$is_product_query = $post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true));
		if ($apply_all && $is_product_query) {
			return true;
		}

		if (!function_exists('is_woocommerce') || !is_woocommerce()) {
			return false;
		}

		$apply_shop = $this->plugin->get_bool_option(Plugin::OPT_APPLY_SHOP, 1);
		$apply_tax = $this->plugin->get_bool_option(Plugin::OPT_APPLY_TAX, 1);
		$apply_tag = $this->plugin->get_bool_option(Plugin::OPT_APPLY_TAG, 1);

		if ($apply_shop && function_exists('is_shop') && is_shop()) {
			return true;
		}
		if ($apply_tax && function_exists('is_product_category') && is_product_category()) {
			return true;
		}
		if ($apply_tag && function_exists('is_product_tag') && is_product_tag()) {
			return true;
		}
		if ($apply_tax && function_exists('is_product_taxonomy') && is_product_taxonomy()) {
			return true;
		}

		return false;
	}

	public function filter_posts_clauses(array $clauses, $query): array {
		if (!($query instanceof \WP_Query)) {
			return $clauses;
		}
		if (!$this->should_apply($query)) {
			return $clauses;
		}

		$clauses = wp_parse_args($clauses, [
			'join' => '',
			'orderby' => '',
		]);

		global $wpdb;
		$alias = 'sso_stock';
		$join = (string) ($clauses['join'] ?? '');
		if (strpos($join, ' ' . $alias . ' ') === false) {
			$clauses['join'] = $join . " LEFT JOIN {$wpdb->postmeta} {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = '_stock_status') ";
		}

		$direction = $this->plugin->get_bool_option(Plugin::OPT_OUTOFSTOCK_LAST, 1) ? 'ASC' : 'DESC';
		$order_expr = "CASE WHEN {$alias}.meta_value = 'outofstock' THEN 1 ELSE 0 END";
		$current_orderby = trim((string) ($clauses['orderby'] ?? ''));
		$clauses['orderby'] = $order_expr . ' ' . $direction . ($current_orderby !== '' ? ', ' . $current_orderby : '');

		return $clauses;
	}
}

