<?php
// Schermo page cache (drop-in, loaded before WordPress when WP_CACHE is true). Serves stored HTML to anonymous
// visitors who have nothing in a cart; everyone else, and every form/cart/checkout/account page, goes to WordPress.
// Pages are stored per URL and per chosen market (prices are shown in that market's currency) for 10 minutes and
// are cleared whenever products, menus or Customizer settings change (see mu-plugins/schermo-cache.php).
defined('ABSPATH') || exit;

(static function (): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if (!in_array($method, ['GET', 'HEAD'], true) || str_contains($uri, '?') || (defined('WP_CLI') && WP_CLI)) {
        return;
    }
    if (preg_match('#^/(cart|checkout|my-account|wp-|wc-api|xmlrpc|feed|.*\.(php|xml|txt))#', $uri)) {
        return;
    }
    foreach (array_keys($_COOKIE) as $c) {
        if (preg_match('/^(wordpress_logged_in_|wp_woocommerce_session_|woocommerce_items_in_cart|woocommerce_cart_hash|comment_author_|wp-postpass_)/', $c)) {
            return;
        }
    }
    $market = preg_replace('/[^A-Z]/', '', strtoupper((string) ($_COOKIE['bh_market'] ?? '')));
    $dir = WP_CONTENT_DIR . '/cache/schermo-page';
    $file = $dir . '/' . md5(($_SERVER['HTTP_HOST'] ?? '') . '|' . $uri . '|' . $market) . '.html';
    if (is_file($file) && filemtime($file) > time() - 600) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Cache: HIT');
        header('Cache-Control: no-cache');
        readfile($file);
        exit;
    }
    header('X-Cache: MISS');
    ob_start(static function (string $html) use ($dir, $file): string {
        $setsCookie = false;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $setsCookie = true;
            }
        }
        if (http_response_code() === 200 && !$setsCookie && !defined('DONOTCACHEPAGE') && strlen($html) > 1000 && stripos($html, '</html>') !== false) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($file . '.tmp', $html) && @rename($file . '.tmp', $file);
        }
        return $html;
    });
})();
