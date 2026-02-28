=== WC AI Review Responder ===
Contributors: tinyship
Tags: woocommerce, ai, reviews, customer support
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One-click AI replies for WooCommerce product reviews directly inside wp-admin.

== Description ==
WC AI Review Responder adds an **AI Reply** action next to each WooCommerce product review in the admin comments screen.

Features:
- One-click AI reply per review (no copy/paste workflow)
- OpenAI-powered responses using your API key
- Brand voice setting so replies sound like your store
- Smart fallback templates if API is unavailable
- Works in native WooCommerce review moderation flow

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP via Plugins > Add New.
2. Activate **WC AI Review Responder**.
3. Go to **WooCommerce > AI Review Responder** and add your OpenAI API key.
4. Set your preferred brand voice.
5. Open **Comments** and filter by **Reviews**. Click **AI Reply** on any review.

== Frequently Asked Questions ==
= Does this auto-reply to all reviews? =
No. It is intentionally one-click-per-review for editorial control.

= Which model should I use? =
Default is `gpt-4o-mini`. You can change it in plugin settings.

= What if OpenAI fails? =
The plugin falls back to built-in polite templates so your workflow never blocks.

== Changelog ==
= 1.1.0 =
* Added one-click inline AI reply action per review
* Added WooCommerce settings page for API key, model, and brand voice
* Added OpenAI API integration with fallback templates

== Upgrade Notice ==
= 1.1.0 =
Major upgrade from bulk-reply flow to per-review AI action and settings.
