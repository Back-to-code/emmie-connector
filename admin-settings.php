<?php

declare(strict_types = 1);

\defined('ABSPATH') || exit;

add_action('admin_menu', 'wp2emmie_add_admin_menu');
add_action('admin_init', 'wp2emmie_settings_init');

add_action('admin_notices', function(): void {
    $notice = get_transient('wp2emmie_token_status');
    if ($notice) {
        $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . wp_kses($notice['message'], ['a' => ['href' => true, 'target' => true]]) . '</p></div>';
        delete_transient('wp2emmie_token_status');
    }
});

function wp2emmie_add_admin_menu(): void
{
    add_options_page(
        'Emmie Connector',
        'Emmie Connector',
        'manage_options',
        'wp2emmie_connector',
        'wp2emmie_options_page',
    );
}

function wp2emmie_settings_init(): void
{
    register_setting('wp2emmie_settings', 'wp2emmie_api_url', ['sanitize_callback' => 'esc_url_raw']);
    register_setting('wp2emmie_settings', 'wp2emmie_api_token', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('wp2emmie_settings', 'wp2emmie_recaptcha_site_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('wp2emmie_settings', 'wp2emmie_recaptcha_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('wp2emmie_settings', 'wp2emmie_booking_base_style', ['sanitize_callback' => static fn($value): bool => (bool) $value, 'default' => false]);

    add_settings_section(
        'wp2emmie_section_main',
        __('Emmie API Settings', 'emmie-connector'),
        null,
        'wp2emmie_settings',
    );

    add_settings_field(
        'wp2emmie_api_url',
        __('API URL', 'emmie-connector'),
        'wp2emmie_api_url_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_api_token',
        __('API Token', 'emmie-connector'),
        'wp2emmie_api_token_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_recaptcha_site_key',
        __('reCAPTCHA v3 Site Key', 'emmie-connector'),
        'wp2emmie_recaptcha_site_key_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_recaptcha_secret_key',
        __('reCAPTCHA v3 Secret Key', 'emmie-connector'),
        'wp2emmie_recaptcha_secret_key_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_booking_base_style',
        __('Booking form', 'emmie-connector'),
        'wp2emmie_booking_base_style_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );
}

function wp2emmie_booking_base_style_render(): void
{
    echo "<label><input type='checkbox' name='wp2emmie_booking_base_style' value='1' " . checked((bool) get_option('wp2emmie_booking_base_style'), true, false) . '> ' . esc_html__('Neutral base style from Emmie', 'emmie-connector') . '</label>';
    echo '<p class="description">' . esc_html__('Without it, your theme styles the booking form of [emmie_kennismaking] entirely. A shortcode can choose per page with opmaak="basis" or opmaak="geen".', 'emmie-connector') . '</p>';
}

function wp2emmie_api_url_render(): void
{
    $value = get_option('wp2emmie_api_url');
    echo "<input type='url' name='wp2emmie_api_url' value='" . esc_attr($value) . "' size='50'>";
}

function wp2emmie_api_token_render(): void
{
    $value_encrypted = get_option('wp2emmie_api_token');
    $value = '';
    if ($value_encrypted) {
        $encryption = new WP2Emmie_Data_Encryption;
        $decrypted = $encryption->decrypt($value_encrypted);
        $value = $decrypted !== false ? $decrypted : '';
    }
    echo "<input type='password' name='wp2emmie_api_token' value='" . esc_attr($value) . "' size='50'>";
}

function wp2emmie_recaptcha_site_key_render(): void
{
    $value = get_option('wp2emmie_recaptcha_site_key');
    echo "<input type='text' name='wp2emmie_recaptcha_site_key' value='" . esc_attr($value) . "' size='50'>";
}

function wp2emmie_recaptcha_secret_key_render(): void
{
    $value = get_option('wp2emmie_recaptcha_secret_key');
    echo "<input type='text' name='wp2emmie_recaptcha_secret_key' value='" . esc_attr($value) . "' size='50'>";
}

function wp2emmie_options_page(): void
{
    // Only chooses the welcome text of the first visit after activation; nothing is changed.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $is_setup_mode = isset($_GET['setup']) && sanitize_text_field(wp_unslash($_GET['setup'])) === '1';

    ?>
    <div class="wrap">
        <h1><?php echo esc_html($is_setup_mode ? __('Configure Emmie API Connection', 'emmie-connector') : 'Emmie Connector'); ?></h1>
        <?php if ($is_setup_mode) { ?>
            <p> <?php esc_html_e('Welcome! Please enter your Emmie API credentials to begin using the plugin.', 'emmie-connector'); ?></p>
            <p> <?php esc_html_e('You can find your API credentials in your Emmie app under Settings > Inflow.', 'emmie-connector'); ?></p>
        <?php } ?>
        <?php if (get_privacy_policy_url() === '') { ?>
            <div class="notice notice-warning inline"><p><?php echo wp_kses(
                \sprintf(
                    // translators: %s: the address of the WordPress privacy settings
                    __('The intake form links to Emmie\'s privacy statement, because this site has none. Choose your own privacy statement under <a href="%s">Settings &gt; Privacy</a>: the personal data from the form are yours.', 'emmie-connector'),
                    esc_url(admin_url('options-privacy.php')),
                ),
                ['a' => ['href' => true]],
            ); ?></p></div>
        <?php } ?>
        <form action="options.php" method="post">
            <?php
                settings_fields('wp2emmie_settings');
    do_settings_sections('wp2emmie_settings');
    submit_button($is_setup_mode ? __('Save & Activate', 'emmie-connector') : __('Save Changes', 'emmie-connector'));
    ?>
        </form>
    </div>
<?php
}

add_filter('pre_update_option_wp2emmie_api_token', 'wp2emmie_encrypt_api_token_option', 10, 2);
add_action('update_option_wp2emmie_api_token', 'wp2emmie_validate_token_on_save', 10, 2);
add_action('add_option_wp2emmie_api_token', 'wp2emmie_validate_token_on_save', 10, 2);
add_action('update_option_wp2emmie_api_url', 'wp2emmie_validate_token_on_save', 10, 2);
add_action('add_option_wp2emmie_api_url', 'wp2emmie_validate_token_on_save', 10, 2);

function wp2emmie_encrypt_api_token_option($new_value, $old_value)
{
    if (empty($new_value)) {
        return $old_value;
    }
    $encryption = new WP2Emmie_Data_Encryption;

    return $encryption->encrypt($new_value);
}

function wp2emmie_validate_token_on_save(): void
{
    // check if token_is_valid option exists, else create it
    if (!empty(get_option('wp2emmie_token_is_valid'))) {
        update_option('wp2emmie_token_is_valid', true);
    } else {
        add_option('wp2emmie_token_is_valid', true);
    }

    $emmie_origin = wp2emmie_emmie_origin();
    $api_token_encrypted = get_option('wp2emmie_api_token');

    if ($emmie_origin === '' || !$api_token_encrypted) {
        update_option('wp2emmie_token_is_valid', false);
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => __('API URL and Token are required', 'emmie-connector'),
        ], 10);

        return;
    }

    $encryption = new WP2Emmie_Data_Encryption;
    $api_token = $encryption->decrypt($api_token_encrypted);

    $verify_url = $emmie_origin . '/api/plugin-token';

    $response = wp_remote_post($verify_url, [
        'headers' => [
            'X-API-KEY' => $api_token,
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => \sprintf(
                // translators: %s: the reason WordPress gives for the failed connection
                __('Could not connect to the Emmie API: %s', 'emmie-connector'),
                esc_html($response->get_error_message()),
            ),
        ], 10);
        update_option('wp2emmie_token_is_valid', false);

        return;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code === 404) {
        update_option('wp2emmie_token_is_valid', false);
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => __('API token validation failed. Please check whether your API URL is correct.', 'emmie-connector'),
        ], 10);
    } elseif ($code !== 200) {
        update_option('wp2emmie_token_is_valid', false);
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => \sprintf(
                // translators: %d: the HTTP status code Emmie answered with
                __('API token validation failed (HTTP %d). Please check your token.', 'emmie-connector'),
                (int) $code,
            ),
        ], 10);
    } else {
        update_option('wp2emmie_token_is_valid', true);
        set_transient('wp2emmie_token_status', [
            'type' => 'success',
            'message' => __('API token validated successfully.', 'emmie-connector'),
        ], 10);

        $page_slug = 'kennismaken';
        $existing = get_page_by_path($page_slug);

        // Once made, the page is the site's own: renamed or moved, it is not made again.
        if (!$existing && !get_option('wp2emmie_form_page_created')) {
            $new_page = [
                'post_title' => 'Kennismaken',
                'post_name' => $page_slug,
                'post_content' => '[wp2emmie_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
            ];

            $post_id = wp_insert_post($new_page);

            if (!is_wp_error($post_id)) {
                update_option('wp2emmie_form_page_created', true);

                $page_url = get_permalink($post_id);

                set_transient('wp2emmie_token_status', [
                    'type' => 'success',
                    'message' => wp2emmie_page_created_message((string) $page_url),
                ], 10);
            }
        } elseif (get_option('wp2emmie_form_page_created') === false) {
            // ask for a new slug
            $new_slug = 'kennismaken-' . wp_generate_password(3, false);
            $new_page = [
                'post_title' => 'Kennismaken',
                'post_name' => $new_slug,
                'post_content' => '[wp2emmie_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
            ];

            $post_id = wp_insert_post($new_page);

            if (!is_wp_error($post_id)) {
                update_option('wp2emmie_form_page_created', true);
            }

            $page_url = get_permalink($post_id);

            set_transient('wp2emmie_token_status', [
                'type' => 'success',
                'message' => wp2emmie_page_created_message((string) $page_url),
            ], 10);
        }
    }
}

function wp2emmie_page_created_message(string $page_url): string
{
    return \sprintf(
        // translators: %s: a link to the page with the intake form
        __('API token validated successfully. The kennismaken page has been created: %s', 'emmie-connector'),
        '<a href="' . esc_url($page_url) . '" target="_blank">' . esc_html($page_url) . '</a>',
    );
}
