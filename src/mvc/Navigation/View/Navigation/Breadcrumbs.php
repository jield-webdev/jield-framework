<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View\Navigation;

use Laminas\Navigation;

use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Page\AbstractPage;
use Laminas\View;
use Laminas\View\Exception;
use function array_merge;
use function array_reverse;
use function count;
use function is_array;
use function is_string;
use function strlen;

/**
 * Helper for printing breadcrumbs.
 */
class Breadcrumbs extends AbstractHelper
{
    /**
     * Whether last page in breadcrumb should be hyperlinked.
     *
     * @var bool
     */
    protected $linkLast = false;

    /**
     * The minimum depth a page must have to be included when rendering.
     *
     * @var int
     */
    protected ?int $minDepth = 1;

    /**
     * Partial view script to use for rendering menu.
     *
     * @var string|array
     */
    protected $partial;

    /**
     * Breadcrumbs separator string.
     *
     * @var string
     */
    protected $separator = ' &gt; ';

    /**
     * Helper entry point.
     *
     * @param string|AbstractContainer|null $container container to operate on
     * @return Breadcrumbs
     */
    public function __invoke(AbstractContainer|string|null $container = null)
    {
        if (null !== $container) {
            $this->setContainer($container);
        }

        return $this;
    }

    /**
     * Renders helper.
     *
     * Implements {@link HelperInterface::render()}.
     *
     * @param string|null|Navigation\AbstractContainer $container [optional] container to render. Default is
     *                                      to render the container registered in the helper.
     * @return string
     */
    public function render($container = null)
    {
        $partial = $this->getPartial();
        if ($partial) {
            return $this->renderPartial(\Jield\Stdlib\Value::nullableInstance($container, \Laminas\Navigation\AbstractContainer::class), $partial);
        }

        return $this->renderStraight(\Jield\Stdlib\Value::nullableInstance($container, \Laminas\Navigation\AbstractContainer::class));
    }

    /**
     * Renders breadcrumbs by chaining 'a' elements with the separator
     * registered in the helper.
     *
     * @param AbstractContainer|null $container [optional] container to render. Default is
     *                                      to render the container registered in the helper.
     * @return string
     */
    public function renderStraight(?AbstractContainer $container = null)
    {
        $this->parseContainer($container);
        if (null === $container) {
            $container = $this->getContainer();
        }

        // find deepest active
        $active = $this->findActive($container);
        if (!isset($active['page'], $active['depth'])) {
            return '';
        }

        $active = $active['page'];

        // put the deepest active page last in breadcrumbs
        if ($this->getLinkLast()) {
            $html = $this->htmlify(\Jield\Stdlib\Value::instance($active, \Laminas\Navigation\Page\AbstractPage::class));
        } else {
            
            $escaper = $this->getView()->plugin('escapeHtml', \Laminas\View\Helper\EscapeHtml::class);
            /** @var mixed $label */
            $label   = $escaper(
                $this->translate(\Jield\Stdlib\Value::nullableString(\Jield\Stdlib\Value::present($active)->getLabel()), \Jield\Stdlib\Value::nullableString(\Jield\Stdlib\Value::present($active)->getTextDomain()))
            );
            $attribs = [
                'aria-current' => 'page',
            ];
            $html    = '<span' . $this->htmlAttribs($attribs) . '>' . \Jield\Stdlib\Value::stringify($label) . '</span>';
        }

        // walk back to root
        /** @var mixed $parent */
        while ($parent = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present(\Jield\Stdlib\Value::object($active)), 'getParent')) {
            if ($parent instanceof AbstractPage) {
                // prepend crumb to html
                $html = $this->htmlify($parent)
                    . $this->getSeparator()
                    . $html;
            }

            if ($parent === $container) {
                // at the root of the given container
                break;
            }

            /** @var mixed $active */
            $active = $parent;
        }

        return strlen($html) ? $this->getIndent() . $html : '';
    }

    /**
     * Renders the given $container by invoking the partial view helper.
     *
     * The container will simply be passed on as a model to the view script
     * as-is, and will be available in the partial script as 'container', e.g.
     * <code>echo 'Number of pages: ', count($this->container);</code>.
     *
     * @param AbstractContainer|null $container [optional] container to pass to view
     *     script. Default is to use the container registered in the helper.
     * @param array|string|null $partial [optional] partial view script to use.
     *     Default is to use the partial registered in the helper. If an array
     *     is given, the first value is used for the partial view script.
     * @return string
     * @throws Exception\RuntimeException         If no partial provided.
     * @throws Exception\InvalidArgumentException If partial is invalid array.
     */
    public function renderPartial(?AbstractContainer $container = null, array|string|null $partial = null)
    {
        return $this->renderPartialModel([], $container, $partial);
    }

    /**
     * Renders the given $container by invoking the partial view helper with the given parameters as the model.
     *
     * The container will simply be passed on as a model to the view script
     * as-is, and will be available in the partial script as 'container', e.g.
     * <code>echo 'Number of pages: ', count($this->container);</code>.
     *
     * Any parameters provided will be passed to the partial via the view model.
     *
     * @param AbstractContainer|null $container [optional] container to pass to view
     *     script. Default is to use the container registered in the helper.
     * @param array|string|null $partial [optional] partial view script to use.
     *     Default is to use the partial registered in the helper. If an array
     *     is given, the first value is used for the partial view script.
     * @return string
     * @throws Exception\RuntimeException         If no partial provided.
     * @throws Exception\InvalidArgumentException If partial is invalid array.
     */
    public function renderPartialWithParams(array $params = [], ?AbstractContainer $container = null, array|string|null $partial = null)
    {
        return $this->renderPartialModel($params, $container, $partial);
    }

    /**
     * Sets whether last page in breadcrumbs should be hyperlinked.
     *
     * @param bool $linkLast whether last page should be hyperlinked
     * @return Breadcrumbs
     */
    public function setLinkLast(bool $linkLast)
    {
        $this->linkLast = $linkLast;
        return $this;
    }

    /**
     * Returns whether last page in breadcrumbs should be hyperlinked.
     *
     * @return bool
     */
    public function getLinkLast()
    {
        return $this->linkLast;
    }

    /**
     * Sets which partial view script to use for rendering menu.
     *
     * @param array|string $partial partial view script or null. If an array is
     *     given, the first value is used for the partial view script.
     * @return Breadcrumbs
     */
    public function setPartial(array|string $partial)
    {
        $this->partial = $partial;
        return $this;
    }

    /**
     * Returns partial view script to use for rendering menu.
     *
     * @return string|array|null
     */
    public function getPartial()
    {
        return $this->partial;
    }

    /**
     * Sets breadcrumb separator.
     *
     * @param string $separator separator string
     * @return Breadcrumbs
     */
    public function setSeparator(string $separator)
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Returns breadcrumb separator.
     *
     * @return string breadcrumb separator
     */
    public function getSeparator()
    {
        return $this->separator;
    }

    /**
     * Render a partial with the given "model".
     *
     * @param AbstractContainer|null $container
     * @param array|string|null $partial
     * @return View\Helper\Partial|string
     * @throws Exception\RuntimeException         If no partial provided.
     * @throws Exception\InvalidArgumentException If partial is invalid array.
     */
    protected function renderPartialModel(array $params, ?AbstractContainer $container, array|string|null $partial)
    {
        $this->parseContainer($container);
        if (null === $container) {
            $container = $this->getContainer();
        }
        if (null === $partial) {
            $partial = $this->getPartial();
        }
        if (empty($partial)) {
            throw new Exception\RuntimeException(
                'Unable to render breadcrumbs: No partial view script provided'
            );
        }
        $model  = array_merge($params, ['pages' => []], ['separator' => $this->getSeparator()]);
        $active = $this->findActive($container);
        if (isset($active['page'], $active['depth'])) {
            $active           = $active['page'];
            $model['pages'][] = $active;
            while ($parent = \Jield\Stdlib\Value::present($active)->getParent()) {
                if (!$parent instanceof AbstractPage) {
                    break;
                }

                $model['pages'][] = $parent;
                if ($parent === $container) {
                    // break if at the root of the given container
                    break;
                }
                $active = $parent;
            }
            $model['pages'] = array_reverse($model['pages']);
        }

        
        $partialHelper = $this->getView()->plugin('partial', \Laminas\View\Helper\Partial::class);
        if (is_array($partial)) {
            if (count($partial) !== 2) {
                throw new Exception\InvalidArgumentException(
                    'Unable to render breadcrumbs: A view partial supplied as '
                    . 'an array must contain one value: the partial view script'
                );
            }

            return $partialHelper(\Jield\Stdlib\Value::nonEmptyString($partial[0]), \Jield\Stdlib\Value::namedValues($model));
        }

        return $partialHelper($partial, \Jield\Stdlib\Value::namedValues($model));
    }
}
