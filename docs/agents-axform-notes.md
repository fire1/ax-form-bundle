# AxForm — notes for agents (brandpier)

Project-specific conventions and pitfalls. **Read this before** implementing or debugging AxForm flows. The bundle sources under `src/` are the last resort after these docs and [`ax-form-service.md`](ax-form-service.md) / [`controller-integration.md`](controller-integration.md).

## Route parameter must be `{id}` (required)

`AxFormService::form()` resolves the record id **only** from these keys, in order:

1. `$request->attributes->get('id')` — Symfony **route** parameter name must be **`id`**
2. `$request->query->get('id')`
3. `$request->request->get('id')`

It does **not** read `from`, `conn`, `task`, or any other route placeholder name.

**Symptom:** Modal opens but behaves like “create” (wrong title, empty form, erase link broken), or entity load fails when the route used `{from}` / `{conn}` while Twig passed `path('…', { from: … })`.

**Fix:**

```php
// Route — use {id}, not {from}
#[Route('/work-scheme/spreading/copy/{id}', name: 'work.scheme.spreading.copy', requirements: ['id' => '\d+'])]
public function copy(Request $request, ClientConnEntity $conn): Response
{
    // $conn is resolved from {id}; argument name may differ from the route key
}
```

```twig
{{ macros.ax_form(path('work.scheme.spreading.copy', { id: con.id }), …) }}
```

- Passing a **DTO** to `formPage($data)` does not bypass this: anything that still relies on request id (same-route POST, erase URL quirks, “modify” vs “create” title when a class name is used) expects **`id` in the URL**.
- Extra context (e.g. bulk copy targets) belongs in the **query string** via `data-query-callback` on the trigger link (`ax-form.js` appends `?to=1,2,3`), not as a renamed route parameter.
- See also **Erase URL** below — erase resolution still omits `attributes['id']` unless you pass an explicit erase URL to `formPage()`.

## Bulk selection: `data-query-callback` + hidden field (two steps)

AxForm does **not** read arbitrary page checkboxes on submit. The usual brandpier pattern (see `Filesystem/recreate.twig`, `WorkTackle/spreading/listing.twig`):

| Step | What happens |
|------|----------------|
| **1. Open modal (GET)** | `data-query-callback` runs. It scans the DOM for checked inputs and returns a query fragment, e.g. `to=5,7,9`. `ax-form.js` appends that to the modal URL: `/…/copy/42?to=5,7,9`. |
| **2. Controller (GET)** | Read `$request->query->get('to')` (or `ids`, etc.), put the CSV into the form model (`$data->toIds`), render a **`HiddenType`** field with that value. |
| **3. Submit (POST)** | Read the same value from the submitted form: `$submit->toIds`. The hidden field carries targets; the query string is usually **not** resent on POST. |

**Route `{id}`** = single record context for AxForm (e.g. “copy **from**” scheme 42). **Query `to=`** = many targets chosen on the listing page. Do not put the target list in `data-from-conn`; that attribute is only for JS to know which row’s “Copy from” was clicked so the source id can be excluded from the `to` list.

**Callback argument:** `ax-form.js` calls `queryFunction(this.th)` where `this.th` is `#ax-form-content` (the modal body), **not** the clicked `<a data-ax-form>`. Callbacks must either scan the page (recreate: all checked folder checkboxes) or use a capture-phase click listener to remember `data-from-conn` on the trigger (spreading). Do not use `el.getAttribute('data-from-conn')` on the callback argument unless you verify what the bundle passes.

## Policy

- **Do not** modify this bundle’s PHP to add project behavior.
- **Do not** override or extend `Library\Service\AxForm\AxFormService` in brandpier; it must remain a thin compatibility bridge (`extends` the bundle class only).
- If you resolve or clarify AxForm behavior for this repo, **append or update this file** (or another file under `docs/`) so future runs rely on documentation instead of code search.

## Errors and flash messages

- **`->do(FormType::class, function ($data, AxFormService $form) { ... })`** runs your callback from `AxFormService::response()`. The bundle **does not wrap** that callable in `try/catch`. A **thrown exception** does **not** get converted into a user-facing flash; it will surface as an error response unless something else handles it.
- **Preferred pattern** for user-visible failures inside the callback:
  - **`$this->errorMessageFlashBag($title, $message)`** (from `LibControllerProvider`) and **`return $form->redirectByReferer()`**, or
  - **`$form->setFlashErrors($title, $arrayOfLines)`** / **`$form->setFlashSuccess($title, $message)`** then return a redirect as your flow requires.
- Flash keys used with the theme Toastr driver: **`record-ok`** (success), **`record-er`** (error). Shape is typically `['title' => string, 'info' => string|array]` depending on the caller; keep messages short unless the UX needs detail.

## Form binding

- DTO properties typed as **`string`** with **`HiddenType`** (or other fields) may receive **`null`** from the request. Use Symfony’s **`empty_data => ''`** (or equivalent) on the field so the mapper never assigns `null` into a non-nullable string (avoids 500s on submit).

## Erase URL when entity id is a **path** parameter

`AbstractAxFormController::resolveEraseUrl()` (bundle) builds `?erase=<id>` using only **`$request->query->get('id')`** and **`$request->request->get('id')`**. It does **not** read Symfony route **`attributes['id']`**, while `AxFormService::form()` *does* resolve the loaded entity id from attributes first.

**Symptom:** In-modal “erase” / delete link is wrong or missing the task id (e.g. `?erase=` empty) for routes like `/work/task/edit/{folder}/{id}`.

**Fix (app code):** Pass an explicit fourth argument to `formPage()` / `formEdit()` — the erase URL — e.g. `generateUrl('work.task.edit', ['folder' => $folder->getId(), 'id' => $id, 'erase' => $id])`. See `WorkTaskController::editTask`.

## Related

- [`ax-form-service.md`](ax-form-service.md) — API overview (`record()`, redirects, `setFlashErrors`).
- Root [`AGENTS.md`](../../../AGENTS.md) — points here for AxForm work and the “docs first” rule.
