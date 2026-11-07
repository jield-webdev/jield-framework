<?php

declare(strict_types=1);

namespace JieldTest\Integration;

use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Jield\Bootstrap5\Form\View\Helper\FormDescription;
use Jield\Bootstrap5\Form\View\Helper\FormElement;
use Jield\Bootstrap5\Form\View\Helper\FormInput;
use Jield\Form\Element\Submit;
use Jield\Form\Element\Text;
use Jield\Form\Factory;
use Jield\Form\FormElementManager;
use Jield\Form\FormElementManagerFactory;
use Jield\Form\View\Helper\FormElementErrors;
use Jield\Form\View\Helper\FormLabel;
use Jield\GoogleAnalytics\Analytics\Tracker;
use Jield\GoogleAnalytics\Service\GoogleAnalyticsFactory;
use Jield\GoogleAnalytics\Service\ScriptFactory;
use Jield\GoogleAnalytics\Service\TrackerFactory;
use Jield\GoogleAnalytics\View\Helper\Script\Gajs;
use Jield\ModuleManager\Listener\ConfigListener;
use Jield\ModuleManager\Listener\ServiceListener;
use Jield\ModuleManager\ModuleEvent;
use Jield\Mvc\Navigation\View\Navigation;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Value;
use Laminas\Escaper\Escaper;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\Doctype;
use Laminas\ServiceManager\Exception\CyclicAliasException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\HeadScript;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer as NativeRenderer;
use Laminas\View\Resolver\TemplateMapResolver;
use PHPUnit\Framework\TestCase;

final class RuntimeCompatibilityTest extends TestCase
{
    public function testModuleConfigurationOverridesServicesInNamedAndDirectPluginManagers(): void
    {
        foreach ([false, true] as $pluginManager) {
            foreach ([false, true] as $named) {
                $services = new ServiceManager();
                $manager = $pluginManager ? new FormElementManager($services) : new ServiceManager();
                $manager->setService('entry', new Text('old'));
                $manager->get('entry');
                $replacement = new Text('new');
                $services->setService('manager', $manager);
                $listener = new ServiceListener($services);
                $listener->addServiceManager($named ? 'manager' : $manager, 'elements', '', 'getElements');
                $config = new ConfigListener();
                $config->setMergedConfig(['elements' => ['services' => ['entry' => $replacement]]]);
                $event = new ModuleEvent();
                $event->setConfigListener($config);

                $listener->onLoadModulesPost($event);

                self::assertSame($replacement, $manager->get('entry'));
                self::assertFalse($manager->getAllowOverride());
            }
        }
    }

    public function testFailedPluginConfigurationRestoresOverrideProtection(): void
    {
        $services = new ServiceManager();
        $manager = new FormElementManager($services);
        $listener = new ServiceListener($services);
        $listener->addServiceManager($manager, 'elements', '', 'getElements');
        $config = new ConfigListener();
        $config->setMergedConfig(['elements' => ['aliases' => ['loop' => 'loop']]]);
        $event = new ModuleEvent();
        $event->setConfigListener($config);

        try {
            $listener->onLoadModulesPost($event);
            self::fail('Cyclic aliases must be rejected.');
        } catch (CyclicAliasException) {
            self::assertFalse($manager->getAllowOverride());
        }
    }

    public function testFormFactoriesAcceptOmittedOptionsAndRegisteredElementAliases(): void
    {
        $services = new ServiceManager();
        $manager = new FormElementManagerFactory()($services, FormElementManager::class);
        self::assertInstanceOf(Text::class, $manager->get('text'));
        $manager->configure(['aliases' => ['save' => 'submit']]);
        $element = new Factory($manager)->create(['name' => 'save', 'type' => 'save']);
        self::assertInstanceOf(Submit::class, $element);
        self::assertSame('save', $element->getName());
    }

    public function testConfiguredClassesKeepNativeConstructorBehavior(): void
    {
        self::assertInstanceOf(MappingDriverChain::class, Value::instantiate(MappingDriverChain::class, [[]]));
        $class = new class ('initial') {
            public function __construct(public string $name)
            {
            }
        };
        self::assertSame('configured', Value::instantiate($class, ['name' => 'configured'])->name);
    }

    public function testNavigationCanBeConfiguredBeforeItsRendererIsInjected(): void
    {
        $services = new ServiceManager();
        $navigation = new Navigation();
        $navigation->setServiceLocator($services);
        $manager = $navigation->getPluginManager();
        $helpers = new HelperPluginManager($services);
        $renderer = $this->renderer($helpers);
        $navigation->setView($renderer);

        self::assertSame($manager, $navigation->getPluginManager());
        self::assertSame($renderer, $manager->get('breadcrumbs')->getView());
    }

    public function testUnlabeledControlsRenderWithATranslator(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturnArgument(0);
        $label = new FormLabel();
        $label->setTranslator($translator);
        $services = [
            'formlabel' => $label,
            'escapehtml' => new EscapeHtml(new Escaper()),
            'lbs5formdescription' => new FormDescription(),
            'formelementerrors' => new FormElementErrors(),
            'lbs5forminput' => new FormInput(),
        ];
        $helpers = new HelperPluginManager(new ServiceManager(), ['services' => $services]);
        $renderer = $this->renderer($helpers);
        foreach ($services as $service) {
            if (method_exists($service, 'setView')) {
                $service->setView($renderer);
            }
        }
        $helper = new FormElement($helpers, $translator);
        $helper->setView($renderer);

        self::assertStringContainsString('name="q"', $helper(new Text('q'), FormElement::TYPE_ELEMENT_ONLY));
    }

    public function testAnalyticsFactoriesHandleMissingIdsAndHonorDisabledTracking(): void
    {
        $configurations = [[false, true, false], ['G-TEST', false, false], ['G-TEST', true, true]];
        foreach ($configurations as [$id, $enabled, $expected]) {
            $services = new ServiceManager([
                'services' => [
                    'config' => ['google_analytics' => ['id' => $id, 'enable' => $enabled]],
                    Gajs::class => new Gajs(),
                ],
                'factories' => [Tracker::class => TrackerFactory::class, ScriptFactory::class => ScriptFactory::class],
            ]);
            self::assertSame($expected, $services->get(Tracker::class)->isEnabled());
            $helper = new GoogleAnalyticsFactory()($services, 'googleAnalytics');
            $scripts = new HeadScript(new Escaper(), new Doctype());
            $helpers = new HelperPluginManager($services, ['services' => ['HeadScript' => $scripts]]);
            $helper->setView($this->renderer($helpers));
            $helper->appendScript();
            $helper->appendScript();

            self::assertSame($expected ? 2 : 0, substr_count((string) $scripts, '<script'));
            if ($expected) {
                self::assertStringContainsString('G-TEST', (string) $scripts);
            }
        }
    }

    private function renderer(HelperPluginManager $helpers): PhpRenderer
    {
        return new PhpRenderer(new NativeRenderer($helpers, new TemplateMapResolver()), $helpers);
    }
}
