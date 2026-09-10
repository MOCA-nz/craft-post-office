# Notifications

Each form has its own list of outgoing emails, on the form's **Settings** tab.

## Recipients

**Send to** is a repeatable table. Each row is one email:

| Column | Meaning |
|---|---|
| Recipient | Where it goes. Accepts an environment variable reference |
| Subject | The subject line. Leave blank for "New {form name} submission" |
| Email template | The Twig template that renders the body. Leave blank for Post Office's default |
| Enabled | Whether this row sends |

Add as many as you need. Three rows means three separate emails, each able to use a different
template, which is how you send a different message to different people from one form.

## Letting a form field decide the recipient

Both the Recipient and the Subject accept `{{ fieldHandle }}`, resolved against each
submission. The usual case is a "who do you want to talk to" dropdown that routes the enquiry
to that person:

Build a `select` field whose option **values are the addresses**:

```
Jane Smith:jane@moca.co.nz
Bob Tane:bob@moca.co.nz
```

Then set the notification's Recipient to `{{ agent }}` and, if you like, its Subject to
`Enquiry for you from {{ fullName }}`.

`{{ values.agent }}` works too, since that is what people reach for.

### Which fields may decide a recipient

Only `select`, `radio`, `checkboxes` and `email`.

The first three can only ever yield an address you put in the options yourself. Email fields
are free text, but sending to an address the visitor supplied is already what the
autoresponder does, so it is allowed for consistency.

A free-text field is **not** allowed. If it were, a visitor could type any address into it
and have your site send mail there, which is a spam relay wearing your domain. Referencing
one is recorded as a failed send explaining why.

A Subject may reference any field: a subject cannot send mail anywhere unintended.

### It is not Twig

Only `{{ fieldHandle }}` is understood. `{{ 7 * 7 }}` is left alone, not evaluated. The
strings come from your settings but the values substituted into them come from whoever filled
the form in, and handing that to a template engine is how server-side template injection
happens.

### When it cannot be resolved

Nothing is sent, and a row is written to Sent Notifications with the reason: the field is
missing, it was left empty, its type is not allowed, or the result is not a valid address.
The submission itself is never affected.

A Subject that resolves to nothing falls back to the default rather than sending a blank one.

### Multiple recipients

A `checkboxes` field resolves to every ticked value, so one notification can go to several
people at once.

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

Leave the template blank and Post Office renders its own, which is a table of `rows` plus the
submission date. To restyle the default for every form at once, copy the plugin's
`src/templates/site/_email.twig` to `templates/post-office/_email.twig` in your project.

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
