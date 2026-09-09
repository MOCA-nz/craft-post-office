# Release Notes for Capture

## 1.0.0 - 2026-09-09

### Added

- Form builder in the control panel, with 11 field types: text, email, number, phone, URL,
  textarea, dropdown, radio buttons, checkboxes, consent and hidden.
- Per-field label, handle, placeholder, required toggle, custom validation message, and an
  include-in-email switch.
- Form definitions are stored in project config, so a form built locally deploys to
  production with `project-config/apply`.
- Submissions are Craft elements, with a control-panel index per form, search, sorting,
  filtering, CSV export and the trash.
- Notifications: a repeatable list of recipients, each with its own email template, plus a
  built-in autoresponder that replies to the submitter's own email field.
- Sent Notifications screen recording every attempted send, successful or not.
- Logs screen for the plugin's own events.
- Spam protection: honeypot with a minimum time-to-submit, reCAPTCHA and Turnstile, each
  switched on or off per form.
- Front-end rendering through `craft.capture.form('handle')`, with fully overridable
  templates, plus a JSON endpoint for hand-written markup.
