<?php

declare(strict_types=1);

namespace Jield\Bootstrap5;

use Jield\Bootstrap5\Event\InjectJavascriptAndCss;
use Jield\Bootstrap5\Form\View\Helper as FormHelper;
use Jield\Bootstrap5\Navigation\View\NavigationHelperFactory;
use Jield\Bootstrap5\View\Helper as ViewHelper;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HeadScript;
use Laminas\View\Helper\InlineScript;

final class ConfigProvider
{
    public function __invoke(): array
    {
        $bootstrapConfig = $this->getBootstrapConfig();

        return [
            'jield-bootstrap5' => $bootstrapConfig,
            'laminas-bootstrap5' => $bootstrapConfig,
            'view_helpers' => $this->getViewHelperConfig(),
            ConfigAbstractFactory::class => $this->getConfigAbstractFactory(),
            'service_manager' => $this->getServiceManagerConfig(),
            'dependencies' => $this->getServiceManagerConfig(),
            'asset_manager' => $this->getAssetManagerConfig(),
        ];
    }

    private function getBootstrapConfig(): array
    {
        return [
            'inject_jquery' => true,
            'inject_jquery_ui' => true,
            'inject_bootstrap_js' => true,
        ];
    }

    public function getViewHelperConfig(): array
    {
        return [
            'aliases' => [
                'filterbarelement' => 'lbs5filterbarelement',
                'initHighlightJs' => 'lbs5initHighlightJs',

                'lbs5navigation' => ViewHelper\Navigation::class,
                'lbs5initHighlightJs' => ViewHelper\InitHighlightJs::class,
                'lbs5filterbarelement' => FormHelper\FilterBarElement::class,
                'lbs5filtercolumnelement' => FormHelper\FilterColumnElement::class,
                'lbs5formelement' => FormHelper\FormElement::class,
                'lbs5formcheckbox' => FormHelper\FormCheckbox::class,
            ],
            'factories' => [
                ViewHelper\Navigation::class => NavigationHelperFactory::class,
                FormHelper\FormElement::class => ConfigAbstractFactory::class,
                FormHelper\FormCheckbox::class => ConfigAbstractFactory::class,
                FormHelper\FilterBarElement::class => ConfigAbstractFactory::class,
                FormHelper\FilterColumnElement::class => ConfigAbstractFactory::class,
                ViewHelper\InitHighlightJs::class => ConfigAbstractFactory::class,
            ],
            'invokables' => [
                'lbs5formdescription' => FormHelper\FormDescription::class,
                'lbs5formdatetimelocal' => FormHelper\FormDateTimeLocal::class,
                'lbs5forminput' => FormHelper\FormInput::class,
                'lbs5formmultislider' => FormHelper\FormMultiSlider::class,
                'lbs5formfile' => FormHelper\FormFile::class,
                'lbs5formradio' => FormHelper\FormRadio::class,
                'lbs5formtextarea' => FormHelper\FormTextarea::class,
                'lbs5formselect' => FormHelper\FormSelect::class,
                'lbs5formmulticheckbox' => FormHelper\FormMultiCheckbox::class,
                'lbs5alert' => ViewHelper\Alert::class,
            ],
        ];
    }

    public function getConfigAbstractFactory(): array
    {
        return [
            FormHelper\FormElement::class => [
                'ViewHelperManager',
                TranslatorInterface::class,
            ],
            FormHelper\FormCheckbox::class => [
                'ViewHelperManager',
                TranslatorInterface::class,
            ],
            FormHelper\FilterBarElement::class => [
                'ViewHelperManager',
                TranslatorInterface::class,
            ],
            FormHelper\FilterColumnElement::class => [
                'ViewHelperManager',
                TranslatorInterface::class,
            ],
            ViewHelper\InitHighlightJs::class => [
                HeadScript::class,
                HeadLink::class,
                InlineScript::class,
            ],
            InjectJavascriptAndCss::class => [
                'Config',
                PhpRenderer::class,
            ],
        ];
    }

    public function getServiceManagerConfig(): array
    {
        return [
            'factories' => [
                InjectJavascriptAndCss::class => ConfigAbstractFactory::class,
            ],
        ];
    }

    public function getAssetManagerConfig(): array
    {
        $publicPath = dirname(__DIR__, 2) . '/resources/bootstrap5/public';
        $cachePath = 'public';

        return [
            'resolver_configs' => [
                'collections' => [
                    'assets/js/jield-bootstrap5/main.js' => [
                        'main/form-submit.js',
                        'simple-load-more/jquery.simpleLoadMore.min.js',
                        'bootstrap-slider/bootstrap-slider.min.js',
                    ],
                    'assets/css/jield-bootstrap5/main.css' => [
                        'bootstrap-slider/css/bootstrap-slider.min.css',
                    ],
                    'assets/js/jield-bootstrap5/filter-column.js' => [
                        'filter-column/filter-column.js',
                    ],
                    'assets/css/jield-bootstrap5/filter-column.css' => [
                        'filter-column/filter-column.css',
                    ],
                    'assets/js/jield-bootstrap5/daterangepicker.js' => [
                        'date-range-picker/moment.min.js',
                        'date-range-picker/daterangepicker.js',
                        'main/form-datepicker.js',
                    ],
                    'assets/css/jield-bootstrap5/daterangepicker.css' => [
                        'date-range-picker/daterangepicker.css',
                    ],
                    'assets/js/jield-bootstrap5/codemirror.js' => [
                        'codemirror/codemirror.js',
                        'main/form-codemirror.js',
                        'codemirror/mode/xml/xml.js',
                        'codemirror/mode/htmlmixed/htmlmixed.js',
                        'codemirror/mode/css/css.js',
                        'codemirror/mode/javascript/javascript.js',
                        'codemirror/mode/sql/sql.js',
                        'codemirror/mode/markdown/markdown.js',
                        'codemirror/mode/twig/twig.js',
                    ],
                    'assets/css/jield-bootstrap5/codemirror.css' => [
                        'codemirror/codemirror.css',
                    ],
                    'assets/js/jield-bootstrap5/bootstrap-select.js' => [
                        'bootstrap-select-1.14-dev/dist/js/bootstrap-select.min.js',
                        'main/form-bootstrap-select.js',
                        'ajax-bootstrap-select/dist/js/ajax-bootstrap-select.min.js',
                    ],
                    'assets/css/jield-bootstrap5/bootstrap-select.css' => [
                        'bootstrap-select-1.14-dev/dist/css/bootstrap-select.min.css',
                        'ajax-bootstrap-select/dist/css/ajax-bootstrap-select.css',
                    ],
                    'assets/js/jield-bootstrap5/highlight.js' => [
                        'highlight.js/highlight.min.js',
                    ],
                    'assets/css/jield-bootstrap5/highlight.css' => [
                        'highlight.js/default.min.css',
                        'highlight.js/github.css',
                    ],
                    'assets/js/jield-bootstrap5/sortable.js' => [
                        'sortable-js/Sortable.min.js',
                    ],
                ],
                'paths' => [
                    $publicPath,
                ],
            ],
            'caching' => [
                'assets/js/jield-bootstrap5/main.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/css/jield-bootstrap5/main.css' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/js/jield-bootstrap5/filter-column.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/css/jield-bootstrap5/filter-column.css' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/js/jield-bootstrap5/daterangepicker.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/css/jield-bootstrap5/daterangepicker.css' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/js/jield-bootstrap5/codemirror.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/css/jield-bootstrap5/codemirror.css' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/js/jield-bootstrap5/bootstrap-select.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/css/jield-bootstrap5/bootstrap-select.css' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/js/jield-bootstrap5/highlight.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/css/jield-bootstrap5/highlight.css' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
                'assets/js/jield-bootstrap5/sortable.js' => [
                    'cache' => 'FilePath',
                    'options' => [
                        'dir' => $cachePath,
                    ],
                ],
            ],
        ];
    }
}
