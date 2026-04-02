<?php

namespace Fire1\AxFormBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Fire1AxFormBundle — AJAX-powered Symfony form modals.
 *
 * Self-contained bundle providing:
 *   - AxFormService: fluent API for AJAX form handling
 *   - AxStepsService: multi-step form session management
 *   - AbstractAxFormController: base controller helpers
 *   - Twig function: ax_form()
 *   - Bootstrap 5 modal template
 *
 * @see Resources/views/forms/form_bootstrap_5.twig  Required modal HTML structure
 */
class Fire1AxFormBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
