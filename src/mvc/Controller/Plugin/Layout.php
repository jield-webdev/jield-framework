<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\View\Model\ModelInterface as Model;

class Layout extends AbstractPlugin
{
    /** @var MvcEvent */
    protected ?MvcEvent $event = null;

    /**
     * Set the layout template
     *
     * @param  string $template
     * @return static
     */
    public function setTemplate($template): static
    {
        $viewModel = $this->getViewModel();
        $viewModel->setTemplate(Value::nonEmptyString($template));
        return $this;
    }

    /**
     * Invoke as a functor
     *
     * If no arguments are given, grabs the "root" or "layout" view model.
     * Otherwise, attempts to set the template for that view model.
     *
     * @param  null|string $template
     * @return Model|static
     */
    public function __invoke($template = null): Model|static
    {
        if (null === $template) {
            return $this->getViewModel();
        }
        return $this->setTemplate($template);
    }

    /**
     * Get the event
     *
     * @return MvcEvent
     * @throws Exception\DomainException If unable to find event.
     */
    protected function getEvent(): MvcEvent
    {
        if ($this->event) {
            return $this->event;
        }

        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                'Layout plugin requires a controller that implements InjectApplicationEventInterface',
            );
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

    /**
     * Retrieve the root view model from the event
     *
     * @return Model
     * @throws Exception\DomainException
     */
    protected function getViewModel(): Model
    {
        $event = $this->getEvent();
        $viewModel = $event->getViewModel();

        return $viewModel;
    }
}
