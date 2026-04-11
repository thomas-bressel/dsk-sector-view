<?php

/**
 * CsrfService
 *
 * Manages a CSRF (Cross-Site Request Forgery) synchroniser token stored in the PHP session.
 * The token is a 64-character hexadecimal string generated from 32 cryptographically
 * secure random bytes.
 *
 * Typical usage:
 * <code>
 * $csrf = new CsrfService();
 * $csrf->init();                          // generate token if not present
 * $token = $csrf->getToken();            // embed in the HTML form as a hidden field
 *
 * // On POST:
 * if (!$csrf->verify($_POST['csrf_token'])) { ... }
 * $csrf->renew();                         // rotate token after successful processing
 * </code>
 *
 * The token name used as the session key is defined by the CSRF_TOKEN_NAME constant
 * in config/app.php.
 *
 * @package DSKscan\Service
 */
class CsrfService
{
    /**
     * Initialises the CSRF token for the current session.
     * A new token is generated only if none is already stored.
     *
     * @return void
     */
    public function init(): void
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $this->renew();
        }
    }

    /**
     * Returns the current CSRF token from the session.
     *
     * @return string 64-character hexadecimal token, or empty string if not initialised
     */
    public function getToken(): string
    {
        return $_SESSION[CSRF_TOKEN_NAME] ?? '';
    }

    /**
     * Verifies that a submitted token matches the session token.
     * Uses hash_equals() to prevent timing attacks.
     *
     * @param  string $submitted The token value submitted via the form
     * @return bool              True if the token is valid
     */
    public function verify(string $submitted): bool
    {
        return hash_equals($this->getToken(), $submitted);
    }

    /**
     * Generates a new cryptographically secure CSRF token and stores it in the session.
     * Should be called after a form is successfully processed to prevent token reuse.
     *
     * @return void
     */
    public function renew(): void
    {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}
