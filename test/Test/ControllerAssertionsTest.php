<?php

declare(strict_types=1);

namespace JieldTest\Test;

use Jield\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Laminas\Http\PhpEnvironment\Response;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

final class ControllerAssertionsTest extends TestCase
{
    public function testHeaderAssertionsCheckEveryRepeatedValue(): void
    {
        $response = new Response();
        $response->getHeaders()->addHeaderLine('Set-Cookie', 'first=one');
        $response->getHeaders()->addHeaderLine('Set-Cookie', 'second=two');
        $helper = $this->helper($response);

        $helper->assertResponseHeaderContains('Set-Cookie', 'second=two');
        $helper->assertNotResponseHeaderContains('Set-Cookie', 'missing=value');
        $helper->assertResponseHeaderRegex('Set-Cookie', '/^second=/');
    }

    public function testMissingHeadersProduceAssertionFailures(): void
    {
        $helper = $this->helper(new Response());
        $this->expectException(ExpectationFailedException::class);
        $helper->assertResponseHeaderContains('X-Missing', 'value');
    }

    public function testRedirectAssertionsReadTheLocationHeader(): void
    {
        $response = new Response();
        $response->getHeaders()->addHeaderLine('Location', '/next');
        $helper = $this->helper($response);

        $helper->assertRedirectTo('/next');
        $helper->assertNotRedirectTo('/other');
        $helper->assertRedirectRegex('~^/next$~');
        $helper->assertNotRedirectRegex('~^/other$~');
    }

    private function helper(Response $response): AbstractHttpControllerTestCase
    {
        $helper = new class('fixture') extends AbstractHttpControllerTestCase {
            public Response $fixture;

            public function getResponse(): Response
            {
                return $this->fixture;
            }
        };
        $helper->fixture = $response;
        return $helper;
    }
}
