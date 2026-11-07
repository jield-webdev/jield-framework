<?php

declare(strict_types=1);

namespace Jield\Test\PHPUnit\Controller;

use ArrayIterator;
use Jield\Test\PHPUnit\Constraint\HasRedirectConstraint;
use Jield\Test\PHPUnit\Constraint\IsRedirectedRouteNameConstraint;
use Jield\Test\PHPUnit\FailureMessage;
use Laminas\Http\Header\HeaderInterface;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\DomCrawler\Crawler;
use UnexpectedValueException;
use Webmozart\Assert\Assert;

use function count;
use function implode;
use function preg_match;
use function sprintf;

abstract class AbstractHttpControllerTestCase extends AbstractControllerTestCase
{
    /**
     * XPath namespaces
     *
     * @var array<string,string>
     */
    protected $xpathNamespaces = [];

    /**
     * Get response header by key
     *
     * @param  string $header
     * @return HeaderInterface|ArrayIterator|false
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getResponseHeader($header)
    {
        $result = $this->getResponse()->getHeaders()->get($header);
        if ($result === false || $result instanceof HeaderInterface || $result instanceof ArrayIterator) {
            return $result;
        }
        throw new UnexpectedValueException('Invalid response header.');
    }

    /**
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getSingleResponseHeader(string $header): HeaderInterface|false
    {
        $result = $this->getResponseHeader($header);
        if ($result instanceof ArrayIterator) {
            /** @mago-var mixed $result */
            $result = $result->valid() ? $result->current() : false;
        }
        return $result === false ? false : Assert::isInstanceOf($result, HeaderInterface::class);
    }

    /**
     * Assert response has the given reason phrase
     *
     * @param string $phrase
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertResponseReasonPhrase($phrase)
    {
        $this->assertEquals($phrase, $this->getResponse()->getReasonPhrase());
    }

    /**
     * Assert response header exists
     *
     * @param string $header
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertHasResponseHeader($header)
    {
        $responseHeader = $this->getResponseHeader($header);
        if (false === $responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response header "%s" found',
                $header,
            )));
        }
        $this->assertNotEquals(false, $responseHeader);
    }

    /**
     * Assert response header does not exist
     *
     * @param string $header
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotHasResponseHeader($header)
    {
        $responseHeader = $this->getResponseHeader($header);
        if (false !== $responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response header "%s" WAS NOT found',
                $header,
            )));
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Assert response header exists and contains the given string
     *
     * @param string $header
     * @param string $match
     * @return void
     * @throws \PHPUnit\Framework\Exception
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertResponseHeaderContains($header, $match)
    {
        $values = $this->responseHeaderValues($header);
        $this->assertContains(
            $match,
            $values,
            sprintf(
                'Failed asserting response header "%s" exists and contains "%s", actual content is "%s"',
                $header,
                $match,
                implode(', ', $values),
            ),
        );
    }

    /**
     * Assert response header exists and contains the given string
     *
     * @param string $header
     * @param string $match
     * @return void
     * @throws \PHPUnit\Framework\Exception
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotResponseHeaderContains($header, $match)
    {
        $values = $this->responseHeaderValues($header);
        $this->assertNotContains(
            $match,
            $values,
            sprintf('Failed asserting response header "%s" DOES NOT CONTAIN "%s"', $header, $match),
        );
    }

    /**
     * Assert response header exists and matches the given pattern
     *
     * @param string $header
     * @param string $pattern
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertResponseHeaderRegex($header, $pattern)
    {
        $values = $this->responseHeaderValues($header);
        $matched = false;
        foreach ($values as $value) {
            if (preg_match($pattern, $value) === 1) {
                $matched = true;
                break;
            }
        }
        $this->assertTrue($matched, sprintf(
            'Failed asserting response header "%s" exists and matches regex "%s", actual content is "%s"',
            $header,
            $pattern,
            implode(', ', $values),
        ));
    }

    /**
     * @return list<string>
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function responseHeaderValues(string $header): array
    {
        $responseHeader = $this->getResponseHeader($header);
        if ($responseHeader === false) {
            throw new ExpectationFailedException(sprintf('Response header "%s" does not exist.', $header));
        }
        $values = [];
        /** @mago-var mixed $currentHeader */
        foreach ($responseHeader instanceof ArrayIterator ? $responseHeader : [$responseHeader] as $currentHeader) {
            $values[] = Assert::isInstanceOf($currentHeader, HeaderInterface::class)->getFieldValue();
        }
        return $values;
    }

    /**
     * Assert response header does not exist and/or does not match the given regex
     *
     * @param string $header
     * @param string $pattern
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotResponseHeaderRegex($header, $pattern)
    {
        $responseHeader = $this->getResponseHeader($header);
        if (!$responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response header, header "%s" doesn\'t exist',
                $header,
            )));
        }

        if (!$responseHeader instanceof ArrayIterator) {
            $responseHeader = [$responseHeader];
        }

        $headerMatched = false;

        /** @mago-var mixed $currentHeader */
        foreach ($responseHeader as $currentHeader) {
            $headerMatched = (bool) preg_match(
                $pattern,
                Assert::isInstanceOf($currentHeader, \Laminas\Http\Header\HeaderInterface::class)->getFieldValue(),
            );

            if ($headerMatched) {
                throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                    'Failed asserting response header "%s" DOES NOT MATCH regex "%s"',
                    $header,
                    $pattern,
                )));
            }
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Assert that response is a redirect
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertRedirect()
    {
        $responseHeader = $this->getSingleResponseHeader('Location');
        if (false === $responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format(
                $this,
                'Failed asserting response is a redirect',
            ));
        }
        $this->assertNotEquals(false, $responseHeader);
    }

    /**
     * Assert that response is NOT a redirect
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotRedirect()
    {
        $responseHeader = $this->getSingleResponseHeader('Location');
        if (false !== $responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response is NOT a redirect, actual redirection is "%s"',
                $responseHeader->getFieldValue(),
            )));
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Assert that response redirects to given URL
     *
     * @param string $url
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertRedirectTo($url)
    {
        $responseHeader = $this->getSingleResponseHeader('Location');
        if (!$responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format(
                $this,
                'Failed asserting response is a redirect',
            ));
        }
        if ($url !== $responseHeader->getFieldValue()) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response redirects to "%s", actual redirection is "%s"',
                $url,
                $responseHeader->getFieldValue(),
            )));
        }
        $this->assertEquals($url, $responseHeader->getFieldValue());
    }

    /**
     * Assert that response does not redirect to given URL
     *
     * @param string $url
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotRedirectTo($url)
    {
        $responseHeader = $this->getSingleResponseHeader('Location');
        if (!$responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format(
                $this,
                'Failed asserting response is a redirect',
            ));
        }
        if ($url === $responseHeader->getFieldValue()) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response redirects to "%s"',
                $url,
            )));
        }
        $this->assertNotEquals($url, $responseHeader->getFieldValue());
    }

    /**
     * Assert that response redirects to given route
     *
     * @throws \PHPUnit\Framework\Exception
     */
    final public function assertRedirectToRoute(string $route): void
    {
        self::assertThat($route, self::logicalAnd(
            new HasRedirectConstraint($this),
            new IsRedirectedRouteNameConstraint($this),
        ));
    }

    /**
     * Assert that response does not redirect to given route
     *
     * @throws \PHPUnit\Framework\Exception
     */
    final public function assertNotRedirectToRoute(string $route): void
    {
        self::assertThat(
            $route,
            self::logicalNot(self::logicalAnd(
                new HasRedirectConstraint($this),
                new IsRedirectedRouteNameConstraint($this),
            )),
        );
    }

    /**
     * Assert that redirect location matches pattern
     *
     * @param string $pattern
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertRedirectRegex($pattern)
    {
        $responseHeader = $this->getSingleResponseHeader('Location');
        if (!$responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format(
                $this,
                'Failed asserting response is a redirect',
            ));
        }
        if (!preg_match($pattern, $responseHeader->getFieldValue())) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response redirects to URL MATCHING "%s", actual redirection is "%s"',
                $pattern,
                $responseHeader->getFieldValue(),
            )));
        }
        $this->assertTrue((bool) preg_match($pattern, $responseHeader->getFieldValue()));
    }

    /**
     * Assert that redirect location does not match pattern
     *
     * @param string $pattern
     * @return void
     * @throws ExpectationFailedException
     * @throws UnexpectedValueException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotRedirectRegex($pattern)
    {
        $responseHeader = $this->getSingleResponseHeader('Location');
        if (!$responseHeader) {
            throw new ExpectationFailedException(FailureMessage::format(
                $this,
                'Failed asserting response is a redirect',
            ));
        }
        if (preg_match($pattern, $responseHeader->getFieldValue())) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response DOES NOT redirect to URL MATCHING "%s"',
                $pattern,
            )));
        }
        $this->assertFalse((bool) preg_match($pattern, $responseHeader->getFieldValue()));
    }

    /**
     * Register XPath namespaces
     *
     * @param array<string,string> $xpathNamespaces
     * @return void
     */
    public function registerXpathNamespaces(array $xpathNamespaces)
    {
        $this->xpathNamespaces = $xpathNamespaces;
    }

    /**
     * Execute a DOM/XPath query
     *
     * @param  string $path
     * @param  bool $useXpath
     * @return Crawler
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function query($path, $useXpath = false)
    {
        $response = $this->getResponse();
        $document = new Crawler(Assert::string($response->getContent()));

        if ($useXpath) {
            foreach ($this->xpathNamespaces as $prefix => $namespace) {
                $document->registerNamespace($prefix, $namespace);
            }
        }

        return $useXpath ? $document->filterXPath($path) : $document->filter($path);
    }

    /**
     * Execute a xpath query
     *
     * @param string $path
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function xpathQuery($path): Crawler
    {
        return $this->query($path, true);
    }

    /**
     * Count the dom query executed
     *
     * @param  string $path
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryCount($path)
    {
        return count($this->query($path, false));
    }

    /**
     * Count the dom query executed
     *
     * @param  string $path
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function xpathQueryCount($path)
    {
        return $this->xpathQuery($path)->count();
    }

    /**
     * @param string $path
     * @param bool $useXpath
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryCountOrxpathQueryCount($path, $useXpath = false): int
    {
        if ($useXpath) {
            return $this->xpathQueryCount($path);
        }

        return $this->queryCount($path);
    }

    /**
     * Assert against DOM/XPath selection
     *
     * @param string $path
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryAssertion($path, $useXpath = false): void
    {
        $match = $this->queryCountOrxpathQueryCount($path, $useXpath);
        if ($match <= 0) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s EXISTS',
                $path,
            )));
        }
        $this->assertGreaterThan(0, $match);
    }

    /**
     * Assert against DOM selection
     *
     * @param string $path CSS selector path
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertQuery($path)
    {
        $this->queryAssertion($path, false);
    }

    /**
     * Assert against XPath selection
     *
     * @param string $path XPath path
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertXpathQuery($path)
    {
        $this->queryAssertion($path, true);
    }

    /**
     * Assert against DOM/XPath selection
     *
     * @param string $path CSS selector path
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function notQueryAssertion($path, $useXpath = false): void
    {
        $match = $this->queryCountOrxpathQueryCount($path, $useXpath);
        if ($match !== 0) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s DOES NOT EXIST',
                $path,
            )));
        }
        $this->assertEquals(0, $match);
    }

    /**
     * Assert against DOM selection
     *
     * @param string $path CSS selector path
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotQuery($path)
    {
        $this->notQueryAssertion($path, false);
    }

    /**
     * Assert against XPath selection
     *
     * @param string $path XPath path
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotXpathQuery($path)
    {
        $this->notQueryAssertion($path, true);
    }

    /**
     * Assert against DOM/XPath selection; should contain exact number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Number of nodes that should match
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryCountAssertion($path, $count, $useXpath = false): void
    {
        $match = $this->queryCountOrxpathQueryCount($path, $useXpath);
        if ($match !== $count) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s OCCURS EXACTLY %d times, actually occurs %d times',
                $path,
                $count,
                $match,
            )));
        }
        $this->assertEquals($match, $count);
    }

    /**
     * Assert against DOM selection; should contain exact number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Number of nodes that should match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertQueryCount($path, $count)
    {
        $this->queryCountAssertion($path, $count, false);
    }

    /**
     * Assert against XPath selection; should contain exact number of nodes
     *
     * @param string $path XPath path
     * @param int $count Number of nodes that should match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertXpathQueryCount($path, $count)
    {
        $this->queryCountAssertion($path, $count, true);
    }

    /**
     * Assert against DOM/XPath selection; should NOT contain exact number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Number of nodes that should NOT match
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function notQueryCountAssertion($path, $count, $useXpath = false): void
    {
        $match = $this->queryCountOrxpathQueryCount($path, $useXpath);
        if ($match === $count) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s DOES NOT OCCUR EXACTLY %d times',
                $path,
                $count,
            )));
        }
        $this->assertNotEquals($match, $count);
    }

    /**
     * Assert against DOM selection; should NOT contain exact number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Number of nodes that should NOT match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotQueryCount($path, $count)
    {
        $this->notQueryCountAssertion($path, $count, false);
    }

    /**
     * Assert against XPath selection; should NOT contain exact number of nodes
     *
     * @param string $path XPath path
     * @param int $count Number of nodes that should NOT match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotXpathQueryCount($path, $count)
    {
        $this->notQueryCountAssertion($path, $count, true);
    }

    /**
     * Assert against DOM/XPath selection; should contain at least this number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Minimum number of nodes that should match
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryCountMinAssertion($path, $count, $useXpath = false): void
    {
        $match = $this->queryCountOrxpathQueryCount($path, $useXpath);
        if ($match < $count) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s OCCURS AT LEAST %d times, actually occurs %d times',
                $path,
                $count,
                $match,
            )));
        }
        $this->assertTrue($match >= $count);
    }

    /**
     * Assert against DOM selection; should contain at least this number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Minimum number of nodes that should match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertQueryCountMin($path, $count)
    {
        $this->queryCountMinAssertion($path, $count, false);
    }

    /**
     * Assert against XPath selection; should contain at least this number of nodes
     *
     * @param string $path XPath path
     * @param int $count Minimum number of nodes that should match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertXpathQueryCountMin($path, $count)
    {
        $this->queryCountMinAssertion($path, $count, true);
    }

    /**
     * Assert against DOM/XPath selection; should contain no more than this number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Maximum number of nodes that should match
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryCountMaxAssertion($path, $count, $useXpath = false): void
    {
        $match = $this->queryCountOrxpathQueryCount($path, $useXpath);
        if ($match > $count) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s OCCURS AT MOST %d times, actually occurs %d times',
                $path,
                $count,
                $match,
            )));
        }
        $this->assertTrue($match <= $count);
    }

    /**
     * Assert against DOM selection; should contain no more than this number of nodes
     *
     * @param string $path CSS selector path
     * @param int $count Maximum number of nodes that should match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertQueryCountMax($path, $count)
    {
        $this->queryCountMaxAssertion($path, $count, false);
    }

    /**
     * Assert against XPath selection; should contain no more than this number of nodes
     *
     * @param string $path XPath path
     * @param int $count Maximum number of nodes that should match
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertXpathQueryCountMax($path, $count)
    {
        $this->queryCountMaxAssertion($path, $count, true);
    }

    /**
     * Assert against DOM/XPath selection; node should contain content
     *
     * @param string $path CSS selector path
     * @param string $match content that should be contained in matched nodes
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryContentContainsAssertion($path, $match, $useXpath = false): void
    {
        $result = $this->query($path, $useXpath);

        if ($result->count() === 0) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s EXISTS',
                $path,
            )));
        }

        $nodeValues = [];

        $currentValue = null;
        foreach ($result as $node) {
            $currentValue = $node->nodeValue;
            if ($currentValue === $match) {
                $this->assertEquals($match, $node->nodeValue);
                return;
            }

            $nodeValues[] = $node->nodeValue;
        }

        throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
            'Failed asserting node denoted by %s CONTAINS content "%s", Contents: [%s]',
            $path,
            $match,
            implode(',', $nodeValues),
        )));
    }

    /**
     * Assert against DOM selection; node should contain content
     *
     * @param string $path CSS selector path
     * @param string $match content that should be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertQueryContentContains($path, $match)
    {
        $this->queryContentContainsAssertion($path, $match, false);
    }

    /**
     * Assert against XPath selection; node should contain content
     *
     * @param string $path XPath path
     * @param string $match content that should be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertXpathQueryContentContains($path, $match)
    {
        $this->queryContentContainsAssertion($path, $match, true);
    }

    /**
     * Assert against DOM/XPath selection; node should NOT contain content
     *
     * @param string $path CSS selector path
     * @param string $match content that should NOT be contained in matched nodes
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function notQueryContentContainsAssertion($path, $match, $useXpath = false): void
    {
        $result = $this->query($path, $useXpath);
        if ($result->count() === 0) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s EXISTS',
                $path,
            )));
        }
        $currentValue = null;
        foreach ($result as $node) {
            $currentValue = $node->nodeValue;
            if ($currentValue === $match) {
                throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                    'Failed asserting node DENOTED BY %s DOES NOT CONTAIN content "%s"',
                    $path,
                    $match,
                )));
            }
        }
        $this->assertNotEquals($currentValue, $match);
    }

    /**
     * Assert against DOM selection; node should NOT contain content
     *
     * @param string $path CSS selector path
     * @param string $match content that should NOT be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotQueryContentContains($path, $match)
    {
        $this->notQueryContentContainsAssertion($path, $match, false);
    }

    /**
     * Assert against XPath selection; node should NOT contain content
     *
     * @param string $path XPath path
     * @param string $match content that should NOT be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotXpathQueryContentContains($path, $match)
    {
        $this->notQueryContentContainsAssertion($path, $match, true);
    }

    /**
     * Assert against DOM/XPath selection; node should match content
     *
     * @param string $path CSS selector path
     * @param string $pattern Pattern that should be contained in matched nodes
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function queryContentRegexAssertion($path, $pattern, $useXpath = false): void
    {
        $result = $this->query($path, $useXpath);
        if ($result->count() === 0) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s EXISTS',
                $path,
            )));
        }

        $found = false;
        $nodeValues = [];

        foreach ($result as $node) {
            $nodeValues[] = $node->nodeValue;
            if (preg_match($pattern, (string) $node->nodeValue)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node denoted by %s CONTAINS content MATCHING "%s", actual content is "%s"',
                $path,
                $pattern,
                implode('', $nodeValues),
            )));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Assert against DOM selection; node should match content
     *
     * @param string $path CSS selector path
     * @param string $pattern Pattern that should be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertQueryContentRegex($path, $pattern)
    {
        $this->queryContentRegexAssertion($path, $pattern, false);
    }

    /**
     * Assert against XPath selection; node should match content
     *
     * @param string $path XPath path
     * @param string $pattern Pattern that should be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertXpathQueryContentRegex($path, $pattern)
    {
        $this->queryContentRegexAssertion($path, $pattern, true);
    }

    /**
     * Assert against DOM/XPath selection; node should NOT match content
     *
     * @param string $path CSS selector path
     * @param string $pattern pattern that should NOT be contained in matched nodes
     * @param bool $useXpath
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function notQueryContentRegexAssertion($path, $pattern, $useXpath = false): void
    {
        $result = $this->query($path, $useXpath);
        if ($result->count() === 0) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s EXISTS',
                $path,
            )));
        }
        $node = $result->getNode(0);
        $nodeValue = $node ? $node->nodeValue : null;

        if ($nodeValue === null || preg_match($pattern, $nodeValue)) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting node DENOTED BY %s DOES NOT CONTAIN content MATCHING "%s"',
                $path,
                $pattern,
            )));
        }
        $this->assertFalse((bool) preg_match($pattern, $nodeValue));
    }

    /**
     * Assert against DOM selection; node should NOT match content
     *
     * @param string $path CSS selector path
     * @param string $pattern pattern that should NOT be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotQueryContentRegex($path, $pattern)
    {
        $this->notQueryContentRegexAssertion($path, $pattern, false);
    }

    /**
     * Assert against XPath selection; node should NOT match content
     *
     * @param string $path XPath path
     * @param string $pattern pattern that should NOT be contained in matched nodes
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotXpathQueryContentRegex($path, $pattern)
    {
        $this->notQueryContentRegexAssertion($path, $pattern, true);
    }
}
