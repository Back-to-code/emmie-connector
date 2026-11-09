<?php

declare(strict_types = 1);

\defined('ABSPATH') || exit;

add_action('http_api_debug', function($response, $context, $transport, $args, $url): void {
    $emmie_origin = wp2emmie_emmie_origin();

    if ($context === 'response' && is_wp_error($response) && $emmie_origin !== '' && str_starts_with((string) $url, $emmie_origin)) {
        wp2emmie_log('HTTP API Error: ' . $response->get_error_message() . ' | URL: ' . $url);
    }
}, 10, 5);

/**
 * Writes to the PHP error log, only while WP_DEBUG is on.
 */
function wp2emmie_log(string $message): void
{
    if (\defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[Emmie Connector] ' . $message);
    }
}

/**
 * The organisation's own Emmie, from the plugin's API URL. Emmie's Instroom page offers that URL with /api, while the
 * plugin appends /api itself, so either form is read.
 */
function wp2emmie_emmie_origin(): string
{
    return (string) preg_replace('#/api$#', '', untrailingslashit((string) get_option('wp2emmie_api_url')));
}

/**
 * The intake's personal data belong to the organisation, so the form links to the site's own privacy statement
 * (Settings > Privacy); Emmie's, which explains its role as processor, only while the site has none.
 */
function wp2emmie_privacy_policy_url(): string
{
    $url = get_privacy_policy_url();

    return $url !== '' ? $url : 'https://emmie.nl/privacy';
}
