<?php
/**
 * Coming soon (schermo.co). A standalone page: a bright studio with one long rail — every Schermo piece hanging
 * side-on, shuffled on each visit; a piece turns to face you on hover and opens large on click — "Coming soon"
 * above and a notify-me sign-up (the Schermo list, with consent).
 *
 * Served for every front-end request on the hosts in SCHERMO_SOON_HOSTS (schermo.co once the domain points here),
 * and at /coming-soon/ on any host as a preview. wp-admin, login, REST and cron are never affected.
 */

defined('ABSPATH') || exit;

const SCHERMO_SOON_HOSTS = ['schermo.co', 'www.schermo.co'];

function schermo_soon_requested(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (preg_match('#^/(wp-admin|wp-login\.php|wp-json|wp-content|wp-includes)#', $path)) {
        return false;
    }
    return in_array($host, SCHERMO_SOON_HOSTS, true) || rtrim($path, '/') === '/coming-soon';
}

/** True when a product photo is a cut-out (WebP/PNG with transparency), so it can hang on the rail. */
function schermo_soon_is_cutout(int $attachment_id): bool
{
    $file = (string) get_attached_file($attachment_id);
    if ($file === '' || !is_readable($file)) {
        return false;
    }
    $h = (string) file_get_contents($file, false, null, 0, 32);
    if (str_starts_with($h, 'RIFF') && substr($h, 8, 4) === 'WEBP') {
        return substr($h, 12, 4) === 'VP8L' || (substr($h, 12, 4) === 'VP8X' && (ord($h[20]) & 0x10));
    }
    if (str_starts_with($h, "\x89PNG")) {
        return in_array(ord($h[25]), [4, 6], true); // grey+alpha or RGBA
    }
    return false;
}

/** Every published piece with a cut-out photo: [name, image url]. Cached for an hour. */
function schermo_soon_pieces(): array
{
    $cached = get_transient('schermo_soon_pieces_v2');
    if (is_array($cached)) {
        return $cached;
    }
    $out = [];
    foreach (wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids']) as $pid) {
        $p = wc_get_product($pid);
        $img = $p ? (int) $p->get_image_id() : 0;
        if ($img && schermo_soon_is_cutout($img)) {
            $coll = '';
            foreach (wp_get_post_terms($pid, 'product_cat', ['fields' => 'slugs']) as $slug) {
                $coll = ['schermo-core' => 'Schermo Core', 'schermo-atelier' => 'Schermo Atelier', 'schermo-sports' => 'Schermo Sports'][$slug] ?? $coll;
            }
            $out[] = [$p->get_name(), (string) wp_get_attachment_image_url($img, 'medium_large'), $coll];
        }
    }
    set_transient('schermo_soon_pieces_v2', $out, HOUR_IN_SECONDS);
    return $out;
}
add_action('save_post_product', static fn () => delete_transient('schermo_soon_pieces_v2'));

// Before WordPress's canonical redirect (priority 10), so schermo.co is never sent to the shop's own domain.
add_action('template_redirect', static function (): void {
    if (!schermo_soon_requested()) {
        return;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    status_header(200);
    nocache_headers();
    include get_stylesheet_directory() . '/coming-soon.php';
    exit;
}, 0);
// No canonical redirect for the coming-soon hosts at all (e.g. /some-path on schermo.co).
add_filter('redirect_canonical', static fn ($to) => schermo_soon_requested() ? false : $to);
