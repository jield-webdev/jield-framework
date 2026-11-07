<?php

/** @noinspection ALL */

declare(strict_types=1);

namespace Jield\Authorize\Guard;

use Jield\Authorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use Jield\Authorize\Provider\Rule\ProviderInterface as RuleProviderInterface;
use Laminas\EventManager\AbstractListenerAggregate;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_keys;

abstract class AbstractGuard extends AbstractListenerAggregate implements
    GuardInterface,
    RuleProviderInterface,
    ResourceProviderInterface
{
    /** @var ContainerInterface */
    protected $container;

    /** @var array[] */
    protected $rules = [];

    public function __construct(array $rules, ContainerInterface $container)
    {
        $this->container = $container;
        /** @mago-var mixed $rule */
        foreach ($rules as $rule) {
            \Jield\Stdlib\Value::setPath($rule, ['roles'], \Jield\Stdlib\Value::toArray(\Jield\Stdlib\Value::accessible($rule)['roles']));
            \Jield\Stdlib\Value::setPath(
                $rule,
                ['action'],
                isset($rule['action']) ? \Jield\Stdlib\Value::toArray($rule['action']) : [null],
            );
            /** @mago-var mixed $resource */
            foreach (Assert::isIterable($this->extractResourcesFromRule(Assert::isArray($rule))) as $resource) {
                $this->rules[\Jield\Stdlib\Value::key($resource)] = [
                    'roles' => \Jield\Stdlib\Value::toArray($rule['roles']),
                ];
                if (isset($rule['assertion'])) {
                    $this->rules[\Jield\Stdlib\Value::key($resource)]['assertion'] = $rule['assertion'];
                }
            }
        }
    }

    abstract protected function extractResourcesFromRule(array $rule);

    public function getResources(): array
    {
        return array_map(strval(...), array_keys($this->rules));
    }

    public function getRules(): array
    {
        $rules = [];
        foreach ($this->rules as $resource => $ruleData) {
            $rule = [];
            $rule[] = $ruleData['roles'];
            $rule[] = $resource;
            if (isset($ruleData['assertion'])) {
                $rule[] = null;
                // no privilege
                $rule[] = $ruleData['assertion'];
            }

            $rules[] = $rule;
        }

        return ['allow' => $rules];
    }
}
