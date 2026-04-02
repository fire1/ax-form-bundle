# Installation

Follow these steps to integrate `AxFormBundle` into your Symfony project.

## 1. Requirement

- PHP 8.2 or higher
- Symfony 5.4, 6.x, or 7.x
- Twig Bundle
- Webpack Encore (for frontend assets)

## 2. Composer

Install the bundle via Composer:

```bash
composer require fire1/ax-form-bundle
```

## 3. Register the Bundle

If you are not using Symfony Flex, register the bundle manually in `config/bundles.php`:

```php
return [
    // ...
    Fire1\AxFormBundle\Fire1AxFormBundle::class => ['all' => true],
];
```

## 4. Frontend Assets

The bundle provides a standalone npm package for its JavaScript and Vue components.

### npm / Yarn

Add the dependency to your `package.json`:

```json
"dependencies": {
    "@fire1/ax-form": "file:vendor/fire1/ax-form-bundle/src/Resources/assets"
}
```

Then run:

```bash
yarn install
# or
npm install
```

### Import in JavaScript

```javascript
import { AxForm } from '@fire1/ax-form';
import '@fire1/ax-form/ax-form.scss';

// Initialize triggers globally
$(document).on('click', '[data-ax-form]', function (event) {
    const ax = new AxForm(this);
});
```

## 5. Twig Setup

Ensure the required modal container is present in your base layout (usually `base.html.twig` before `</body>`):

```twig
{{ ax_form_modal() }}
```
