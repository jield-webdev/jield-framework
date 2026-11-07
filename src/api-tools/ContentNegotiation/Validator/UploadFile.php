<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Validator;

use Jield\Validator\Options;
use Laminas\Stdlib\RequestInterface;
use Laminas\Validator\File\UploadFile as BaseValidator;
use Laminas\Validator\ValidatorInterface;

class UploadFile implements ValidatorInterface
{
    private readonly BaseValidator $validator;
    private ?RequestInterface $request = null;
    /** @var array<string, string> */
    private array $messages = [];

    public function __construct(?array $options = null)
    {
        $this->validator = new BaseValidator(Options::normalize($options ?? []));
    }

    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function isValid(mixed $value): bool
    {
        $valid = $this->validator->isValid($value);
        $this->messages = $this->validator->getMessages();
        if (
            !$valid
            && $this->request !== null
            && method_exists($this->request, 'getMethod')
            && in_array($this->request->getMethod(), ['PUT', 'PATCH'], true)
            && array_keys($this->messages) === [BaseValidator::ATTACK]
        ) {
            $this->messages = [];
            return true;
        }
        return $valid;
    }
}
