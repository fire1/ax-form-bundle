# Frontend Assets

`AxFormBundle` includes a standalone npm package located in `src/Resources/assets`.

## Package Information

- **Name**: `@fire1/ax-form`
- **Logic**: Vanilla JavaScript (jQuery-dependent)
- **UI**: Vue 3 Components & SCSS

## Components

### `AxForm` (JS Class)

The main engine that handles modal opening, AJAX loading, and plugin management.

```javascript
import { AxForm } from '@fire1/ax-form';

// Manual trigger
const ax = new AxForm('#my-button');
```

### Vue 3 Components

The bundle provides the following Vue components for easy integration:

- `AxForm.vue`: Trigger link for standard forms.
- `AxEdit.vue`: Trigger link for edit forms (auto-compiles JSON data).
- `AxPage.vue`: Trigger link for full-page forms.
- `AxLink.vue`: Generic AJAX link.

**Example Usage:**

```javascript
import AxForm from '@fire1/ax-form/components/ax-form.vue';

// Register globally or locally
app.component('ax-form', AxForm);
```

```vue
<template>
    <ax-form :path="path('item_new')" plugin="ax-submit">
        Add Item
    </ax-form>
</template>
```

## Styling

Import the SCSS file to get the standard modal styles:

```scss
@import "~@fire1/ax-form/ax-form.scss";
```

## Plugins

You can activate specialized behavior via the `data-ax-plugin` attribute (comma-separated):

- `ax-submit`: Handles async form submission with file upload support.
- `validation`: Performs a "pre-submit" to the server to check for validation errors before final submission.
