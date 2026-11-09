<?php

declare(strict_types = 1);

add_action('admin_menu', 'wp2emmie_add_admin_menu');
add_action('admin_init', 'wp2emmie_settings_init');

add_action('admin_notices', function(): void {
    if ($notice = get_transient('wp2emmie_token_status')) {
        $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . $notice['message'] . '</p></div>';
        delete_transient('wp2emmie_token_status');
    }
});

function wp2emmie_add_admin_menu(): void
{
    add_options_page(
        'WP Emmie Plugin',
        'WP Emmie Plugin',
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
        __('Emmie API Settings', 'wp-emmie-plugin'),
        null,
        'wp2emmie_settings',
    );

    add_settings_field(
        'wp2emmie_api_url',
        __('API URL', 'wp-emmie-plugin'),
        'wp2emmie_api_url_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_api_token',
        __('API Token', 'wp-emmie-plugin'),
        'wp2emmie_api_token_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_recaptcha_site_key',
        __('reCAPTCHA v3 Site Key', 'wp-emmie-plugin'),
        'wp2emmie_recaptcha_site_key_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_recaptcha_secret_key',
        __('reCAPTCHA v3 Secret Key', 'wp-emmie-plugin'),
        'wp2emmie_recaptcha_secret_key_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );

    add_settings_field(
        'wp2emmie_booking_base_style',
        __('Booking form', 'wp-emmie-plugin'),
        'wp2emmie_booking_base_style_render',
        'wp2emmie_settings',
        'wp2emmie_section_main',
    );
}

function wp2emmie_booking_base_style_render(): void
{
    $checked = get_option('wp2emmie_booking_base_style') ? 'checked' : '';
    echo "<label><input type='checkbox' name='wp2emmie_booking_base_style' value='1' " . $checked . '> ' . esc_html__('Neutral base style from Emmie', 'wp-emmie-plugin') . '</label>';
    echo '<p class="description">' . esc_html__('Without it, your theme styles the booking form of [emmie_kennismaking] entirely. A shortcode can choose per page with opmaak="basis" or opmaak="geen".', 'wp-emmie-plugin') . '</p>';
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
        $encryption = new FSD_Data_Encryption;
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
    $is_setup_mode = isset($_GET['setup']) && $_GET['setup'] === '1';

    ?>
    <div class="wrap">
        <h1><?= $is_setup_mode ? _e('Configure Emmie API Connection', 'wp-emmie-plugin') : 'Wordpress Emmie Plugin'; ?></h1>
        <?php if ($is_setup_mode) { ?>
            <p> <?php _e('Welcome! Please enter your Emmie API credentials to begin using the plugin.', 'wp-emmie-plugin'); ?></p>
            <p> <?php _e('You can find your API credentials in your Emmie app under Settings > Inflow.', 'wp-emmie-plugin'); ?></p>
        <?php } ?>
        <?php if (get_privacy_policy_url() === '') { ?>
            <div class="notice notice-warning inline"><p><?= \sprintf(
                // translators: %s: the address of the WordPress privacy settings
                __('The intake form links to Emmie\'s privacy statement, because this site has none. Choose your own privacy statement under <a href="%s">Settings &gt; Privacy</a>: the personal data from the form are yours.', 'wp-emmie-plugin'),
                esc_url(admin_url('options-privacy.php')),
            ); ?></p></div>
        <?php } ?>
        <form action="options.php" method="post">
            <?php
                settings_fields('wp2emmie_settings');
    do_settings_sections('wp2emmie_settings');
    submit_button($is_setup_mode ? __('Save & Activate', 'wp-emmie-plugin') : __('Save Changes', 'wp-emmie-plugin'));
    ?>
        </form>
    </div>
<?php
}

add_filter('pre_update_option_wp2emmie_api_token', 'pre_update_api_token_option', 10, 2);
add_action('update_option_wp2emmie_api_token', 'wp2emmie_validate_token_on_save', 10, 2);
add_action('add_option_wp2emmie_api_token', 'wp2emmie_validate_token_on_save', 10, 2);
add_action('update_option_wp2emmie_api_url', 'wp2emmie_validate_token_on_save', 10, 2);
add_action('add_option_wp2emmie_api_url', 'wp2emmie_validate_token_on_save', 10, 2);

function pre_update_api_token_option($new_value, $old_value)
{
    if (empty($new_value)) {
        return $old_value;
    }
    $encryption = new FSD_Data_Encryption;

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

    $api_url = get_option('wp2emmie_api_url');
    $api_token_encrypted = get_option('wp2emmie_api_token');

    if (!$api_url || !$api_token_encrypted) {
        update_option('wp2emmie_token_is_valid', false);
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => __('API URL and Token are required', 'wp-emmie-plugin'),
        ], 10);

        return;
    }

    $encryption = new FSD_Data_Encryption;
    $api_token = $encryption->decrypt($api_token_encrypted);

    $verify_url = $api_url . '/api/plugin-token'; // Append endpoint

    $response = wp_remote_post($verify_url, [
        'headers' => [
            'X-API-KEY' => $api_token,
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => __('Could not connect to the Emmie API: ', 'wp-emmie-plugin') . $response->get_error_message(),
        ], 10);
        update_option('wp2emmie_token_is_valid', false);

        return;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code === 404) {
        update_option('wp2emmie_token_is_valid', false);
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => __('API token validation failed . Please check whether your API URL is correct.', 'wp-emmie-plugin'),
        ], 10);
    } elseif ($code !== 200) {
        update_option('wp2emmie_token_is_valid', false);
        set_transient('wp2emmie_token_status', [
            'type' => 'error',
            'message' => __('API token validation failed (HTTP ' . $code . '). Please check your token.', 'wp-emmie-plugin'),
        ], 10);
    } else {
        update_option('wp2emmie_token_is_valid', true);
        set_transient('wp2emmie_token_status', [
            'type' => 'success',
            'message' => __('API token validated successfully.', 'wp-emmie-plugin'),
        ], 10);

        $page_slug = 'kennismaken';
        $existing = get_page_by_path($page_slug);

        if (!$existing) {
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
                    'message' => __('✅ API token validated successfully. The kennismaken page has been created: <a href="' . $page_url . '" target="_blank">' . esc_html($page_url) . '</a>', 'wp-emmie-plugin'),
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
                'message' => __('✅ API token validated successfully. The kennismaken page has been created: <a href="' . $page_url . '" target="_blank">' . esc_html($page_url) . '</a>', 'wp-emmie-plugin'),
            ], 10);
        }
    }
}
