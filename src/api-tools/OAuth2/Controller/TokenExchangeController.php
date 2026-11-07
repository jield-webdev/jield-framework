<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Controller;

use Jield\ApiTools\OAuth2\EntraException;
use Jield\ApiTools\OAuth2\TokenExchangeInterface;
use Jield\Mvc\Controller\AbstractActionController;
use Jield\Stdlib\Value;
use JsonException;
use Laminas\Http\Header\ContentType;
use Laminas\Http\Headers;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Webmozart\Assert\Assert;

final class TokenExchangeController extends AbstractActionController
{
    public function __construct(
        private readonly TokenExchangeInterface $exchange,
    ) {}

    public function tokenAction(): Response
    {
        $request = $this->getRequest();

        if (!$request instanceof Request || !$request->isPost()) {
            $response = $this->json(status: 405, data: [
                'error' => 'invalid_request',
                'error_description' => 'Use POST',
            ]);
            $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Allow', fieldValue: 'POST');
            return $response;
        }

        try {
            if (strlen($request->getContent()) > 65_536) {
                throw new EntraException(error: 'invalid_request', description: 'Request is too large');
            }
            $type = Assert::isInstanceOf($request->getHeaders(), Headers::class)->get('Content-Type');

            if ($type instanceof ContentType && $type->match('application/json')) {
                $data = json_decode(
                    json: $request->getContent(),
                    associative: true,
                    depth: 16,
                    flags: JSON_THROW_ON_ERROR,
                );
                if (!is_array($data) || array_is_list($data)) {
                    throw new EntraException(error: 'invalid_request', description: 'Expected a JSON object');
                }
            } elseif ($type instanceof ContentType && $type->match('application/x-www-form-urlencoded')) {
                $data = Value::call($request->getPost(), 'toArray');
            } else {
                throw new EntraException(
                    error: 'invalid_request',
                    description: 'Use JSON or form-urlencoded content',
                    status: 415,
                );
            }

            return $this->json(status: 200, data: $this->exchange->exchange($data));
        } catch (JsonException) {
            return $this->json(status: 400, data: [
                'error' => 'invalid_request',
                'error_description' => 'Invalid JSON',
            ]);
        } catch (EntraException $exception) {
            return $this->json(status: $exception->status, data: [
                'error' => $exception->error,
                'error_description' => $exception->getMessage(),
            ]);
        }
    }

    private function json(int $status, array $data): Response
    {
        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode($status);

        $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Content-Type', fieldValue: 'application/json');
        $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Cache-Control', fieldValue: 'no-store');
        $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Pragma', fieldValue: 'no-cache');

        return Assert::isInstanceOf(
            $response->setContent(json_encode(value: $data, flags: JSON_THROW_ON_ERROR)),
            \Laminas\Http\Response::class,
        );
    }
}
