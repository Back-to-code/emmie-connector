<?php
/*
 * Plugin Name: WP Emmie Plugin
 * Description: Shares client data from your Wordpress site to Emmie
 * Version: 1.3.0
 * Author: Script
 * Author URI:  https://emmie.nl
 * Text Domain: wp-emmie-plugin
 * Domain Path: /languages
 */

\defined('ABSPATH') || exit(__('Something went wrong', 'wp-emmie-plugin'));

require_once plugin_dir_path(__FILE__) . 'functions.php';
require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'form-handler.php';
require_once plugin_dir_path(__FILE__) . 'booking-form.php';
require_once plugin_dir_path(__FILE__) . 'fsd-data-encryption.php';

add_action('user_register', 'wp2emmie_send_user_data', 10, 1);

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
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('options-general.php?page=wp2emmie_connector&setup=1'));
            exit;
        }
    }
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    // Add a data attribute we’ll hook with JS
    $deactivate_link = admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(__FILE__) . '&plugin_status=all&paged=1&_wpnonce=' . wp_create_nonce('deactivate-plugin_' . plugin_basename(__FILE__)));

    $custom = '<a href="#" class="wp2emmie-deactivate" data-deactivate-url="' . esc_url($deactivate_link) . '">' . __('Deactivate', 'wp-emmie-plugin') . ' </a>';
    $links['deactivate'] = $custom;

    return $links;
});

add_action('admin_footer-plugins.php', function(): void {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.wp2emmie-deactivate').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    const deactivateUrl = el.dataset.deactivateUrl;
                    const confirmed = confirm(<?= wp_json_encode(__('Do you want to delete all WP to emmie Connector settings and data?', 'wp-emmie-plugin')); ?>);

                    if (confirmed) {
                        window.location.href = deactivateUrl + '&wp2emmie_delete=1';
                    } else {
                        window.location.href = deactivateUrl;
                    }
                });
            });
        });
    </script>
<?php
});

add_action('admin_init', function(): void {
    if (
        isset($_GET['action'], $_GET['plugin'], $_GET['wp2emmie_delete'])
        && $_GET['action'] === 'deactivate'
        && $_GET['plugin'] === plugin_basename(__FILE__)
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

        // Find and delete any page containing the [wp2emmie_form] shortcode
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
                    wp_delete_post($page_post->ID, true);
                }
            }
        }
    }
});

add_action('plugins_loaded', 'wp_emmie_plugin_load_textdomain');

function wp_emmie_plugin_load_textdomain(): void
{
    load_plugin_textdomain('wp-emmie-plugin', false, \dirname(plugin_basename(__FILE__)) . '/languages/');
}
