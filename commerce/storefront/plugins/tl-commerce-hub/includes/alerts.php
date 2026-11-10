<?php
/** Morning email: low and out-of-stock sizes, and orders waiting more than 24 hours to be packed. */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action('init', static function (): void {
    if (!wp_next_scheduled('tli_daily_digest')) {
        $next = (new DateTimeImmutable('tomorrow 09:00', wp_timezone()))->getTimestamp();
        wp_schedule_event($next, 'daily', 'tli_daily_digest');
    }
});

function tli_digest_body(): ?string
{
    global $wpdb;
    $low = $out = [];
    foreach (tli_inventory_rows() as $g) {
        foreach ($g['rows'] as $r) {
            $line = $g['product']->get_name() . ($r['size'] !== '—' ? ' · ' . $r['size'] : '') . ' (' . $r['sku'] . ')';
            if ($r['state'] === 'out') {
                $out[] = $line;
            } elseif ($r['state'] === 'low') {
                $low[] = $line . ' — ' . $r['qty'] . ' left';
            }
        }
    }
    $cut = time() - DAY_IN_SECONDS;
    $late = count(wc_get_orders(['status' => ['processing'], 'limit' => -1, 'return' => 'ids', 'date_created' => '<' . $cut]));
    if (tli_mode() === 'hub') {
        $late += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tl_remote_orders WHERE status = 'processing' AND placed_at < %s", gmdate('Y-m-d H:i:s', $cut)));
    }
    if (!$low && !$out && !$late) {
        return null;
    }
    $b = 'Good morning — here is what needs attention at ' . get_bloginfo('name') . ".\n\n";
    if ($late) {
        $b .= "Orders waiting more than 24 hours to be packed: {$late}\n\n";
    }
    if ($out) {
        $b .= 'Out of stock (' . count($out) . "):\n- " . implode("\n- ", $out) . "\n\n";
    }
    if ($low) {
        $b .= 'Low stock (' . count($low) . "):\n- " . implode("\n- ", $low) . "\n\n";
    }
    return $b . 'Open TL Commerce Hub: ' . admin_url('admin.php?page=tl-inventory') . "\n\n— TL Commerce Hub by Thoda Logic";
}

add_action('tli_daily_digest', static function (): void {
    $body = tli_digest_body();
    if ($body !== null) {
        wp_mail(get_option('tli_alert_email') ?: get_option('admin_email'), get_bloginfo('name') . ': stock and orders to check today', $body);
    }
});
