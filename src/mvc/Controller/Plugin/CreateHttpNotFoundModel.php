<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\View\Model\ViewModel;
use Laminas\Http\Response;

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
