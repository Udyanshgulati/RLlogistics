<?php
/**
 * Coming soon (schermo.co). A standalone page: a bright boutique rail — every Schermo garment on its own hanger,
 * facing out, evenly spaced and shuffled on each visit; hover names a piece and click opens it large — "Coming soon"
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

/**
 * Where the garment sits inside its photo: [left, top, right, bottom] as fractions of the image (transparent margins
 * trimmed), so every piece can be hung at the same height and scaled to a true-to-life width; plus 1 when the photo
 * cuts the garment off at a side (a straight edge down most of it), else 0. Kept on the attachment.
 */
function schermo_soon_box(int $attachment_id): array
{
    $file = (string) get_attached_file($attachment_id);
    $key = '_scm_box2_' . (int) @filemtime($file);
    $box = get_post_meta($attachment_id, $key, true);
    if (is_array($box) && count($box) === 5) {
        return $box;
    }
    $box = [0.0, 0.0, 1.0, 1.0, 0];
    $src = function_exists('imagecreatefromstring') && is_readable($file) ? @imagecreatefromstring((string) file_get_contents($file)) : false;
    if ($src) {
        $w = imagesx($src);
        $h = imagesy($src);
        $sw = 160;
        $sh = max(1, (int) round($h * $sw / $w));
        $sm = imagecreatetruecolor($sw, $sh);
        imagealphablending($sm, false);
        imagesavealpha($sm, true);
        imagefill($sm, 0, 0, imagecolorallocatealpha($sm, 0, 0, 0, 127));
        imagecopyresampled($sm, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);
        [$x0, $y0, $x1, $y1] = [$sw, $sh, -1, -1];
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                if (((imagecolorat($sm, $x, $y) >> 24) & 0x7f) < 90) { // visibly opaque
                    $x0 = min($x0, $x);
                    $x1 = max($x1, $x);
                    $y0 = min($y0, $y);
                    $y1 = max($y1, $y);
                }
            }
        }
        if ($x1 > $x0 && $y1 > $y0) {
            $side = static function (int $x) use ($sm, $y0, $y1): float {
                $n = 0;
                for ($y = $y0; $y <= $y1; $y++) {
                    $n += ((imagecolorat($sm, $x, $y) >> 24) & 0x7f) < 90 ? 1 : 0;
                }
                return $n / ($y1 - $y0 + 1);
            };
            $cut = max($side($x0), $side($x1)) > .45 ? 1 : 0;
            $box = [round($x0 / $sw, 4), round($y0 / $sh, 4), round(($x1 + 1) / $sw, 4), round(($y1 + 1) / $sh, 4), $cut];
        }
        imagedestroy($sm);
        imagedestroy($src);
    }
    update_post_meta($attachment_id, $key, $box);
    return $box;
}

/**
 * The garments for the rail: every published clothing or sportswear piece with a cut-out photo (caps, bags and other
 * accessories don't go on hangers). Each: n name, u image, c collection, k 'top' or 'bottom' (clip hanger),
 * b garment box in the photo, r photo height / width. Cached for an hour.
 */
function schermo_soon_pieces(): array
{
    $cached = get_transient('schermo_soon_pieces_v4');
    if (is_array($cached)) {
        return $cached;
    }
    $colls = ['schermo-core' => 'Schermo Core', 'schermo-atelier' => 'Schermo Atelier', 'schermo-sports' => 'Schermo Sports'];
    $out = [];
    foreach (wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'category' => ['clothing', 'sportswear']]) as $pid) {
        $p = wc_get_product($pid);
        $img = $p ? (int) $p->get_image_id() : 0;
        if (!$img || !schermo_soon_is_cutout($img)) {
            continue;
        }
        $slugs = wp_get_post_terms($pid, 'product_cat', ['fields' => 'slugs']);
        if (array_intersect($slugs, ['accessories', 'fan-merch', 'caps-hats', 'bags-backpacks', 'other-accessories', 'glasgow-cosmic']) // Glasgow Cosmic: not on the rail for now (owner)
            || preg_match('/\\b(gloves?|helmets?|pads?|guards?|bats?|balls?|socks?|shoes?|sneakers?)\\b/i', $p->get_name())) { // kit, not garments
            continue;
        }
        $coll = '';
        foreach ($slugs as $slug) {
            $coll = $colls[$slug] ?? $coll;
        }
        $bottom = array_intersect($slugs, ['trousers', 'shorts']) || preg_match('/\\b(shorts?|trousers?|pants|jeans|joggers?|cargos?|denims?|bottoms?|skirt)\\b/i', $p->get_name());
        $src = wp_get_attachment_image_src($img, 'medium_large');
        $box = schermo_soon_box($img);
        if (!$src || $box[4]) { // a photo that cuts the garment off would look wrong on a hanger
            continue;
        }
        $out[] = [
            'n' => $p->get_name(),
            'u' => wp_make_link_relative((string) $src[0]),
            'c' => $coll,
            'k' => $bottom ? 'bottom' : 'top',
            'b' => array_slice($box, 0, 4),
            'r' => $src[1] ? round($src[2] / $src[1], 4) : 1.25,
        ];
    }
    set_transient('schermo_soon_pieces_v4', $out, HOUR_IN_SECONDS);
    return $out;
}
add_action('save_post_product', static fn () => delete_transient('schermo_soon_pieces_v4'));

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
