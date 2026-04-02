<?php

namespace Fire1\AxFormBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing the `ax_form()` and `ax_form_modal()` functions.
 *
 * ## ax_form()
 *
 * Renders a trigger link that opens an AJAX form in a Bootstrap 5 modal.
 *
 * ```twig
 * {# Simple link #}
 * {{ ax_form(path('item.form', {id: item.id}), item.name, 'btn btn-sm btn-primary') }}
 *
 * {# With tooltip #}
 * {{ ax_form(path('item.form', {id: item.id}), 'Edit', 'btn btn-outline-secondary', 'Edit this item') }}
 *
 * {# With plugin override #}
 * {{ ax_form(path('item.form', {id: item.id}), 'Edit', 'btn btn-primary', '', {plugin: 'validation'}) }}
 * ```
 *
 * ## ax_form_modal()
 *
 * Renders the required modal container HTML. Place this once in your base layout.
 *
 * ```twig
 * {{ ax_form_modal() }}
 * ```
 */
class AxFormTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ax_form', [$this, 'renderAxForm'], ['is_safe' => ['html']]),
            new TwigFunction('ax_form_modal', [$this, 'renderAxFormModal'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render the trigger `<a>` element for an AJAX form.
     *
     * @param string $path        The URL the form will be fetched from
     * @param string $label       Link text or HTML label (raw, not escaped)
     * @param string $class       CSS class(es) for the `<a>` tag
     * @param string $description Optional tooltip (title attribute)
     * @param array  $attr        Extra data/HTML attributes merged onto the element.
     *                            Recognized keys:
     *                              - `plugin`  Plugin name(s), default 'ax-submit'
     *                              - Any HTML attribute key → value pair
     */
    public function renderAxForm(
        string $path,
        string $label = '',
        string $class = '',
        string $description = '',
        array $attr = [],
    ): string {
        $plugin = $attr['plugin'] ?? 'ax-submit';
        unset($attr['plugin']);

        $defaults = [
            'data-bs-target'       => '#ax-form-modal',
            'data-modal-animation' => 'animate__fadeInUp',
        ];

        $extras = array_merge($defaults, $attr);

        $extraAttrs = '';
        foreach ($extras as $key => $value) {
            $extraAttrs .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars((string) $value));
        }

        $title = $description ? sprintf(' title="%s"', htmlspecialchars($description)) : '';

        return sprintf(
            '<a href="#" class="%s" data-ax-form="%s" data-ax-plugin="%s" data-uiv="bs5"%s%s>%s</a>',
            htmlspecialchars($class),
            htmlspecialchars($path),
            htmlspecialchars($plugin),
            $title,
            $extraAttrs,
            $label,
        );
    }

    /**
     * Render the required Bootstrap 5 modal container.
     *
     * Place this once in your base layout (before </body>):
     *
     * ```twig
     * {{ ax_form_modal() }}
     * ```
     *
     * The container IDs (`ax-form-modal`, `ax-form-content`) are relied upon
     * by the JavaScript AxForm class. Do not rename them unless you also
     * update the JS constants.
     */
    public function renderAxFormModal(): string
    {
        return <<<HTML
<div id="ax-form-modal" class="modal animate__animated animate__fadeInUp" aria-hidden="true" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div id="ax-form-content" class="modal-content"></div>
    </div>
</div>
HTML;
    }
}
