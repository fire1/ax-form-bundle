# AxStepsService

The `AxStepsService` allows you to orchestrate multi-step forms using a session-based approach.

## Basic Usage

In your controller, use `formSteps()` to get the service and add closures for each step.

```php
public function flow(): Response
{
    $steps = $this->formSteps();

    // Step 1: Data gathering
    $steps->add(function (AxStepsService $steps) {
        $form = $this->formPage(MyEntity::class, 'First Step');
        return $form->do(StepOneType::class, function ($data) use ($steps) {
            $steps->setData($data); // Persists data in cache for next steps
            return null; // Return null to advance to the next step
        });
    });

    // Step 2: Confirmation & Save
    $steps->add(function (AxStepsService $steps) {
        $form = $this->formPage(MyEntity::class, 'Final Step');
        return $form->do(StepTwoType::class, function ($entity, AxFormService $form) use ($steps) {
            $form->record();
            $steps->reset(); // Clears step progress and cache
            return $form->redirectByReferer();
        });
    });

    return $steps->render();
}
```

## How it works

1. **Session Persistence**: The current step index is stored in the session (`form_step`).
2. **Data Cache**: The `setData()` method uses a `FilesystemCacheTrait` to store arbitrary data between steps, keyed by the referer URL.
3. **Automatic Advancement**: If a step callback returns `null` or a `RedirectResponse`, the service automatically increments the step and re-renders.
4. **Resets**: If the data is missing (e.g., session expired) or `reset()` is called, the flow starts over from Step 0.
