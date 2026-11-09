<?php
/*
 * Plugin Name:       Emmie Connector
 * Description:       Connects your website to Emmie: an intake form that adds a new client to your Emmie, and Emmie's booking form for introductory meetings.
 * Version:           1.4.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            Emmie B.V.
 * Author URI:        https://emmie.nl
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       emmie-connector
 * Domain Path:       /languages
 */

\defined('ABSPATH') || exit;

\define('WP2EMMIE_VERSION', '1.4.0');
\define('WP2EMMIE_PLUGIN_FILE', __FILE__);

require_once plugin_dir_path(__FILE__) . 'functions.php';
require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'form-handler.php';
require_once plugin_dir_path(__FILE__) . 'booking-form.php';
require_once plugin_dir_path(__FILE__) . 'class-wp2emmie-data-encryption.php';

register_activation_hook(__FILE__, 'wp2emmie_activate_plugin');

function wp2emmie_activate_plugin(): void
{
    // Set an option to track first-time setup
    add_option('wp2emmie_do_activation_redirect', true);
}

add_action('admin_init', 'wp2emmie_redirect_after_activation');

function wp2emmie_redirect_after_activation(): void
{
    if (get_option('wp2emmie_do_activation_redirect', false)) {
        delete_option('wp2emmie_do_activation_redirect');
        // Only whether WordPress activated several plugins at once; nothing is read from it.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('options-general.php?page=wp2emmie_connector&setup=1'));
            exit;
        }
    }
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    // Add a data attribute we’ll hook with JS
    $deactivate_link = admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(__FILE__) . '&plugin_status=all&paged=1&_wpnonce=' . wp_create_nonce('deactivate-plugin_' . plugin_basename(__FILE__)));

    $custom = '<a href="' . esc_url($deactivate_link) . '" class="wp2emmie-deactivate" data-deactivate-url="' . esc_url($deactivate_link) . '">' . esc_html__('Deactivate', 'emmie-connector') . ' </a>';
    $links['deactivate'] = $custom;

    return $links;
});

add_action('admin_enqueue_scripts', function(string $hook_suffix): void {
    if ($hook_suffix !== 'plugins.php') {
        return;
    }

    wp_enqueue_script('wp2emmie-plugins-page', plugins_url('assets/js/plugins-page.js', __FILE__), [], WP2EMMIE_VERSION, ['in_footer' => true]);
    wp_add_inline_script(
        'wp2emmie-plugins-page',
        'var wp2emmiePluginsPage = ' . wp_json_encode(['confirmDelete' => __('Do you also want to delete the settings of Emmie Connector, and move the pages with its intake form to the trash?', 'emmie-connector')]) . ';',
        'before',
    );
});

add_action('admin_init', function(): void {
    if (
        isset($_GET['action'], $_GET['plugin'], $_GET['wp2emmie_delete'], $_GET['_wpnonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'deactivate-plugin_' . plugin_basename(__FILE__))
        && sanitize_text_field(wp_unslash($_GET['action'])) === 'deactivate'
        && sanitize_text_field(wp_unslash($_GET['plugin'])) === plugin_basename(__FILE__)
        && current_user_can('activate_plugins')
    ) {
        // Delete all settings
        delete_option('wp2emmie_api_url');
        delete_option('wp2emmie_api_token');
        delete_option('wp2emmie_recaptcha_site_key');
        delete_option('wp2emmie_recaptcha_secret_key');
        delete_option('wp2emmie_booking_base_style');
        delete_option('wp2emmie_token_is_valid');
        delete_option('wp2emmie_form_page_created');

        // Every page with the intake form goes to the trash, where the site owner can still restore it.
        $pages_with_shortcode = get_posts([
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'any',
            's' => 'wp2emmie_form',
            'fields' => 'all',
        ]);

        if (!empty($pages_with_shortcode)) {
            foreach ($pages_with_shortcode as $page_post) {
                if (!empty($page_post->post_content) && has_shortcode($page_post->post_content, 'wp2emmie_form')) {
                    wp_trash_post($page_post->ID);
                }
            }
        }
    }
});

add_action('plugins_loaded', 'wp2emmie_load_textdomain');

function wp2emmie_load_textdomain(): void
{
    load_plugin_textdomain('emmie-connector', false, \dirname(plugin_basename(__FILE__)) . '/languages/');
}
