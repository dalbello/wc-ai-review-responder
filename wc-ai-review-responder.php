<?php
/**
 * Plugin Name: WC AI Review Responder
 * Plugin URI: https://tinyship.ai/plugins/wc-ai-review-responder/
 * Description: Adds one-click AI-powered reply generation for WooCommerce product reviews directly in wp-admin.
 * Version: 1.1.0
 * Author: TinyShip
 * Author URI: https://tinyship.ai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-review-responder
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WC_AI_Review_Responder {
    private const OPT_API_KEY = 'wc_airr_openai_api_key';
    private const OPT_MODEL = 'wc_airr_openai_model';
    private const OPT_BRAND_VOICE = 'wc_airr_brand_voice';
    private const NONCE_ACTION = 'wc_airr_generate_reply';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('comment_row_actions', [$this, 'add_ai_reply_action'], 10, 2);
        add_action('wp_ajax_wc_airr_generate_reply', [$this, 'ajax_generate_reply']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_settings_page(): void {
        add_submenu_page(
            'woocommerce',
            __('AI Review Responder', 'wc-ai-review-responder'),
            __('AI Review Responder', 'wc-ai-review-responder'),
            'manage_woocommerce',
            'wc-ai-review-responder',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting('wc_airr_settings_group', self::OPT_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting('wc_airr_settings_group', self::OPT_MODEL, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-4o-mini',
        ]);

        register_setting('wc_airr_settings_group', self::OPT_BRAND_VOICE, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => __('Friendly, concise, and human. No hype. Offer help if the review is negative.', 'wc-ai-review-responder'),
        ]);
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WC AI Review Responder', 'wc-ai-review-responder'); ?></h1>
            <p><?php esc_html_e('Set your brand voice once, then generate review replies with one click from the WooCommerce Reviews screen.', 'wc-ai-review-responder'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('wc_airr_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wc_airr_openai_api_key"><?php esc_html_e('OpenAI API Key', 'wc-ai-review-responder'); ?></label></th>
                        <td>
                            <input type="password" id="wc_airr_openai_api_key" name="<?php echo esc_attr(self::OPT_API_KEY); ?>" class="regular-text" value="<?php echo esc_attr((string) get_option(self::OPT_API_KEY, '')); ?>" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wc_airr_openai_model"><?php esc_html_e('Model', 'wc-ai-review-responder'); ?></label></th>
                        <td>
                            <input type="text" id="wc_airr_openai_model" name="<?php echo esc_attr(self::OPT_MODEL); ?>" class="regular-text" value="<?php echo esc_attr((string) get_option(self::OPT_MODEL, 'gpt-4o-mini')); ?>" />
                            <p class="description"><?php esc_html_e('Example: gpt-4o-mini', 'wc-ai-review-responder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wc_airr_brand_voice"><?php esc_html_e('Brand Voice', 'wc-ai-review-responder'); ?></label></th>
                        <td>
                            <textarea id="wc_airr_brand_voice" name="<?php echo esc_attr(self::OPT_BRAND_VOICE); ?>" class="large-text" rows="5"><?php echo esc_textarea((string) get_option(self::OPT_BRAND_VOICE, '')); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'wc-ai-review-responder')); ?>
            </form>
        </div>
        <?php
    }

    public function add_ai_reply_action(array $actions, WP_Comment $comment): array {
        if (!is_admin() || !current_user_can('edit_comment', $comment->comment_ID)) {
            return $actions;
        }

        if (!$this->is_woocommerce_review($comment)) {
            return $actions;
        }

        $nonce = wp_create_nonce(self::NONCE_ACTION . '_' . $comment->comment_ID);
        $label = esc_html__('AI Reply', 'wc-ai-review-responder');

        $actions['wc_airr'] = sprintf(
            '<a href="#" class="wc-airr-generate" data-comment-id="%1$d" data-nonce="%2$s">%3$s</a>',
            (int) $comment->comment_ID,
            esc_attr($nonce),
            $label
        );

        return $actions;
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'edit-comments.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-comments') {
            return;
        }

        wp_add_inline_script('jquery-core', $this->admin_js());
    }

    public function ajax_generate_reply(): void {
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wc-ai-review-responder')], 403);
        }

        $comment_id = isset($_POST['comment_id']) ? absint($_POST['comment_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!$comment_id || !wp_verify_nonce($nonce, self::NONCE_ACTION . '_' . $comment_id)) {
            wp_send_json_error(['message' => __('Invalid request.', 'wc-ai-review-responder')], 400);
        }

        $comment = get_comment($comment_id);
        if (!$comment || !$this->is_woocommerce_review($comment)) {
            wp_send_json_error(['message' => __('Review not found.', 'wc-ai-review-responder')], 404);
        }

        if ($this->has_reply($comment_id)) {
            wp_send_json_error(['message' => __('This review already has a reply.', 'wc-ai-review-responder')], 409);
        }

        $reply = $this->generate_reply($comment);

        $inserted = wp_insert_comment([
            'comment_post_ID' => (int) $comment->comment_post_ID,
            'comment_parent' => (int) $comment->comment_ID,
            'comment_content' => $reply,
            'comment_type' => '',
            'comment_approved' => 1,
            'user_id' => get_current_user_id(),
            'comment_author' => wp_get_current_user()->display_name,
            'comment_author_email' => wp_get_current_user()->user_email,
        ]);

        if (!$inserted) {
            wp_send_json_error(['message' => __('Could not save reply.', 'wc-ai-review-responder')], 500);
        }

        wp_send_json_success(['message' => __('Reply posted.', 'wc-ai-review-responder')]);
    }

    private function generate_reply(WP_Comment $review): string {
        $review_text = trim((string) $review->comment_content);
        $rating = (int) get_comment_meta($review->comment_ID, 'rating', true);
        $product_title = get_the_title($review->comment_post_ID);
        $brand_voice = (string) get_option(self::OPT_BRAND_VOICE, '');
        $api_key = trim((string) get_option(self::OPT_API_KEY, ''));
        $model = trim((string) get_option(self::OPT_MODEL, 'gpt-4o-mini'));

        if ($api_key !== '') {
            $generated = $this->generate_with_openai($api_key, $model, $brand_voice, $review_text, $rating, $product_title);
            if ($generated !== '') {
                return $generated;
            }
        }

        return $this->fallback_reply($review_text, $rating, $product_title);
    }

    private function generate_with_openai(string $api_key, string $model, string $brand_voice, string $review_text, int $rating, string $product_title): string {
        $system_prompt = "You write short, professional ecommerce customer support replies to product reviews. Keep it under 90 words. Never mention AI.\nBrand voice: {$brand_voice}";
        $user_prompt = "Product: {$product_title}\nRating: {$rating}/5\nReview: {$review_text}\n\nWrite a public merchant reply.";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt],
                ],
                'temperature' => 0.4,
            ]),
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return '';
        }

        $reply = $body['choices'][0]['message']['content'] ?? '';

        return is_string($reply) ? trim($reply) : '';
    }

    private function fallback_reply(string $review_text, int $rating, string $product_title): string {
        if ($rating >= 4) {
            return sprintf(
                __('Thanks so much for your kind review of %1$s. We really appreciate you taking the time to share your experience — it means a lot to our team.', 'wc-ai-review-responder'),
                $product_title
            );
        }

        return sprintf(
            __('Thanks for your feedback on %1$s. We\'re sorry your experience wasn\'t perfect. Please contact our support team so we can make this right.', 'wc-ai-review-responder'),
            $product_title
        );
    }

    private function is_woocommerce_review(WP_Comment $comment): bool {
        if ($comment->comment_type !== 'review') {
            return false;
        }

        return get_post_type((int) $comment->comment_post_ID) === 'product';
    }

    private function has_reply(int $review_id): bool {
        $children = get_comments([
            'parent' => $review_id,
            'status' => 'all',
            'number' => 1,
        ]);

        return !empty($children);
    }

    private function admin_js(): string {
        $ajax_url = admin_url('admin-ajax.php');

        return <<<JS
jQuery(function($) {
  $(document).on('click', '.wc-airr-generate', function(e) {
    e.preventDefault();
    const btn = $(this);
    if (btn.data('busy')) return;

    btn.data('busy', true).text('Generating...');

    $.post('{$ajax_url}', {
      action: 'wc_airr_generate_reply',
      comment_id: btn.data('comment-id'),
      nonce: btn.data('nonce')
    }).done(function(res) {
      if (res && res.success) {
        btn.text('Replied ✓');
      } else {
        const msg = res && res.data && res.data.message ? res.data.message : 'Failed to generate reply.';
        alert(msg);
        btn.text('AI Reply').data('busy', false);
      }
    }).fail(function(xhr) {
      const msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Request failed.';
      alert(msg);
      btn.text('AI Reply').data('busy', false);
    });
  });
});
JS;
    }
}

new WC_AI_Review_Responder();
