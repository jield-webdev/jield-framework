<?php

declare(strict_types=1);

namespace Jield\ApiTools;

use Jield\ApiTools\ApiProblem\Factory\ApiProblemListenerFactory;
use Jield\ApiTools\ApiProblem\Factory\ApiProblemRendererFactory;
use Jield\ApiTools\ApiProblem\Factory\ApiProblemStrategyFactory;
use Jield\ApiTools\ApiProblem\Factory\RenderErrorListenerFactory;
use Jield\ApiTools\ApiProblem\Factory\SendApiProblemResponseListenerFactory;
use Jield\ApiTools\ApiProblem\Listener\ApiProblemListener;
use Jield\ApiTools\ApiProblem\Listener\RenderErrorListener;
use Jield\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\ApiProblem\View\ApiProblemStrategy;
use Jield\ApiTools\ContentNegotiation\AcceptFilterListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\ContentTypeFilterListener;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\BodyParam;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\BodyParams;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\QueryParam;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\QueryParams;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\RouteParam;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\RouteParams;
use Jield\ApiTools\ContentNegotiation\Factory\AcceptFilterListenerFactory;
use Jield\ApiTools\ContentNegotiation\Factory\ContentNegotiationOptionsFactory;
use Jield\ApiTools\ContentNegotiation\Factory\ContentTypeFilterListenerFactory;
use Jield\ApiTools\ContentNegotiation\Factory\HttpMethodOverrideListenerFactory;
use Jield\ApiTools\ContentNegotiation\Factory\RenameUploadFilterFactory;
use Jield\ApiTools\ContentNegotiation\Factory\UploadFileValidatorFactory;
use Jield\ApiTools\ContentNegotiation\HttpMethodOverrideListener;
use Jield\ApiTools\ContentValidation\ContentValidationListener;
use Jield\ApiTools\ContentValidation\ContentValidationListenerFactory;
use Jield\ApiTools\ContentValidation\InputFilter\InputFilterPlugin;
use Jield\ApiTools\Hal\Extractor\LinkCollectionExtractor;
use Jield\ApiTools\Hal\Extractor\LinkExtractor;
use Jield\ApiTools\Hal\Factory\HalConfigFactory;
use Jield\ApiTools\Hal\Factory\HalControllerPluginFactory;
use Jield\ApiTools\Hal\Factory\HalJsonRendererFactory;
use Jield\ApiTools\Hal\Factory\HalJsonStrategyFactory;
use Jield\ApiTools\Hal\Factory\HalViewHelperFactory;
use Jield\ApiTools\Hal\Factory\LinkCollectionExtractorFactory;
use Jield\ApiTools\Hal\Factory\LinkExtractorFactory;
use Jield\ApiTools\Hal\Factory\LinkUrlBuilderFactory;
use Jield\ApiTools\Hal\Factory\MetadataMapFactory;
use Jield\ApiTools\Hal\Factory\RendererOptionsFactory;
use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Jield\ApiTools\Hal\Metadata\MetadataMap;
use Jield\ApiTools\Hal\Plugin\Hal;
use Jield\ApiTools\Hal\RendererOptions;
use Jield\ApiTools\Hal\View\HalJsonRenderer;
use Jield\ApiTools\Hal\View\HalJsonStrategy;
use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationPostListener;
use Jield\ApiTools\MvcAuth\Authorization\AclAuthorization;
use Jield\ApiTools\MvcAuth\Authorization\AuthorizationInterface;
use Jield\ApiTools\MvcAuth\Authorization\DefaultAuthorizationListener;
use Jield\ApiTools\MvcAuth\Authorization\DefaultAuthorizationPostListener;
use Jield\ApiTools\MvcAuth\Authorization\DefaultResourceResolverListener;
use Jield\ApiTools\MvcAuth\Factory\AclAuthorizationFactory;
use Jield\ApiTools\MvcAuth\Factory\AuthenticationAdapterDelegatorFactory;
use Jield\ApiTools\MvcAuth\Factory\AuthenticationServiceFactory;
use Jield\ApiTools\MvcAuth\Factory\DefaultAuthenticationListenerFactory;
use Jield\ApiTools\MvcAuth\Factory\DefaultAuthHttpAdapterFactory;
use Jield\ApiTools\MvcAuth\Factory\DefaultAuthorizationListenerFactory;
use Jield\ApiTools\MvcAuth\Factory\DefaultResourceResolverListenerFactory;
use Jield\ApiTools\MvcAuth\Factory\FileResolverFactory;
use Jield\ApiTools\MvcAuth\Factory\NamedOAuth2ServerFactory;
use Jield\ApiTools\MvcAuth\Identity\IdentityPlugin;
use Jield\ApiTools\OAuth2\Adapter\PdoAdapter;
use Jield\ApiTools\OAuth2\Factory\AuthControllerFactory;
use Jield\ApiTools\OAuth2\Factory\PdoAdapterFactory;
use Jield\ApiTools\OAuth2\Provider\UserId\AuthenticationService;
use Jield\ApiTools\Rest\Factory\RestControllerFactory;
use Jield\ApiTools\Rest\Listener\RestParametersListener;
use Jield\ApiTools\Rpc\Factory\OptionsListenerFactory;
use Jield\ApiTools\Rpc\Factory\RpcControllerFactory;
use Jield\ApiTools\Rpc\OptionsListener;
use Jield\ApiTools\Versioning\AcceptListener;
use Jield\ApiTools\Versioning\ContentTypeListener;
use Jield\ApiTools\Versioning\Factory\AcceptListenerFactory;
use Jield\ApiTools\Versioning\Factory\ContentTypeListenerFactory;
use Jield\ApiTools\Versioning\VersionListener;
use Laminas\Authentication\Adapter\Http;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\Filter\File\RenameUpload;
use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Validator\File\UploadFile;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'service_manager' => $this->getServiceMangerConfig(),
            'controller_plugins' => $this->getControllerPluginConfig(),
            'controllers' => $this->getControllerConfig(),
            'filters' => $this->getFiltersConfig(),
            'validators' => $this->getValidatorsConfig(),
            'input_filters' => $this->getInputFiltersConfig(),
            'view_helpers' => $this->getViewHelpersConfig(),
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'aliases' => [
                'routeParam' => RouteParam::class,
                'queryParam' => QueryParam::class,
                'bodyParam' => BodyParam::class,
                'routeParams' => RouteParams::class,
                'queryParams' => QueryParams::class,
                'bodyParams' => BodyParams::class,
                'getInputFilter' => InputFilterPlugin::class,
                'Hal' => Hal::class,
                'getIdentity' => IdentityPlugin::class,
            ],
            'factories' => [
                RouteParam::class => InvokableFactory::class,
                QueryParam::class => InvokableFactory::class,
                BodyParam::class => InvokableFactory::class,
                RouteParams::class => InvokableFactory::class,
                QueryParams::class => InvokableFactory::class,
                BodyParams::class => InvokableFactory::class,
                InputFilterPlugin::class => InvokableFactory::class,
                Hal::class => HalControllerPluginFactory::class,
                IdentityPlugin::class => InvokableFactory::class,
            ],
        ];
    }

    public function getFiltersConfig(): array
    {
        return [
            'factories' => [
                // Overwrite RenameUpload filter's factory
                RenameUpload::class => RenameUploadFilterFactory::class,
            ],
        ];
    }

    public function getInputFiltersConfig(): array
    {
        return [
            'abstract_factories' => [
                InputFilterAbstractServiceFactory::class,
            ],
        ];
    }

    public function getValidatorsConfig(): array
    {
        return [
            'factories' => [
                // Overwrite UploadFile validator's factory
                UploadFile::class => UploadFileValidatorFactory::class,
            ],
        ];
    }

    public function getServiceMangerConfig(): array
    {
        return [
            'aliases' => [
                'authentication' => 'Jield\ApiTools\MvcAuth\Authentication',
                'authorization' => AuthorizationInterface::class,
                AuthorizationInterface::class => AclAuthorization::class,
                'Jield\ApiTools\OAuth2\Provider\UserId' => AuthenticationService::class,
            ],
            'delegators' => [
                DefaultAuthenticationListener::class => [
                    AuthenticationAdapterDelegatorFactory::class,
                ],
            ],
            'invokables' => [
                RestParametersListener::class => RestParametersListener::class,
            ],
            'factories' => [
                \Jield\ApiTools\OAuth2\EntraJwksProvider::class =>
                    \Jield\ApiTools\OAuth2\Factory\EntraJwksProviderFactory::class,
                \Jield\ApiTools\OAuth2\EntraOnBehalfOfClient::class =>
                    \Jield\ApiTools\OAuth2\Factory\EntraOnBehalfOfClientFactory::class,
                \Jield\ApiTools\OAuth2\EntraTokenValidator::class =>
                    \Jield\ApiTools\OAuth2\Factory\EntraTokenValidatorFactory::class,
                \Jield\ApiTools\OAuth2\TokenExchange::class =>
                    \Jield\ApiTools\OAuth2\Factory\TokenExchangeFactory::class,

                \Jield\ApiTools\Rest\Listener\OptionsListener::class =>
                    \Jield\ApiTools\Rest\Factory\OptionsListenerFactory::class,
                OptionsListener::class => OptionsListenerFactory::class,

                PdoAdapter::class => PdoAdapterFactory::class,
                AuthenticationService::class =>
                    \Jield\ApiTools\OAuth2\Provider\UserId\AuthenticationServiceFactory::class,
                //                'Jield\ApiTools\OAuth2\Service\OAuth2Server'                        => \Jield\ApiTools\OAuth2\Factory\OAuth2ServerFactory::class,

                MvcAuth\UnauthenticatedListener::class => InvokableFactory::class,
                MvcAuth\UnauthorizedListener::class => InvokableFactory::class,

                ApiProblemListener::class => ApiProblemListenerFactory::class,
                RenderErrorListener::class => RenderErrorListenerFactory::class,
                SendApiProblemResponseListener::class => SendApiProblemResponseListenerFactory::class,
                ApiProblemRenderer::class => ApiProblemRendererFactory::class,
                ApiProblemStrategy::class => ApiProblemStrategyFactory::class,

                \Jield\ApiTools\ContentNegotiation\ContentTypeListener::class =>
                    \Laminas\ServiceManager\Factory\InvokableFactory::class,
                \Jield\ApiTools\ContentNegotiation\AcceptListener::class =>
                    \Jield\ApiTools\ContentNegotiation\Factory\AcceptListenerFactory::class,
                AcceptFilterListener::class => AcceptFilterListenerFactory::class,
                ContentTypeFilterListener::class => ContentTypeFilterListenerFactory::class,
                ContentNegotiationOptions::class => ContentNegotiationOptionsFactory::class,
                HttpMethodOverrideListener::class => HttpMethodOverrideListenerFactory::class,

                ContentValidationListener::class => ContentValidationListenerFactory::class,

                LinkExtractor::class => LinkExtractorFactory::class,
                LinkCollectionExtractor::class => LinkCollectionExtractorFactory::class,
                'Jield\ApiTools\Hal\HalConfig' => HalConfigFactory::class,
                HalJsonRenderer::class => HalJsonRendererFactory::class,
                HalJsonStrategy::class => HalJsonStrategyFactory::class,
                LinkUrlBuilder::class => LinkUrlBuilderFactory::class,
                MetadataMap::class => MetadataMapFactory::class,
                RendererOptions::class => RendererOptionsFactory::class,

                'Jield\ApiTools\MvcAuth\Authentication' => AuthenticationServiceFactory::class,
                'Jield\ApiTools\MvcAuth\FileResolver' => FileResolverFactory::class,
                DefaultAuthenticationListener::class => DefaultAuthenticationListenerFactory::class,
                Http::class => DefaultAuthHttpAdapterFactory::class,
                AclAuthorization::class => AclAuthorizationFactory::class,
                DefaultAuthorizationListener::class => DefaultAuthorizationListenerFactory::class,
                DefaultResourceResolverListener::class => DefaultResourceResolverListenerFactory::class,
                'Jield\ApiTools\OAuth2\Service\OAuth2Server' => NamedOAuth2ServerFactory::class,
                NonPersistent::class => InvokableFactory::class,
                DefaultAuthenticationPostListener::class => InvokableFactory::class,
                DefaultAuthorizationPostListener::class => InvokableFactory::class,

                AcceptListener::class => AcceptListenerFactory::class,
                ContentTypeListener::class => ContentTypeListenerFactory::class,
                VersionListener::class => InvokableFactory::class,
            ],
        ];
    }

    private function getViewHelpersConfig(): array
    {
        return [
            'factories' => [
                'Hal' => HalViewHelperFactory::class,
            ],
        ];
    }

    private function getControllerConfig(): array
    {
        return [
            'factories' => [
                \Jield\ApiTools\OAuth2\Controller\AuthController::class => AuthControllerFactory::class,
            ],
            'abstract_factories' => [
                RestControllerFactory::class,
                RpcControllerFactory::class,
            ],
        ];
    }
}
