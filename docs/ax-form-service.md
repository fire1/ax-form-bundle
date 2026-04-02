# AxFormService

The `AxFormService` is the heart of the bundle. It provides a fluent API to handle Symfony forms inside AJAX-powered modals.

## Basic Workflow

1. **Initialize** with an entity class or object.
2. **Configure** UI elements (title, style, size).
3. **Create** the form and handle the request.
4. **Respond** with a redirect or a custom handler.

## Core API

### `form(mixed $entity, string $keyword = '', string $eraseUrl = '', string $headColor = 'primary')`

Initializes the service. If `$entity` is a class name, it tries to load the entity from the DB using the `id` from the request.

### `create(mixed $formClass, array $formOptions = [])`

Creates the Symfony form. It automatically calls `handleRequest()` on the current request.

### `do(mixed $formClass, mixed $handler = null, array $routeParams = [])`

A powerful shortcut that combines `create()` and `response()`. 

```php
return $form->do(TaskType::class, function(Task $task, AxFormService $service) {
    $service->record(); // Persists to DB
    return $service->redirectByReferer();
});
```

## UI Configuration

- `title(string $title)`: Set the modal title.
- `style(string $color)`: Set header color (primary, danger, success, etc.).
- `setModalSize(string $size)`: Use `AxFormService::SizeWide` or `SizeExtraWide`.
- `setInfoTop(string $title, ?string $content)`: Show an info box above the form.
- `btnSaveLabel(string $label)`: Change the submit button text.

## Database Actions

- `record(?object $entity = null)`: Persists and flushes the entity. Automatically adds success or error flash messages.
- `forceInsert()`: Forces Doctrine to insert a new row even if an ID is present (useful for cloning).
- `handleCacheRegion()`: Manually evicts the Doctrine second-level cache for the entity class.

## Redirection

- `redirectByReferer()`: Returns a `RedirectResponse` to the previous page.
- `redirect(string $route, array $params)`: Redirects to a specific route.
