<?php

declare(strict_types = 1);

/**
 * Class responsible for encrypting and decrypting data.
 */
final class WP2Emmie_Data_Encryption
{
    /**
     * Key to use for encryption.
     *
     * @since 1.0.0
     */
    private string $key;

    /**
     * Salt to use for encryption.
     *
     * @since 1.0.0
     */
    private string $salt;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->key = $this->get_default_key();
        $this->salt = $this->get_default_salt();
    }

    /**
     * Encrypts a value.
     *
     * If a user-based key is set, that key is used. Otherwise the default key is used.
     *
     * @since 1.0.0
     *
     * @param string $value value to encrypt
     *
     * @return string|bool encrypted value, or false on failure
     */
    public function encrypt(string $value): bool|string
    {
        if (!\extension_loaded('openssl')) {
            return $value;
        }

        $method = 'aes-256-ctr';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $raw_value = openssl_encrypt($value . $this->salt, $method, $this->key, 0, $iv);
        if (!$raw_value) {
            return false;
        }

        return base64_encode($iv . $raw_value); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Decrypts a value.
     *
     * If a user-based key is set, that key is used. Otherwise the default key is used.
     *
     * @since 1.0.0
     *
     * @param string $raw_value value to decrypt
     *
     * @return string|bool decrypted value, or false on failure
     */
    public function decrypt(string $raw_value): bool|string
    {
        if (!\extension_loaded('openssl')) {
            return $raw_value;
        }

        $raw_value = base64_decode($raw_value, true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        $method = 'aes-256-ctr';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = mb_substr($raw_value, 0, $ivlen, '8bit');

        $raw_value = mb_substr($raw_value, $ivlen, null, '8bit');

        $value = openssl_decrypt($raw_value, $method, $this->key, 0, $iv);
        if (!$value || mb_substr($value, -mb_strlen($this->salt), null, '8bit') !== $this->salt) {
            return false;
        }

        return mb_substr($value, 0, -mb_strlen($this->salt), '8bit');
    }

    /**
     * Gets the default encryption key to use.
     *
     * @since 1.0.0
     *
     * @return string default (not user-based) encryption key
     */
    private function get_default_key(): string
    {
        if (\defined('LOGGED_IN_KEY') && LOGGED_IN_KEY !== '') {
            return LOGGED_IN_KEY;
        }

        // If this is reached, you're either not on a live site or have a serious security issue.
        return 'this-is-a-fallback-key-but-not-secure';
    }

    /**
     * Gets the default encryption salt to use.
     *
     * @since 1.0.0
     *
     * @return string encryption salt
     */
    private function get_default_salt(): string
    {
        if (\defined('LOGGED_IN_SALT') && LOGGED_IN_SALT !== '') {
            return LOGGED_IN_SALT;
        }

        // If this is reached, you're either not on a live site or have a serious security issue.
        return 'this-is-a-fallback-salt-but-not-secure';
    }
}
