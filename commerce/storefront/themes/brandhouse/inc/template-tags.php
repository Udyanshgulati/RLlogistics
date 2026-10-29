<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** Logo from Customizer → Site identity; otherwise the brand name set in type (never a redrawn logo). */
function brandhouse_logo(): void
{
    if (has_custom_logo()) {
        the_custom_logo();
        return;
    }
    printf('<a class="wordmark" href="%s" rel="home">%s</a>', esc_url(home_url('/')), esc_html(get_bloginfo('name')));
}

/** Neutral placeholder tile for missing photography — states what goes there instead of faking it. */
function brandhouse_placeholder(string $label, string $class = ''): string
{
    return sprintf('<div class="ph %s" role="img" aria-label="%s"><span>%s</span></div>', esc_attr($class), esc_attr($label), esc_html($label));
}

function brandhouse_icon(string $name): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'bag' => '<path d="M5 8h14l-1 13H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'menu' => '<path d="M3 7h18M3 12h18M3 17h18"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'truck' => '<path d="M2 6h12v10H2zM14 10h4l3 3v3h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="1"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}
