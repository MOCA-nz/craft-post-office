<?php

namespace moca\postoffice\services;

use Craft;
use craft\helpers\App;
use moca\postoffice\models\Form;
use moca\postoffice\Plugin;
use Throwable;
use yii\base\Component;

/**
 * Spam protection.
 *
 * Three independent checks, each switched on or off per form: a honeypot field paired with a
 * minimum time-to-submit, and the two captcha services. Credentials are plugin-wide; whether
 * a check runs is per form.
 *
 * Every check fails closed only for the captchas. The honeypot deliberately fails open on a
 * missing timestamp, because a cached page or a hand-written form that omits the input
 * should not silently reject every real visitor.
 */
class Spam extends Component
{
    /**
     * The name of the honeypot input rendered into forms.
     */
    public const HONEYPOT_FIELD = 'postoffice_hp';

    /**
     * The name of the signed-timestamp input used for the timing check.
     */
    public const TIMESTAMP_FIELD = 'postoffice_ts';

    /**
     * How quickly a form may be submitted, in seconds, before it is treated as automated.
     */
    public const MIN_SECONDS = 3;

    private const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Returns the signed timestamp a form renders for the timing check.
     *
     * Signed rather than plain so it cannot be back-dated to defeat the minimum
     * time-to-submit.
     */
    public function timestampValue(): string
    {
        return Craft::$app->getSecurity()->hashData((string)time());
    }

    /**
     * Runs whichever checks the form has enabled.
     *
     * @return bool Whether the submission looks legitimate.
     */
    public function check(Form $form): bool
    {
        if ($form->honeypotEnabled && !$this->_checkHoneypot()) {
            return false;
        }

        if ($form->recaptchaEnabled && !$this->_checkRecaptcha()) {
            return false;
        }

        if ($form->turnstileEnabled && !$this->_checkTurnstile()) {
            return false;
        }

        return true;
    }

    /**
     * Whether reCAPTCHA is usable: both keys are present.
     */
    public function hasRecaptchaKeys(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        return App::parseEnv($settings->recaptchaSiteKey) !== ''
            && App::parseEnv($settings->recaptchaSecretKey) !== '';
    }

    /**
     * Whether Turnstile is usable: both keys are present.
     */
    public function hasTurnstileKeys(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        return App::parseEnv($settings->turnstileSiteKey) !== ''
            && App::parseEnv($settings->turnstileSecretKey) !== '';
    }

    /**
     * The hidden field must be empty, and the form must not have been submitted instantly.
     */
    private function _checkHoneypot(): bool
    {
        $request = Craft::$app->getRequest();

        if ($request->getBodyParam(self::HONEYPOT_FIELD)) {
            return false;
        }

        $hashed = $request->getBodyParam(self::TIMESTAMP_FIELD);

        if (!is_string($hashed) || $hashed === '') {
            // No timestamp to check. Fail open: a hand-written form or a cached page that
            // omits the input must not reject every visitor.
            return true;
        }

        $timestamp = Craft::$app->getSecurity()->validateData($hashed);

        if ($timestamp === false) {
            // Present but tampered with, which a real visitor's browser never does.
            return false;
        }

        return (time() - (int)$timestamp) >= self::MIN_SECONDS;
    }

    private function _checkRecaptcha(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        return $this->_verify(
            self::RECAPTCHA_VERIFY_URL,
            App::parseEnv($settings->recaptchaSecretKey),
            (string)Craft::$app->getRequest()->getBodyParam('g-recaptcha-response'),
        );
    }

    private function _checkTurnstile(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        return $this->_verify(
            self::TURNSTILE_VERIFY_URL,
            App::parseEnv($settings->turnstileSecretKey),
            (string)Craft::$app->getRequest()->getBodyParam('cf-turnstile-response'),
        );
    }

    /**
     * Posts a captcha response to its verification endpoint.
     *
     * Fails closed: if the check is switched on but cannot be completed, the submission is
     * rejected rather than waved through. A misconfigured captcha that silently accepts
     * everything is worse than one that visibly rejects.
     */
    private function _verify(string $url, string $secret, string $response): bool
    {
        if ($secret === '' || $response === '') {
            return false;
        }

        try {
            $result = Craft::createGuzzleClient(['timeout' => 5])
                ->post($url, [
                    'form_params' => [
                        'secret' => $secret,
                        'response' => $response,
                        'remoteip' => Craft::$app->getRequest()->getUserIP(),
                    ],
                ]);

            $body = json_decode((string)$result->getBody(), true);

            return ($body['success'] ?? false) === true;
        } catch (Throwable $e) {
            Plugin::getInstance()->log->write(
                Log::LEVEL_ERROR,
                'captcha.unreachable',
                "Captcha verification failed: {$e->getMessage()}",
            );

            return false;
        }
    }
}
