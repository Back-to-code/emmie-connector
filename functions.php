<?php

declare(strict_types = 1);

add_action('http_api_debug', function($response, $type, $class, $args, $url): void {
    if ($type !== 'response') {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');

    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        error_log("[{$timestamp}] HTTP API Error: {$error_msg} | URL: {$url}");

        return;
    }
}, 10, 5);

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
    return get_privacy_policy_url() ?: 'https://emmie.nl/privacy';
}
