<?php

namespace Jield\Mvc\Controller\Plugin;

use Jield\Http\Header\Accept;
use Jield\Http\Header\Accept\FieldValuePart\AbstractFieldValuePart;
use Jield\Http\Request;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\Exception\InvalidArgumentException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;

use function class_exists;
use function is_array;
use function key;
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

    /** @var MvcEvent */
    protected MvcEvent $event;

    /** @var Request */
    protected Request $request;

    /**
     * Default array to match against.
     *
     * @var Array
     */
    protected array $defaultMatchAgainst;

    /** @var string Default ViewModel */
    protected string $defaultViewModelName = ViewModel::class;

    /**
     * Detects an appropriate viewmodel for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @param bool $returnDefault (optional) If no match is available. Return default instead
     * @param AbstractFieldValuePart|null $resultReference (optional) The object that was matched
     * @return ModelInterface|null
     *@throws InvalidArgumentException If the supplied and matched View Model could not be found.
     */
    public function __invoke(
        ?array                  $matchAgainst = null,
        bool                    $returnDefault = true,
        ?AbstractFieldValuePart &$resultReference = null
    ): ViewModel|ModelInterface|null
    {
        return $this->getViewModel(matchAgainst: $matchAgainst, returnDefault: $returnDefault, resultReference: $resultReference);
    }

    /**
     * Detects an appropriate viewmodel for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @param bool $returnDefault (optional) If no match is available. Return default instead
     * @param AbstractFieldValuePart|null $resultReference (optional) The object that was matched
     * @return ModelInterface|null
     *@throws InvalidArgumentException If the supplied and matched View Model could not be found.
     */
    public function getViewModel(
        ?array                  $matchAgainst = null,
        bool                    $returnDefault = true,
        ?AbstractFieldValuePart &$resultReference = null
    ): ViewModel|ModelInterface|null
    {
        $name = $this->getViewModelName(matchAgainst: $matchAgainst, returnDefault: $returnDefault, resultReference: $resultReference);

        if (! $name) {
            return;
        }

        if (! class_exists(class: $name)) {
            throw new InvalidArgumentException(message: 'The supplied View Model could not be found');
        }

        return new $name();
    }

    /**
     * Detects an appropriate viewmodel name for request.
     *
     * @param array $matchAgainst (optional) The Array to match against
     * @param bool $returnDefault (optional) If no match is available. Return default instead
     * @param AbstractFieldValuePart|null $resultReference (optional) The object that was matched.
     * @return ModelInterface|null Returns null if $returnDefault = false and no match could be made
     */
    public function getViewModelName(
        ?array                  $matchAgainst = null,
        bool                    $returnDefault = true,
        ?AbstractFieldValuePart &$resultReference = null
    ): string|ModelInterface|null
    {
        $res = $this->match(matchAgainst: $matchAgainst);
        if ($res) {
            $resultReference = $res;
            return $this->extractViewModelName(res: $res);
        }

        if ($returnDefault) {
            return $this->defaultViewModelName;
        }
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

        if ((! $matchAgainst && ! $this->defaultMatchAgainst) || ! $headers->has(name: 'accept')) {
            return;
        }

        if (! $matchAgainst) {
            $matchAgainst = $this->defaultMatchAgainst;
        }

        $matchAgainstString = '';
        foreach ($matchAgainst as $modelName => $modelStrings) {
            foreach ((array) $modelStrings as $modelString) {
                $matchAgainstString .= $this->injectViewModelName(modelAcceptString: $modelString, modelName: $modelName);
            }
        }

        /** @var Accept $accept */
        $accept = $headers->get(name: 'Accept');
        if (($res = $accept->match(matchAgainst: $matchAgainstString)) === false) {
            return;
        }

        return $res;
    }

    /**
     * Set the default View Model (name) to return if no match could be made
     *
     * @param string $defaultViewModelName The default View Model name
     * @return AcceptableViewModelSelector provides fluent interface
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
     * @return AcceptableViewModelSelector provides fluent interface
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
    public function getDefaultMatchAgainst(): array|array|null
    {
        return $this->defaultMatchAgainst;
    }

    /**
     * Inject the viewmodel name into the accept header string
     *
     * @param string $modelAcceptString
     * @param string $modelName
     * @return string
     */
    protected function injectViewModelName(string $modelAcceptString, string $modelName): string
    {
        $modelName         = str_replace(search: '\\', replace: '|', subject: $modelName);
        $modelAcceptString = is_array(value: $modelAcceptString)
            ? $modelAcceptString[key(array: $modelAcceptString)]
            : $modelAcceptString;
        return $modelAcceptString . '; ' . self::INJECT_VIEWMODEL_NAME . '="' . $modelName . '", ';
    }

    /**
     * Extract the viewmodel name from a match
     *
     * @return string
     */
    protected function extractViewModelName(AbstractFieldValuePart $res): string
    {
        $modelName = $res->getMatchedAgainst()->params[self::INJECT_VIEWMODEL_NAME];
        return str_replace(search: '|', replace: '\\', subject: $modelName);
    }

    /**
     * Get the request
     *
     * @return Request
     * @throws DomainException If unable to find request.
     */
    protected function getRequest(): Request
    {
        if ($this->request) {
            return $this->request;
        }

        $event   = $this->getEvent();
        $request = $event->getRequest();
        if (! $request instanceof Request) {
            throw new DomainException(
                message: 'The event used does not contain a valid Request, but must.'
            );
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
        if ($this->event) {
            return $this->event;
        }

        $controller = $this->getController();
        if (! $controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                message: 'A controller that implements InjectApplicationEventInterface '
                  . 'is required to use ' . self::class
            );
        }

        $event = $controller->getEvent();
        if (! $event instanceof MvcEvent) {
            $params = $event->getParams();
            $event  = new MvcEvent();
            $event->setParams(params: $params);
        }
        $this->event = $event;

        return $this->event;
    }
}
