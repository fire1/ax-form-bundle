<?php

namespace Fire1\AxFormBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Fire1\AxFormBundle\Exception\AxFormException;
use Fire1\AxFormBundle\Traits\AxFormHelpTrait;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * AxFormService — fluent API for AJAX-powered Symfony form modals.
 *
 * ## Quick start
 *
 * ```php
 * // In a controller extending AbstractAxFormController:
 *
 * public function edit(int $id): Response
 * {
 *     $form = $this->formPage(MyEntity::class, 'my item');
 *
 *     return $form
 *         ->title('Edit item')
 *         ->style(AxFormService::StylePrimary)
 *         ->do(MyFormType::class, function (MyEntity $entity, AxFormService $form) {
 *             $form->record();
 *             return $form->redirectByReferer();
 *         });
 * }
 * ```
 *
 * ## Required HTML structure
 *
 * The following modal container must be present in the page layout:
 *
 * ```html
 * <div id="ax-form-modal" class="modal animate__animated animate__fadeInUp" aria-hidden="true" tabindex="-1">
 *     <div class="modal-dialog modal-dialog-centered" role="document">
 *         <div id="ax-form-content" class="modal-content"></div>
 *     </div>
 * </div>
 * ```
 *
 * The Twig helper `{{ ax_form_modal() }}` auto-renders this structure.
 *
 * ## Plugin system (JavaScript)
 *
 * Plugins are activated via the `data-ax-plugin` attribute on the trigger link.
 * Available plugins (comma-separated):
 *   - `ax-submit`  — async form submission with file upload support
 *   - `validation` — client-side pre-validation before submit
 *
 * ## Modal sizes
 *
 * Use {@see SizeWide} or {@see SizeExtraWide} with {@see setModalSize()}.
 */
class AxFormService
{
    //
    // Helper trait containing alias methods and additional helping function.
    use AxFormHelpTrait;

    // Title prefixes (translatable by overriding before use)
    public static string $titleCreate = 'Create new ';
    public static string $titleModify = 'Modify existing ';
    public static string $titleItem = ' item ';

    // Modal width constants (applied as CSS class on the <form> element)
    public const SizeWide = 'ax-form-wide-modal-required';
    public const SizeExtraWide = 'ax-form-extra-wide-modal-required';

    // Modal header color constants (Bootstrap contextual colors)
    public const StylePrimary = 'primary';
    public const StyleLight = 'light';
    public const StyleSecondary = 'secondary';
    public const StyleDanger = 'danger';
    public const StyleWarning = 'warning';
    public const StyleSuccess = 'success';
    public const StyleInfo = 'info';

    // Query key used to trigger in-modal entity deletion
    public const EraseQuery = 'form_erase';

    // Default Twig template (Bootstrap 5)
    public const Template = '@Fire1AxForm/forms/form_bootstrap_5.twig';

    // --- Internal state ---

    protected ?string $keyword = null;
    protected ?string $generatedTitle = null;
    protected string $title = '';
    protected string $color = self::StylePrimary;
    protected string $erase = '';
    protected ?string $btnLabelSave = null;
    protected ?string $btnLabelClose = null;
    protected ?string $modalSize = null;
    protected bool $isValidationEnabled = true;
    protected bool $isAxNextSubmit = false;
    protected ?array $infoTop = null;
    protected ?array $infoBot = null;
    protected ?FormInterface $form = null;
    protected mixed $entity = null;
    protected ?int $id = null;
    protected string $template = self::Template;
    protected array $templateParams = [];
    protected ?string $actionRoute = null;
    protected bool $doForceInsert = false;
    protected array $formOptions = [];

    private bool $isCaptureData = false;
    private EntityManagerInterface $manager;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly ManagerRegistry $doctrine,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
    ) {
        $this->manager = $this->doctrine->getManager();
    }

    // =========================================================================
    // Entry points
    // =========================================================================

    /**
     * Initialize the service for a given entity.
     *
     * When `$entityClass` is a class name string and `id` is non-zero in the
     * current request, the entity is loaded from the database; otherwise a new
     * instance is created.
     *
     * Resolves ID from the request using the following priority:
     *  1. URL Path parameters, 2. Query string (GET), 3. Request body (POST).
     *
     * Typically called via {@see AbstractAxFormController::formPage()} or
     * {@see AbstractAxFormController::formEdit()}.
     *
     * @param string|object $entityClass Entity class name or pre-loaded object
     * @param string        $keyword     Short noun used in auto-generated title
     * @param string        $eraseUrl    URL for the in-modal delete button (empty = no button)
     * @param string        $headColor   Modal header color ({@see StylePrimary}, etc.)
     */
    public function form(mixed $entityClass, string $keyword = '', string $eraseUrl = '', string $headColor = self::StylePrimary): static
    {
        $this->keyword = empty($keyword) ? self::$titleItem : $keyword;
        $this->erase = $eraseUrl;
        $this->color = $headColor;

        $request = $this->getRequest();
        // Default to 0 if no id is found in the request
        $this->id = (int) ($request->attributes->get('id') ?? $request->query->get('id') ?? $request->request->get('id', 0));

        if (is_string($entityClass)) {
            $this->entity = (0 !== $this->id)
                ? $this->manager->getRepository($entityClass)->find($this->id)
                : new $entityClass();
        } elseif (is_object($entityClass)) {
            $this->entity = $entityClass;
            $this->id = method_exists($entityClass, 'getId') ? (int) $entityClass->getId() : 0;
        }

        return $this;
    }

    /**
     * Create the Symfony form from a FormType class, FormInterface, or callable builder.
     *
     * Handles the request automatically (captureData).
     *
     * @param string|FormInterface|callable $formClass
     * @param array                         $formOptions Extra options merged with previously set options
     */
    public function create(mixed $formClass, array $formOptions = []): static
    {
        $this->generatedTitle = (0 === $this->id) ? static::$titleCreate : static::$titleModify;
        $options = array_merge($this->formOptions, ['action' => $this->actionRoute ?? $this->getSameRoute()], $formOptions);

        if ($formClass instanceof FormInterface) {
            $this->form = $formClass;
        } elseif (is_callable($formClass)) {
            $builder = $this->formFactory->createBuilder(FormType::class, $this->entity, $options);
            $formClass($builder, $this->entity);
            $this->form = $builder->getForm();
        } else {
            $this->form = $this->formFactory->create($formClass, $this->entity, $options);
        }

        $flash = $this->getFlashBag();
        if ($flash->has('past_referer')) {
            $flash->set('past_referer', current($flash->get('past_referer')));
        }

        $this->captureData();

        return $this;
    }

    /**
     * Shortcut combining {@see create()} and {@see Response()}.
     *
     * This is the preferred single-call API:
     *
     * ```php
     * return $form->do(MyFormType::class, function (MyEntity $entity, AxFormService $form) {
     *     $form->record();
     *     return $form->redirectByReferer();
     * });
     * ```
     *
     * @param string|FormInterface|callable         $formClass
     * @param callable|RedirectResponse|string|null $handler     Callback, redirect, route name, or null
     * @param array                                 $routeParams Parameters when $handler is a route name
     */
    public function do(mixed $formClass, mixed $handler = null, array $routeParams = []): Response|RedirectResponse
    {
        return $this->create($formClass)->response($handler, $routeParams);
    }

    /**
     * Modify the entity before form creation (useful for pre-filling fields).
     *
     * ```php
     * $form->push(function (MyEntity $entity, AxFormService $service) {
     *     $entity->setOwner($this->getUser());
     * });
     * ```
     */
    public function push(callable $callback): static
    {
        $callback($this->getEntity(), $this);

        return $this;
    }

    // =========================================================================
    // Configuration — fluent setters (chain before do() or create())
    // =========================================================================

    /** Set the modal title explicitly (overrides keyword-based auto-title). */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** Set the modal header color. Use StyleXxx constants. */
    public function style(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /** Set the modal width. Use {@see SizeWide} or {@see SizeExtraWide}. */
    public function setModalSize(string $size): static
    {
        $this->modalSize = $size;

        return $this;
    }

    /** Pass extra options to the Symfony FormType. */
    public function opt(array $options = []): static
    {
        $this->formOptions = array_merge($this->formOptions, $options);

        return $this;
    }

    /**
     * Override the default Twig template and pass extra template variables.
     *
     * @param string $template Twig template path (e.g. '@MyBundle/forms/custom.twig')
     * @param array  $params   Extra variables available in the template
     */
    public function tpl(string $template, array $params = []): static
    {
        $this->template = $template;
        $this->templateParams = array_merge($this->templateParams, $params);

        return $this;
    }

    /** Show a collapsible info box above the form fields. */
    public function setInfoTop(string $title, ?string $content = null): static
    {
        $this->infoTop = ['title' => $title, 'content' => $content];

        return $this;
    }

    /** Show a collapsible info box below the form fields. */
    public function setInfoBot(string $title, ?string $content = null): static
    {
        $this->infoBot = ['title' => $title, 'content' => $content];

        return $this;
    }

    /**
     * Enable multi-step mode.
     *
     * The "Next" button is shown instead of "Submit" until all steps are complete.
     * The number of steps is determined by the JS `data-clicks` attribute.
     */
    public function setAxNextSubmit(bool $state = true): static
    {
        $this->isAxNextSubmit = $state;

        return $this;
    }

    /** Override the submit button label. */
    public function btnSaveLabel(string $name): static
    {
        $this->btnLabelSave = ucfirst($name);

        return $this;
    }

    /** Override the dismiss/close button label. */
    public function btnCloseLabel(string $name): static
    {
        $this->btnLabelClose = ucfirst($name);

        return $this;
    }

    /**
     * Disable server-side pre-validation (207 response).
     *
     * When disabled, the X-Form-validation header is ignored and forms are
     * always submitted directly.
     */
    public function disableFormValidation(): static
    {
        $this->isValidationEnabled = false;

        return $this;
    }

    /**
     * Override the form action URL using a named route.
     *
     * Useful when the form must POST to a different route than the current one.
     */
    public function setActionRoute(string $routeName, array $routeParams = []): static
    {
        $this->actionRoute = $this->router->generate($routeName, $routeParams);

        return $this;
    }

    /** Set the erase URL and entity ID for the in-modal delete button. */
    public function setErase(string $erase, int $id = -1): static
    {
        $this->id = $id;
        $this->erase = $erase;

        return $this;
    }

    /**
     * Reset ID to 0, forcing a new insert on {@see record()}.
     *
     * Use when cloning an entity or creating from an existing one.
     */
    public function clearId(): void
    {
        $this->id = 0;
    }

    /**
     * Force Doctrine to insert a new row instead of updating.
     *
     * Clones the form data before persisting.
     */
    public function forceInsert(): void
    {
        $this->doForceInsert = true;
    }

    /**
     * Merge additional variables into the Twig template context.
     *
     * @@param array
     */
    public function setTemplateParams(array $params): void
    {
        $this->templateParams = array_merge($this->templateParams, $params);
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Persist the entity and flush.
     *
     * On success: adds a flash success message and returns true.
     * On invalid form: adds flash error messages and returns false.
     *
     * @param object|null $entity Override which entity to persist (default: form data)
     */
    public function record(?object $entity = null): bool
    {
        $form = $this->getForm();
        if ($form->isValid()) {
            $data = $entity ?? ($this->doForceInsert ? clone $form->getData() : $form->getData());
            $this->manager->persist($data);
            $this->manager->flush();
            $this->handleCacheRegion();
            $this->setFlashSuccess('Data saved O.K.', 'Modification recorded into database.');

            return true;
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            /* @var FormError $error */
            $errors[] = sprintf('"%s" - %s', $error->getOrigin()->getName(), $error->getMessage());
        }
        $this->setFlashErrors('Unable to record', $errors);

        return false;
    }

    /**
     * Render the form template and return an HTTP Response.
     *
     * @param string|null $template Override template for this render only
     * @param array       $params   Extra template variables for this render only
     * @param int         $status   HTTP status code (207 = validation error)
     */
    public function render(?string $template = null, array $params = [], int $status = 200): Response
    {
        $template = $template ?? $this->template;
        $content = $this->twig->render(
            $template,
            array_merge($this->templateParams, $this->getTemplateData(), $params),
        );

        return new Response($content, $status);
    }

    /**
     * Handle submission and produce the appropriate response.
     *
     * - Not submitted: renders the form (GET)
     * - Submitted with X-Form-validation header: returns 207 on invalid or JSON on valid
     * - Submitted with callable handler: delegates to the callback
     * - Submitted with route/null: calls {@see record()} and redirects
     *
     * @internal prefer {@see do()} over calling this directly
     */
    public function response(mixed $handler = null, array $routeParams = []): Response|RedirectResponse
    {
        if (!$this->form->isSubmitted()) {
            return $this->render();
        }

        if ($this->isValidationEnabled) {
            $request = $this->getRequest();
            if ('XMLHttpRequest' === $request->headers->get('X-Requested-With') && $request->headers->has('X-Form-validation')) {
                if (!$this->form->isValid()) {
                    return $this->render(null, [], 207);
                }

                return new JsonResponse(['valid' => true]);
            }
        }

        if (is_callable($handler)) {
            return $handler($this->form->getData(), $this);
        }

        if ($handler instanceof RedirectResponse) {
            $this->record();

            return $handler;
        }

        $this->record();
        $url = is_null($handler)
            ? $this->getReferer()
            : $this->router->generate($handler, $routeParams);

        return new RedirectResponse($url, 302, ['redirect' => $url]);
    }

    /**
     * Generate the erase URL for the current route with the given entity ID appended.
     *
     * Used internally to create the in-modal delete button URL.
     */
    public function generateEraseUrl(?int $id = null): string
    {
        $request = $this->getRequest();
        $params = array_merge(
            $request->attributes->get('_route_params', []),
            [self::EraseQuery => $id ?? ($request->query->get('id') ?? $request->request->get('id'))],
        );

        return $this->router->generate($request->attributes->get('_route'), $params);
    }

    // =========================================================================
    // Flash messages
    // =========================================================================

    public function setFlashSuccess(string $title, string $message): void
    {
        $this->getFlashBag()->set('record-ok', ['title' => $title, 'info' => $message]);
    }

    public function setFlashErrors(string $title, array $info): void
    {
        $this->getFlashBag()->set('record-er', ['title' => $title, 'info' => $info]);
    }

    // =========================================================================
    // Getters / accessors
    // =========================================================================

    public function getForm(): ?FormInterface
    {
        return $this->form;
    }

    public function getEntity(): mixed
    {
        return $this->entity;
    }

    public function getData(): mixed
    {
        return $this->form->getData();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManager(): EntityManagerInterface
    {
        return $this->manager;
    }

    /** Alias for {@see getManager()}. */
    public function em(): EntityManagerInterface
    {
        return $this->manager;
    }

    public function getRequest(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function getFlashBag(): FlashBagInterface
    {
        return $this->requestStack->getSession()->getFlashBag();
    }

    public function isAxOrPost(): bool
    {
        $request = $this->getRequest();

        return 'XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->isMethod('POST');
    }

    public function getReferer(): string
    {
        $flash = $this->getFlashBag();
        if ($flash->has('past_referer')) {
            return current($flash->get('past_referer'));
        }

        return $this->getRequest()->headers->get('referer', '/');
    }

    public function redirectByReferer(): RedirectResponse
    {
        return new RedirectResponse($this->getReferer());
    }

    public function redirect(string $routeName, array $params = []): RedirectResponse
    {
        return new RedirectResponse($this->router->generate($routeName, $params), 302);
    }

    /**
     * Read a single submitted form field value by field name.
     *
     * Replaces the deprecated `getUploadField()`.
     */
    public function getSubmittedField(string $fieldName): mixed
    {
        if (isset($this->getForm()[$fieldName])) {
            return $this->getForm()[$fieldName]->getData();
        }

        return null;
    }

    /**
     * Validate and set flash messages without persisting.
     *
     * @return bool True if form is valid
     */
    public function handleValidationInfo(): bool
    {
        $form = $this->getForm();
        if ($form->isValid()) {
            $this->setFlashSuccess('Data saved O.K.', 'Modification recorded into database.');

            return true;
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            /* @var FormError $error */
            $errors[] = sprintf('"%s" - %s', $error->getOrigin()->getName(), $error->getMessage());
        }
        $this->setFlashErrors('Something went wrong', $errors);

        return false;
    }

    /**
     * Evict all second-level cache for the current entity class.
     *
     * Workaround for a Doctrine ORM second-level cache invalidation issue.
     *
     * @see https://github.com/doctrine/orm/issues/5821
     */
    public function handleCacheRegion(): bool
    {
        $cache = $this->manager->getCache();
        if ($cache && $region = $cache->getEntityCacheRegion(get_class($this->getEntity()))) {
            $region->evictAll();

            return true;
        }

        return false;
    }

    /**
     * Build the data array passed to the Twig template.
     *
     * @throws AxFormException If create() was not called or no title/keyword is set
     */
    public function getTemplateData(): array
    {
        if (is_null($this->form)) {
            throw new AxFormException('Call create() before rendering the form.');
        }
        if (empty($this->title) && empty($this->keyword)) {
            throw new AxFormException('Set a title() or keyword before rendering.');
        }

        $title = empty($this->title)
            ? ($this->generatedTitle ?? '').$this->keyword
            : $this->title;

        return [
            'id' => $this->id,
            'form' => $this->form->createView(),
            'color' => $this->color,
            'title' => $title,
            'erase' => $this->erase,
            'infoTop' => $this->infoTop,
            'infoBot' => $this->infoBot,
            'button_label' => $this->btnLabelSave,
            'button_close' => $this->btnLabelClose,
            'modal_size' => $this->modalSize,
            'ax_next_submit' => $this->isAxNextSubmit,
        ];
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function captureData(): void
    {
        if ($this->isCaptureData) {
            return;
        }
        $this->form->handleRequest($this->getRequest());
        $this->isCaptureData = true;
    }

    private function getSameRoute(): string
    {
        return $this->getRequest()->getRequestUri();
    }
}
