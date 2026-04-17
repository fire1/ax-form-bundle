<?php

namespace Fire1\AxFormBundle\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Fire1\AxFormBundle\Service\AxFormService;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Twig\TemplateWrapper;

/**
 * Trait AxFormHelpTrait
 */
trait AxFormHelpTrait
{
    /**
     * @var array
     */
    protected array $formOptions = [];

    /**
     * @param array $options
     * @return $this
     */
    public function setFormOptions(array $options): static
    {
        $this->formOptions = $options;
        return $this;
    }

    /**
     * @param array $inputOptions
     * @return array
     */
    public function getFormOptions(array $inputOptions = []): array
    {
        return array_merge($this->formOptions, $inputOptions);
    }

    /**
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public static function isAxOrPost(Request $request): bool
    {
        return ('XMLHttpRequest' === $request->headers->get('X-Requested-With') || $request->isMethod("POST"));
    }

    /**
     * Clears ID (force insert)
     */
    public function clearId(): void
    {
        $this->id = 0;
    }

    /**
     * @return FormFactoryInterface
     */
    public function getFormFactory(): FormFactoryInterface
    {
        return self::$container->get('form.factory');
    }

    /**
     * @param $routeName
     * @param $routeParams
     * @return $this
     */
    public function setActionRoute($routeName, $routeParams = []): static
    {
        $this->actionRoute = $this->router->generate($routeName, $routeParams);
        return $this;
    }

    /**
     * @param string $color
     * @return $this
     */
    public function setColor(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function style(string $color): static
    {
        return $this->setColor($color);
    }

    /**
     * @param string $erase
     * @return $this
     */
    public function setErase(string $erase): static
    {
        $this->erase = $erase;
        return $this;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return self::$container;
    }

    /**
     * @return FormInterface|null
     */
    public function getForm(): ?FormInterface
    {
        return $this->form;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getManager(): EntityManagerInterface
    {
        return $this->manager;
    }

    /**
     * @return FlashBagInterface
     */
    public function getFlashBag(): FlashBagInterface
    {
        return $this->flash;
    }

    /**
     * @return object|null
     */
    public function getEntity(): ?object
    {
        return $this->entity;
    }

    /**
     * @param $fieldName
     * @return mixed|null
     */
    public function getSubmittedField($fieldName): mixed
    {
        if(isset($this->getForm()[$fieldName])) {
            return $this->getForm()[$fieldName]->getData();
        }
        return null;
    }

    /**
     * @param $fieldName
     * @return UploadedFile|null
     * @deprecated  use getSubmittedField
     */
    public function getUploadField($fieldName): ?UploadedFile
    {
        if(isset($this->getForm()[$fieldName])) {
            return $this->getForm()[$fieldName]->getData();
        }
        return null;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param $entity
     */
    public function setEntityNew($entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @param FormInterface $form
     */
    public function setFormNew(FormInterface $form): void
    {
        $this->form = $form;
    }

    /**
     * @param array $errors
     */
    public function setErrorMessages(array $errors): void
    {
        $this->flash->set('record-er', ['title' => 'Something went wrong', 'info' => $errors]);
    }

    /**
     * Forcing insert
     */
    public function forceInsert(): void
    {
        $this->forceInsert = true;
    }

    /**
     * @return object|null
     */
    public function getAssignedData(): ?object
    {
        return $this->assignedData;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->form->getData();
    }

    /**
     * @return Request
     */
    public static function getRequest(): Request
    {
        return self::$container->get('request_stack')->getCurrentRequest();
    }

    /**
     * Sets custom template
     * @param string $template The template name
     * @return $this
     */
    public function setTemplate(string $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSameRoute(): ?string
    {
        return $this->getRequest()->getRequestUri();
    }

    /**
     * Generates info bar at top
     * @param $title
     * @param null $content
     * @return $this
     */
    public function setInfoTop($title, $content = null): static
    {
        $this->infoTop = ['title' => $title, 'content' => $content];
        return $this;
    }

    /**
     * Generates info bar at bottom
     * @param $title
     * @param null $content
     * @return $this
     */
    public function setInfoBot($title, $content = null): static
    {
        $this->infoBot = ['title' => $title, 'content' => $content];
        return $this;
    }

    /**
     * Captures submitted data
     */
    public function captureData(): void
    {
        if($this->isCaptureData) return;
        $this->form->handleRequest($this->getRequest());
        $this->isCaptureData = true;
    }

    /**
     * Shortcut for create -> response
     * @param mixed $formClass
     * @param null|RedirectResponse|callable|string $routeName
     * @param array $routeParams
     * @return RedirectResponse|Response
     */
    public function do(mixed $formClass, mixed $routeName = null, array $routeParams = []): RedirectResponse|Response
    {
        return $this->create($formClass)->response($routeName, $routeParams);
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function push(callable $callback): static
    {
        $callback($this->getEntity());
        return $this;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function title(string $title): static
    {
        $this->setTitle($title);
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function btnSaveLabel(string $name): static
    {
        $this->btnLabelSave = ucfirst($name);
        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function btnCloseLabel($name): static
    {
        $this->btnLabelClose = ucfirst($name);
        return $this;
    }

    /**
     * @param array $option
     * @return $this
     */
    public function opt(array $option = []): static
    {
        $this->setFormOptions($option);
        return $this;
    }

    /**
     * @param string $template
     * @param array $params
     * @return $this
     */
    public function tpl(string $template, array $params = []): static
    {
        $this->setTemplate($template);
        $this->templateParams = array_merge($this->templateParams, $params);
        return $this;
    }

    /**
     * @return FormInterface|null
     */
    public function submit(): ?FormInterface
    {
        return $this->form;
    }

    /**
     * @return EntityManagerInterface
     */
    public function em(): EntityManagerInterface
    {
        return $this->manager;
    }

    /**
     * @return string|null
     */
    public function getReferer(): ?string
    {
        if($this->flash->has('past_referer'))
            return current($this->flash->get('past_referer'));

        return $this->getRequest()->headers->get('referer');
    }

    /**
     * @return RedirectResponse
     */
    public function redirectByReferer(): RedirectResponse
    {
        return new RedirectResponse($this->getReferer());
    }
}
