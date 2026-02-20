=== WC AI Review Responder ===
Contributors: tinyship
Tags: woocommerce, reviews, ai, customer-service, ecommerce
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate professional WooCommerce review replies in one click.

== Description ==

WC AI Review Responder adds a **Reply All with AI** button to WooCommerce product reviews in wp-admin.

When clicked, it:

* Finds approved product reviews that do not already have a reply
* Generates a tailored response based on rating + review sentiment
* Creates a merchant reply under each review automatically

Great for stores that want fast, consistent, professional review management.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wc-ai-review-responder/`, or install via the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Comments → Reviews** (`comment_type=review`) in wp-admin.
4. Click **Reply All with AI**.

== Frequently Asked Questions ==

= Does this send data to third-party AI services? =

No. Version 1.0.0 generates responses locally inside WordPress.

= Will it overwrite existing replies? =

No. It only replies to reviews that currently have no reply.

== Changelog ==

= 1.0.0 =
* Initial release
* One-click bulk review replies in WooCommerce
* Positive/negative response logic
