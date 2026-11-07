<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Laminas\View\Model\ModelInterface as Model;
use Webmozart\Assert\Assert;

class Layout extends AbstractPlugin
{
    protected ?MvcEvent $event = null;

    /**
     * Set the layout template
     *
     * @param  string $template
     */
    public function setTemplate($template): static
    {
        $viewModel = $this->getViewModel();
        $viewModel->setTemplate(Assert::stringNotEmpty($template));
        return $this;
    }

    /**
     * Invoke as a functor
     *
     * If no arguments are given, grabs the "root" or "layout" view model.
     * Otherwise, attempts to set the template for that view model.
     *
     * @param  null|string $template
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
     * @throws Exception\DomainException
     */
    protected function getViewModel(): Model
    {
        $event = $this->getEvent();
        return $event->getViewModel();
    }
}
