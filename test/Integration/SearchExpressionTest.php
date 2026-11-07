<?php

declare(strict_types=1);

namespace JieldTest\Integration;

use Jield\Search\Solr\Expression\BoostExpression;
use Jield\Search\Solr\Expression\ExpressionBuilder;
use PHPUnit\Framework\TestCase;

final class SearchExpressionTest extends TestCase
{
    public function testEmptySearchesMatchAllDocumentsWithoutADefaultTextField(): void
    {
        $builder = new ExpressionBuilder();
        foreach ([null, '', '  ', '*', '*:*'] as $query) {
            self::assertSame('*:*', (string) $builder->advancedSearchQuery($query, ['title', 'title_sort']));
        }
    }

    public function testBoostedFieldsRemainQueryExpressions(): void
    {
        $builder = new ExpressionBuilder();
        self::assertSame(
            '(title:*software* OR title_sort:*software*^2)',
            (string) $builder->advancedSearchQuery('software', ['title', 'title_sort']),
        );
        self::assertSame('"a\\:b"^3', (string) new BoostExpression(3, 'a:b'));
    }

    public function testPhraseSearchesKeepFieldNamesAndBoosts(): void
    {
        $builder = new ExpressionBuilder();
        self::assertSame(
            'title:"smart city"^50',
            (string) $builder->advancedSearchQuery('"smart city"', ['title'], ['title' => 50]),
        );
    }
}
