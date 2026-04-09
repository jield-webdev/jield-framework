<?php

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Laminas\View\Model\ModelInterface as Model;

class Layout extends AbstractPlugin
{
    /** @var MvcEvent */
    protected MvcEvent $event;

    /**
     * Set the layout template
     *
     * @param string $template
     * @return Layout
     */
    public function setTemplate(string $template): static
    {
        $viewModel = $this->getViewModel();
        $viewModel->setTemplate(template: $template);
        return $this;
    }

    /**
     * Invoke as a functor
     *
     * If no arguments are given, grabs the "root" or "layout" view model.
     * Otherwise, attempts to set the template for that view model.
     *
     * @param string|null $template
     * @return Model|Layout
     */
    public function __invoke(?string $template = null): Layout|Model|static
    {
        if (null === $template) {
            return $this->getViewModel();
        }
        return $this->setTemplate(template: $template);
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
        if (! $controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                message: 'Layout plugin requires a controller that implements InjectApplicationEventInterface'
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

    /**
     * Retrieve the root view model from the event
     *
     * @return Model
     * @throws Exception\DomainException
     */
    protected function getViewModel(): Model
    {
        $event     = $this->getEvent();
        $viewModel = $event->getViewModel();
        if (! $viewModel instanceof Model) {
            throw new DomainException(message: 'Layout plugin requires that event view model is populated');
        }
        return $viewModel;
    }
}
