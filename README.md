[![Stable Version](https://img.shields.io/packagist/v/moca-nz/craft-capture?label=stable)](https://packagist.org/packages/moca-nz/craft-capture)
[![Total Downloads](https://img.shields.io/packagist/dt/moca-nz/craft-capture)](https://packagist.org/packages/moca-nz/craft-capture)

<p align="center"><img width="150" src="https://raw.githubusercontent.com/MOCA-nz/craft-capture/main/src/icon.svg"></p>

# Capture, a Plugin for Craft CMS

### Contact forms that store submissions as elements and email from your own templates.

Capture is a free plugin for [Craft CMS](https://craftcms.com/) for building contact forms in
the control panel. Submissions are stored as Craft elements, so they get the element index,
search, filtering and CSV export for free. Notification emails render from your own Twig
templates, and form definitions live in project config, so a form you build locally deploys
to production.

```twig
{{ craft.capture.form('contact') }}
```

That renders the whole form. If you would rather write the markup yourself, post to the
plugin's endpoint and it returns JSON with errors keyed by field handle.

## Requirements

This plugin requires [Craft CMS](https://craftcms.com/) 5.5.0 or later, and PHP 8.2 or later.

## Installation

To install the plugin, search for "Capture" in the Craft Plugin Store, or install manually
using composer.

```shell
composer require moca-nz/craft-capture
php craft plugin/install capture
```

## What you get

- **A form builder** with 11 field types. Each field has a label, handle, placeholder, a
  required toggle, a custom validation message, and a switch for whether it appears in email.
- **Submissions as elements**, with a source per form, search, sorting, filtering, bulk
  actions, CSV export and the trash.
- **Notifications** as a repeatable list of recipients, each with its own email template,
  plus a built-in autoresponder that replies to whatever the submitter typed in the form's
  email field.
- **A record of every email**, sent or failed, with the reason it failed.
- **Spam protection**: a honeypot with a minimum time-to-submit, reCAPTCHA and Turnstile,
  each switched on or off per form.
- **Project config**, so forms deploy between environments instead of being rebuilt by hand.

## Documentation

| Guide | Covers |
|---|---|
| [Field types](docs/field-types.md) | Every field type, its settings and its validation |
| [Form settings](docs/form-settings.md) | From and reply-to, success behaviour, redirect vs no-reload |
| [Notifications](docs/notifications.md) | Recipients, the autoresponder, email templates and their variables |
| [Spam protection](docs/spam.md) | Honeypot, timing, reCAPTCHA and Turnstile |
| [Templating](docs/templating.md) | Rendering, hand-written markup, the JSON response, template overrides |

## License

This plugin is licensed for free under the MIT License.

---

Created by [MOCA](https://www.moca.co.nz/).
