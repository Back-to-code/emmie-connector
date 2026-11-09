<?php

declare(strict_types = 1);

// Shortcode: [wp2emmie_form]
add_shortcode('wp2emmie_form', 'wp2emmie_render_form');

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

function wp2emmie_render_form(): false|string
{
    $site_key = get_option('wp2emmie_recaptcha_site_key');
    $token_valid = get_option('wp2emmie_token_is_valid');

    // If token is invalid or not set, disable form
    if (!$token_valid) {
        return '<div style="color: red; font-weight: bold;"> ' . __('The form is not available', 'emmie-connector') . '</div>';
    }

    $options = wp2emmie_get_intake_options();

    // Process submission first so we know whether to prefill or clear values
    $submission_result = null;
    $submission_success = false;
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emmie_form_nonce'])) {
        $submission_result = wp2emmie_process_form();
        $submission_success = !empty($submission_result['success']);
        $errors = $submission_result['errors'] ?? [];
    }

    ob_start();
    ?>
    <style>
        #emmie-form .wp2emmie-field-error {
            border-color: #dc3232;
        }

        #emmie-form .wp2emmie-error-text {
            color: #dc3232;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }

        #emmie-form label.required::after {
            content: '*';
            color: #dc3232;
        }

        #emmie-form .wp2emmie-section {
            margin-bottom: 10px;
        }

        #emmie-form .wp2emmie-contact-fields {
            display: none;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
        }

        #emmie-form .wp2emmie-referral-other {
            display: none;
            margin-top: 5px;
        }
    </style>
    <form method="post" id="emmie-form" enctype="multipart/form-data">
        <?php wp_nonce_field('emmie_form_action', 'emmie_form_nonce'); ?>

        <!-- Required fields -->
        <p>
            <label for="client_first_name" class="required"><?php _e('First Name', 'emmie-connector'); ?></label><br>
            <input type="text" name="client_first_name" class="<?= isset($errors['client_first_name']) ? 'wp2emmie-field-error' : ''; ?>" value="<?= !$submission_success && isset($_POST['client_first_name']) ? esc_attr((string) $_POST['client_first_name']) : ''; ?>" required>
            <?php if (isset($errors['client_first_name'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['client_first_name']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="client_last_name" class="required"><?php _e('Last Name', 'emmie-connector'); ?></label><br>
            <input type="text" name="client_last_name" class="<?= isset($errors['client_last_name']) ? 'wp2emmie-field-error' : ''; ?>" value="<?= !$submission_success && isset($_POST['client_last_name']) ? esc_attr((string) $_POST['client_last_name']) : ''; ?>" required>
            <?php if (isset($errors['client_last_name'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['client_last_name']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="client_email" class="required"><?php _e('Email Address', 'emmie-connector'); ?></label><br>
            <input type="email" name="client_email" class="<?= isset($errors['client_email']) ? 'wp2emmie-field-error' : ''; ?>" value="<?= !$submission_success && isset($_POST['client_email']) ? esc_attr((string) $_POST['client_email']) : ''; ?>" required>
            <?php if (isset($errors['client_email'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['client_email']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="client_phone_number" class="required"><?php _e('Telephone Number', 'emmie-connector'); ?></label><br>
            <input type="text" name="client_phone_number" class="<?= isset($errors['client_phone_number']) ? 'wp2emmie-field-error' : ''; ?>" value="<?= !$submission_success && isset($_POST['client_phone_number']) ? esc_attr((string) $_POST['client_phone_number']) : ''; ?>">
            <?php if (isset($errors['client_phone_number'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['client_phone_number']); ?></span>
            <?php } ?>
        </p>
        <br>

        <!-- Address fields -->
        <div class="wp2emmie-section">
            <p>
                <label for="client_street"><?php _e('Street', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_street" value="<?= !$submission_success && isset($_POST['client_street']) ? esc_attr((string) $_POST['client_street']) : ''; ?>">
            </p>
            <p>
                <label for="client_number"><?php _e('House Number', 'emmie-connector'); ?></label><br>
                <input type="number" name="client_number" value="<?= !$submission_success && isset($_POST['client_number']) ? esc_attr((string) $_POST['client_number']) : ''; ?>">
            </p>
            <p>
                <label for="client_number_addition"><?php _e('Addition', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_number_addition" value="<?= !$submission_success && isset($_POST['client_number_addition']) ? esc_attr((string) $_POST['client_number_addition']) : ''; ?>">
            </p>
            <p>
                <label for="client_zipcode"><?php _e('Zipcode', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_zipcode" value="<?= !$submission_success && isset($_POST['client_zipcode']) ? esc_attr((string) $_POST['client_zipcode']) : ''; ?>">
            </p>
            <p>
                <label for="client_city"><?php _e('City', 'emmie-connector'); ?></label><br>
                <input type="text" name="client_city" value="<?= !$submission_success && isset($_POST['client_city']) ? esc_attr((string) $_POST['client_city']) : ''; ?>">
            </p>
        </div>

        <!-- Location dropdown -->
        <?php if (!empty($options['locations'])) { ?>
        <p>
            <label for="client_location_id" class="required"><?php _e('Which location are you interested in?', 'emmie-connector'); ?></label><br>
            <select name="client_location_id" required class="<?= isset($errors['client_location_id']) ? 'wp2emmie-field-error' : ''; ?>">
                <option value=""><?php _e('Select a location', 'emmie-connector'); ?></option>
                <?php foreach ($options['locations'] as $location) { ?>
                    <option value="<?= esc_attr((string) $location['id']); ?>" <?= (!$submission_success && isset($_POST['client_location_id']) && $_POST['client_location_id'] === $location['id']) ? 'selected' : ''; ?>>
                        <?= esc_html((string) $location['name']); ?>
                    </option>
                <?php } ?>
            </select>
            <?php if (isset($errors['client_location_id'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['client_location_id']); ?></span>
            <?php } ?>
        </p>
        <br>
        <?php } ?>

        <!-- Income situation dropdown -->
        <?php if (!empty($options['income_situations'])) { ?>
        <p>
            <label for="client_income_situation"><?php _e('Income Situation', 'emmie-connector'); ?></label><br>
            <select name="client_income_situation">
                <option value=""><?php _e('Select income situation', 'emmie-connector'); ?></option>
                <?php foreach ($options['income_situations'] as $situation) { ?>
                    <option value="<?= esc_attr((string) $situation['id']); ?>" <?= (!$submission_success && isset($_POST['client_income_situation']) && $_POST['client_income_situation'] === $situation['id']) ? 'selected' : ''; ?>>
                        <?= esc_html((string) $situation['name']); ?>
                    </option>
                <?php } ?>
            </select>
        </p>
        <br>
        <?php } ?>

        <!-- Contact preference dropdown -->
        <?php if (!empty($options['contact_preferences'])) { ?>
        <p>
            <label for="client_contact_preference_id"><?php _e('Contact Preference', 'emmie-connector'); ?></label><br>
            <select name="client_contact_preference_id">
                <option value=""><?php _e('Select contact preference', 'emmie-connector'); ?></option>
                <?php foreach ($options['contact_preferences'] as $preference) { ?>
                    <option value="<?= esc_attr((string) $preference['id']); ?>" <?= (!$submission_success && isset($_POST['client_contact_preference_id']) && $_POST['client_contact_preference_id'] === $preference['id']) ? 'selected' : ''; ?>>
                        <?= esc_html((string) $preference['name']); ?>
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
                    <input type="checkbox" id="wp2emmie-has-contact" name="has_contact_person" <?= (!$submission_success && isset($_POST['has_contact_person'])) ? 'checked' : ''; ?>>
                    <?php _e('Add contact person', 'emmie-connector'); ?>
                </label>
            </p>
            <div class="wp2emmie-contact-fields" id="wp2emmie-contact-fields">
                <p>
                    <label for="contact_first_name"><?php _e('Contact First Name', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_first_name" value="<?= !$submission_success && isset($_POST['contact_first_name']) ? esc_attr((string) $_POST['contact_first_name']) : ''; ?>">
                </p>
                <p>
                    <label for="contact_last_name"><?php _e('Contact Last Name', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_last_name" value="<?= !$submission_success && isset($_POST['contact_last_name']) ? esc_attr((string) $_POST['contact_last_name']) : ''; ?>">
                </p>
                <p>
                    <label for="contact_organisation"><?php _e('Organisation', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_organisation" value="<?= !$submission_success && isset($_POST['contact_organisation']) ? esc_attr((string) $_POST['contact_organisation']) : ''; ?>">
                </p>
                <p>
                    <label for="contact_email"><?php _e('Contact Email', 'emmie-connector'); ?></label><br>
                    <input type="email" name="contact_email" value="<?= !$submission_success && isset($_POST['contact_email']) ? esc_attr((string) $_POST['contact_email']) : ''; ?>">
                </p>
                <p>
                    <label for="contact_phone_number"><?php _e('Contact Phone Number', 'emmie-connector'); ?></label><br>
                    <input type="text" name="contact_phone_number" value="<?= !$submission_success && isset($_POST['contact_phone_number']) ? esc_attr((string) $_POST['contact_phone_number']) : ''; ?>">
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="contact_may_contact_before_intake" value="1" <?= (!$submission_success && isset($_POST['contact_may_contact_before_intake'])) ? 'checked' : ''; ?>>
                        <?php _e('The organisation may contact this person before the first meeting', 'emmie-connector'); ?>
                    </label>
                </p>
            </div>
        </div>
        <br>

        <!-- Referral source dropdown -->
        <?php if (!empty($options['referral_sources'])) { ?>
        <p>
            <label for="client_referral_source"><?php _e('How did you find us?', 'emmie-connector'); ?></label><br>
            <select name="client_referral_source" id="wp2emmie-referral-source">
                <option value=""><?php _e('Select an option', 'emmie-connector'); ?></option>
                <?php foreach ($options['referral_sources'] as $source) { ?>
                    <option value="<?= esc_attr((string) $source['name']); ?>" <?= (!$submission_success && isset($_POST['client_referral_source']) && $_POST['client_referral_source'] === $source['name']) ? 'selected' : ''; ?>>
                        <?= esc_html((string) $source['name']); ?>
                    </option>
                <?php } ?>
            </select>
            <span class="wp2emmie-referral-other" id="wp2emmie-referral-other">
                <br>
                <input type="text" name="client_referral_source_other" placeholder="<?php _e('Please specify', 'emmie-connector'); ?>" value="<?= !$submission_success && isset($_POST['client_referral_source_other']) ? esc_attr((string) $_POST['client_referral_source_other']) : ''; ?>">
            </span>
        </p>
        <br>
        <?php } ?>

        <!-- File upload -->
        <p>
            <label for="documents"><?php _e('Documents', 'emmie-connector'); ?></label><br>
            <input type="file" name="documents[]" multiple accept=".doc,.docx,.rtf,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.pdf">
            <small><?php _e('Allowed formats: doc, docx, rtf, xls, xlsx, txt, jpg, jpeg, png, pdf. Max 40MB per file.', 'emmie-connector'); ?></small>
        </p>
        <br>

        <!-- Comment -->
        <p>
            <label for="client_comment"><?php _e('Comments', 'emmie-connector'); ?></label><br>
            <textarea name="client_comment" rows="4" cols="50" class="<?= isset($errors['client_comment']) ? 'wp2emmie-field-error' : ''; ?>"><?= !$submission_success && isset($_POST['client_comment']) ? esc_textarea((string) $_POST['client_comment']) : ''; ?></textarea>
            <?php if (isset($errors['client_comment'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['client_comment']); ?></span>
            <?php } ?>
        </p>
        <br>

        <p>
            <label for="privacy_checkbox" class="required"><?php _e('Personal Data', 'emmie-connector'); ?></label><br>
            <input type="checkbox" name="privacy_checkbox" id="privacy_checkbox" <?= !$submission_success && (isset($_POST['privacy_checkbox']) && $_POST['privacy_checkbox'] === 'on') ? 'checked' : ''; ?>> <br>
            <?php if (isset($errors['privacy_checkbox'])) { ?>
                <span class="wp2emmie-error-text"><?= esc_html((string) $errors['privacy_checkbox']); ?></span>
            <?php } ?>
            <?= \sprintf(
                // translators: %s: the address of the privacy statement
                __('By checking this box, you agree to the processing of your personal data according to the <a href="%s" target="_blank">privacy statement</a>', 'emmie-connector'),
                esc_url(wp2emmie_privacy_policy_url()),
            ); ?>
        </p>
        <br>
        <?php if ($site_key) { ?>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <script>
                function onSubmit(token) {
                    document.getElementById("emmie-form").submit();
                }
            </script>
            <p>
                <button type="submit"
                    class="g-recaptcha"
                    data-sitekey="<?= esc_attr($site_key); ?>"
                    data-callback="onSubmit"
                    data-action="submit">
                    <?php _e('Submit', 'emmie-connector'); ?>
                </button>
                <?php if (isset($errors['recaptcha'])) { ?>
                    <span class="wp2emmie-error-text"><?= esc_html((string) $errors['recaptcha']); ?></span>
                <?php } ?>
            </p>
        <?php } else { ?>
            <p>
                <button type="submit"><?php _e('Submit', 'emmie-connector'); ?></button>
            </p>
        <?php } ?>
    </form>

    <script>
        (function() {
            // Contact person toggle
            var checkbox = document.getElementById('wp2emmie-has-contact');
            var fields = document.getElementById('wp2emmie-contact-fields');
            if (checkbox && fields) {
                function toggleContact() {
                    fields.style.display = checkbox.checked ? 'block' : 'none';
                }
                checkbox.addEventListener('change', toggleContact);
                toggleContact();
            }

            // Referral source "Anders" toggle
            var referralSelect = document.getElementById('wp2emmie-referral-source');
            var referralOther = document.getElementById('wp2emmie-referral-other');
            if (referralSelect && referralOther) {
                function toggleReferralOther() {
                    referralOther.style.display = referralSelect.value === 'Anders' ? 'inline' : 'none';
                }
                referralSelect.addEventListener('change', toggleReferralOther);
                toggleReferralOther();
            }
        })();
    </script>

<?php
        if ($submission_result && isset($submission_result['message'])) {
            echo (string) $submission_result['message'];
        }

    if ($submission_success) {
        echo '<p><a class="wp2emmie-booking-button button" href="' . esc_url(wp2emmie_booking_page_url()) . '">' . esc_html__('Plan an introductory meeting', 'emmie-connector') . '</a></p>';
    }

    return ob_get_clean();
}

function wp2emmie_process_form(): array
{
    if (
        !isset($_POST['emmie_form_nonce'])
        || !wp_verify_nonce($_POST['emmie_form_nonce'], 'emmie_form_action')
    ) {
        return [
            'success' => false,
            'message' => '<div style="color: red; font-weight: bold;">' . __('Security check failed. Please reload the page and try again.', 'emmie-connector') . '</div>',
        ];
    }

    $errors = [];
    if (!isset($_POST['privacy_checkbox']) || $_POST['privacy_checkbox'] !== 'on') {
        $errors['privacy_checkbox'] = __('You must agree to share the submitted personal data', 'emmie-connector');
    }

    // Required fields
    $first_name = sanitize_text_field($_POST['client_first_name']);
    $last_name = sanitize_text_field($_POST['client_last_name']);
    $email = sanitize_email($_POST['client_email']);
    $phone_number = sanitize_text_field($_POST['client_phone_number']);
    $comment = sanitize_textarea_field($_POST['client_comment'] ?? '');

    if ($first_name === '') {
        $errors['client_first_name'] = __('First Name is required', 'emmie-connector');
    }
    if ($last_name === '') {
        $errors['client_last_name'] = __('Last Name is required', 'emmie-connector');
    }
    if ($email === '' || !is_email($email)) {
        $errors['client_email'] = __('Please enter a valid email address', 'emmie-connector');
    }
    if ($phone_number === '') {
        $errors['client_phone_number'] = __('Please enter a valid phone number', 'emmie-connector');
    }

    $location_id = $_POST['client_location_id'] ?? '';
    if ($location_id === '') {
        $errors['client_location_id'] = __('Location is required', 'emmie-connector');
    }

    // Optional fields
    $street = sanitize_text_field($_POST['client_street'] ?? '');
    $number = $_POST['client_number'] ?? '';
    $number_addition = sanitize_text_field($_POST['client_number_addition'] ?? '');
    $zipcode = sanitize_text_field($_POST['client_zipcode'] ?? '');
    $city = sanitize_text_field($_POST['client_city'] ?? '');
    $income_situation = $_POST['client_income_situation'] ?? '';
    $contact_preference_id = $_POST['client_contact_preference_id'] ?? '';

    // Contact person
    $has_contact = isset($_POST['has_contact_person']);
    $contact_first_name = $has_contact ? sanitize_text_field($_POST['contact_first_name'] ?? '') : '';
    $contact_last_name = $has_contact ? sanitize_text_field($_POST['contact_last_name'] ?? '') : '';
    $contact_organisation = $has_contact ? sanitize_text_field($_POST['contact_organisation'] ?? '') : '';
    $contact_email = $has_contact ? sanitize_email($_POST['contact_email'] ?? '') : '';
    $contact_phone_number = $has_contact ? sanitize_text_field($_POST['contact_phone_number'] ?? '') : '';
    $contact_may_contact = $has_contact && isset($_POST['contact_may_contact_before_intake']);

    // Referral source — append to comment
    $referral_source = sanitize_text_field($_POST['client_referral_source'] ?? '');
    if ($referral_source === 'Anders') {
        $referral_other = sanitize_text_field($_POST['client_referral_source_other'] ?? '');
        if ($referral_other !== '') {
            $referral_source = $referral_other;
        }
    }
    if ($referral_source !== '') {
        $comment = __('How did you find us:', 'emmie-connector') . ' ' . $referral_source . "\n\n" . $comment;
    }

    $recaptcha_secret = get_option('wp2emmie_recaptcha_secret_key');
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if ($recaptcha_secret) {
        if (!$recaptcha_response) {
            $errors['recaptcha'] = __('Please complete the reCAPTCHA verification.', 'emmie-connector');
        } else {
            $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_response,
                    'remoteip' => $_SERVER['REMOTE_ADDR'],
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
            'message' => "<div style='color:red;'>" . __('Please correct the errors above before submitting.', 'emmie-connector') . '</div>',
        ];
    }

    $emmie_origin = wp2emmie_emmie_origin();
    $api_token_encrypted = get_option('wp2emmie_api_token');

    if ($emmie_origin === '' || !$api_token_encrypted) {
        return [
            'success' => false,
            'message' => "<div style='color:red;'>" . __('API not configured. Please contact support.', 'emmie-connector') . '</div>',
        ];
    }

    $encryption = new WP2Emmie_Data_Encryption;
    $api_token = $encryption->decrypt($api_token_encrypted);

    $api_endpoint = $emmie_origin . '/api/intake';

    // Build multipart body for file upload support
    $boundary = wp_generate_password(24, false);

    $fields = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone_number' => $phone_number,
        'comment' => $comment,
    ];

    // Add optional fields only if they have values
    if ($street !== '') {
        $fields['street'] = $street;
    }
    if ($number !== '') {
        $fields['number'] = $number;
    }
    if ($number_addition !== '') {
        $fields['number_addition'] = $number_addition;
    }
    if ($zipcode !== '') {
        $fields['zipcode'] = $zipcode;
    }
    if ($city !== '') {
        $fields['city'] = $city;
    }
    if ($location_id !== '') {
        $fields['location_id'] = $location_id;
    }
    if ($income_situation !== '') {
        $fields['income_situation'] = $income_situation;
    }
    if ($contact_preference_id !== '') {
        $fields['contact_preference_id'] = $contact_preference_id;
    }

    // Contact person fields
    if ($has_contact && $contact_first_name !== '' && $contact_last_name !== '') {
        $fields['contact_first_name'] = $contact_first_name;
        $fields['contact_last_name'] = $contact_last_name;
        if ($contact_organisation !== '') {
            $fields['contact_organisation'] = $contact_organisation;
        }
        if ($contact_email !== '') {
            $fields['contact_email'] = $contact_email;
        }
        if ($contact_phone_number !== '') {
            $fields['contact_phone_number'] = $contact_phone_number;
        }
        if ($contact_may_contact) {
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
    if (!empty($_FILES['documents']['name'][0])) {
        foreach ($_FILES['documents']['tmp_name'] as $i => $tmp_name) {
            if ($_FILES['documents']['error'][$i] !== \UPLOAD_ERR_OK) {
                continue;
            }
            $filename = sanitize_file_name($_FILES['documents']['name'][$i]);
            $mime = $_FILES['documents']['type'][$i];
            $content = file_get_contents($tmp_name);

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"documents[]\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: {$mime}\r\n\r\n";
            $body .= $content . "\r\n";
        }
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

    $code = wp_remote_retrieve_response_code($response);

    if ($code >= 400) {
        $decoded = json_decode((string) $response['body'], true);

        $response_errors = \is_array($decoded) ? ($decoded['errors'] ?? []) : [];
        $message_text = '';
        if (!empty($response_errors)) {
            foreach ($response_errors as $property_error) {
                $message_text .= '<p>' . esc_html((string) $property_error[0]) . '</p>';
            }
        }

        error_log('Error: HTTP ' . $code . ' (' . wp_remote_retrieve_response_message($response) . ')');

        return [
            'success' => false,
            'message' => "<div style='color:red;'>" . __('Submission failed:', 'emmie-connector') . ' ' . $message_text . '</div>',
        ];
    }

    return [
        'success' => true,
        'message' => "<div style='color:green;'>" . __('Thank you! Your information has been submitted.', 'emmie-connector') . '</div>',
    ];
}
