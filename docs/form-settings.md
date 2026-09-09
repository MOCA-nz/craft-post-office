# Form settings

A form's **Settings** tab.

## Name and handle

The handle is what you pass to `craft.capture.form()` and what identifies the form in posted
markup. Changing it will break any template referencing the old one.

## Email addresses

| Setting | Default when blank |
|---|---|
| From name | Craft's system mail settings |
| From email | Craft's system mail settings |
| Reply-To | Whatever the submitter typed in the form's first `email` field |

All three accept an environment variable reference, for example `$SUPPORT_EMAIL`, which is
resolved at send time.

The Reply-To default is usually what you want: replying to a notification then replies to the
person who filled the form in, not to the site.

## On success

Two options.

**Redirect** takes a URL. On a successful submission the visitor is sent there, with the
success message set as a Craft flash notice.

```
Redirect URL: /thanks
```

**No reload** leaves the page in place. Capture's rendered form ships a small script that
posts with `fetch()` and updates the page from the JSON response. If you write your own
markup, you handle the response yourself, see [templating](templating.md).

The **Success message** is shown as the flash notice in redirect mode, and returned as
`message` in the JSON response in no-reload mode.

## Notifications

Covered in [notifications](notifications.md).

## Spam protection

Three switches, covered in [spam protection](spam.md). Which run is set here, per form; the
API keys are set once in the plugin's settings screen.
