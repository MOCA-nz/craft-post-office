<?php

namespace moca\capture\models;

use craft\base\Model;

/**
 * Plugin-wide settings.
 *
 * Only the captcha credentials live here. Which of the three spam protections actually run
 * is decided per form, on the form's Settings tab.
 *
 * Plugin settings are tracked in project config, so these must never hold a literal secret.
 * Each is stored as an environment variable reference (`$RECAPTCHA_SECRET_KEY`) and resolved
 * through App::parseEnv() at the point of use.
 */
class Settings extends Model
{
    /**
     * @var string reCAPTCHA site key, or an env variable reference.
     */
    public string $recaptchaSiteKey = '';

    /**
     * @var string reCAPTCHA secret key, or an env variable reference.
     */
    public string $recaptchaSecretKey = '';

    /**
     * @var string Turnstile site key, or an env variable reference.
     */
    public string $turnstileSiteKey = '';

    /**
     * @var string Turnstile secret key, or an env variable reference.
     */
    public string $turnstileSecretKey = '';

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [
                [
                    'recaptchaSiteKey',
                    'recaptchaSecretKey',
                    'turnstileSiteKey',
                    'turnstileSecretKey',
                ],
                'string',
            ],
        ]);
    }
}
