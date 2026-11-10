<?php
/** Launch checklist: what still stands between this store and taking real orders, checked live. */

declare(strict_types=1);

defined('ABSPATH') || exit;

/** [label, done?, detail, link] for every launch item. */
function tli_launch_items(): array
{
    $gateways = function_exists('WC') ? array_keys(WC()->payment_gateways()->get_available_payment_gateways()) : [];
    $policies = ['shipping-policy' => 'Shipping', 'returns-and-refunds' => 'Refunds', 'terms-and-conditions' => 'Terms', 'privacy-policy' => 'Privacy', 'contact' => 'Contact', 'faq' => 'FAQ'];
    $draft = [];
    foreach ($policies as $slug => $label) {
        $pg = get_page_by_path($slug, OBJECT, 'page');
        if (!$pg || $pg->post_status !== 'publish' || str_contains($pg->post_content, '[LEGAL BUSINESS NAME]')) {
            $draft[] = $label;
        }
    }
    $conf = tli_invoice_conf();
    $biz_missing = [];
    foreach (['legal_name' => 'legal name', 'address' => 'registered address', 'email' => 'support email', 'phone' => 'support phone', 'grievance' => 'grievance officer'] as $k => $label) {
        if ($conf[$k] === '') {
            $biz_missing[] = $label;
        }
    }
    // Worldwide delivery needs a shipping method outside India ("Rest of the world" or a zone per region).
    $intl = false;
    if (class_exists('WC_Shipping_Zones')) {
        foreach (array_merge(WC_Shipping_Zones::get_zones(), [['zone_id' => 0, 'zone_name' => 'Rest of the world']]) as $z) {
            $zone = new WC_Shipping_Zone((int) $z['zone_id']);
            $india_only = (int) $z['zone_id'] !== 0 && array_column($zone->get_zone_locations(), 'code') === ['IN'];
            if (!$india_only && array_filter($zone->get_shipping_methods(), static fn ($m) => $m->is_enabled())) {
                $intl = true;
            }
        }
    }
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
    $unpriced = count(array_filter($products, static fn ($p) => $p->get_price() === ''));
    $s = tli_stock_summary();
    $couriers = array_filter(tli_couriers());
    $bk = tli_last_backup();
    $missing = tli_invoice_missing();
    return [
        ['Payment method switched on', (bool) $gateways, $gateways ? 'Active: ' . implode(', ', $gateways) : 'No payment method is active — customers cannot place orders. Add Razorpay keys (test first) in WooCommerce → Settings → Payments.', admin_url('admin.php?page=wc-settings&tab=checkout')],
        ['Emails are sent', function_exists('schermo_mail_ready') && schermo_mail_ready() && !get_option('schermo_mail_error'), 'Order, dispatch and password emails need an SMTP mail account in /opt/schermo/schermo.env on the server.', admin_url('admin.php?page=tl-settings')],
        ['GST invoice details', !$missing, $missing ? 'Missing: ' . implode(', ', $missing) . '.' : 'Invoices are numbered automatically.', admin_url('admin.php?page=tl-settings')],
        ['Policy pages published', !$draft, $draft ? 'Not published: ' . implode(', ', $draft) . '.' : 'Terms, Privacy, Refunds & Cancellations, Shipping, Contact and FAQ are live.', admin_url('edit.php?post_type=page')],
        ['Business details on the pages', !$biz_missing, $biz_missing ? 'Missing: ' . implode(', ', $biz_missing) . ' — the pages leave these out until filled in (India\'s e-commerce rules require the seller\'s name, address, contact and a grievance officer).' : 'Shown on Contact, Terms and Privacy.', admin_url('admin.php?page=tl-settings')],
        ['Shipping rates outside India', $intl, $intl ? 'International delivery has a shipping method.' : 'Worldwide delivery is on, but no shipping method exists outside India — foreign customers cannot check out. Add rates in WooCommerce → Settings → Shipping (e.g. "Rest of the world" zone) or connect a courier.', admin_url('admin.php?page=wc-settings&tab=shipping')],
        ['Every product has a price', $unpriced === 0, $unpriced ? $unpriced . ' published products have no price (shown as "price coming soon", cannot be bought).' : 'All priced.', admin_url('edit.php?post_type=product')],
        ['Stock counted', $s['untracked'] === 0, $s['untracked'] ? $s['untracked'] . ' sizes have no stock count and sell without a limit.' : 'All sizes counted.', admin_url('admin.php?page=tl-stock&filter=untracked')],
        ['Courier tracking links', (bool) $couriers, $couriers ? 'Links set for: ' . implode(', ', array_keys($couriers)) . '.' : 'No courier has a tracking link yet — customers get the AWB number only.', admin_url('admin.php?page=tl-settings')],
        ['Daily backups running', $bk !== null && time() - $bk < 2 * DAY_IN_SECONDS, $bk ? 'Last backup ' . human_time_diff($bk) . ' ago.' : 'No backup found yet.', admin_url('admin.php?page=tl-settings')],
        ['Visible to Google', (bool) get_option('blog_public'), get_option('blog_public') ? 'Search engines can index the store.' : 'Hidden from search engines on purpose until launch — switch on in Settings → Reading (untick "Discourage search engines") on launch day.', admin_url('options-reading.php')],
    ];
}

function tli_launch_card(bool $compact = false): void
{
    $items = tli_launch_items();
    $done = count(array_filter(array_column($items, 1)));
    printf('<section class="tli-card"><div class="tli-card-head"><div><h2>Launch checklist</h2><p>%d of %d ready. Checked live every time this page opens.</p></div></div><ul class="tli-launch">', $done, count($items));
    foreach ($items as [$label, $ok, $detail, $link]) {
        if ($compact && $ok) {
            continue;
        }
        printf('<li class="%s"><span class="tick">%s</span><div><b>%s</b><span class="small muted">%s</span></div><a class="tli-btn sm ghost" href="%s">Open</a></li>', $ok ? 'ok' : 'todo', $ok ? '✓' : '!', esc_html($label), esc_html($detail), esc_url($link));
    }
    echo '</ul></section>';
}
