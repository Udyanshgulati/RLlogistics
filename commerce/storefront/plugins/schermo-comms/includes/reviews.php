<?php
/**
 * Verified product reviews from delivered orders.
 *
 * - When an order is marked delivered (status "completed"), each purchased line item gets one review invitation: a
 *   random token (only its SHA-256 hash is stored) tied to that order, that line item and that product. The link
 *   is the product's own page with ?review=<token>; it carries no order number or personal data.
 * - The server re-checks on every view and submit: the token exists and has not expired, the order is still
 *   delivered, the line item is in that order and is this product. A URL product ID or hidden field is never trusted.
 * - One review per order item; the same link lets the customer edit their review. WooCommerce's own public review
 *   form is closed for products, so nobody can review a product they did not buy through this flow.
 * - Reviews are WooCommerce product reviews (comment type "review") with WooCommerce's "verified" meta, so the
 *   product's average and count are WooCommerce's own. Admin-entered reviews are marked scm_source = admin.
 */

defined('ABSPATH') || exit;

const SCM_REVIEW_DAYS = 180;

/** Create invitations for every product line in a delivered order and queue the request (email + WhatsApp). */
function scm_review_invites_for_order(WC_Order $o): void
{
    global $wpdb;
    $t = $wpdb->prefix . 'scm_review_invites';
    $links = [];
    $names = [];
    foreach ($o->get_items() as $item_id => $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $pid = (int) $item->get_product_id(); // the parent product for variations: reviews belong to the product
        if (!$pid || get_post_type($pid) !== 'product') {
            continue;
        }
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE item_id = %d", $item_id))) { // phpcs:ignore
            continue; // already invited for this item (status set twice, webhook replay…)
        }
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $wpdb->insert($t, ['token_hash' => hash('sha256', $token), 'order_id' => $o->get_id(), 'item_id' => $item_id, 'product_id' => $pid, 'created_at' => scm_now(), 'expires_at' => gmdate('Y-m-d H:i:s', time() + SCM_REVIEW_DAYS * DAY_IN_SECONDS)]);
        $url = add_query_arg('review', $token, get_permalink($pid)) . '#reviews';
        $name = $item->get_name();
        $links[] = $name . ': ' . $url;
        $names[] = $name;
        // WhatsApp: one message per piece (the approved template has one product + one link).
        scm_queue('review_request', 'whatsapp', 'updates', (string) $o->get_billing_phone(), scm_order_vars($o) + ['product_name' => $name, 'review_link' => $url], "review:i{$item_id}:wa", $o->get_id());
    }
    if ($links) {
        // Email: one message listing a link for each piece.
        scm_queue('review_request', 'email', 'updates', (string) $o->get_billing_email(), scm_order_vars($o) + ['review_links' => implode("\n\n", $links), 'product_list_short' => count($names) === 1 ? $names[0] : __('order', 'schermo-comms')], "review:o{$o->get_id()}:email", $o->get_id());
    }
}

/** Resolve a token to [invite row, order, item] if it is valid right now; null otherwise. */
function scm_review_check(string $token): ?array
{
    global $wpdb;
    if ($token === '' || strlen($token) > 64) {
        return null;
    }
    $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scm_review_invites WHERE token_hash = %s", hash('sha256', $token)), ARRAY_A); // phpcs:ignore
    if (!$inv || strtotime($inv['expires_at'] . ' UTC') < time()) {
        return null;
    }
    $o = wc_get_order((int) $inv['order_id']);
    if (!$o || $o->get_status() !== 'completed') {
        return null; // only delivered orders
    }
    $item = $o->get_item((int) $inv['item_id']);
    if (!$item instanceof WC_Order_Item_Product || (int) $item->get_product_id() !== (int) $inv['product_id']) {
        return null;
    }
    return [$inv, $o, $item];
}

// WooCommerce's open review form is closed for products: reviews only come through verified links (or the admin).
add_filter('comments_open', static fn ($open, $post_id) => get_post_type($post_id) === 'product' ? false : $open, 20, 2);

// ---- Submit / edit
add_action('admin_post_nopriv_scm_review', 'scm_review_submit');
add_action('admin_post_scm_review', 'scm_review_submit');

function scm_review_submit(): void
{
    $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
    $back = wp_get_referer() ?: home_url('/');
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_scmr'] ?? '')), 'scm_review_' . $token)) {
        wp_safe_redirect(add_query_arg('scm_r', 'expired', $back));
        exit;
    }
    $ok = scm_review_check($token);
    if (!$ok) {
        wp_safe_redirect(add_query_arg('scm_r', 'invalid', remove_query_arg('review', $back)));
        exit;
    }
    [$inv, $o, $item] = $ok;
    $rating = (int) ($_POST['rating'] ?? 0);
    $text = trim(sanitize_textarea_field(wp_unslash($_POST['text'] ?? '')));
    $url = add_query_arg('review', $token, get_permalink((int) $inv['product_id']));
    if ($rating < 1 || $rating > 5 || mb_strlen($text) < 10 || mb_strlen($text) > 2000) {
        wp_safe_redirect(add_query_arg('scm_r', 'incomplete', $url) . '#reviews');
        exit;
    }
    $approved = scm_settings()['review_moderation'] ? 0 : 1;
    $author = trim($o->get_billing_first_name() . ' ' . mb_substr($o->get_billing_last_name(), 0, 1) . ($o->get_billing_last_name() ? '.' : ''));
    $existing = (int) $inv['review_id'] ? get_comment((int) $inv['review_id']) : null;
    if ($existing) {
        wp_update_comment(['comment_ID' => $existing->comment_ID, 'comment_content' => $text, 'comment_approved' => $approved]);
        update_comment_meta($existing->comment_ID, 'rating', $rating);
        update_comment_meta($existing->comment_ID, 'scm_edited_at', time());
        $cid = (int) $existing->comment_ID;
        scm_review_audit($cid, 'edited by customer', 'order #' . $o->get_order_number() . ', item ' . (int) $inv['item_id']);
    } else {
        $cid = (int) wp_insert_comment([
            'comment_post_ID' => (int) $inv['product_id'],
            'comment_author' => $author ?: __('Customer', 'schermo-comms'),
            'comment_author_email' => $o->get_billing_email(),
            'comment_content' => $text,
            'comment_type' => 'review',
            'comment_approved' => $approved,
            'user_id' => $o->get_customer_id(),
        ]);
        add_comment_meta($cid, 'rating', $rating, true);
        add_comment_meta($cid, 'verified', 1, true); // WooCommerce's own "verified owner" flag
        add_comment_meta($cid, 'scm_order_id', $o->get_id(), true);
        add_comment_meta($cid, 'scm_item_id', (int) $inv['item_id'], true);
        add_comment_meta($cid, 'scm_source', 'delivered-order-link', true);
        scm_review_audit($cid, 'submitted by customer', 'delivered order #' . $o->get_order_number() . ', item ' . (int) $inv['item_id']);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'scm_review_invites', ['review_id' => $cid], ['id' => $inv['id']]);
    }
    if (class_exists('WC_Comments')) {
        WC_Comments::clear_transients((int) $inv['product_id']);
    }
    wp_safe_redirect(add_query_arg('scm_r', $approved ? 'published' : 'pending', $url) . '#reviews');
    exit;
}

// ---- Product page: reviews for this product only, and the form when a valid link for this product is open.
add_action('woocommerce_after_single_product_summary', 'scm_reviews_section', 30);

function scm_reviews_section(): void
{
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $pid = $product->get_id();
    $reviews = get_comments(['post_id' => $pid, 'status' => 'approve', 'type' => 'review', 'orderby' => 'comment_date_gmt', 'order' => 'DESC']);
    $count = count($reviews);
    $token = sanitize_text_field(wp_unslash($_GET['review'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification -- token is verified below
    $check = $token !== '' ? scm_review_check($token) : null;
    $mine = $check && (int) $check[0]['product_id'] === $pid ? $check : null;
    $state = sanitize_key(wp_unslash($_GET['scm_r'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    echo '<section class="pdp-reviews" id="reviews" aria-labelledby="reviews-title">';
    echo '<div class="rv-head"><h2 id="reviews-title">' . esc_html__('Reviews', 'schermo-comms') . '</h2>';
    if ($count) {
        $avg = (float) $product->get_average_rating();
        printf('<p class="rv-summary"><span class="rv-stars" aria-hidden="true">%s</span> <strong>%s</strong> <span>%s</span></p>', esc_html(scm_stars($avg)), esc_html(number_format_i18n($avg, 1)), esc_html(sprintf(_n('%d review', '%d reviews', $count, 'schermo-comms'), $count)));
    }
    echo '</div>';
    $msg = [
        'published' => __('Thank you — your review is live.', 'schermo-comms'),
        'pending' => __('Thank you — your review will appear here after a quick check.', 'schermo-comms'),
        'incomplete' => __('Please choose a rating and write at least a few words (10 characters).', 'schermo-comms'),
        'invalid' => __('This review link is not valid any more. If you think that is wrong, reply to the email we sent.', 'schermo-comms'),
        'expired' => __('The page had been open too long. Please submit your review again.', 'schermo-comms'),
    ][$state] ?? '';
    if ($msg) {
        echo '<p class="rv-note" role="status">' . esc_html($msg) . '</p>';
    }
    if ($token !== '' && !$mine && !$msg) {
        echo '<p class="rv-note" role="status">' . esc_html__('This review link is not valid for this piece, or it has expired.', 'schermo-comms') . '</p>';
    }
    if ($mine) {
        [$inv] = $mine;
        $prev = (int) $inv['review_id'] ? get_comment((int) $inv['review_id']) : null;
        $r = $prev ? (int) get_comment_meta($prev->comment_ID, 'rating', true) : 0;
        echo '<form class="rv-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<h3>' . esc_html($prev ? __('Edit your review', 'schermo-comms') : __('Review your purchase', 'schermo-comms')) . '</h3>';
        echo '<input type="hidden" name="action" value="scm_review"><input type="hidden" name="token" value="' . esc_attr($token) . '">';
        wp_nonce_field('scm_review_' . $token, '_scmr', false);
        echo '<fieldset class="rv-rate"><legend>' . esc_html__('Your rating', 'schermo-comms') . '</legend>';
        for ($i = 5; $i >= 1; $i--) {
            printf('<input type="radio" id="rv-%1$d" name="rating" value="%1$d"%2$s required><label for="rv-%1$d" title="%3$s"><span aria-hidden="true">★</span><span class="sr-only">%3$s</span></label>', $i, checked($r, $i, false), esc_attr(sprintf(_n('%d star', '%d stars', $i, 'schermo-comms'), $i)));
        }
        echo '</fieldset>';
        echo '<label class="rv-text"><span>' . esc_html__('Your review', 'schermo-comms') . '</span><textarea name="text" rows="5" minlength="10" maxlength="2000" required>' . esc_textarea($prev ? $prev->comment_content : '') . '</textarea></label>';
        echo '<p class="rv-fine">' . esc_html__('Shown with your first name and last initial, and marked as a verified purchase.', 'schermo-comms') . '</p>';
        echo '<button type="submit" class="btn">' . esc_html($prev ? __('Update review', 'schermo-comms') : __('Submit review', 'schermo-comms')) . '</button></form>';
    }
    if ($count) {
        echo '<ol class="rv-list">';
        foreach ($reviews as $c) {
            $rating = (int) get_comment_meta($c->comment_ID, 'rating', true);
            $verified = (int) get_comment_meta($c->comment_ID, 'verified', true) === 1;
            echo '<li class="rv-item"><p class="rv-meta">';
            if ($rating) {
                printf('<span class="rv-stars" role="img" aria-label="%s">%s</span>', esc_attr(sprintf(__('Rated %d out of 5', 'schermo-comms'), $rating)), esc_html(scm_stars($rating)));
            }
            printf(' <strong>%s</strong> <time datetime="%s">%s</time>', esc_html($c->comment_author), esc_attr(get_comment_date('c', $c)), esc_html(get_comment_date(get_option('date_format'), $c)));
            if ($verified) {
                echo ' <span class="rv-verified">' . esc_html__('Verified purchase', 'schermo-comms') . '</span>';
            }
            echo '</p>' . wpautop(esc_html($c->comment_content)) . '</li>';
        }
        echo '</ol>';
    } elseif (!$mine) {
        echo '<p class="rv-empty">' . esc_html__('No reviews yet.', 'schermo-comms') . ' ' . esc_html__('Bought this piece? Once your order is delivered we send you a personal link to leave the first review.', 'schermo-comms') . '</p>';
    }
    echo '</section>';
}

/**
 * Audit trail on every review: who created or changed it, when, how and why (kept in comment meta "scm_audit";
 * never shown publicly). Customer edits, admin entries, admin edits and status changes are all recorded.
 */
function scm_review_audit(int $cid, string $event, string $note = ''): void
{
    $log = get_comment_meta($cid, 'scm_audit', true);
    $log = is_array($log) ? $log : [];
    $u = wp_get_current_user();
    $log[] = ['at' => gmdate('c'), 'event' => $event, 'by' => $u && $u->ID ? $u->user_login . ' (#' . $u->ID . ')' : 'customer via verified link', 'note' => mb_substr($note, 0, 300)];
    update_comment_meta($cid, 'scm_audit', $log);
}

add_action('edit_comment', static function ($cid): void {
    if (is_admin() && get_comment_type((int) $cid) === 'review') {
        scm_review_audit((int) $cid, 'edited in admin');
    }
});
add_action('transition_comment_status', static function ($new, $old, $c): void {
    if ($c instanceof WP_Comment && $c->comment_type === 'review' && $new !== $old) {
        scm_review_audit((int) $c->comment_ID, 'status ' . $old . ' → ' . $new);
    }
}, 10, 3);

/** True when this order item already has a review (customer link or admin entry). */
function scm_item_reviewed(int $item_id): bool
{
    global $wpdb;
    if ($wpdb->get_var($wpdb->prepare("SELECT review_id FROM {$wpdb->prefix}scm_review_invites WHERE item_id = %d AND review_id > 0", $item_id))) { // phpcs:ignore
        return true;
    }
    return (bool) get_comments(['type' => 'review', 'status' => 'all', 'count' => true, 'meta_key' => 'scm_item_id', 'meta_value' => $item_id]);
}

function scm_stars(float $r): string
{
    $full = (int) round($r);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

// The product schema/meta: WooCommerce only adds rating data when real reviews exist, so nothing to change there.
