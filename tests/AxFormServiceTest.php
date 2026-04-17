<?php

namespace Fire1\AxFormBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Fire1\AxFormBundle\Service\AxFormService;
use Fire1\AxFormBundle\Service\AxStepsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class DummyEntity
{
    public $id;

    public function getId(): ?int
    {
        return $this->id;
    }
}

class AxFormServiceTest extends TestCase
{
    private ?RouterInterface $router;
    private ?RequestStack $requestStack;
    private ?ManagerRegistry $doctrine;
    private ?FormFactoryInterface $formFactory;
    private ?Environment $twig;
    private ?EntityManagerInterface $entityManager;
    private ?Request $request;
    private ?Session $session;
    private ?FlashBagInterface $flashBag;
    private ?AxFormService $service;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->request = new Request();
        $this->session = $this->createMock(Session::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);

        $this->requestStack->method('getCurrentRequest')->willReturn($this->request);
        $this->requestStack->method('getSession')->willReturn($this->session);
        $this->session->method('getFlashBag')->willReturn($this->flashBag);
        $this->doctrine->method('getManager')->willReturn($this->entityManager);

        $this->service = new AxFormService(
            $this->router,
            $this->requestStack,
            $this->doctrine,
            $this->formFactory,
            $this->twig
        );
    }


    public function testFormInitializesNewEntity()
    {
        $this->service->form(DummyEntity::class, 'test-item');

        $this->assertInstanceOf(DummyEntity::class, $this->service->getEntity());
        $this->assertEquals(0, $this->service->getId());
    }

    public function testFormLoadsExistingEntity()
    {
        $this->request->query->set('id', 123);
        $entity = new DummyEntity();
        $entity->id = 123;

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($entity);

        $this->entityManager->method('getRepository')
            ->with(DummyEntity::class)
            ->willReturn($repository);

        $this->service->form(DummyEntity::class, 'test-item');

        $this->assertSame($entity, $this->service->getEntity());
        $this->assertEquals(123, $this->service->getId());
    }

    public function testCreateWithFormType()
    {
        $form = $this->createMock(FormInterface::class);
        $this->formFactory->expects($this->once())
            ->method('create')
            ->with('SomeFormType', $this->isInstanceOf(DummyEntity::class))
            ->willReturn($form);

        $this->service->form(DummyEntity::class);
        $this->service->create('SomeFormType');

        $this->assertSame($form, $this->service->getForm());
    }

    public function testRecordSuccess()
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new DummyEntity());

        $this->formFactory->method('create')->willReturn($form);
        $this->service->form(DummyEntity::class)->create('SomeFormType');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->flashBag->expects($this->once())
            ->method('set')
            ->with('record-ok');

        $result = $this->service->record();
        $this->assertTrue($result);
    }

    public function testRecordFailure()
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(false);
        
        $errors = $this->createMock(FormErrorIterator::class);
        $errors->method('valid')->willReturn(false);
        
        $form->method('getErrors')->willReturn($errors);

        $this->formFactory->method('create')->willReturn($form);
        $this->service->form(DummyEntity::class)->create('SomeFormType');

        $this->entityManager->expects($this->never())->method('persist');

        $this->flashBag->expects($this->once())
            ->method('set')
            ->with('record-er');

        $result = $this->service->record();
        $this->assertFalse($result);
    }

    public function testResponseRendersFormOnGet()
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($this->createMock(FormView::class));

        $this->formFactory->method('create')->willReturn($form);
        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>form</html>');

        $this->service->form(DummyEntity::class, 'keyword');
        $response = $this->service->do('SomeFormType');

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
        $this->assertEquals('<html>form</html>', $response->getContent());
    }

    public function testResponseHandlesValidationHeader()
    {
        $this->request->setMethod('POST');
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->headers->set('X-Form-validation', '1');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn($this->createMock(FormView::class));

        $this->formFactory->method('create')->willReturn($form);
        $this->twig->method('render')->willReturn('errors');

        $this->service->form(DummyEntity::class, 'keyword');
        $response = $this->service->do('SomeFormType');

        $this->assertEquals(207, $response->getStatusCode());
    }

    public function testRedirectByReferer()
    {
        $this->request->headers->set('referer', '/previous-page');

        $response = $this->service->redirectByReferer();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/previous-page', $response->getTargetUrl());
    }

    public function testAxStepsServiceBasicFlow()
    {
        $this->request->setMethod('POST');
        $this->request->request->set('_dummy', '1');

        $sessionData = [];
        $this->session->method('set')->willReturnCallback(function ($key, $val) use (&$sessionData) {
            $sessionData[$key] = $val;
        });
        $this->session->method('get')->willReturnCallback(function ($key, $default = null) use (&$sessionData) {
            return $sessionData[$key] ?? $default;
        });
        $this->session->method('has')->willReturnCallback(function ($key) use (&$sessionData) {
            return array_key_exists($key, $sessionData);
        });

        $steps = new AxStepsService($this->requestStack);

        $step1Called = 0;
        $steps->add(function ($s) use (&$step1Called) {
            $step1Called++;

            return null; // null means proceed
        });

        $step2Called = 0;
        $steps->add(function ($s) use (&$step2Called) {
            $step2Called++;

            return new \Symfony\Component\HttpFoundation\Response('step2');
        });

        $response = $steps->render();

        $this->assertEquals(1, $step1Called);
        $this->assertEquals(1, $step2Called);
        $this->assertEquals('step2', $response->getContent());
    }

    public function testEraseRow()
    {
        $controller = new class extends \Fire1\AxFormBundle\Controller\AbstractAxFormController {
            public function testEraseRow(string $entity, ?string $redirect = null)
            {
                return $this->eraseRow($entity, $redirect);
            }
        };

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['request_stack', 1, $this->requestStack],
            [\Doctrine\ORM\EntityManagerInterface::class, 1, $this->entityManager],
        ]);
        $controller->setContainer($container);

        $this->request->query->set('erase', '123');
        $this->entityManager->expects($this->once())->method('getReference')->with(DummyEntity::class, '123')->willReturn(new DummyEntity());
        $this->entityManager->expects($this->once())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $controller->testEraseRow(DummyEntity::class, '/redirect');
        
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/redirect', $response->getTargetUrl());
    }

    public function testHandleValidationInfoSuccess()
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $this->formFactory->method('create')->willReturn($form);

        $this->service->form(DummyEntity::class)->create('SomeType');
        $this->flashBag->expects($this->once())->method('set')->with('record-ok');

        $this->assertTrue($this->service->handleValidationInfo());
    }

    public function testHandleValidationInfoFailure()
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(false);
        $errors = $this->createMock(FormErrorIterator::class);
        $errors->method('valid')->willReturn(false);
        $form->method('getErrors')->willReturn($errors);
        $this->formFactory->method('create')->willReturn($form);

        $this->service->form(DummyEntity::class)->create('SomeType');
        $this->flashBag->expects($this->once())->method('set')->with('record-er');

        $this->assertFalse($this->service->handleValidationInfo());
    }

    public function testHandleCacheRegionSuccess()
    {
        $cache = $this->createMock(\Doctrine\ORM\Cache::class);
        $region = $this->createMock(\Doctrine\ORM\Cache\Region::class);
        
        $this->entityManager->method('getCache')->willReturn($cache);
        $cache->method('getEntityCacheRegion')->willReturn($region);
        $region->expects($this->once())->method('evictAll');

        $this->service->form(new DummyEntity());
        $this->assertTrue($this->service->handleCacheRegion());
    }

    public function testSettersAndGetters()
    {
        $this->service->setInfoTop('Top Title', 'Top Content');
        $this->service->setInfoBot('Bot Title', 'Bot Content');
        $this->service->setAxNextSubmit(true);
        $this->service->setModalSize(AxFormService::SizeWide);
        $this->service->btnSaveLabel('Save Label');
        $this->service->btnCloseLabel('Close Label');

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($this->createMock(FormView::class));
        $this->formFactory->method('create')->willReturn($form);

        $this->service->form(DummyEntity::class, 'Item');
        $this->service->create('Type');
        
        $data = $this->service->getTemplateData();
        
        $this->assertEquals(['title' => 'Top Title', 'content' => 'Top Content'], $data['infoTop']);
        $this->assertEquals(['title' => 'Bot Title', 'content' => 'Bot Content'], $data['infoBot']);
        $this->assertTrue($data['ax_next_submit']);
        $this->assertEquals(AxFormService::SizeWide, $data['modal_size']);
        $this->assertEquals('Save Label', $data['button_label']);
        $this->assertEquals('Close Label', $data['button_close']);
    }
}
