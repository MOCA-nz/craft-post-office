# Templating

Two ways to put a form on a page.

## Let Capture render it

```twig
{{ craft.capture.form('contact') }}
```

That outputs the whole form: the CSRF token, the action, every field with its label,
placeholder, required state and error slot, the honeypot if enabled, and the submit button.
In no-reload mode it also includes the script that posts with `fetch()`.

If the handle does not exist it returns nothing rather than throwing, so a typo does not take
the page down.

### Overriding the markup

The rendered markup comes from templates in the plugin, and any of them can be replaced by
putting a file at the same path in your own `templates/` directory:

| Plugin template | Your override | Renders |
|---|---|---|
| `capture/_form` | `templates/capture/_form.twig` | The whole form |
| `capture/_field` | `templates/capture/_field.twig` | One field |
| `capture/_email` | `templates/capture/_email.twig` | The default notification email |

An override receives everything the original does, so you can restructure freely. Copy the
plugin's version out of `vendor/moca-nz/craft-capture/src/templates/site/` as a starting
point.

## Write the markup yourself

Post to the plugin's action with the form's handle. Values go under `fields`, keyed by field
handle.

```twig
{% set form = craft.capture.getForm('contact') %}

<form method="post" accept-charset="UTF-8">
  {{ csrfInput() }}
  {{ actionInput('capture/submit') }}
  {{ hiddenInput('formHandle', 'contact') }}

  <label for="name">Your name</label>
  <input type="text" id="name" name="fields[fullName]">

  <label for="email">Email</label>
  <input type="email" id="email" name="fields[email]">

  <textarea name="fields[message]"></textarea>

  <button type="submit">Send</button>
</form>
```

Only handles the form actually declares are read. An extra input cannot smuggle a value into
storage.

### Showing errors after a failed post

In redirect mode a failed submission re-renders the page with the submission available:

```twig
{% set submission = captureSubmission ?? null %}
{% set errors = submission ? submission.fieldErrors : {} %}

<input type="email" name="fields[email]" value="{{ submission ? submission.values.email }}">

{% if errors.email is defined %}
  <p class="error">{{ errors.email|first }}</p>
{% endif %}
```

## The JSON response

Post with `Accept: application/json` and you get JSON back instead of a redirect. This is
what no-reload mode uses, and what you should use for your own `fetch()`.

Success:

```json
{
  "success": true,
  "message": "Thanks, we'll be in touch.",
  "submissionId": 17635
}
```

Failure:

```json
{
  "success": false,
  "message": "Please check the form for errors.",
  "errors": {
    "fullName": ["Full name is required."],
    "email": ["Email must be a valid email address."]
  }
}
```

`errors` is keyed by field handle, so you can put each message next to its own input without
matching on text.

Note that a submission rejected as spam returns `success: true` with a null `submissionId`.
See [spam protection](spam.md) for why.

## Exporting

The submissions index has an Export button. Capture adds its own **Submissions** exporter,
which is the default and writes one column per form field, plus ID, form, date and IP. Craft's
built-in "Raw data" exporter is still available but writes the values as a single cell of
JSON, which is rarely what you want.

Exporting across several forms at once produces the union of their fields, with blanks where
a form has no such field.

## Other template variables

```twig
{{ craft.capture.getForm('contact') }}      {# one Form, or null #}
{{ craft.capture.getForms() }}              {# every Form #}
{{ craft.capture.submissionCount(formId) }} {# across all sites #}
{{ craft.capture.submissionCount(formId, siteId) }} {# one site #}
{{ craft.capture.submissions() }}           {# a submission query #}
```

`craft.capture.submissions()` returns an element query, so it takes the usual parameters and
chains like any other:

```twig
{% set form = craft.capture.getForm('contact') %}

{% for submission in craft.capture.submissions({ formId: form.id, limit: 5 }).all() %}
  {{ submission.values.fullName }} - {{ submission.dateCreated|datetime('short') }}
{% endfor %}
```

Note that Craft's own `craft.query()` will not work here: it is a generic database query
builder that takes no element type.

Submissions are elements, so everything else on them behaves normally: `dateCreated`,
`id`, `getForm()`, and `values` keyed by field handle. See [submissions](submissions.md).
