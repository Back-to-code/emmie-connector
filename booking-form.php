<?php

declare(strict_types = 1);

add_shortcode('emmie_kennismaking', 'wp2emmie_render_booking_form');

/**
 * The booking form for introductory meetings, as Emmie's own script draws it into the page (EMMIE-2740). The base
 * style follows the plugin setting, unless the shortcode says opmaak="basis" or opmaak="geen".
 *
 * @param array<string, string>|string $atts
 */
function wp2emmie_render_booking_form(array|string $atts = []): string
{
    $atts = shortcode_atts(['opmaak' => ''], $atts, 'emmie_kennismaking');
    $origin = wp2emmie_emmie_origin();

    if ($origin === '' || !get_option('wp2emmie_token_is_valid')) {
        return current_user_can('manage_options')
            ? '<p class="wp2emmie-notice">' . esc_html__('The booking form is not shown: enter a valid Emmie API URL and token in the plugin settings. Visitors see nothing here.', 'emmie-connector') . '</p>'
            : '';
    }

    $base_style = $atts['opmaak'] === '' ? (bool) get_option('wp2emmie_booking_base_style') : $atts['opmaak'] === 'basis';

    wp_enqueue_script('wp2emmie-kennismaking', $origin . '/kennismaking/widget.js', [], null, ['strategy' => 'async', 'in_footer' => true]);

    // The script replaces the link with the form; when the script cannot load, the visitor still reaches Emmie's booking page.
    return '<div data-emmie-kennismaking' . ($base_style ? ' data-opmaak="basis"' : '') . '><p><a href="' . esc_url($origin . '/kennismaking') . '">'
        . esc_html__('Plan an introductory meeting', 'emmie-connector') . '</a></p></div>';
}

/**
 * Where a visitor plans a meeting after signing up: the first published page with the booking form, or else the booking
 * page in Emmie.
 */
function wp2emmie_booking_page_url(): string
{
    foreach (get_posts(['post_type' => 'page', 'post_status' => 'publish', 's' => '[emmie_kennismaking', 'numberposts' => 10]) as $page) {
        if (has_shortcode($page->post_content, 'emmie_kennismaking')) {
            return (string) get_permalink($page);
        }
    }

    return wp2emmie_emmie_origin() . '/kennismaking';
}
