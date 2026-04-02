# Controller Integration

To use the bundle efficiently, your controllers should extend `Fire1\AxFormBundle\Controller\AbstractAxFormController`.

## Methods Provided

### `formPage(mixed $entity, string $keyword = ...)`

Initializes the `AxFormService` and forces the response to be rendered as a modal, even if the request is not AJAX. This is the most common entry point for "Form Pages".

### `formEdit(mixed $entity, string $keyword = ...)`

Initializes the `AxFormService` **only** if the request is AJAX or POST. 
- If it is AJAX/POST: returns the service.
- If it is GET: returns `false`.

This allows a single route to serve both a full page and a modal:

```php
public function edit(int $id): Response
{
    $entity = $this->repository->find($id);
    
    // Serve modal if AJAX
    if ($form = $this->formEdit($entity, 'item')) {
        return $form->do(MyType::class);
    }

    // Serve full page if regular request
    return $this->render('item/edit.html.twig', ['item' => $entity]);
}
```

### `formSteps()`

Returns the `AxStepsService` instance for multi-step flows.

### `eraseInForm(string|object $entity, ?string $redirect = null)`

Built-in logic for entity deletion within a modal.
- It detects the `erase` query parameter.
- If present, it deletes the entity and returns a `JsonResponse` or `RedirectResponse`.
- If not present, it registers the erase URL for the modal's delete button.

```php
public function form(int $id): Response
{
    // Handle deletion first
    if ($response = $this->eraseInForm(MyEntity::class)) {
        return $response;
    }

    return $this->formPage(MyEntity::class, 'Item')->do(MyType::class);
}
```
