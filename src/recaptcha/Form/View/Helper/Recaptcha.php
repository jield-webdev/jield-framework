<?php

declare(strict_types=1);

namespace Jield\Recaptcha\Form\View\Helper;

use Jield\Form\View\Helper\AbstractHelper;
use Jield\Form\ElementInterface;
use Override;

class Recaptcha extends AbstractHelper
{
    public function __invoke(?ElementInterface $element = null): string|static
    {
        return $element === null ? $this : $this->render($element);
    }

    public function render($element): string
    {
        assert($element instanceof \Jield\Recaptcha\Form\Element\Recaptcha);

        $scriptTag = sprintf(
            '<script src="//www.google.com/recaptcha/api.js?render=%s"></script>',
            $element->getSecret(),
        );

        $callBackScript = <<<SCRIPT
            <input type="hidden" name="%s" value="" id="recaptchaResponse">
            <script>grecaptcha.ready(function() {
                    grecaptcha.execute('%s', { action: 'submit' }).then(function (token) {
                        const recaptchaResponse = document.getElementById('recaptchaResponse');
                        recaptchaResponse.value = token;
                    })
                 });
                </script>
            SCRIPT;
        $callBack = sprintf($callBackScript, $element->getName(), $element->getSecret());

        return sprintf('%s%s', $scriptTag, $callBack);
    }
}
