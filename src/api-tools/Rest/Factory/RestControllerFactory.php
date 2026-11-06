<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest\Factory;

use Jield\ApiTools\Hal\Collection;
use Jield\ApiTools\Rest\AbstractResourceListener;
use Jield\ApiTools\Rest\Resource;
use Jield\ApiTools\Rest\RestController;
use Laminas\EventManager\Event;
use Laminas\InputFilter\InputFilter;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Laminas\Stdlib\Parameters;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function class_exists;
use function in_array;
use function is_array;
use function is_string;
use function method_exists;
use function sprintf;

class RestControllerFactory implements AbstractFactoryInterface
{
    /**
     * Cache of canCreateServiceWithName lookups
     */
    protected array $lookupCache = [];

    #[Override]
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        if (array_key_exists(key: \Jield\Stdlib\Value::plainScalar($requestedName), array: $this->lookupCache)) {
            return \Jield\Stdlib\Value::bool($this->lookupCache[$requestedName]);
        }

        if (!$container->has('config') || !$container->has('EventManager')) {
            // Config and EventManager are required
            return false;
        }

        /** @var mixed $config */
        $config = $container->get('config');
        if (!isset($config['api-tools-rest']) || !is_array(value: $config['api-tools-rest'])) {
            $this->lookupCache[\Jield\Stdlib\Value::key($requestedName)] = false;
            return false;
        }

        $config = $config['api-tools-rest'];

        if (
            !isset($config[$requestedName])
            || !isset($config[$requestedName]['listener'])
            || !isset($config[$requestedName]['route_name'])
        ) {
            // Configuration, and specifically the listener and route_name
            // keys, is required
            $this->lookupCache[\Jield\Stdlib\Value::key($requestedName)] = false;
            return false;
        }

        if (
            !$container->has(\Jield\Stdlib\Value::string($config[$requestedName]['listener']))
            && !class_exists(class: \Jield\Stdlib\Value::string($config[$requestedName]['listener']))
        ) {
            // Service referenced by listener key is required
            $this->lookupCache[\Jield\Stdlib\Value::key($requestedName)] = false;
            throw new ServiceNotFoundException(message: sprintf(
                '%s requires that a valid "listener" service be specified for controller %s; no service found',
                __METHOD__,
                \Jield\Stdlib\Value::scalar($requestedName),
            ));
        }

        $this->lookupCache[\Jield\Stdlib\Value::key($requestedName)] = true;
        return true;
    }

    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RestController
    {
        /** @var mixed $config */
        $config = $container->get('config');
        /** @var mixed $config */
        $config = \Jield\Stdlib\Value::path($config, ['api-tools-rest', $requestedName]);

        /** @var AbstractResourceListener $listener */
        $listener = $container->has(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($config)['listener']))
            ? $container->get(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($config)['listener']))
            : \Jield\Stdlib\Value::instantiate(\Jield\Stdlib\Value::accessible($config)['listener']);


        $resourceIdentifiers = [$listener::class];
        if (isset($config['resource_identifiers'])) {
            if (!is_array(value: $config['resource_identifiers'])) {
                $config['resource_identifiers'] = \Jield\Stdlib\Value::toArray($config['resource_identifiers']);
            }

            $resourceIdentifiers = array_merge($resourceIdentifiers, $config['resource_identifiers']);
        }

        $events = \Jield\Stdlib\Service::get($container, \Laminas\EventManager\EventManagerInterface::class, 'EventManager');
        $events->setIdentifiers(\Jield\Stdlib\Value::strings($resourceIdentifiers));

        $listener->attach(events: $events);

        $resource = new Resource();
        $resource->setEventManager($events);

        /** @var mixed $identifier */
        $identifier = $requestedName;
        if (isset($config['identifier'])) {
            /** @var mixed $identifier */
            $identifier = $config['identifier'];
        }

        /** @var mixed $controllerClass */
        $controllerClass = $config['controller_class'] ?? RestController::class;
        $controller = \Jield\Stdlib\Value::instantiate($controllerClass, [$identifier]);

        if (!$controller instanceof RestController) {
            throw new ServiceNotCreatedException(message: sprintf(
                '"%s" must be an implementation of Jield\ApiTools\Rest\RestController',
                \Jield\Stdlib\Value::scalar($controllerClass),
            ));
        }

        $controller->setEventManager(eventManager: \Jield\Stdlib\Service::get($container, \Laminas\EventManager\EventManagerInterface::class, 'EventManager'));
        $controller->setResource(resource: $resource);
        $this->setControllerOptions(config: \Jield\Stdlib\Value::array($config), controller: $controller);

        if (isset($config['entity_class'])) {
            $listener->setEntityClass(className: \Jield\Stdlib\Value::string($config['entity_class']));
        }

        if (isset($config['collection_class'])) {
            $listener->setCollectionClass(className: \Jield\Stdlib\Value::string($config['collection_class']));
        }

        return $controller;
    }

    /**
     * Loop through configuration to discover and set controller options.
     *
     */
    protected function setControllerOptions(array $config, RestController $controller): void
    {
        /** @var mixed $value */
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'collection_http_methods':
                    $controller->setCollectionHttpMethods(methods: \Jield\Stdlib\Value::array($value));
                    break;

                case 'collection_name':
                    $controller->setCollectionName(name: \Jield\Stdlib\Value::string($value));
                    break;

                case 'collection_query_whitelist':
                    if (is_string(value: $value)) {
                        $value = (array) $value;
                    }

                    if (!is_array(value: $value)) {
                        break;
                    }

                    // Create a listener that checks the query string against
                    // the whitelisted query parameters in order to seed the
                    // collection route options.
                    $whitelist = $value;
                    $controller->getEventManager()->attach(eventName: 'getList.pre', listener: static function (Event $e) use (
                        $whitelist,
                    ): void {
                        $controller = $e->getTarget();
                        if (!$controller instanceof RestController) {
                            return;
                        }

                        $resource = $controller->getResource();
                        if (!$resource instanceof Resource) {
                            // ResourceInterface does not define setQueryParams, so we need
                            // specifically a Resource instance
                            return;
                        }

                        $request = $controller->getRequest();
                        if (!method_exists(object_or_class: $request, method: 'getQuery')) {
                            return;
                        }

                        /** @var mixed $query */
                        $query = $request->getQuery();
                        $params = new Parameters(values: []);

                        // If a query Input Filter exists, merge its keys with the query whitelist
                        $inputFilter = $resource->getInputFilter();
                        if ($inputFilter instanceof InputFilter) {
                            $whitelist = array_unique(array: array_merge(
                                $whitelist,
                                array_keys(array: \Jield\Stdlib\Value::array($inputFilter->getInputs())),
                            ));
                        }

                        /** @var mixed $key
                         * @var mixed $value
                         */
                        foreach (\Jield\Stdlib\Value::iterable($query) as $key => $value) {
                            if (!in_array(needle: $key, haystack: $whitelist, strict: true)) {
                                continue;
                            }

                            $params->set(name: $key, value: $value);
                        }

                        $resource->setQueryParams(params: $params);
                    });

                    $controller->getEventManager()->attach(eventName: 'getList.post', listener: static function (Event $e): void {
                        $controller = $e->getTarget();
                        if (!$controller instanceof RestController) {
                            return;
                        }

                        $resource = $controller->getResource();
                        if (!$resource instanceof Resource) {
                            // ResourceInterface does not define setQueryParams, so we need
                            // specifically a Resource instance
                            return;
                        }

                        /** @var mixed $collection */
                        $collection = $e->getParam(name: 'collection');
                        if (!$collection instanceof Collection) {
                            return;
                        }

                        $queryParams = $resource->getQueryParams();
                        $params = $queryParams instanceof Parameters ? $queryParams->getArrayCopy() : [];

                        // Set collection route options with the captured query whitelist, to
                        // ensure paginated links are generated correctly
                        $collection->setCollectionRouteOptions(options: [
                            'query' => $params,
                        ]);

                        // If no self link defined, set the options in the collection and return
                        $links = $collection->getLinks();
                        if (!$links->has(relation: 'self')) {
                            return;
                        }

                        // If self link is defined, but is not route-based, return
                        $self = $links->get(relation: 'self');
                        if (!$self instanceof \Jield\ApiTools\Hal\Link\Link) {
                            return;
                        }

                        if (!$self->hasRoute()) {
                            return;
                        }

                        // Otherwise, merge the query string parameters with
                        // the self link's route options
                        $self = $links->get(relation: 'self');
                        if (!$self instanceof \Jield\ApiTools\Hal\Link\Link) {
                            return;
                        }

                        $options = $self->getRouteOptions();
                        $self->setRouteOptions(options: array_merge($options, [
                            'query' => $params,
                        ]));
                    });
                    break;

                case 'entity_http_methods':
                    $controller->setEntityHttpMethods(methods: \Jield\Stdlib\Value::array($value));
                    break;

                    /**
                     * The identifierName is a property of the ancestor
                     * and is described by Laminas API Tools as route_identifier_name
                     */
                case 'route_identifier_name':
                    $controller->setIdentifierName(name: \Jield\Stdlib\Value::string($value));
                    break;

                case 'min_page_size':
                    $controller->setMinPageSize(count: \Jield\Stdlib\Value::int($value));
                    break;

                case 'page_size':
                    $controller->setPageSize(count: (int) $value);
                    break;

                case 'max_page_size':
                    $controller->setMaxPageSize(count: (int) $value);
                    break;

                case 'page_size_param':
                    $controller->setPageSizeParam(param: \Jield\Stdlib\Value::string($value));
                    break;

                case 'route_name':
                    $controller->setRoute(route: \Jield\Stdlib\Value::string($value));
                    break;
            }
        }
    }
}
