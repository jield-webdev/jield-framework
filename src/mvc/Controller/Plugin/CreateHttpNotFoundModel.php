<?php

namespace Jield\Mvc\Controller\Plugin;

use Laminas\Http\Response;
use Laminas\View\Model\ViewModel;

class CreateHttpNotFoundModel extends AbstractPlugin
{
    /**
     * Create an HTTP view model representing a "not found" page
     *
     * @return ViewModel
     */
    public function __invoke(Response $response): ViewModel
    {
        $response->setStatusCode(404);

        return new ViewModel(['content' => 'Page not found']);
    }
}
