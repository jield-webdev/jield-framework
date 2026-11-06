<?php

declare(strict_types=1);

namespace Jield\Test\PHPUnit\Constraint;

use Exception;
use Jield\Test\PHPUnit\Controller\AbstractControllerTestCase;
use Override;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Comparator\ComparisonFailure;
use Throwable;

use function implode;
use function sprintf;

// @codingStandardsIgnoreLine
abstract class LaminasConstraint extends Constraint
{
    /** @var AbstractControllerTestCase */
    protected $activeTestCase;

    public function __construct(AbstractControllerTestCase $activeTestCase)
    {
        $this->activeTestCase = $activeTestCase;
    }

    final public function getTestCase(): AbstractControllerTestCase
    {
        return $this->activeTestCase;
    }

    /**
     * @psalm-return never
     */
    #[Override]
    final public function fail(mixed $other, string $description, ?ComparisonFailure $comparisonFailure = null): never
    {
        try {
            parent::fail($other, $description, $comparisonFailure);
        } catch (ExpectationFailedException $failedException) {
            throw new ExpectationFailedException(
                $this->createFailureMessage($failedException->getMessage()),
                $failedException->getComparisonFailure(),
            );
        }
    }

    final public function getControllerFullClassName(): string
    {
        $routeMatch = $this->activeTestCase->getApplication()->getMvcEvent()->getRouteMatch();
        /** @var mixed $controllerIdentifier */
        $controllerIdentifier = \Jield\Stdlib\Value::present($routeMatch)->getParam('controller');

        /** @var mixed $controllerManager */
        $controllerManager = $this->activeTestCase->getApplicationServiceLocator()->get('ControllerManager');

        return \Jield\Stdlib\Value::string(\Jield\Stdlib\Value::call($controllerManager, 'get', [$controllerIdentifier])::class);
    }

    /**
     * Create a failure message.
     *
     * If traceError is true, appends exception details, if any.
     */
    private function createFailureMessage(string $message): string
    {
        return \Jield\Test\PHPUnit\FailureMessage::format($this->activeTestCase, $message);
    }
}
