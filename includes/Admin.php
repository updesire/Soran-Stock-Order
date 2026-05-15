<?php
namespace SSO;

defined('ABSPATH') || exit;

final class Admin {
	private $plugin;
	private $page_hook = '';

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
	}

	public function init(): void {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_filter('plugin_action_links_' . plugin_basename(SSO_FILE), [$this, 'add_action_links']);
	}

	public function add_action_links(array $links): array {
		$url = admin_url('admin.php?page=' . Plugin::PAGE_SLUG);
		$links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Settings') . '</a>';
		return $links;
	}

	public function register_menu(): void {
		$capability = $this->plugin->capability();

		if ($this->plugin->woocommerce_available()) {
			$this->page_hook = (string) add_submenu_page(
				'woocommerce',
				__('مرتب‌سازی موجودی', 'soran-stock-order'),
				__('مرتب‌سازی موجودی', 'soran-stock-order'),
				$capability,
				Plugin::PAGE_SLUG,
				[$this, 'render_settings_page']
			);
			return;
		}

		$this->page_hook = (string) add_options_page(
			__('مرتب‌سازی موجودی', 'soran-stock-order'),
			__('مرتب‌سازی موجودی', 'soran-stock-order'),
			$capability,
			Plugin::PAGE_SLUG,
			[$this, 'render_settings_page']
		);
	}

	public function enqueue_assets(string $hook): void {
		if ($this->page_hook === '' || $hook !== $this->page_hook) {
			return;
		}
		wp_enqueue_style('sso-admin', SSO_URL . 'assets/admin.css', [], SSO_VERSION);
	}

	private function sanitize_checkbox($value): int {
		return !empty($value) ? 1 : 0;
	}

	private function sanitize_rules_mode($value): string {
		$value = sanitize_key((string) $value);
		return in_array($value, ['exclude', 'include'], true) ? $value : 'exclude';
	}

	private function sanitize_taxonomy_slug($value): string {
		$value = sanitize_key((string) $value);
		return $value;
	}

	private function sanitize_csv_ids($value): array {
		if (is_array($value)) {
			$value = implode(',', array_map('strval', $value));
		}

		$raw = trim((string) $value);
		if ($raw === '') {
			return [];
		}

		$parts = preg_split('/[\s,;]+/', $raw);
		$out = [];
		foreach ((array) $parts as $part) {
			$id = (int) trim((string) $part);
			if ($id > 0) {
				$out[] = $id;
			}
		}

		$out = array_values(array_unique($out));
		sort($out);
		return $out;
	}

	private function sanitize_stock_priority($value): array {
		$allowed = ['instock', 'onbackorder', 'outofstock'];
		$default = ['instock', 'onbackorder', 'outofstock'];

		if (!is_array($value)) {
			return $default;
		}

		$out = [];
		foreach ($value as $item) {
			$key = sanitize_key((string) $item);
			if (in_array($key, $allowed, true)) {
				$out[] = $key;
			}
		}

		$out = array_values(array_unique($out));
		if (count($out) !== 3) {
			return $default;
		}

		return $out;
	}

	public function register_settings(): void {
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_ENABLE, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_OUTOFSTOCK_LAST, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_RESPECT_ORDERBY, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_APPLY_SHOP, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_APPLY_TAX, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_APPLY_TAG, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_APPLY_ALL, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 0,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_ONLY_MAIN_QUERY, [
			'type' => 'integer',
			'sanitize_callback' => [$this, 'sanitize_checkbox'],
			'default' => 1,
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_STOCK_PRIORITY, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_stock_priority'],
			'default' => ['instock', 'onbackorder', 'outofstock'],
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_RULES_MODE, [
			'type' => 'string',
			'sanitize_callback' => [$this, 'sanitize_rules_mode'],
			'default' => 'exclude',
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_EXCLUDE_PRODUCT_IDS, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_csv_ids'],
			'default' => [],
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_EXCLUDE_CAT_IDS, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_csv_ids'],
			'default' => [],
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_EXCLUDE_TAG_IDS, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_csv_ids'],
			'default' => [],
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_EXCLUDE_CUSTOM_TAX, [
			'type' => 'string',
			'sanitize_callback' => [$this, 'sanitize_taxonomy_slug'],
			'default' => '',
		]);
		register_setting(Plugin::OPTION_GROUP, Plugin::OPT_EXCLUDE_CUSTOM_TAX_TERM_IDS, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitize_csv_ids'],
			'default' => [],
		]);
	}

	private function checkbox_field(string $name, bool $checked): string {
		$out = '<input type="hidden" name="' . esc_attr($name) . '" value="0" />';
		$out .= '<label class="sso-switch"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked(true, $checked, false) . ' /><span class="sso-slider" aria-hidden="true"></span></label>';
		return $out;
	}

	private function stock_status_label(string $status): string {
		if ($status === 'instock') {
			return 'موجود';
		}
		if ($status === 'onbackorder') {
			return 'بک‌اوردر/پیش‌فروش';
		}
		if ($status === 'outofstock') {
			return 'ناموجود';
		}
		return $status;
	}

	private function stock_priority_field(array $priority): string {
		$allowed = ['instock', 'onbackorder', 'outofstock'];
		$priority = $this->sanitize_stock_priority($priority);

		$out = '<div class="sso-priority">';
		for ($i = 0; $i < 3; $i++) {
			$out .= '<div class="sso-priority__row">';
			$out .= '<div class="sso-priority__pos">' . esc_html((string) ($i + 1)) . '</div>';
			$out .= '<select class="sso-select" name="' . esc_attr(Plugin::OPT_STOCK_PRIORITY) . '[]">';
			foreach ($allowed as $status) {
				$out .= '<option value="' . esc_attr($status) . '"' . selected($priority[$i] ?? '', $status, false) . '>' . esc_html($this->stock_status_label($status)) . '</option>';
			}
			$out .= '</select>';
			$out .= '</div>';
		}
		$out .= '</div>';

		return $out;
	}

	private function input_field(string $name, string $value, string $placeholder = ''): string {
		$out = '<input class="sso-input" type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"';
		if ($placeholder !== '') {
			$out .= ' placeholder="' . esc_attr($placeholder) . '"';
		}
		$out .= ' />';
		return $out;
	}

	private function rules_mode_field(string $mode): string {
		$mode = $this->sanitize_rules_mode($mode);
		$out = '<select class="sso-select" name="' . esc_attr(Plugin::OPT_RULES_MODE) . '">';
		$out .= '<option value="exclude"' . selected($mode, 'exclude', false) . '>' . esc_html__('اعمال برای همه به جز لیست (Exclude)', 'soran-stock-order') . '</option>';
		$out .= '<option value="include"' . selected($mode, 'include', false) . '>' . esc_html__('فقط برای لیست مشخص (Only apply to listed)', 'soran-stock-order') . '</option>';
		$out .= '</select>';
		return $out;
	}

	private function ids_to_string($value): string {
		$value = is_array($value) ? $value : [];
		$ids = array_values(array_filter(array_map('intval', $value), static function ($id) {
			return $id > 0;
		}));
		return $ids ? implode(', ', $ids) : '';
	}

	public function render_settings_page(): void {
		if (!current_user_can($this->plugin->capability())) {
			return;
		}

		$enable = $this->plugin->get_bool_option(Plugin::OPT_ENABLE, 1);
		$outofstock_last = $this->plugin->get_bool_option(Plugin::OPT_OUTOFSTOCK_LAST, 1);
		$respect_orderby = $this->plugin->get_bool_option(Plugin::OPT_RESPECT_ORDERBY, 1);
		$apply_shop = $this->plugin->get_bool_option(Plugin::OPT_APPLY_SHOP, 1);
		$apply_tax = $this->plugin->get_bool_option(Plugin::OPT_APPLY_TAX, 1);
		$apply_tag = $this->plugin->get_bool_option(Plugin::OPT_APPLY_TAG, 1);
		$apply_all = $this->plugin->get_bool_option(Plugin::OPT_APPLY_ALL, 0);
		$only_main_query = $this->plugin->get_bool_option(Plugin::OPT_ONLY_MAIN_QUERY, 1);
		$stock_priority = get_option(Plugin::OPT_STOCK_PRIORITY, ['instock', 'onbackorder', 'outofstock']);
		$rules_mode = (string) get_option(Plugin::OPT_RULES_MODE, 'exclude');
		$exclude_products = get_option(Plugin::OPT_EXCLUDE_PRODUCT_IDS, []);
		$exclude_cats = get_option(Plugin::OPT_EXCLUDE_CAT_IDS, []);
		$exclude_tags = get_option(Plugin::OPT_EXCLUDE_TAG_IDS, []);
		$exclude_custom_tax = (string) get_option(Plugin::OPT_EXCLUDE_CUSTOM_TAX, '');
		$exclude_custom_tax_terms = get_option(Plugin::OPT_EXCLUDE_CUSTOM_TAX_TERM_IDS, []);

		echo '<div class="wrap sso-wrap">';
		echo '<div class="sso-header">';
		echo '<div>';
		echo '<h1 class="sso-title">' . esc_html__('مرتب‌سازی محصولات بر اساس موجودی', 'soran-stock-order') . '</h1>';
		echo '<div class="sso-subtitle">' . esc_html__('این پلاگین محصولات ناموجود را در انتهای لیست نمایش می‌دهد و برای جلوگیری از تداخل، می‌تواند فقط روی کوئری اصلی اعمال شود.', 'soran-stock-order') . '</div>';
		echo '</div>';
		echo '<div class="sso-badges">';
		echo '<span class="sso-badge">v' . esc_html(SSO_VERSION) . '</span>';
		echo '</div>';
		echo '</div>';

		if (!$this->plugin->woocommerce_available()) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('برای فعال شدن این پلاگین، ووکامرس باید نصب و فعال باشد.', 'soran-stock-order') . '</p></div>';
		}

		echo '<div class="sso-grid">';
		echo '<div class="sso-card sso-card--intro">';
		echo '<div class="sso-intro">';
		echo '<div class="sso-intro__icon" aria-hidden="true"><span class="dashicons dashicons-sort"></span></div>';
		echo '<div class="sso-intro__body">';
		echo '<div class="sso-intro__title">' . esc_html__('مرتب‌سازی محصولات سوران', 'soran-stock-order') . '</div>';
		echo '<div class="sso-intro__desc">' . esc_html__('یک ابزار سبک و کم‌ریسک برای اینکه محصولات ناموجود همیشه پایین‌تر از محصولات موجود نمایش داده شوند—بدون دستکاری در ادمین و با حداقل احتمال تداخل.', 'soran-stock-order') . '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sso-pills">';
		echo '<span class="sso-pill ' . ($enable ? 'sso-pill--on' : 'sso-pill--off') . '"><span class="dashicons ' . ($enable ? 'dashicons-yes-alt' : 'dashicons-no-alt') . '" aria-hidden="true"></span>' . esc_html($enable ? 'فعال' : 'غیرفعال') . '</span>';
		echo '<span class="sso-pill ' . ($only_main_query ? 'sso-pill--primary' : 'sso-pill--muted') . '"><span class="dashicons dashicons-filter" aria-hidden="true"></span>' . esc_html($only_main_query ? 'فقط کوئری اصلی' : 'همه کوئری‌ها') . '</span>';
		echo '<span class="sso-pill ' . ($respect_orderby ? 'sso-pill--muted' : 'sso-pill--warn') . '"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>' . esc_html($respect_orderby ? 'عدم دخالت در orderby' : 'اجبار مرتب‌سازی') . '</span>';
		echo '</div>';

		echo '<div class="sso-sep" role="separator" aria-hidden="true"></div>';

		echo '<div class="sso-note-title">' . esc_html__('نکته‌های کاربردی', 'soran-stock-order') . '</div>';
		echo '<ul class="sso-list sso-list--compact">';
		echo '<li>' . esc_html__('برای کمترین تداخل، گزینه “فقط روی کوئری اصلی” را روشن نگه دارید.', 'soran-stock-order') . '</li>';
		echo '<li>' . esc_html__('اگر کاربر/افزونه ترتیب (orderby) را تعیین می‌کند، بهتر است گزینه “عدم تغییر هنگام مرتب‌سازی کاربر” روشن باشد.', 'soran-stock-order') . '</li>';
		echo '</ul>';

		echo '<div class="sso-contact">';
		echo '<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>';
		echo '<a href="' . esc_url('mailto:updesire.com@gmail.com') . '">' . esc_html('updesire.com@gmail.com') . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sso-card">';
		echo '<h2 class="sso-card-title">' . esc_html__('تنظیمات', 'soran-stock-order') . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields(Plugin::OPTION_GROUP);
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__('فعال', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_ENABLE, $enable) . '<p class="description">' . esc_html__('اگر خاموش باشد، هیچ تغییری روی ترتیب نمایش محصولات اعمال نمی‌شود.', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('انتقال ناموجودها به انتها (حالت ساده)', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_OUTOFSTOCK_LAST, $outofstock_last) . '<p class="description">' . esc_html__('این گزینه برای حالت ساده است و بیشتر برای سازگاری استفاده می‌شود.', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('اولویت وضعیت موجودی', 'soran-stock-order') . '</th><td>' . $this->stock_priority_field((array) $stock_priority) . '<p class="description">' . esc_html__('ترتیب نمایش وضعیت‌ها را مشخص کنید (مثلاً موجود → بک‌اوردر/پیش‌فروش → ناموجود).', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('قوانین استثنا', 'soran-stock-order') . '</th><td>' . $this->rules_mode_field($rules_mode) . '<p class="description">' . esc_html__('اگر لیست‌ها را پر کنید، می‌توانید مشخص کنید این پلاگین روی کدام دسته/تگ/برند (یا لیست‌های خاص) اعمال شود یا نشود.', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('دسته‌های محصول (ID)', 'soran-stock-order') . '</th><td>' . $this->input_field(Plugin::OPT_EXCLUDE_CAT_IDS, $this->ids_to_string($exclude_cats), 'مثال: 12, 48, 77') . '<p class="description">' . esc_html__('شناسه دسته‌های محصول (product_cat).', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('تگ‌های محصول (ID)', 'soran-stock-order') . '</th><td>' . $this->input_field(Plugin::OPT_EXCLUDE_TAG_IDS, $this->ids_to_string($exclude_tags), 'مثال: 5, 9') . '<p class="description">' . esc_html__('شناسه تگ‌های محصول (product_tag).', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('محصولات (ID)', 'soran-stock-order') . '</th><td>' . $this->input_field(Plugin::OPT_EXCLUDE_PRODUCT_IDS, $this->ids_to_string($exclude_products), 'مثال: 101, 205') . '<p class="description">' . esc_html__('در صورت استفاده از کوئری‌هایی که post__in دارند (مثل برخی شورت‌کدها) قابل اعمال است.', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Taxonomy سفارشی (مثل برند)', 'soran-stock-order') . '</th><td>' . $this->input_field(Plugin::OPT_EXCLUDE_CUSTOM_TAX, $exclude_custom_tax, 'مثال: product_brand یا pa_brand') . '<p class="description">' . esc_html__('اسلاگ taxonomy را وارد کنید (در صورت نیاز).', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Termهای taxonomy سفارشی (ID)', 'soran-stock-order') . '</th><td>' . $this->input_field(Plugin::OPT_EXCLUDE_CUSTOM_TAX_TERM_IDS, $this->ids_to_string($exclude_custom_tax_terms), 'مثال: 3, 8') . '<p class="description">' . esc_html__('شناسه termها برای taxonomy سفارشی وارد شده.', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('عدم تغییر هنگام مرتب‌سازی کاربر', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_RESPECT_ORDERBY, $respect_orderby) . '<p class="description">' . esc_html__('وقتی کاربر/ویجت‌ها orderby می‌فرستند، پلاگین دخالت نمی‌کند.', 'soran-stock-order') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('فقط روی کوئری اصلی', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_ONLY_MAIN_QUERY, $only_main_query) . '<p class="description">' . esc_html__('پیشنهادی برای جلوگیری از تداخل با کوئری‌های سفارشی قالب/المنتور/فیلترها.', 'soran-stock-order') . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__('اعمال روی صفحه فروشگاه', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_APPLY_SHOP, $apply_shop) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('اعمال روی دسته‌بندی/صفت', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_APPLY_TAX, $apply_tax) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('اعمال روی برچسب', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_APPLY_TAG, $apply_tag) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('اعمال روی همه کوئری‌های محصول', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_APPLY_ALL, $apply_all) . '<p class="description">' . esc_html__('اگر روشن شود، روی هر کوئری محصول (حتی خارج از صفحات ووکامرس) هم اعمال می‌شود.', 'soran-stock-order') . '</p></td></tr>';

		echo '</table>';
		submit_button();
		echo '</form>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
}
