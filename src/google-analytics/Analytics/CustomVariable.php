<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Analytics;

use Jield\GoogleAnalytics\Exception\InvalidArgumentException;

class CustomVariable
{
    public const int SCOPE_VISITOR = 1;

    public const int SCOPE_SESSION = 2;

    public const int SCOPE_PAGE_LEVEL = 3;

    protected int $scope;

    public function __construct(
        protected int $index,
        protected string $name,
        protected string $value,
        int $scope = self::SCOPE_PAGE_LEVEL,
    ) {
        $this->setScope(scope: $scope);
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): CustomVariable
    {
        $this->index = $index;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): CustomVariable
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): CustomVariable
    {
        $this->value = $value;
        return $this;
    }

    public function setScope(int $scope): CustomVariable
    {
        $allowed = [self::SCOPE_VISITOR, self::SCOPE_SESSION, self::SCOPE_PAGE_LEVEL];

        if (!in_array(needle: $scope, haystack: $allowed, strict: true)) {
            throw new InvalidArgumentException(message: sprintf('Invalid value given for scope. Acceptable values are: %s.', implode(
                separator: ', ',
                array: $allowed,
            )));
        }

        $this->scope = $scope;

        return $this;
    }

    public function getScope(): int
    {
        return $this->scope;
    }
}
