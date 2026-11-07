<?php

declare(strict_types=1);

namespace Jield\Export\Columns;

use Jield\Export\ValueObject\Column;

interface ColumnsHelperInterface
{
    /**
     * @return array<Column>
     */
    public function getColumns(): array;

    public function getName(): string;

    /** @return array<array-key, string> Names of exports that must run before this export. */
    public function getDependencies(): array;
}
