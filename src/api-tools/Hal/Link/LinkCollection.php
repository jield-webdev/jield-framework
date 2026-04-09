<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Jield\ApiTools\ApiProblem\Exception;
use Override;
use Psr\Link\LinkInterface;
use ReturnTypeWillChange;

use function array_diff;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function count;
use function get_class;
use function gettype;
use function in_array;
use function is_array;
use function is_object;
use function sprintf;

/**
 * Object describing a collection of link relations
 */
class LinkCollection implements Countable, IteratorAggregate
{
    /** @var array<mixed> */
    protected array $links = [];

    /**
     * Return a count of link relations
     *
     * @return int
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function count(): int
    {
        return count(value: $this->links);
    }

    /**
     * Retrieve internal iterator
     *
     * @return ArrayIterator
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(array: $this->links);
    }

    /**
     * Add a link
     *
     * @param  bool $overwrite
     * @throws Exception\DomainException
     *@deprecated Since 1.5.0; use idempotentAdd() for PSR-13 and RFC 5988 compliance.
     *
     */
    public function add(Link $link, bool $overwrite = false): static
    {
        $relation = $link->getRelation();
        if (! isset($this->links[$relation]) || $overwrite || 'self' === $relation) {
            $this->links[$relation] = $link;
            return $this;
        }

        if ($this->links[$relation] instanceof LinkInterface) {
            $this->links[$relation] = [$this->links[$relation]];
        }

        if (! is_array(value: $this->links[$relation])) {
            $type = get_debug_type(value: $this->links[$relation]);

            throw new Exception\DomainException(message: sprintf(
                '%s::$links should be either a %s\Link or an array; however, it is a "%s"',
                self::class,
                __NAMESPACE__,
                $type
            ));
        }

        $this->links[$relation][] = $link;
        return $this;
    }

    /**
     * Add a link to the collection and update the collection's relations according to RFC 5988.
     *
     * @todo Rename to "add" after deprecating the current "add" implementation
     */
    public function idempotentAdd(LinkInterface $link): void
    {
        $existingRels = array_keys(array: $this->links);
        $linkRels     = $link->getRels();

        // update existing rels
        $intersection = array_intersect($linkRels, $existingRels);
        foreach ($intersection as $relation) {
            $relationLinks = $this->links[$relation];
            if (! is_array(value: $relationLinks)) {
                $relationLinks = [$relationLinks];
            }

            if (! in_array(needle: $link, haystack: $relationLinks, strict: true)) {
                $relationLinks[]        = $link;
                $this->links[$relation] = $relationLinks; // inside the if, otherwise it's not really idempotent
            }
        }

        // add missing rels
        $diff = array_diff($linkRels, $existingRels);
        foreach ($diff as $relation) {
            $this->links[$relation] = $link;
        }
    }

    /**
     * Retrieve a link relation
     *
     * @param string $relation
     * @return LinkInterface|Link|array<mixed>|null
     */
    public function get(string $relation): Link|array|LinkInterface|null
    {
        if (! $this->has(relation: $relation)) {
            return null;
        }

        /** @psalm-var LinkInterface|Link|array<mixed>|null $value */
        $value = $this->links[$relation];

        return $value;
    }

    /**
     * Does a given link relation exist?
     *
     * @param string $relation
     * @return bool
     */
    public function has(string $relation): bool
    {
        return array_key_exists(key: $relation, array: $this->links);
    }

    /**
     * Remove a given link relation
     *
     * @param string $relation
     * @return bool
     */
    public function remove(string $relation): bool
    {
        if (! $this->has(relation: $relation)) {
            return false;
        }

        unset($this->links[$relation]);
        return true;
    }
}
