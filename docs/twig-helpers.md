# Twig Helpers

The bundle provides two main Twig functions through the `AxFormTwigExtension`.

## `ax_form()`

Renders an `<a>` tag that acts as a trigger for an AJAX modal.

**Arguments:**

1. `path` (string): The URL to load the form from.
2. `label` (string): The link text or HTML.
3. `class` (string): CSS classes for the link.
4. `description` (string, optional): Tooltip title.
5. `attr` (array, optional): Extra HTML attributes or plugin config.

**Examples:**

```twig
{{ ax_form(path('item_edit', {id: 1}), 'Edit', 'btn btn-primary') }}

{# With plugins #}
{{ ax_form(url, 'Save', 'btn', 'Click to save', {plugin: 'validation'}) }}
```

## `ax_form_modal()`

Renders the required HTML structure for the Bootstrap 5 modal container. This must be placed once in your base layout.

```twig
{{ ax_form_modal() }}
```

**Output:**

```html
<div id="ax-form-modal" class="modal animate__animated animate__fadeInUp" aria-hidden="true" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div id="ax-form-content" class="modal-content"></div>
    </div>
</div>
```
