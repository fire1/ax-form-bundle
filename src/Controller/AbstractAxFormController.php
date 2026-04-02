<?php

namespace Fire1\AxFormBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Fire1\AxFormBundle\Service\AxFormService;
use Fire1\AxFormBundle\Service\AxStepsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * AbstractAxFormController — base controller providing AJAX form helpers.
 *
 * Extend this class (directly or via TomatoControllerProvider) to gain access
 * to {@see formPage()}, {@see formEdit()}, {@see formSteps()}, and related
 * delete/erase utilities.
 *
 * ## Example
 *
 * ```php
 * class MyController extends AbstractAxFormController
 * {
 *     #[Route('/item/{id}', name: 'item.form')]
 *     public function form(int $id = 0): Response
 *     {
 *         $form = $this->formPage(MyEntity::class, 'item');
 *
 *         return $form->do(MyFormType::class, function (MyEntity $entity, AxFormService $form) {
 *             $form->record();
 *             return $form->redirectByReferer();
 *         });
 *     }
 * }
 * ```
 *
 * ## Required HTML structure
 *
 * Every page that uses AJAX forms must include the modal container. Add it to
 * your base layout or render it via the Twig helper:
 *
 * ```twig
 * {{ ax_form_modal() }}
 * ```
 */
abstract class AbstractAxFormController extends AbstractController
{
    //
    // This is you url query key for the erase request
    protected const ERASE_KEY = 'erase';

    /**
     * Allow formPage() to force AJAX mode regardless of request type.
     *
     * Set to true when the route's sole purpose is to serve a form modal.
     */
    protected bool $allowAxFormRequest = false;

    /**
     * Query key used for in-form delete functionality.
     *
     * Set automatically by {@see eraseInForm()}.
     */
    protected ?string $allowEraseAxFormRequest = null;

    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'fire1_ax_form' => AxFormService::class,
            'fire1_ax_form.steps' => AxStepsService::class,
        ]);
    }

    // =========================================================================
    // Form initialization
    // =========================================================================

    /**
     * Initialize AxFormService for a route whose primary purpose is a form modal.
     *
     * Forces `allowAxFormRequest = true` — the form is always served regardless
     * of whether the request is AJAX or not.
     *
     * @param string|object $entity    Entity class name or pre-loaded entity object
     * @param string        $keyword   Short noun for the auto-generated title
     * @param string        $headColor Modal header color ({@see AxFormService::StylePrimary})
     * @param string        $eraseUrl  URL for in-modal delete button
     */
    protected function formPage(mixed $entity, string $keyword = '', string $headColor = AxFormService::StylePrimary, string $eraseUrl = ''): AxFormService
    {
        $this->allowAxFormRequest = true;

        /** @var AxFormService $form */
        $form = $this->container->get('fire1_ax_form');
        $this->resolveUiTemplate($form);

        return $form->form($entity, $keyword, $this->resolveEraseUrl($eraseUrl), $headColor);
    }

    /**
     * Initialize AxFormService only when the request is AJAX or POST.
     *
     * Use this for routes that show a standard page view but also support an
     * inline edit form opened via a modal.
     *
     * Returns false when the request is a plain GET (non-AJAX), allowing the
     * controller to fall through to its regular page render.
     *
     * ```php
     * $form = $this->formEdit($entity, 'item');
     * if (!$form) {
     *     return $this->render('@MyBundle/page.twig', ['entity' => $entity]);
     * }
     * return $form->do(MyFormType::class, ...);
     * ```
     */
    protected function formEdit(mixed $entity, string $keyword = '', string $headColor = AxFormService::StylePrimary, string $eraseUrl = ''): AxFormService|false
    {
        /** @var AxFormService $form */
        $form = $this->container->get('fire1_ax_form');

        if (!$form->isAxOrPost()) {
            return false;
        }

        $this->resolveUiTemplate($form);

        return $form->form($entity, $keyword, $this->resolveEraseUrl($eraseUrl), $headColor);
    }

    /**
     * Retrieve the AxStepsService for multi-step form workflows.
     */
    protected function formSteps(): AxStepsService
    {
        return $this->container->get('fire1_ax_form.steps');
    }

    // =========================================================================
    // Delete / erase helpers
    // =========================================================================

    /**
     * Handle in-form entity deletion and set up erase URL generation.
     *
     * Call this at the top of a form action method. If the request contains the
     * erase query parameter, the entity is deleted and a response is returned.
     * Otherwise the erase URL is registered for {@see formPage()} / {@see formEdit()}.
     *
     * ```php
     * public function form(int $id = 0): Response
     * {
     *     if ($response = $this->eraseInForm(MyEntity::class)) {
     *         return $response;
     *     }
     *     return $this->formPage(MyEntity::class, 'item')->do(MyFormType::class, ...);
     * }
     * ```
     *
     * @param string|object $entity   Entity class name or object (id resolved via getId())
     * @param string|null   $redirect Route name or URL to redirect after deletion (null = referer)
     * @param array         $params   Route parameters for $redirect
     * @param string        $queryKey Query key used to trigger deletion
     */
    protected function eraseInForm(string|object $entity, ?string $redirect = null, array $params = [], string $queryKey = self::ERASE_KEY): ?Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (is_object($entity)) {
            $request->query->set('id', $entity->getId());
            $entity = get_class($entity);
        }

        if ($eraseResponse = $this->eraseRow($entity, $redirect, $params, $queryKey)) {
            return $eraseResponse;
        }

        $this->allowEraseAxFormRequest = $queryKey;

        return null;
    }

    /**
     * Perform entity deletion if the erase query key is present in the request.
     *
     * Returns a Response/RedirectResponse on deletion, or false when the query
     * key is absent (no-op).
     */
    protected function eraseRow(string $entity, ?string $redirect = null, array $params = [], string $queryKey = self::ERASE_KEY): Response|false
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (!$request->query->has($queryKey)) {
            return false;
        }

        /** @var EntityManagerInterface */
        $em = $this->getDoctrine()->getManager();
        $em->remove($em->getReference($entity, $request->query->get($queryKey)));
        $em->flush();

        if (null === $redirect) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse('ok');
            }

            return $this->redirectByReferer();
        }

        return str_contains($redirect, '/')
            ? new RedirectResponse($redirect)
            : $this->redirectToRoute($redirect, $params);
    }

    // =========================================================================
    // Navigation helpers
    // =========================================================================

    /**
     * Redirect to the HTTP Referer header value (or home if absent).
     */
    protected function redirectByReferer(?string $default = null): RedirectResponse
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        //
        // Hope home route is existing, if is not then you can redirect from there to your index when fallback occurs.
        return $this->redirect($request->headers->get('referer') ?? $default ?? $this->generateUrl('home'));
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Detect the Bootstrap version from the X-UI-Version request header and
     * configure the form service to use the appropriate template.
     *
     * Only Bootstrap 5 is supported. Unknown/missing headers default to BS5.
     * X-UI-Version also can be used to define different templates from the link,
     *  but highly not recommended.
     */
    private function resolveUiTemplate(AxFormService $form): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if (!$request->headers->has('X-UI-Version')) {
            return;
        }

        // Only BS5 is supported — BS4 support has been removed.
        // Any header value other than 'bs5' is ignored (defaults to BS5).
        $form->tpl(AxFormService::Template);
    }

    /**
     * Build the erase URL from the current route when no explicit URL is provided.
     */
    private function resolveEraseUrl(string $eraseUrl): string
    {
        if (!empty($eraseUrl) || !$this->allowEraseAxFormRequest) {
            return $eraseUrl;
        }

        $request = $this->container->get('request_stack')->getCurrentRequest();
        $params = array_merge(
            $request->attributes->get('_route_params', []),
            [$this->allowEraseAxFormRequest => $request->get('id')],
        );

        return $this->generateUrl($request->get('_route'), $params);
    }
}
