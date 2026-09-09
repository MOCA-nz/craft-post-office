# Notifications

Each form has its own list of outgoing emails, on the form's **Settings** tab.

## Recipients

**Send to** is a repeatable table. Each row is one email:

| Column | Meaning |
|---|---|
| Recipient | Where it goes. Accepts an environment variable reference |
| Subject | The subject line. Leave blank for "New {form name} submission" |
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

Set per notification, in the Subject column. Leave it blank and recipients get
"New {form name} submission", and the autoresponder gets "Thanks for getting in touch".

## When they send

Notifications are queued, not sent during the visitor's request, so a slow or unreachable
mail host never delays a submission. They go out on the next queue run.

The submission is saved before any email is attempted, so a mail failure costs an email and
never the enquiry. If your queue is not running, submissions still arrive; the emails simply
wait.

## What gets recorded

Every attempt writes a row to **Sent Notifications**, whether it succeeded or not, with the
date, time, recipient, status, and a link to the submission. A failure also records the
reason, and writes an entry to the log.

Both that screen and the log are paginated, and both are pruned by Craft's garbage
collection according to **Keep history for** on the plugin's settings screen (90 days by
default, 0 to keep everything).

A missing template is the most common failure, and it shows up as
`Unable to find the template "…"`. The submission itself is never lost: it is saved before
any email is attempted.
