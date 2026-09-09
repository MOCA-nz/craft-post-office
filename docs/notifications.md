# Notifications

Each form has its own list of outgoing emails, on the form's **Settings** tab.

## Recipients

**Send to** is a repeatable table. Each row is one email:

| Column | Meaning |
|---|---|
| Recipient | Where it goes. Accepts an environment variable reference |
| Email template | The Twig template that renders the body. Leave blank for Capture's default |
| Enabled | Whether this row sends |

Add as many as you need. Three rows means three separate emails, each able to use a different
template, which is how you send a different message to different people from one form.

## The autoresponder

Below the recipients is a single fixed row, **Send a copy to the submitter**. It has no
recipient of its own: it sends to whatever the visitor typed in the form's first `email`
field, resolved at send time.

It is created automatically, cannot be deleted, and is off by default. It has its own
template so the reply to the submitter can read differently from the internal notification.

If the form has no `email` field, or the visitor left it blank, the autoresponder is skipped
and a line is written to the log.

## Email templates

A template path is relative to your site templates directory, so `_emails/enquiry` means
`templates/_emails/enquiry.twig`. These are ordinary site templates.

Available variables:

| Variable | What it is |
|---|---|
| `submission` | The Submission element |
| `form` | The Form the submission came through |
| `values` | The submitted values, keyed by field handle |
| `rows` | Label/value pairs for every field marked **include in email** |

`rows` is the easy one, and honours the include-in-email switch. `values` gives you direct
access by handle when you want to build something specific.

```twig
<h1>New enquiry from {{ values.fullName }}</h1>

<table>
  {% for row in rows %}
    <tr>
      <th align="left">{{ row.label }}</th>
      <td>{{ row.value|nl2br }}</td>
    </tr>
  {% endfor %}
</table>

<p>Received {{ submission.dateCreated|datetime('short') }}</p>
```

Leave the template blank and Capture renders its own, which is a table of `rows` plus the
submission date. To restyle the default for every form at once, copy the plugin's
`src/templates/site/_email.twig` to `templates/capture/_email.twig` in your project.

## Subjects

Recipient emails are subjected "New {form name} submission". The autoresponder uses "Thanks
for getting in touch". To control the subject yourself, set it in your own template's
context, or override the default template.

## What gets recorded

Every attempt writes a row to **Sent Notifications**, whether it succeeded or not, with the
date, time, recipient, status, and a link to the submission. A failure also records the
reason, and writes an entry to the [log](../README.md).

A missing template is the most common failure, and it shows up as
`Unable to find the template "…"`. The submission itself is never lost: it is saved before
any email is attempted.
