<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Validator;

use Jield\Stdlib\RequestInterface;
use Laminas\Validator\File\UploadFile as BaseValidator;
use Override;
use function count;
use function method_exists;


//@phpstan-ignore-next-line
class UploadFile extends BaseValidator
{
    protected ?RequestInterface $request = null;

    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Overrides isValid()
     *
     * If the reason for failure is self::ATTACK, we can assume that
     * is_uploaded_file() has failed -- which is
     *
     * @param mixed $value
     * @return bool
     */
    #[Override]
    public function isValid(mixed $value): bool
    {
        $isPut = $this->request !== null
            && method_exists(object_or_class: $this->request, method: 'isPut')
            && $this->request->isPut();
        $isPatch = $this->request !== null
            && method_exists(object_or_class: $this->request, method: 'isPatch')
            && $this->request->isPatch();

        if (null === $this->request || (! $isPut && ! $isPatch)) {
            // In absence of a request object, an HTTP request, or a PATCH/PUT
            // operation, just use the parent logic.
            return parent::isValid(value: $value);
        }

        $result = parent::isValid(value: $value);
        if ($result !== false) {
            return $result;
        }

        if (!isset($this->abstractOptions['messages'][static::ATTACK])) {
            return false;
        }

        if (count(value: $this->abstractOptions['messages']) > 1) {
            return false;
        }

        unset($this->abstractOptions['messages'][static::ATTACK]);
        return true;
    }
}
