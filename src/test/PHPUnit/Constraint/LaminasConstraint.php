<?php

declare(strict_types=1);

namespace Jield\Test\PHPUnit\Constraint;

use Jield\Stdlib\Value;
use Jield\Test\PHPUnit\Controller\AbstractControllerTestCase;
use Jield\Test\PHPUnit\FailureMessage;
use Override;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Comparator\ComparisonFailure;
use Webmozart\Assert\Assert;

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
        /** @mago-var mixed $controllerIdentifier */
        $controllerIdentifier = Assert::notNull($routeMatch)->getParam('controller');

        /** @mago-var mixed $controllerManager */
        $controllerManager = $this->activeTestCase->getApplicationServiceLocator()->get('ControllerManager');

        return Assert::string(Value::call($controllerManager, 'get', [$controllerIdentifier])::class);
    }

    /**
     * Create a failure message.
     *
     * If traceError is true, appends exception details, if any.
     */
    private function createFailureMessage(string $message): string
    {
        return FailureMessage::format($this->activeTestCase, $message);
    }
}
