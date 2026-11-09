<?php

declare(strict_types = 1);

\defined('ABSPATH') || exit;

add_shortcode('wp2emmie_form', 'wp2emmie_render_form');
add_action('wp_enqueue_scripts', 'wp2emmie_register_form_assets');

function wp2emmie_register_form_assets(): void
{
    wp_register_style('wp2emmie-form', plugins_url('assets/css/form.css', WP2EMMIE_PLUGIN_FILE), [], WP2EMMIE_VERSION);
    wp_register_script('wp2emmie-form', plugins_url('assets/js/form.js', WP2EMMIE_PLUGIN_FILE), [], WP2EMMIE_VERSION, ['in_footer' => true]);
    // Google serves this script and versions it itself.
    // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
    wp_register_script('wp2emmie-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, ['strategy' => 'async', 'in_footer' => true]);

    // In the head on a page that holds the form, so the contact person's fields do not flash open.
    $post = get_post();
    if (is_singular() && $post instanceof WP_Post && has_shortcode($post->post_content, 'wp2emmie_form')) {
        wp_enqueue_style('wp2emmie-form');
    }
}

/**
 * Fetch intake form options from the API, cached in a transient for 1 hour.
 */
function wp2emmie_get_intake_options(): ?array
{
    $cached = get_transient('wp2emmie_intake_options');
    if ($cached !== false) {
        return $cached;
    }

    $emmie_origin = wp2emmie_emmie_origin();
    $api_token_encrypted = get_option('wp2emmie_api_token');

    if ($emmie_origin === '' || !$api_token_encrypted) {
        return null;
    }

    $encryption = new WP2Emmie_Data_Encryption;
    $api_token = $encryption->decrypt($api_token_encrypted);

    $response = wp_remote_get($emmie_origin . '/api/intake/options', [
        'headers' => [
            'X-API-KEY' => $api_token,
            'Accept' => 'application/json',
        ],
    ]);

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        return null;
    }

    $options = json_decode(wp_remote_retrieve_body($response), true);
    set_transient('wp2emmie_intake_options', $options, HOUR_IN_SECONDS);

    return $options;
}

function wp2emmie_render_form(): string
{
    $site_key = (string) get_option('wp2emmie_recaptcha_site_key');
    $token_valid = get_option('wp2emmie_token_is_valid');

    // If token is invalid or not set, disable form
    if (!$token_valid) {
        return '<div style="color: red; font-weight: bold;"> ' . esc_html__('The form is not available', 'emmie-connector') . '</div>';
    }

    wp_enqueue_style('wp2emmie-form');
    wp_enqueue_script('wp2emmie-form');
    if ($site_key !== '') {
        wp_enqueue_script('wp2emmie-recaptcha');
    }

    $options = wp2emmie_get_intake_options();

    // Process submission first so we know whether to prefill or clear values. This only detects a submission:
    // wp2emmie_process_form() verifies the nonce before it reads any field.
    $submission_result = null;
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (isset($_SERVER['REQUEST_METHOD'], $_POST['emmie_form_nonce']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $submission_result = wp2emmie_process_form();
    }
    $submission_success = $submission_result !== null && $submission_result['success'];
    $errors = $submission_result['errors'] ?? [];
    $values = $submission_success ? [] : ($submission_result['values'] ?? []);
    $value = static fn(string $name): string => isset($values[$name]) ? (string) $values[$name] : '';
    $error_class = static fn(string $name): string => isset($errors[$name]) ? 'wp2emmie-field-error' : '';

    ob_start();
    ?>
    <form method="post" id="emmie-form" enctype="multipart/form-data">
        <?php wp_nonce_field('emmie_form_action', 'emmie_form_nonce'); ?>

        <!-- Required fields -->
        <p>
            <label for="client_first_name" class="required"><?php esc_html_e('First Name', 'emmie-connector'); ?></label><br>
            <input type="text" name="client_first_name" class="<?php echo esc_attr($error_class('client_first_name')); ?>" value="<?php echo esc_attr($value('client_first_name')); ?>" required>
            <?php if (isset($errors['client_first_name'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['client_first_name']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="client_last_name" class="required"><?php esc_html_e('Last Name', 'emmie-connector'); ?></label><br>
            <input type="text" name="client_last_name" class="<?php echo esc_attr($error_class('client_last_name')); ?>" value="<?php echo esc_attr($value('client_last_name')); ?>" required>
            <?php if (isset($errors['client_last_name'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['client_last_name']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="client_email" class="required"><?php esc_html_e('Email Address', 'emmie-connector'); ?></label><br>
            <input type="email" name="client_email" class="<?php echo esc_attr($error_class('client_email')); ?>" value="<?php echo esc_attr($value('client_email')); ?>" required>
            <?php if (isset($errors['client_email'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['client_email']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="client_phone_number" class="required"><?php esc_html_e('Telephone Number', 'emmie-connector'); ?></label><br>
            <input type="text" name="client_phone_number" class="<?php echo esc_attr($error_class('client_phone_number')); ?>" value="<?php echo esc_attr($value('client_phone_number')); ?>">
            <?php if (isset($errors['client_phone_number'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['client_phone_number']); ?></span>
            <?php } ?>
        </p>
        <br>

        <!-- Address fields -->
        <div class="wp2emmie-section">
            <p>
                <label for="client_street"><?php esc_html_e('Street', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_street" value="<?php echo esc_attr($value('client_street')); ?>">
            </p>
            <p>
                <label for="client_number"><?php esc_html_e('House Number', 'emmie-connector'); ?></label><br>
                <input type="number" name="client_number" value="<?php echo esc_attr($value('client_number')); ?>">
            </p>
            <p>
                <label for="client_number_addition"><?php esc_html_e('Addition', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_number_addition" value="<?php echo esc_attr($value('client_number_addition')); ?>">
            </p>
            <p>
                <label for="client_zipcode"><?php esc_html_e('Zipcode', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_zipcode" value="<?php echo esc_attr($value('client_zipcode')); ?>">
            </p>
            <p>
                <label for="client_city"><?php esc_html_e('City', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_city" value="<?php echo esc_attr($value('client_city')); ?>">
            </p>
        </div>

        <!-- Location dropdown -->
        <?php if (!empty($options['locations'])) { ?>
        <p>
            <label for="client_location_id" class="required"><?php esc_html_e('Which location are you interested in?', 'emmie-connector'); ?></label><br>
            <select name="client_location_id" required class="<?php echo esc_attr($error_class('client_location_id')); ?>">
                <option value=""><?php esc_html_e('Select a location', 'emmie-connector'); ?></option>
                <?php foreach ($options['locations'] as $location) { ?>
                    <option value="<?php echo esc_attr((string) $location['id']); ?>" <?php selected($value('client_location_id'), (string) $location['id']); ?>>
                        <?php echo esc_html((string) $location['name']); ?>
                    </option>
                <?php } ?>
            </select>
            <?php if (isset($errors['client_location_id'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['client_location_id']); ?></span>
            <?php } ?>
        </p>
        <br>
        <?php } ?>

        <!-- Income situation dropdown -->
        <?php if (!empty($options['income_situations'])) { ?>
        <p>
            <label for="client_income_situation"><?php esc_html_e('Income Situation', 'emmie-connector'); ?></label><br>
            <select name="client_income_situation">
                <option value=""><?php esc_html_e('Select income situation', 'emmie-connector'); ?></option>
                <?php foreach ($options['income_situations'] as $situation) { ?>
                    <option value="<?php echo esc_attr((string) $situation['id']); ?>" <?php selected($value('client_income_situation'), (string) $situation['id']); ?>>
                        <?php echo esc_html((string) $situation['name']); ?>
                    </option>
                <?php } ?>
            </select>
        </p>
        <br>
        <?php } ?>

        <!-- Contact preference dropdown -->
        <?php if (!empty($options['contact_preferences'])) { ?>
        <p>
            <label for="client_contact_preference_id"><?php esc_html_e('Contact Preference', 'emmie-connector'); ?></label><br>
            <select name="client_contact_preference_id">
                <option value=""><?php esc_html_e('Select contact preference', 'emmie-connector'); ?></option>
                <?php foreach ($options['contact_preferences'] as $preference) { ?>
                    <option value="<?php echo esc_attr((string) $preference['id']); ?>" <?php selected($value('client_contact_preference_id'), (string) $preference['id']); ?>>
                        <?php echo esc_html((string) $preference['name']); ?>
                    </option>
                <?php } ?>
            </select>
        </p>
        <br>
        <?php } ?>

        <!-- Contact person section (collapsible) -->
        <div class="wp2emmie-section">
            <p>
                <label>
                    <input type="checkbox" id="wp2emmie-has-contact" name="has_contact_person" <?php checked($value('has_contact_person'), '1'); ?>>
                    <?php esc_html_e('Add contact person', 'emmie-connector'); ?>
                </label>
            </p>
            <div class="wp2emmie-contact-fields" id="wp2emmie-contact-fields">
                <p>
                    <label for="contact_first_name"><?php esc_html_e('Contact First Name', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_first_name" value="<?php echo esc_attr($value('contact_first_name')); ?>">
                </p>
                <p>
                    <label for="contact_last_name"><?php esc_html_e('Contact Last Name', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_last_name" value="<?php echo esc_attr($value('contact_last_name')); ?>">
                </p>
                <p>
                    <label for="contact_organisation"><?php esc_html_e('Organisation', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_organisation" value="<?php echo esc_attr($value('contact_organisation')); ?>">
                </p>
                <p>
                    <label for="contact_email"><?php esc_html_e('Contact Email', 'emmie-connector'); ?></label><br>
                    <input type="email" name="contact_email" value="<?php echo esc_attr($value('contact_email')); ?>">
                </p>
                <p>
                    <label for="contact_phone_number"><?php esc_html_e('Contact Phone Number', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_phone_number" value="<?php echo esc_attr($value('contact_phone_number')); ?>">
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="contact_may_contact_before_intake" value="1" <?php checked($value('contact_may_contact_before_intake'), '1'); ?>>
                        <?php esc_html_e('The organisation may contact this person before the first meeting', 'emmie-connector'); ?>
                    </label>
                </p>
            </div>
        </div>
        <br>

        <!-- Referral source dropdown -->
        <?php if (!empty($options['referral_sources'])) { ?>
        <p>
            <label for="client_referral_source"><?php esc_html_e('How did you find us?', 'emmie-connector'); ?></label><br>
            <select name="client_referral_source" id="wp2emmie-referral-source">
                <option value=""><?php esc_html_e('Select an option', 'emmie-connector'); ?></option>
                <?php foreach ($options['referral_sources'] as $source) { ?>
                    <option value="<?php echo esc_attr((string) $source['name']); ?>" <?php selected($value('client_referral_source'), (string) $source['name']); ?>>
                        <?php echo esc_html((string) $source['name']); ?>
                    </option>
                <?php } ?>
            </select>
            <span class="wp2emmie-referral-other" id="wp2emmie-referral-other">
                <br>
                <input type="text" name="client_referral_source_other" placeholder="<?php esc_attr_e('Please specify', 'emmie-connector'); ?>" value="<?php echo esc_attr($value('client_referral_source_other')); ?>">
            </span>
        </p>
        <br>
        <?php } ?>

        <!-- File upload -->
        <p>
            <label for="documents"><?php esc_html_e('Documents', 'emmie-connector'); ?></label><br>
            <input type="file" name="documents[]" multiple accept=".doc,.docx,.rtf,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.pdf">
            <small><?php esc_html_e('Allowed formats: doc, docx, rtf, xls, xlsx, txt, jpg, jpeg, png, pdf. Max 40MB per file.', 'emmie-connector'); ?></small>
        </p>
        <br>

        <!-- Comment -->
        <p>
            <label for="client_comment"><?php esc_html_e('Comments', 'emmie-connector'); ?></label><br>
            <textarea name="client_comment" rows="4" cols="50" class="<?php echo esc_attr($error_class('client_comment')); ?>"><?php echo esc_textarea($value('client_comment')); ?></textarea>
            <?php if (isset($errors['client_comment'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['client_comment']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="privacy_checkbox" class="required"><?php esc_html_e('Personal Data', 'emmie-connector'); ?></label><br>
            <input type="checkbox" name="privacy_checkbox" id="privacy_checkbox" <?php checked($value('privacy_checkbox'), '1'); ?>> <br>
            <?php if (isset($errors['privacy_checkbox'])) { ?>
                <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['privacy_checkbox']); ?></span>
            <?php } ?>
            <?php echo wp_kses(
                \sprintf(
                    // translators: %s: the address of the privacy statement
                    __('By checking this box, you agree to the processing of your personal data according to the <a href="%s" target="_blank">privacy statement</a>', 'emmie-connector'),
                    esc_url(wp2emmie_privacy_policy_url()),
                ),
                ['a' => ['href' => true, 'target' => true]],
            ); ?>
        </p>
        <br>
        <?php if ($site_key !== '') { ?>
            <p>
                <button type="submit"
                    class="g-recaptcha"
                    data-sitekey="<?php echo esc_attr($site_key); ?>"
                    data-callback="wp2emmieRecaptchaSubmit"
                    data-action="submit">
                    <?php esc_html_e('Submit', 'emmie-connector'); ?>
                </button>
                <?php if (isset($errors['recaptcha'])) { ?>
                    <span class="wp2emmie-error-text"><?php echo esc_html((string) $errors['recaptcha']); ?></span>
                <?php } ?>
            </p>
        <?php } else { ?>
            <p>
                <button type="submit"><?php esc_html_e('Submit', 'emmie-connector'); ?></button>
            </p>
        <?php } ?>
    </form>

<?php
    if ($submission_result !== null) {
        echo '<div style="color:' . ($submission_success ? 'green' : 'red') . ';">' . esc_html($submission_result['message']);
        foreach ($submission_result['details'] as $detail) {
            echo '<p>' . esc_html($detail) . '</p>';
        }
        echo '</div>';
    }

    if ($submission_success) {
        echo '<p><a class="wp2emmie-booking-button button" href="' . esc_url(wp2emmie_booking_page_url()) . '">' . esc_html__('Plan an introductory meeting', 'emmie-connector') . '</a></p>';
    }

    return (string) ob_get_clean();
}

/**
 * Checks the nonce, reads and validates the fields, and sends the intake to Emmie.
 *
 * @return array{success: bool, errors: array<string, string>, values: array<string, string>, message: string, details: list<string>}
 */
function wp2emmie_process_form(): array
{
    if (
        !isset($_POST['emmie_form_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['emmie_form_nonce'])), 'emmie_form_action')
    ) {
        return [
            'success' => false,
            'errors' => [],
            'values' => [],
            'message' => __('Security check failed. Please reload the page and try again.', 'emmie-connector'),
            'details' => [],
        ];
    }

    $has_contact = isset($_POST['has_contact_person']);
    $values = [
        'client_first_name' => isset($_POST['client_first_name']) ? sanitize_text_field(wp_unslash($_POST['client_first_name'])) : '',
        'client_last_name' => isset($_POST['client_last_name']) ? sanitize_text_field(wp_unslash($_POST['client_last_name'])) : '',
        'client_email' => isset($_POST['client_email']) ? sanitize_email(wp_unslash($_POST['client_email'])) : '',
        'client_phone_number' => isset($_POST['client_phone_number']) ? sanitize_text_field(wp_unslash($_POST['client_phone_number'])) : '',
        'client_comment' => isset($_POST['client_comment']) ? sanitize_textarea_field(wp_unslash($_POST['client_comment'])) : '',
        'client_location_id' => isset($_POST['client_location_id']) ? sanitize_text_field(wp_unslash($_POST['client_location_id'])) : '',
        'client_street' => isset($_POST['client_street']) ? sanitize_text_field(wp_unslash($_POST['client_street'])) : '',
        'client_number' => isset($_POST['client_number']) ? sanitize_text_field(wp_unslash($_POST['client_number'])) : '',
        'client_number_addition' => isset($_POST['client_number_addition']) ? sanitize_text_field(wp_unslash($_POST['client_number_addition'])) : '',
        'client_zipcode' => isset($_POST['client_zipcode']) ? sanitize_text_field(wp_unslash($_POST['client_zipcode'])) : '',
        'client_city' => isset($_POST['client_city']) ? sanitize_text_field(wp_unslash($_POST['client_city'])) : '',
        'client_income_situation' => isset($_POST['client_income_situation']) ? sanitize_text_field(wp_unslash($_POST['client_income_situation'])) : '',
        'client_contact_preference_id' => isset($_POST['client_contact_preference_id']) ? sanitize_text_field(wp_unslash($_POST['client_contact_preference_id'])) : '',
        'has_contact_person' => $has_contact ? '1' : '',
        'contact_first_name' => $has_contact && isset($_POST['contact_first_name']) ? sanitize_text_field(wp_unslash($_POST['contact_first_name'])) : '',
        'contact_last_name' => $has_contact && isset($_POST['contact_last_name']) ? sanitize_text_field(wp_unslash($_POST['contact_last_name'])) : '',
        'contact_organisation' => $has_contact && isset($_POST['contact_organisation']) ? sanitize_text_field(wp_unslash($_POST['contact_organisation'])) : '',
        'contact_email' => $has_contact && isset($_POST['contact_email']) ? sanitize_email(wp_unslash($_POST['contact_email'])) : '',
        'contact_phone_number' => $has_contact && isset($_POST['contact_phone_number']) ? sanitize_text_field(wp_unslash($_POST['contact_phone_number'])) : '',
        'contact_may_contact_before_intake' => $has_contact && isset($_POST['contact_may_contact_before_intake']) ? '1' : '',
        'client_referral_source' => isset($_POST['client_referral_source']) ? sanitize_text_field(wp_unslash($_POST['client_referral_source'])) : '',
        'client_referral_source_other' => isset($_POST['client_referral_source_other']) ? sanitize_text_field(wp_unslash($_POST['client_referral_source_other'])) : '',
        'privacy_checkbox' => isset($_POST['privacy_checkbox']) && sanitize_text_field(wp_unslash($_POST['privacy_checkbox'])) === 'on' ? '1' : '',
    ];

    $errors = [];
    if ($values['privacy_checkbox'] === '') {
        $errors['privacy_checkbox'] = __('You must agree to share the submitted personal data', 'emmie-connector');
    }
    if ($values['client_first_name'] === '') {
        $errors['client_first_name'] = __('First Name is required', 'emmie-connector');
    }
    if ($values['client_last_name'] === '') {
        $errors['client_last_name'] = __('Last Name is required', 'emmie-connector');
    }
    if ($values['client_email'] === '' || !is_email($values['client_email'])) {
        $errors['client_email'] = __('Please enter a valid email address', 'emmie-connector');
    }
    if ($values['client_phone_number'] === '') {
        $errors['client_phone_number'] = __('Please enter a valid phone number', 'emmie-connector');
    }
    if ($values['client_location_id'] === '') {
        $errors['client_location_id'] = __('Location is required', 'emmie-connector');
    }

    // Referral source — append to comment
    $comment = $values['client_comment'];
    $referral_source = $values['client_referral_source'];
    if ($referral_source === 'Anders' && $values['client_referral_source_other'] !== '') {
        $referral_source = $values['client_referral_source_other'];
    }
    if ($referral_source !== '') {
        $comment = __('How did you find us:', 'emmie-connector') . ' ' . $referral_source . "\n\n" . $comment;
    }

    $recaptcha_secret = (string) get_option('wp2emmie_recaptcha_secret_key');
    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';

    if ($recaptcha_secret !== '') {
        if ($recaptcha_response === '') {
            $errors['recaptcha'] = __('Please complete the reCAPTCHA verification.', 'emmie-connector');
        } else {
            $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_response,
                    'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
                ],
            ]);

            $body = json_decode(wp_remote_retrieve_body($verify), true);

            if (empty($body['success'])) {
                $errors['recaptcha'] = __('reCAPTCHA verification failed. Please try again.', 'emmie-connector');
            }
        }
    }

    if (!empty($errors)) {
        return [
            'success' => false,
            'errors' => $errors,
            'values' => $values,
            'message' => __('Please correct the errors above before submitting.', 'emmie-connector'),
            'details' => [],
        ];
    }

    $emmie_origin = wp2emmie_emmie_origin();
    $api_token_encrypted = get_option('wp2emmie_api_token');

    if ($emmie_origin === '' || !$api_token_encrypted) {
        return [
            'success' => false,
            'errors' => [],
            'values' => $values,
            'message' => __('API not configured. Please contact support.', 'emmie-connector'),
            'details' => [],
        ];
    }

    $encryption = new WP2Emmie_Data_Encryption;
    $api_token = $encryption->decrypt($api_token_encrypted);

    $api_endpoint = $emmie_origin . '/api/intake';

    // Build multipart body for file upload support
    $boundary = wp_generate_password(24, false);

    $fields = [
        'first_name' => $values['client_first_name'],
        'last_name' => $values['client_last_name'],
        'email' => $values['client_email'],
        'phone_number' => $values['client_phone_number'],
        'comment' => $comment,
    ];

    // Add optional fields only if they have values
    $optional_fields = [
        'street' => 'client_street',
        'number' => 'client_number',
        'number_addition' => 'client_number_addition',
        'zipcode' => 'client_zipcode',
        'city' => 'client_city',
        'location_id' => 'client_location_id',
        'income_situation' => 'client_income_situation',
        'contact_preference_id' => 'client_contact_preference_id',
    ];
    foreach ($optional_fields as $field => $name) {
        if ($values[$name] !== '') {
            $fields[$field] = $values[$name];
        }
    }

    // Contact person fields
    if ($has_contact && $values['contact_first_name'] !== '' && $values['contact_last_name'] !== '') {
        $fields['contact_first_name'] = $values['contact_first_name'];
        $fields['contact_last_name'] = $values['contact_last_name'];
        foreach (['contact_organisation', 'contact_email', 'contact_phone_number'] as $name) {
            if ($values[$name] !== '') {
                $fields[$name] = $values[$name];
            }
        }
        if ($values['contact_may_contact_before_intake'] !== '') {
            $fields['contact_may_contact_before_intake'] = '1';
        }
    }

    $body = '';
    foreach ($fields as $name => $value) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
        $body .= "{$value}\r\n";
    }

    // Append files
    $document_count = isset($_FILES['documents']['error']) && \is_array($_FILES['documents']['error']) ? \count($_FILES['documents']['error']) : 0;
    for ($i = 0; $i < $document_count; $i++) {
        if (
            !isset($_FILES['documents']['error'][$i], $_FILES['documents']['tmp_name'][$i], $_FILES['documents']['name'][$i], $_FILES['documents']['type'][$i])
            || (int) $_FILES['documents']['error'][$i] !== \UPLOAD_ERR_OK
        ) {
            continue;
        }
        // A path PHP chose for the upload; is_uploaded_file() is its check, no sanitising applies.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $tmp_name = (string) $_FILES['documents']['tmp_name'][$i];
        if (!is_uploaded_file($tmp_name)) {
            continue;
        }
        $filename = sanitize_file_name(wp_unslash($_FILES['documents']['name'][$i]));
        $mime = sanitize_mime_type(wp_unslash($_FILES['documents']['type'][$i]));
        // The temporary upload on this server, not a URL.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = (string) file_get_contents($tmp_name);

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"documents[]\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mime}\r\n\r\n";
        $body .= $content . "\r\n";
    }

    $body .= "--{$boundary}--\r\n";

    $response = wp_remote_post($api_endpoint, [
        'headers' => [
            'X-API-KEY' => $api_token,
            'Accept' => 'application/json',
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ],
        'body' => $body,
        'timeout' => 30,
    ]);

    $code = (int) wp_remote_retrieve_response_code($response);

    // A connection that failed has no status code, and must not read as sent.
    if (is_wp_error($response) || $code < 200 || $code >= 300) {
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        $details = [];
        foreach (\is_array($decoded) && \is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [] as $property_error) {
            $details[] = (string) (\is_array($property_error) ? ($property_error[0] ?? '') : $property_error);
        }

        wp2emmie_log('Intake to Emmie failed: HTTP ' . $code . ' (' . wp_remote_retrieve_response_message($response) . ')');

        return [
            'success' => false,
            'errors' => [],
            'values' => $values,
            'message' => __('Submission failed:', 'emmie-connector'),
            'details' => $details,
        ];
    }

    return [
        'success' => true,
        'errors' => [],
        'values' => [],
        'message' => __('Thank you! Your information has been submitted.', 'emmie-connector'),
        'details' => [],
    ];
}
