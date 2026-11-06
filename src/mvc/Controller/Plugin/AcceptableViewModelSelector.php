<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\Exception\InvalidArgumentException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Laminas\Http\Header\Accept;
use Laminas\Http\Header\Accept\FieldValuePart\AbstractFieldValuePart;
use Laminas\Http\Request;
use Laminas\View\Model\ModelInterface;

use function class_exists;
use function str_replace;

/**
 * Controller Plugin to assist in selecting an appropriate View Model type based on the
 * User Agent's accept header.
 */
class AcceptableViewModelSelector extends AbstractPlugin
{
    /**
     * @var string the Key to inject the name of a viewmodel with in an Accept Header
     */
    public const string INJECT_VIEWMODEL_NAME = '_internalViewModel';

    protected ?MvcEvent $event = null;

    protected ?Request $request = null;

    protected ?array $defaultMatchAgainst = null;

    /** @var string Default ViewModel */
    protected string $defaultViewModelName = ViewModel::class;

    /**
     * Detects an appropriate viewmodel for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @param bool $returnDefault (optional) If no match is available. Return default instead
     * @param AbstractFieldValuePart|null $resultReference (optional) The object that was matched
     * @return ModelInterface|null
     * @throws InvalidArgumentException If the supplied and matched View Model could not be found.
     */
    public function __invoke(
        ?array $matchAgainst = null,
        bool $returnDefault = true,
        ?AbstractFieldValuePart &$resultReference = null,
    ): ViewModel|ModelInterface|null {
        return $this->getViewModel($matchAgainst, $returnDefault, $resultReference);
    }

    /**
     * Detects an appropriate viewmodel for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @param bool $returnDefault (optional) If no match is available. Return default instead
     * @param AbstractFieldValuePart|null $resultReference (optional) The object that was matched
     * @return ModelInterface|null
     * @throws InvalidArgumentException If the supplied and matched View Model could not be found.
     */
    public function getViewModel(
        ?array $matchAgainst = null,
        bool $returnDefault = true,
        ?AbstractFieldValuePart &$resultReference = null,
    ): ViewModel|ModelInterface|null {
        $name = $this->getViewModelName($matchAgainst, $returnDefault, $resultReference);

        if ($name === null) {
            return null;
        }

        if (!class_exists($name)) {
            throw new InvalidArgumentException('The supplied View Model could not be found');
        }

        return new $name();
    }

    /**
     * Detects an appropriate viewmodel name for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @param bool $returnDefault (optional) If no match is available. Return default instead
     * @param AbstractFieldValuePart|null $resultReference (optional) The object that was matched.
     * @return string|null Returns null if $returnDefault = false and no match could be made
     */
    public function getViewModelName(
        ?array $matchAgainst = null,
        bool $returnDefault = true,
        ?AbstractFieldValuePart &$resultReference = null,
    ): ?string {
        $res = $this->match($matchAgainst);
        if ($res) {
            $resultReference = $res;
            return $this->extractViewModelName($res);
        }

        if ($returnDefault) {
            return $this->defaultViewModelName;
        }

        return null;
    }

    /**
     * Detects an appropriate viewmodel name for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @return AbstractFieldValuePart|null The object that was matched
     */
    public function match(?array $matchAgainst = null): ?AbstractFieldValuePart
    {
        $request = $this->getRequest();
        $headers = $request->getHeaders();

        if (!$matchAgainst && !$this->defaultMatchAgainst || !\Jield\Stdlib\Value::instance($headers, \Laminas\Http\Headers::class)->has('accept')) {
            return null;
        }

        if (!$matchAgainst) {
            $matchAgainst = $this->defaultMatchAgainst;
        }

        $matchAgainstString = '';
        /** @var mixed $modelStrings */
        foreach ($matchAgainst as $modelName => $modelStrings) {
            /** @var mixed $modelString */
            foreach (\Jield\Stdlib\Value::toArray($modelStrings) as $modelString) {
                $matchAgainstString .= $this->injectViewModelName(\Jield\Stdlib\Value::string($modelString), \Jield\Stdlib\Value::string($modelName));
            }
        }

        /** @var Accept $accept */
        $accept = \Jield\Stdlib\Value::instance($headers, \Laminas\Http\Headers::class)->get('Accept');
        $res = $accept->match($matchAgainstString);
        if (!$res instanceof AbstractFieldValuePart) {
            return null;
        }

        return $res;
    }

    /**
     * Set the default View Model (name) to return if no match could be made
     *
     * @param string $defaultViewModelName The default View Model name
     * @return static
     */
    public function setDefaultViewModelName(string $defaultViewModelName): static
    {
        $this->defaultViewModelName = $defaultViewModelName;
        return $this;
    }

    /**
     * Set the default View Model (name) to return if no match could be made
     *
     * @return string
     */
    public function getDefaultViewModelName(): string
    {
        return $this->defaultViewModelName;
    }

    /**
     * Set the default Accept Types and View Model combinations to match against if none are specified.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @return static
     */
    public function setDefaultMatchAgainst(?array $matchAgainst = null): static
    {
        $this->defaultMatchAgainst = $matchAgainst;
        return $this;
    }

    /**
     * Get the default Accept Types and View Model combinations to match against if none are specified.
     *
     * @return array|null
     */
    public function getDefaultMatchAgainst(): ?array
    {
        return $this->defaultMatchAgainst;
    }

    /**
     * Inject the viewmodel name into the accept header string
     *
     * @param string|array $modelAcceptString
     * @param string $modelName
     * @return string
     */
    protected function injectViewModelName(string|array $modelAcceptString, string $modelName): string
    {
        $modelName = str_replace('\\', '|', $modelName);

        return $modelAcceptString . '; ' . self::INJECT_VIEWMODEL_NAME . '="' . $modelName . '", ';
    }

    /**
     * Extract the viewmodel name from a match
     *
     * @return string
     */
    protected function extractViewModelName(AbstractFieldValuePart $res): string
    {
        $params = get_object_vars(\Jield\Stdlib\Value::present($res->getMatchedAgainst())->getParams());
        $modelName = \Jield\Stdlib\Value::string($params[self::INJECT_VIEWMODEL_NAME]);
        return str_replace('|', '\\', $modelName);
    }

    /**
     * Get the request
     *
     * @return Request
     * @throws DomainException If unable to find request.
     */
    protected function getRequest(): Request
    {
        if ($this->request instanceof Request) {
            return $this->request;
        }

        $event = $this->getEvent();
        $request = $event->getRequest();
        if (!$request instanceof Request) {
            throw new DomainException('The event used does not contain a valid Request, but must.');
        }

        $this->request = $request;
        return $request;
    }

    /**
     * Get the event
     *
     * @return MvcEvent
     * @throws DomainException If unable to find event.
     */
    protected function getEvent(): MvcEvent
    {
        if ($this->event instanceof MvcEvent) {
            return $this->event;
        }

        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new DomainException('A controller that implements InjectApplicationEventInterface is required to use '
            . self::class);
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent) {
            $params = $event->getParams();
            $event = new MvcEvent();
            $event->setParams($params);
        }
        $this->event = $event;

        return $this->event;
    }
}
