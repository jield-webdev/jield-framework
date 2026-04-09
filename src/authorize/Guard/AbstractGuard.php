<?php

/** @noinspection ALL */

declare(strict_types=1);

namespace Jield\Authorize\Guard;

use Jield\Authorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use Jield\Authorize\Provider\Rule\ProviderInterface as RuleProviderInterface;
use Psr\Container\ContainerInterface;
use Laminas\EventManager\AbstractListenerAggregate;
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

    /**
     * @param array $rules
     */
    public function __construct(array $rules, ContainerInterface $container)
    {
        $this->container = $container;
        foreach ($rules as $rule) {
            $rule['roles']  = (array)$rule['roles'];
            $rule['action'] = isset($rule['action']) ? (array)$rule['action'] : [null];
            foreach ($this->extractResourcesFromRule($rule) as $resource) {
                $this->rules[$resource] = ['roles' => (array)$rule['roles']];
                if (isset($rule['assertion'])) {
                    $this->rules[$resource]['assertion'] = $rule['assertion'];
                }
            }
        }
    }

    abstract protected function extractResourcesFromRule(array $rule);

    public function getResources(): array
    {
        $resources = [];
        $resources = array_keys($this->rules);

        return $resources;
    }

    public function getRules(): array
    {
        $rules = [];
        foreach ($this->rules as $resource => $ruleData) {
            $rule   = [];
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
