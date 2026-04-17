# The Symfony AxFormBundle

A standalone, redistributable Symfony bundle for building AJAX-powered modals and multi-step forms with ease.

## Features

- **Fluent AxFormService**: A clean API for handling entity creation and modification within modals (requires **Doctrine** and **Symfony Form**).
- **AxStepsService**: Session-based orchestrator for complex multi-step form workflows.
- **AbstractAxFormController**: Base controller with built-in helpers for modal initialization and entity deletion.
- **Twig Integration**: Custom functions for rendering modal triggers and the required container structure.
- **Bootstrap 5 Support**: Out-of-the-box templates compatible with Bootstrap 5.
- **Portable Assets**: Includes a standalone npm package `@fire1/ax-form` with Vue 3 components.

## Requirements

- **PHP 8.2** or higher
- **Symfony 5.4, 6.x, or 7.x**
- **Doctrine ORM** (for `AxFormService` entity persistence)
- **Symfony Form Component** (for form handling)
- **Twig Bundle** (for rendering)

## Documentation

Full documentation is available in the [docs](docs/index.md) folder:

1. [Installation](docs/installation.md)
2. [AxFormService](docs/ax-form-service.md)
3. [AxStepsService](docs/ax-steps-service.md)
4. [Controller Integration](docs/controller-integration.md)
5. [Frontend Assets](docs/frontend-assets.md)
6. [Twig Helpers](docs/twig-helpers.md)

## Quick Installation

```bash
composer require fire1/ax-form-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Fire1\AxFormBundle\Fire1AxFormBundle::class => ['all' => true],
];
```

Ensure you have the required modal structure in your base layout:

```twig
{{ ax_form_modal() }}
```

## Basic Usage Example

```php
// In a Controller extending AbstractAxFormController
public function new(): Response
{
    $form = $this->formPage(Task::class, 'task');

    return $form
        ->title('Create New Task')
        ->do(TaskType::class, function (Task $task, AxFormService $form) {
            $form->record();
            return $form->redirectByReferer();
        });
}
```
