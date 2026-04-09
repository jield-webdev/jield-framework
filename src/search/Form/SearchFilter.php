<?php

declare(strict_types=1);

namespace Jield\Search\Form;

use Jield\Form\Element\Search;
use Jield\Form\Element\Checkbox;
use Jield\Form\Element\Submit;
use Jield\Form\Element;
use Jield\Form\Form;

use function _;

class SearchFilter extends Form
{
    public function __construct()
    {
        parent::__construct();
        $this->setAttribute(key: 'method', value: 'get');
        $this->setAttribute(key: 'id', value: 'search-form');

        $this->add(
            elementOrFieldset: [
                'type'       => Search::class,
                'name'       => 'query',
                'attributes' => [
                    'class'       => 'form-control',
                    'placeholder' => _(message: 'txt-search'),
                ],
            ]
        );

        $this->add(
            elementOrFieldset: [
                'type' => Checkbox::class,
                'name' => 'onlyActive',
            ]
        );

        $this->add(
            elementOrFieldset: [
                'type'       => Submit::class,
                'name'       => 'search',
                'attributes' => [
                    'id'    => 'search',
                    'class' => 'btn btn-primary submitButton',
                    'value' => _(message: 'txt-search'),
                ],
            ]
        );

        $this->add(
            elementOrFieldset: [
                'type'       => Submit::class,
                'name'       => 'submit',
                'attributes' => [
                    'id'    => 'search',
                    'class' => 'btn btn-primary submitButton',
                    'value' => _(message: 'txt-search'),
                ],
            ]
        );

        $this->add(
            elementOrFieldset: [
                'type'       => Submit::class,
                'name'       => 'reset',
                'attributes' => [
                    'id'    => 'resetButton',
                    'class' => 'btn btn-warning resetButton',
                    'value' => _(message: 'txt-reset'),
                ],
            ]
        );
    }
}
