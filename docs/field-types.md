# Field types

Fields are added on a form's **Builder** tab. Click a field to open its settings, drag the
handle to reorder, and use Delete inside the editor to remove it.

## Settings every field has

| Setting | What it does |
|---|---|
| Label | What the visitor sees above the input |
| Handle | How the field is referenced in templates, email and the stored value |
| Type | One of the types below |
| Placeholder | Placeholder text, where the type supports it |
| Options | One per line, for dropdown, radio and checkboxes. Use `Label:value` to store something different from what is shown |
| Required | Whether the field must be filled in |
| Validation message | Shown when the field fails. Leave blank for the default |
| Include in email | Whether the field appears in notification emails |

The handle is generated from the label but stays editable. It must be unique within the form.

## The types

| Type | Renders | Also validates |
|---|---|---|
| `text` | `<input type="text">` | |
| `email` | `<input type="email">` | Must be a valid email address |
| `number` | `<input type="number">` | Must be numeric |
| `tel` | `<input type="tel">` | |
| `url` | `<input type="url">` | Must be a valid URL |
| `textarea` | `<textarea rows="5">` | |
| `select` | `<select>` with a blank first option | |
| `radio` | A radio group | |
| `checkboxes` | A checkbox group, stored as a list | |
| `consent` | A single checkbox with the label beside it | |
| `hidden` | `<input type="hidden">` | |

## Validation

The builder deliberately exposes only a required toggle. Everything else follows from the
type, so there is nothing else to configure and nothing to keep in sync.

An empty optional field is never type-checked: only a field that has a value is checked
against its type. That means a blank optional email field passes, and `not-an-email` does not.

Your custom validation message covers both the required check and the type check, because
the builder only gives you one field for it.

Leave it blank and Capture picks a default that suits whichever check failed. An email field
left empty reads "Email is required."; one containing `not-an-email` reads "Email must be a
valid email address." Only the defaults differ; a custom message replaces both.

## The email field

The first `email` field on a form is special: it is what the autoresponder replies to, and
what Reply-To falls back to. If a form has no email field, the autoresponder has nowhere to
send and is skipped, which is recorded in the log.
