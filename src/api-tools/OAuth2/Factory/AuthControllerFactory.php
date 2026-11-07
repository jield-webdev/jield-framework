<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\Controller\AuthController;
use Jield\ApiTools\OAuth2\Provider\UserId\UserIdProviderInterface;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OAuth2\Server as OAuth2Server;
use Override;
use Psr\Container\ContainerInterface;

class AuthControllerFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AuthController
    {
        $authController = new AuthController(
            serverFactory: $this->getOAuth2ServerFactory(container: $container),
            userIdProvider: Value::instance(
                $container->get('Jield\ApiTools\OAuth2\Provider\UserId'),
                UserIdProviderInterface::class,
            ),
        );

        $authController->setApiProblemErrorResponse(apiProblemErrorResponse: $this->marshalApiProblemErrorResponse(
            container: $container,
        ));

        return $authController;
    }

    /**
     * Retrieve the OAuth2\Server factory.
     *
     * For BC purposes, if the OAuth2Server service returns an actual
     * instance, this will wrap it in a closure before returning it.
     */
    private function getOAuth2ServerFactory(ContainerInterface $container): callable
    {
        $oauth2ServerFactory = $container->get('Jield\ApiTools\OAuth2\Service\OAuth2Server');
        if (!$oauth2ServerFactory instanceof OAuth2Server) {
            return Value::callback($oauth2ServerFactory);
        }

        return static fn() => $oauth2ServerFactory;
    }

    /**
     * Determine whether or not to render API Problem error responses.
     */
    private function marshalApiProblemErrorResponse(ContainerInterface $container): bool
    {
        if (!$container->has('config')) {
            return false;
        }

        $config = $container->get('config');

        return (
            isset($config['api-tools-oauth2']['api_problem_error_response'])
            && $config['api-tools-oauth2']['api_problem_error_response'] === true
        );
    }
}
