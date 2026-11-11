<?php
/**
 * WooCommerce → Schermo messages: mode (dry-run / live), provider status, which flows run on which channel,
 * WhatsApp approved template per event, email wording, the message log, consent counts, a drop announcement,
 * and "Add a customer review" for real reviews a customer sent in another way (with their permission).
 */

defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    add_submenu_page('woocommerce', 'Schermo messages', 'Schermo messages', 'manage_woocommerce', 'scm', 'scm_admin_page');
    add_submenu_page('edit.php?post_type=product', 'Add a customer review', 'Add customer review', 'moderate_comments', 'scm-review', 'scm_admin_review_page');
});

add_action('admin_post_scm_save', static function (): void {
    if (!current_user_can('manage_woocommerce') || !check_admin_referer('scm_save')) {
        wp_die('Not allowed');
    }
    $in = wp_unslash($_POST);
    $s = scm_settings();
    $s['mode'] = ($in['mode'] ?? '') === 'live' ? 'live' : 'dry-run';
    $s['review_moderation'] = !empty($in['review_moderation']) ? 1 : 0;
    $s['cart_reminder_hours'] = max(1, (int) ($in['cart_reminder_hours'] ?? 3));
    $s['newsletter_offer_on'] = !empty($in['newsletter_offer_on']) ? 1 : 0;
    $s['newsletter_offer_percent'] = max(1, min(50, (int) ($in['newsletter_offer_percent'] ?? 10)));
    $s['flows'] = [];
    foreach ((array) ($in['flows'] ?? []) as $k => $v) {
        if (preg_match('/^[a-z_]+:(email|whatsapp)$/', (string) $k)) {
            $s['flows'][(string) $k] = 1;
        }
    }
    $s['wa_templates'] = [];
    foreach ((array) ($in['wa'] ?? []) as $ev => $row) {
        $name = sanitize_key($row['name'] ?? '');
        if ($name !== '' && isset(scm_events()[$ev])) {
            $s['wa_templates'][$ev] = ['name' => $name, 'lang' => sanitize_text_field($row['lang'] ?? 'en') ?: 'en'];
        }
    }
    update_option('scm_settings', $s, false);
    $tpl = [];
    foreach ((array) ($in['tpl'] ?? []) as $ev => $row) {
        if (isset(scm_events()[$ev])) {
            $tpl[$ev] = ['subject' => sanitize_text_field($row['subject'] ?? ''), 'body' => sanitize_textarea_field($row['body'] ?? '')];
        }
    }
    update_option('scm_tpl', $tpl, false);
    wp_safe_redirect(add_query_arg(['page' => 'scm', 'saved' => 1], admin_url('admin.php')));
    exit;
});

add_action('admin_post_scm_drop', static function (): void {
    if (!current_user_can('manage_woocommerce') || !check_admin_referer('scm_drop')) {
        wp_die('Not allowed');
    }
    $in = wp_unslash($_POST);
    $chs = array_values(array_intersect(['email', 'whatsapp'], (array) ($in['channels'] ?? [])));
    $n = scm_send_drop(sanitize_text_field($in['headline'] ?? ''), sanitize_textarea_field($in['message'] ?? ''), esc_url_raw($in['link'] ?? ''), $chs);
    wp_safe_redirect(add_query_arg(['page' => 'scm', 'queued' => $n], admin_url('admin.php')));
    exit;
});

function scm_admin_page(): void
{
    global $wpdb;
    $s = scm_settings();
    $live = $s['mode'] === 'live';
    echo '<div class="wrap"><h1>Schermo messages</h1>';
    if (isset($_GET['saved'])) { // phpcs:ignore
        echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }
    if (isset($_GET['queued'])) { // phpcs:ignore
        printf('<div class="notice notice-info"><p>%d messages queued%s.</p></div>', (int) $_GET['queued'], $live ? '' : ' (dry-run: logged, not sent)'); // phpcs:ignore
    }
    printf('<p><strong>Mode:</strong> %s · <strong>Email provider:</strong> %s · <strong>WhatsApp provider:</strong> %s</p>',
        $live ? 'LIVE' : 'Dry-run (nothing is sent; every message is logged as “simulated”)',
        scm_email_ready() ? 'SMTP configured' : 'not configured (set SMTP_* in the server env file)',
        scm_wa_ready() ? 'Cloud API configured' : 'not configured (set WA_PHONE_NUMBER_ID, WA_ACCESS_TOKEN, WA_APP_SECRET, WA_VERIFY_TOKEN)');
    echo '<p>WhatsApp webhook URL for Meta: <code>' . esc_html(rest_url('scm/v1/whatsapp')) . '</code> (handles delivery statuses and STOP opt-outs).</p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('scm_save');
    echo '<input type="hidden" name="action" value="scm_save">';
    printf('<p><label><input type="radio" name="mode" value="dry-run"%s> Dry-run</label> &nbsp; <label><input type="radio" name="mode" value="live"%s> Live (only after providers, approved templates and consent wording are confirmed)</label></p>', checked(!$live, true, false), checked($live, true, false));
    printf('<p><label><input type="checkbox" name="review_moderation" value="1"%s> Hold customer reviews for approval (Products → Reviews) before they appear</label></p>', checked($s['review_moderation'], 1, false));
    printf('<p><label>Bag reminder after <input type="number" min="1" name="cart_reminder_hours" value="%d" style="width:60px"> hours (signed-in customers with marketing consent only)</label></p>', (int) $s['cart_reminder_hours']);
    printf('<p><label><input type="checkbox" name="newsletter_offer_on" value="1"%s> Schermo list welcome offer: <input type="number" min="1" max="50" name="newsletter_offer_percent" value="%d" style="width:60px">%% off the first order (one personal code, single use, 30 days, not on sale items)</label></p>', checked((int) ($s['newsletter_offer_on'] ?? 1), 1, false), (int) ($s['newsletter_offer_percent'] ?? 10));
    printf('<p><strong>Sign in with Google:</strong> %s</p>', scm_google_ready() ? 'configured' : 'not configured (set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET; redirect URI ' . esc_html(scm_google_redirect_uri()) . ')');
    echo '<table class="widefat striped"><thead><tr><th>Event</th><th>Purpose</th><th>Email</th><th>WhatsApp</th><th>WhatsApp approved template (name / language)</th><th>Template must have these body parameters, in order</th></tr></thead><tbody>';
    foreach (scm_events() as $ev => $d) {
        $cell = static function (string $ch) use ($ev, $d, $s): string {
            if (!in_array($ch, $d['channels'], true)) {
                return $ch === 'email' ? '<span title="WooCommerce / TL Commerce Hub already email this">sent by WooCommerce</span>' : '—';
            }
            return sprintf('<label><input type="checkbox" name="flows[%s:%s]" value="1"%s> on</label>', esc_attr($ev), $ch, checked(!empty($s['flows']["$ev:$ch"]), true, false));
        };
        $wa = $s['wa_templates'][$ev] ?? ['name' => '', 'lang' => 'en'];
        printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><input name="wa[%s][name]" value="%s" placeholder="template_name" style="width:170px"> <input name="wa[%s][lang]" value="%s" style="width:60px"></td><td><code>%s</code></td></tr>',
            esc_html($d['label']), $d['purpose'] === 'marketing' ? '<strong>marketing</strong>' : 'order update', $cell('email'), $cell('whatsapp'),
            esc_attr($ev), esc_attr($wa['name']), esc_attr($ev), esc_attr($wa['lang']), esc_html(implode(', ', $d['wa'])));
    }
    echo '</tbody></table><h2>Email wording</h2><p>Placeholders like {first_name}, {order_number}, {tracking_link}, {review_links} are filled from the real order. Marketing emails get an unsubscribe link and List-Unsubscribe headers automatically.</p>';
    foreach (scm_events() as $ev => $d) {
        if (!in_array('email', $d['channels'], true)) {
            continue;
        }
        [$subj, $body] = scm_email_text($ev);
        printf('<h3>%s</h3><p><input name="tpl[%s][subject]" value="%s" class="large-text"></p><p><textarea name="tpl[%s][body]" rows="5" class="large-text">%s</textarea></p>', esc_html($d['label']), esc_attr($ev), esc_attr($subj), esc_attr($ev), esc_textarea($body));
    }
    submit_button('Save');
    echo '</form>';

    echo '<h2>Announce a drop</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('scm_drop');
    $aud = ['email' => count(scm_marketing_audience('email')), 'whatsapp' => count(scm_marketing_audience('whatsapp'))];
    echo '<input type="hidden" name="action" value="scm_drop"><p><input name="headline" class="regular-text" placeholder="Headline" required></p><p><textarea name="message" rows="3" class="large-text" placeholder="Message" required></textarea></p><p><input name="link" class="regular-text" placeholder="https://… link" type="url"></p>';
    printf('<p><label><input type="checkbox" name="channels[]" value="email"> Email (%d opted in)</label> &nbsp; <label><input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp (%d opted in)</label></p>', $aud['email'], $aud['whatsapp']);
    submit_button('Queue announcement', 'secondary');
    echo '</form>';

    $rows = $wpdb->get_results("SELECT id, event, channel, purpose, template, template_version, recipient, order_id, status, attempts, result, created_at FROM {$wpdb->prefix}scm_messages ORDER BY id DESC LIMIT 100", ARRAY_A); // phpcs:ignore
    echo '<h2>Message log (latest 100)</h2><table class="widefat striped"><thead><tr><th>#</th><th>When (UTC)</th><th>Event</th><th>Channel</th><th>Purpose</th><th>Template</th><th>To</th><th>Order</th><th>Status</th><th>Tries</th><th>Result</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $to = $r['channel'] === 'email' ? preg_replace('/(^.).*(@.*$)/', '$1…$2', $r['recipient']) : substr($r['recipient'], 0, 4) . '…' . substr($r['recipient'], -3);
        printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>', $r['id'], esc_html($r['created_at']), esc_html($r['event']), esc_html($r['channel']), esc_html($r['purpose']), esc_html($r['template']), esc_html($r['template_version']), esc_html($to), $r['order_id'] ? '#' . (int) $r['order_id'] : '', esc_html($r['status']), $r['attempts'], esc_html(wp_trim_words((string) $r['result'], 18)));
    }
    echo '</tbody></table>';
    $cons = $wpdb->get_results("SELECT channel, purpose, status, COUNT(*) n FROM {$wpdb->prefix}scm_consents GROUP BY channel, purpose, status", ARRAY_A); // phpcs:ignore
    echo '<h2>Consent records</h2><ul>';
    foreach ($cons as $c) {
        printf('<li>%s / %s / %s: %d</li>', esc_html($c['channel']), esc_html($c['purpose']), esc_html($c['status']), (int) $c['n']);
    }
    echo '</ul></div>';
}

// ---- Admin-entered reviews: only real reviews a customer gave (e.g. by email or in person) and agreed to publish.
add_action('admin_post_scm_admin_review', static function (): void {
    if (!current_user_can('moderate_comments') || !check_admin_referer('scm_admin_review')) {
        wp_die('Not allowed');
    }
    $in = wp_unslash($_POST);
    $pid = absint($in['product_id'] ?? 0);
    $rating = (int) ($in['rating'] ?? 0);
    $text = trim(sanitize_textarea_field($in['text'] ?? ''));
    $author = sanitize_text_field($in['author'] ?? '');
    $back = add_query_arg(['post_type' => 'product', 'page' => 'scm-review'], admin_url('edit.php'));
    if (get_post_type($pid) !== 'product' || $rating < 1 || $rating > 5 || $text === '' || $author === '' || empty($in['consent'])) {
        wp_safe_redirect(add_query_arg('err', 1, $back));
        exit;
    }
    // "Verified purchase" only when the order system confirms it: a delivered order with this product and this email.
    $verified = 0;
    $order_id = absint($in['order_id'] ?? 0);
    $email = sanitize_email($in['email'] ?? '');
    $item_id = 0;
    if ($order_id && ($o = wc_get_order($order_id)) && $o->get_status() === 'completed' && $email && strtolower($o->get_billing_email()) === strtolower($email)) {
        foreach ($o->get_items() as $iid => $it) {
            if ($it instanceof WC_Order_Item_Product && (int) $it->get_product_id() === $pid) {
                $verified = 1;
                $item_id = (int) $iid;
            }
        }
    }
    // One review per order item: the customer's own link and an admin entry can't both review the same purchase.
    if ($item_id && scm_item_reviewed($item_id)) {
        wp_safe_redirect(add_query_arg('err', 'dup', $back));
        exit;
    }
    $date = sanitize_text_field($in['date'] ?? '');
    $cid = (int) wp_insert_comment([
        'comment_post_ID' => $pid, 'comment_author' => $author, 'comment_author_email' => $email, 'comment_content' => $text,
        'comment_type' => 'review', 'comment_approved' => 1,
        'comment_date' => $date && strtotime($date) ? gmdate('Y-m-d H:i:s', strtotime($date)) : current_time('mysql'),
    ]);
    add_comment_meta($cid, 'rating', $rating, true);
    add_comment_meta($cid, 'verified', $verified, true);
    add_comment_meta($cid, 'scm_source', 'admin', true);
    add_comment_meta($cid, 'scm_admin_user', get_current_user_id(), true);
    add_comment_meta($cid, 'scm_consent_note', sanitize_text_field($in['consent_note'] ?? ''), true);
    if ($verified) {
        add_comment_meta($cid, 'scm_order_id', $order_id, true);
        add_comment_meta($cid, 'scm_item_id', $item_id, true);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'scm_review_invites', ['review_id' => $cid], ['item_id' => $item_id]); // the customer's link now edits this review
    }
    scm_review_audit($cid, 'entered by admin from customer feedback', 'consent: ' . sanitize_text_field($in['consent_note'] ?? '') . ($verified ? ' · verified against delivered order #' . $order_id : ' · not verified'));
    WC_Comments::clear_transients($pid);
    wp_safe_redirect(add_query_arg('added', $cid, $back));
    exit;
});

function scm_admin_review_page(): void
{
    echo '<div class="wrap"><h1>Add a customer review</h1>';
    echo '<p>Use this only for a <strong>real review a customer gave you</strong> (for example by email or WhatsApp) and <strong>agreed to have published</strong>. Paste their words as they wrote them. It is marked “admin-entered” in the backend. It is marked “verified purchase” only if you give a delivered order number with the same email and this product.</p>';
    if (isset($_GET['err'])) { // phpcs:ignore
        $dup = $_GET['err'] === 'dup'; // phpcs:ignore
        echo '<div class="notice notice-error"><p>' . esc_html($dup ? 'That delivered order item already has a review (from the customer\'s link or an earlier entry). Edit that review instead.' : 'Please fill in the product, rating, name and text, and confirm the customer agreed to publication.') . '</p></div>';
    }
    if (isset($_GET['added'])) { // phpcs:ignore
        echo '<div class="notice notice-success"><p>Review added.</p></div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('scm_admin_review');
    echo '<input type="hidden" name="action" value="scm_admin_review"><table class="form-table">';
    echo '<tr><th>Product</th><td><select name="product_id" required><option value="">—</option>';
    foreach (wc_get_products(['status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $p) {
        printf('<option value="%d">%s (%s)</option>', $p->get_id(), esc_html($p->get_name()), esc_html($p->get_sku()));
    }
    echo '</select></td></tr>';
    echo '<tr><th>Customer name (as they want it shown)</th><td><input name="author" class="regular-text" required></td></tr>';
    echo '<tr><th>Customer email (not shown)</th><td><input name="email" type="email" class="regular-text"></td></tr>';
    echo '<tr><th>Delivered order number (optional)</th><td><input name="order_id" type="number" min="1"> <span class="description">Only used to confirm a verified purchase.</span></td></tr>';
    echo '<tr><th>Date they wrote it</th><td><input name="date" type="date"></td></tr>';
    echo '<tr><th>Rating</th><td><select name="rating" required><option value="">—</option><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select></td></tr>';
    echo '<tr><th>Review text (their words)</th><td><textarea name="text" rows="5" class="large-text" required></textarea></td></tr>';
    echo '<tr><th>Permission</th><td><label><input type="checkbox" name="consent" value="1" required> The customer agreed to publish this review on the website</label><br><input name="consent_note" class="regular-text" placeholder="How/when they agreed (kept privately)"></td></tr>';
    echo '</table>';
    submit_button('Add review');
    echo '</form></div>';
}

// Reviews list in WP admin: show where each review came from.
add_filter('comment_text', static function ($text, $comment = null) {
    if (is_admin() && $comment && get_comment_type($comment) === 'review') {
        $src = (string) get_comment_meta($comment->comment_ID, 'scm_source', true);
        $label = ['admin' => 'Admin-entered', 'delivered-order-link' => 'Customer, via delivered-order link'][$src] ?? 'Other';
        $text .= '<p style="color:#777;font-size:11px">' . esc_html($label . ((int) get_comment_meta($comment->comment_ID, 'verified', true) ? ' · verified purchase' : '')) . '</p>';
    }
    return $text;
}, 10, 2);
