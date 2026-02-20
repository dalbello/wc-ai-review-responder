<?php
/**
 * Plugin Name: WC AI Review Responder
 * Plugin URI: https://tinyship.ai/plugins/wc-ai-review-responder/
 * Description: Generate professional, SEO-friendly WooCommerce review replies in one click.
 * Version: 1.0.0
 * Author: TinyShip
 * Author URI: https://tinyship.ai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-review-responder
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WC_AI_Review_Responder {
    private const NONCE_ACTION = 'wc_airr_reply_all';
    private const NOTICE_KEY = 'wc_airr_notice';

    public function __construct() {
        add_action('restrict_manage_comments', [$this, 'render_reply_all_button']);
        add_action('admin_post_wc_airr_reply_all', [$this, 'handle_reply_all']);
        add_action('admin_notices', [$this, 'render_admin_notice']);
    }

    public function render_reply_all_button(): void {
        if (!is_admin() || !current_user_can('moderate_comments')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-comments') {
            return;
        }

        $comment_type = isset($_GET['comment_type']) ? sanitize_text_field(wp_unslash($_GET['comment_type'])) : '';
        if ($comment_type !== 'review') {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=wc_airr_reply_all'),
            self::NONCE_ACTION
        );

        echo '<a href="' . esc_url($url) . '" class="button button-primary" style="margin-left:8px;">';
        echo esc_html__('Reply All with AI', 'wc-ai-review-responder');
        echo '</a>';
    }

    public function handle_reply_all(): void {
        if (!current_user_can('moderate_comments')) {
            wp_die(esc_html__('You do not have permission to do that.', 'wc-ai-review-responder'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $reviews = get_comments([
            'type'       => 'review',
            'status'     => 'approve',
            'post_type'  => 'product',
            'number'     => 200,
            'orderby'    => 'comment_date_gmt',
            'order'      => 'ASC',
        ]);

        $generated = 0;

        foreach ($reviews as $review) {
            if ($this->review_has_reply((int) $review->comment_ID)) {
                continue;
            }

            $reply_content = $this->generate_reply($review);

            $inserted = wp_insert_comment([
                'comment_post_ID'      => (int) $review->comment_post_ID,
                'comment_parent'       => (int) $review->comment_ID,
                'comment_content'      => $reply_content,
                'comment_type'         => '',
                'comment_approved'     => 1,
                'user_id'              => get_current_user_id(),
                'comment_author'       => wp_get_current_user()->display_name,
                'comment_author_email' => wp_get_current_user()->user_email,
            ]);

            if ($inserted) {
                $generated++;
            }
        }

        set_transient(self::NOTICE_KEY, $generated, 60);

        wp_safe_redirect(admin_url('edit-comments.php?comment_type=review'));
        exit;
    }

    private function review_has_reply(int $review_id): bool {
        $children = get_comments([
            'parent' => $review_id,
            'status' => 'approve',
            'number' => 1,
        ]);

        return !empty($children);
    }

    private function generate_reply(WP_Comment $review): string {
        $rating = (int) get_comment_meta($review->comment_ID, 'rating', true);
        $product_title = get_the_title($review->comment_post_ID);
        $customer_name = trim((string) $review->comment_author);
        $customer_name = $customer_name !== '' ? $customer_name : __('there', 'wc-ai-review-responder');

        $content = strtolower((string) $review->comment_content);
        $negative_keywords = ['bad', 'poor', 'broken', 'slow', 'terrible', 'disappointed', 'refund', 'not happy', 'awful'];
        $is_negative_language = false;
        foreach ($negative_keywords as $word) {
            if (strpos($content, $word) !== false) {
                $is_negative_language = true;
                break;
            }
        }

        $is_positive = $rating >= 4 && !$is_negative_language;

        if ($is_positive) {
            return sprintf(
                __('Thanks so much for your review, %1$s! We\'re thrilled you\'re enjoying %2$s. Your feedback means a lot to our team and helps other shoppers buy with confidence. We appreciate your support!', 'wc-ai-review-responder'),
                $customer_name,
                $product_title
            );
        }

        return sprintf(
            __('Thank you for your honest feedback, %1$s. We\'re sorry %2$s didn\'t fully meet expectations this time. We\'d love to make this right — please reply to this thread or contact our support team so we can help quickly.', 'wc-ai-review-responder'),
            $customer_name,
            $product_title
        );
    }

    public function render_admin_notice(): void {
        $count = get_transient(self::NOTICE_KEY);

        if ($count === false) {
            return;
        }

        delete_transient(self::NOTICE_KEY);

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html(sprintf(__('WC AI Review Responder generated %d review replie(s).', 'wc-ai-review-responder'), (int) $count));
        echo '</p></div>';
    }
}

new WC_AI_Review_Responder();
