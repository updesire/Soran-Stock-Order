# Soran Stock Order

Soran Stock Order is a lightweight WordPress plugin that improves WooCommerce product listing UX by pushing **out-of-stock** products to the end of the product loop. It is designed to be low-risk and avoid conflicts by optionally limiting its behavior to the **main query** only.

## Requirements

- WordPress 5.6+
- PHP 7.2+
- WooCommerce

## Installation

1. Copy the plugin folder to:
   - `wp-content/plugins/soran-stock-order`
2. Activate it from:
   - WordPress Admin → Plugins
3. Configure settings from:
   - WooCommerce → “مرتب‌سازی موجودی” (or Settings → “مرتب‌سازی موجودی” if WooCommerce is not available)

## How It Works

The plugin hooks into WordPress query clauses and adds an `ORDER BY` expression that prioritizes in-stock products over out-of-stock products using the `_stock_status` post meta.

## Settings

- **Enable**: turn the behavior on/off.
- **Move out-of-stock to the end**: if disabled, out-of-stock items can be moved to the beginning.
- **Respect user sorting (orderby)**: do not modify sorting when a custom orderby is requested.
- **Only main query (Recommended)**: apply only to the main WooCommerce loop to minimize conflicts with page builders and custom queries.
- **Apply on shop / taxonomy / tag**: limit where the behavior is applied.
- **Apply to all product queries**: forces behavior across any product query (use with caution).

## Conflict-Avoidance Notes

- Admin assets are loaded only on the plugin settings page.
- A dedicated SQL join alias is used to reduce collision risk with other plugins.
- “Only main query” is enabled by default to avoid affecting custom loops.

## Support

- Email: updesire.com@gmail.com

## License

No license file is included in this repository. Add a license if you plan to distribute this plugin.
