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
		$is_main = $query->is_main_query();
		if ($this->plugin->get_bool_option(Plugin::OPT_ONLY_MAIN_QUERY, 1) && !$is_main) {
			return false;
		}

		if (!$this->passes_rules($query)) {
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

		$post_type = $query->get('post_type');
		$is_product_query = $post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true));
		$apply_all = $this->plugin->get_bool_option(Plugin::OPT_APPLY_ALL, 0);
		if ($apply_all && $is_product_query) {
			return true;
		}

		$apply_shortcodes_blocks = $this->plugin->get_bool_option(Plugin::OPT_APPLY_SHORTCODES_BLOCKS, 0);
		$only_wc_loops = $this->plugin->get_bool_option(Plugin::OPT_ONLY_WOOCOMMERCE_LOOPS, 1);
		if (!$is_main && $apply_shortcodes_blocks && $is_product_query) {
			return $only_wc_loops ? $this->is_woocommerce_loop_query($query) : true;
		}

		if (!$is_main) {
			return false;
		}

		$apply_search = $this->plugin->get_bool_option(Plugin::OPT_APPLY_SEARCH, 0);
		if ($apply_search && !empty($query->is_search) && $is_product_query) {
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

	private function is_woocommerce_loop_query(\WP_Query $query): bool {
		$wc_query = (string) $query->get('wc_query');
		if ($wc_query === 'product_query') {
			return true;
		}

		$tax_query = $query->get('tax_query');
		if (is_array($tax_query)) {
			foreach ($tax_query as $item) {
				if (!is_array($item)) {
					continue;
				}
				$tax = isset($item['taxonomy']) ? sanitize_key((string) $item['taxonomy']) : '';
				if ($tax === 'product_visibility') {
					return true;
				}
			}
		}

		$post__in = $query->get('post__in');
		if (is_array($post__in) && $post__in && $query->get('fields') === 'ids') {
			return true;
		}

		return false;
	}

	private function passes_rules(\WP_Query $query): bool {
		$mode = get_option(Plugin::OPT_RULES_MODE, 'exclude');
		$mode = in_array($mode, ['exclude', 'include'], true) ? $mode : 'exclude';

		$cat_ids = get_option(Plugin::OPT_EXCLUDE_CAT_IDS, []);
		$tag_ids = get_option(Plugin::OPT_EXCLUDE_TAG_IDS, []);
		$product_ids = get_option(Plugin::OPT_EXCLUDE_PRODUCT_IDS, []);
		$custom_tax = (string) get_option(Plugin::OPT_EXCLUDE_CUSTOM_TAX, '');
		$custom_tax_term_ids = get_option(Plugin::OPT_EXCLUDE_CUSTOM_TAX_TERM_IDS, []);

		$cat_ids = array_values(array_filter(array_map('intval', (array) $cat_ids)));
		$tag_ids = array_values(array_filter(array_map('intval', (array) $tag_ids)));
		$product_ids = array_values(array_filter(array_map('intval', (array) $product_ids)));
		$custom_tax_term_ids = array_values(array_filter(array_map('intval', (array) $custom_tax_term_ids)));
		$custom_tax = sanitize_key($custom_tax);

		$has_rules = !empty($cat_ids) || !empty($tag_ids) || !empty($product_ids) || ($custom_tax !== '' && !empty($custom_tax_term_ids));
		if (!$has_rules) {
			return true;
		}

		$matched = false;

		if ($this->matches_current_term('product_cat', $cat_ids)) {
			$matched = true;
		}
		if (!$matched && $this->matches_current_term('product_tag', $tag_ids)) {
			$matched = true;
		}
		if (!$matched && $custom_tax !== '' && $this->matches_current_term($custom_tax, $custom_tax_term_ids)) {
			$matched = true;
		}
		if (!$matched && $product_ids) {
			$post__in = $query->get('post__in');
			if (is_array($post__in) && $post__in) {
				$in_ids = array_values(array_filter(array_map('intval', $post__in)));
				$matched = (bool) array_intersect($in_ids, $product_ids);
			}
		}

		if ($mode === 'exclude') {
			return !$matched;
		}

		return $matched;
	}

	private function matches_current_term(string $taxonomy, array $ids): bool {
		$taxonomy = sanitize_key($taxonomy);
		$ids = array_values(array_filter(array_map('intval', (array) $ids)));
		if ($taxonomy === '' || !$ids) {
			return false;
		}

		$is_main_tax = false;
		if (function_exists('is_tax')) {
			$is_main_tax = is_tax($taxonomy);
		}
		if (!$is_main_tax) {
			return false;
		}

		$obj = function_exists('get_queried_object') ? get_queried_object() : null;
		if (!($obj instanceof \WP_Term)) {
			return false;
		}
		if ((string) $obj->taxonomy !== $taxonomy) {
			return false;
		}

		return in_array((int) $obj->term_id, $ids, true);
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

		$priority = get_option(Plugin::OPT_STOCK_PRIORITY, null);
		$allowed = ['instock', 'onbackorder', 'outofstock'];
		$priority_list = [];
		if (is_array($priority)) {
			foreach ($priority as $item) {
				$key = sanitize_key((string) $item);
				if (in_array($key, $allowed, true)) {
					$priority_list[] = $key;
				}
			}
			$priority_list = array_values(array_unique($priority_list));
		}
		if (count($priority_list) !== 3) {
			$priority_list = ['instock', 'onbackorder', 'outofstock'];
			if (!$this->plugin->get_bool_option(Plugin::OPT_OUTOFSTOCK_LAST, 1)) {
				$priority_list = ['outofstock', 'instock', 'onbackorder'];
			}
		}

		$cases = [];
		foreach ($priority_list as $i => $status) {
			$cases[] = "WHEN {$alias}.meta_value = '{$status}' THEN " . (int) $i;
		}
		$order_expr = 'CASE ' . implode(' ', $cases) . ' ELSE 3 END';
		$current_orderby = trim((string) ($clauses['orderby'] ?? ''));
		$clauses['orderby'] = $order_expr . ' ASC' . ($current_orderby !== '' ? ', ' . $current_orderby : '');

		return $clauses;
	}
}
