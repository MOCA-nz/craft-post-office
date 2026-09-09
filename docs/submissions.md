# Submissions

Submissions are Craft elements, so the Submissions screen is a normal element index: search,
sorting, filtering, view settings, bulk actions, export and the trash all behave the way they
do everywhere else in Craft.

## The index

The sidebar has one source per form, plus **All submissions**.

Columns are Submission, Form and Date by default. **IP address** is available under View
settings. The Submission column shows the first non-empty value on the submission, which for
most forms is the person's name.

Clicking a row opens the submission.

## A single submission

Read-only. There is no edit screen: a submission is a record of what someone sent, and the
plugin never offers to type over it.

The body lists every field as `Label: value`, in the form's field order. Values are shown the
way a person reads them, so a dropdown shows its option label rather than the stored value,
and a consent field shows Yes or No. What is stored stays raw.

The sidebar shows the form, the date submitted, and the IP address.

Below the values is every notification sent for that submission, with its status. A failed
send shows why it failed.

### Fields deleted since the submission arrived

Their values are not thrown away. They appear at the bottom of the list under their raw
handle rather than a label, so old submissions keep everything that was captured even after
the form changes.

## Searching

Submissions are indexed on their values, so searching for an email address or a name in the
index search box finds them.

## Exporting

The Export button offers Capture's own **Submissions** exporter by default, which writes one
column per form field plus ID, Form, Date Submitted and IP address. Craft's built-in
"Raw data" exporter is still offered but writes the values as a single cell of JSON.

Exporting from **All submissions** across several forms produces the union of their fields,
with blanks where a form has no such field.

## Deleting and restoring

Deleting moves a submission to Craft's trash rather than removing it, so it can be restored
from the Trashed status filter. Craft removes trashed elements permanently after its own
retention period (`softDeleteDuration`, 30 days by default).

Deleting a **form** deletes its submissions outright. That is not reversible, and it applies
on every environment the project config change reaches.

## Multi-site

A submission belongs to the site it was submitted from, and only that site. It is not
propagated or translated: it records one event that happened on one site, so copying it
elsewhere would invent submissions nobody made.

The **Site** column is available under View settings, and the index's site menu filters by it
the way it does for entries.

Counts through `craft.capture.submissionCount()` span every site by default, because "how
many enquiries has this form had" is rarely a per-site question. Pass a site ID as the second
argument to scope it.

## Permissions

One permission, **View submissions**, under Capture in the user group settings. It gates the
Submissions, Sent Notifications and Logs screens.

The Forms and Settings screens are gated on admin changes instead, because they write to
project config.

## Querying from Twig

```twig
{% set form = craft.capture.getForm('contact') %}

{% for submission in craft.capture.submissions({ formId: form.id, limit: 5 }).all() %}
  {{ submission.values.fullName }}
{% endfor %}
```

See [templating](templating.md) for the full list.

## Querying from PHP

```php
use moca\capture\elements\Submission;

$submissions = Submission::find()
    ->formId($form->id)
    ->limit(5)
    ->all();

foreach ($submissions as $submission) {
    $values = $submission->getValues();      // keyed by field handle, raw
    $rows = $submission->getDisplayValues(); // label/value pairs, formatted for reading
}
```

## History

Sent Notifications and Logs both paginate at 100 rows a page and report a total.

Both are pruned by Craft's garbage collection according to **Keep history for** on the
plugin's settings screen, which defaults to 90 days. Set it to 0 to keep everything forever.
Submissions themselves are never pruned.
