<?php

namespace Fire1\AxFormBundle\Service;

use Fire1\AxFormBundle\Traits\FilesystemCacheTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * AxStepsService — session-based multi-step form orchestrator.
 *
 * ## Usage
 *
 * ```php
 * // In a controller extending AbstractAxFormController:
 *
 * $steps = $this->formSteps();
 *
 * $steps->add(function (AxStepsService $steps): Response {
 *     $form = $this->formPage(MyEntity::class, 'step 1');
 *     return $form->do(StepOneFormType::class, function ($data) use ($steps) {
 *         $steps->setData($data);
 *         return null; // null signals "proceed to next step"
 *     });
 * });
 *
 * $steps->add(function (AxStepsService $steps): Response {
 *     $form = $this->formPage(MyEntity::class, 'step 2');
 *     return $form->do(StepTwoFormType::class, function ($data, AxFormService $form) use ($steps) {
 *         $form->record();
 *         $steps->reset();
 *         return $form->redirectByReferer();
 *     });
 * });
 *
 * return $steps->render();
 * ```
 */
class AxStepsService
{
    use FilesystemCacheTrait;

    private const SESSION_STEP_KEY = 'form_step';
    private const SESSION_REFERER_KEY = 'form_referer';

    /** @var callable[] */
    private array $forms = [];

    private mixed $data = null;
    private bool $isReset = false;
    private SessionInterface $session;

    public function __construct(private readonly RequestStack $requestStack)
    {
        $this->session = $this->requestStack->getSession();
    }

    /**
     * Register a step callback.
     *
     * Each callback receives this `AxStepsService` instance and must return
     *
     * @param $form callable
     *              a {@see Response} or `null`/`RedirectResponse` to advance to the next step
     */
    public function add(callable $form): void
    {
        $this->forms[] = $form;
    }

    /** Store arbitrary data to pass between steps (backed by filesystem cache). */
    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /** Mark the step sequence for reset on the next {@see render()} call. */
    public function reset(): void
    {
        $this->isReset = true;
    }

    public function resetStepOffset(): void
    {
        $this->session->set(self::SESSION_STEP_KEY, 0);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Execute the current step and return its Response.
     *
     * Advances automatically when a step returns null or a RedirectResponse.
     */
    public function render(): ?Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $step = $this->resolveStepOffset();

        if (0 === $step) {
            $this->storeReferer($request->headers->get('referer', '/'));
        }

        if (null === $this->data && $step > 0 && 0 === $request->request->count()) {
            $this->resetStepOffset();

            return $this->render();
        }

        if (null !== $this->data) {
            $item = $this->traitCacheItem(crc32($request->headers->get('referer', '/')), $step);
            $item->set($this->data);
        }

        $result = $this->invokeStep($step);

        if ($result instanceof RedirectResponse || null === $result) {
            $this->advanceStep();

            return $this->render();
        }

        if ($result instanceof Response) {
            return $result;
        }

        return null;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function resolveStepOffset(): int
    {
        if (!$this->session->has(self::SESSION_STEP_KEY)) {
            $this->resetStepOffset();
        }

        return (int) $this->session->get(self::SESSION_STEP_KEY);
    }

    private function advanceStep(): void
    {
        $offset = (int) $this->session->get(self::SESSION_STEP_KEY, 0);
        if (!$this->isLastStep($offset)) {
            ++$offset;
        }
        $this->session->set(self::SESSION_STEP_KEY, $offset);
    }

    private function isLastStep(int $step): bool
    {
        return $step >= (\count($this->forms) - 1);
    }

    private function storeReferer(string $referer): void
    {
        $this->session->set(self::SESSION_REFERER_KEY, $referer);
    }

    private function getStoredReferer(): ?string
    {
        return $this->session->get(self::SESSION_REFERER_KEY);
    }

    private function invokeStep(int $index): mixed
    {
        if (!isset($this->forms[$index])) {
            throw new \OutOfRangeException(sprintf('No step registered at index %d.', $index));
        }

        return ($this->forms[$index])($this);
    }
}
