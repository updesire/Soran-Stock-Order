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
	}

	private function checkbox_field(string $name, bool $checked): string {
		$out = '<input type="hidden" name="' . esc_attr($name) . '" value="0" />';
		$out .= '<label class="sso-switch"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked(true, $checked, false) . ' /><span class="sso-slider" aria-hidden="true"></span></label>';
		return $out;
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
		echo '<tr><th scope="row">' . esc_html__('انتقال ناموجودها به انتها', 'soran-stock-order') . '</th><td>' . $this->checkbox_field(Plugin::OPT_OUTOFSTOCK_LAST, $outofstock_last) . '<p class="description">' . esc_html__('اگر خاموش باشد، ناموجودها به ابتدای لیست منتقل می‌شوند.', 'soran-stock-order') . '</p></td></tr>';
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
