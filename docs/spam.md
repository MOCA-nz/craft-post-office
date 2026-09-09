# Spam protection

Three independent checks. Which of them run is set **per form**, on the form's Settings tab.
API keys are set once, in the plugin's settings screen.

## Honeypot

Adds two things to a rendered form: a hidden text input that people never see, and a signed
timestamp.

A submission is rejected if:

- the hidden input has anything in it, which a person cannot do but a bot routinely does; or
- the signed timestamp has been tampered with; or
- less than 3 seconds passed between the page rendering and the form being submitted.

If the timestamp is **absent entirely**, the submission is allowed. That matters for
hand-written markup that does not include the input, and for pages served from a static
cache. The check fails open here on purpose: silently rejecting every real visitor is worse
than letting a bot through.

Costs nothing, needs no configuration, and adds no friction for visitors.

## reCAPTCHA and Turnstile

Both need two keys, set on **Capture > Settings**:

- Site key, rendered into your form
- Secret key, used server-side to verify

Store them as environment variable references, not as literal keys:

```
Site key:   $RECAPTCHA_SITE_KEY
Secret key: $RECAPTCHA_SECRET_KEY
```

Plugin settings are written to project config, which is committed to your repository. A
literal secret there is a secret in your git history.

Unlike the honeypot, these **fail closed**: if the check is switched on but cannot be
completed, because a key is missing or the verification endpoint is unreachable, the
submission is rejected and the reason is logged. A captcha that silently accepts everything
when misconfigured is worse than one that visibly rejects.

You are responsible for rendering the widget itself in your markup, and for posting the
`g-recaptcha-response` or `cf-turnstile-response` parameter. Capture verifies whichever one
the form has enabled.

## What a rejection looks like

A rejected submission gets exactly the same response as a successful one: the same message,
the same status code, the same redirect. Nothing is stored and no email is sent, and a line
is written to the log.

This is deliberate. Telling a bot which check caught it is free information for tuning
against you.
