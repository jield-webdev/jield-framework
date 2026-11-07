<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View\Navigation;

use Jield\Stdlib\Value;
use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Page\AbstractPage;
use Laminas\View\Exception;
use Laminas\View\Helper\Partial;
use RecursiveIteratorIterator;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_merge;
use function count;
use function implode;
use function is_array;
use function is_int;
use function rtrim;
use function str_repeat;

use const PHP_EOL;

/**
 * Helper for rendering menus from navigation containers.
 */
class Menu extends AbstractHelper
{
    /**
     * Whether page class should be applied to <li> element.
     *
     * @var bool
     */
    protected bool $addClassToListItem = false;

    /**
     * Whether labels should be escaped.
     *
     * @var bool
     */
    protected bool $escapeLabels = true;

    /**
     * Whether only active branch should be rendered.
     *
     * @var bool
     */
    protected bool $onlyActiveBranch = false;

    /**
     * Partial view script to use for rendering menu.
     *
     * @var string|array|null
     */
    protected string|array|null $partial = null;

    /**
     * Whether parents should be rendered when only rendering active branch.
     *
     * @var bool
     */
    protected bool $renderParents = true;

    /**
     * CSS class to use for the ul element.
     *
     * @var string
     */
    protected string $ulClass = 'navigation';

    /**
     * CSS class to use for the active li element.
     *
     * @var string
     */
    protected string $liActiveClass = 'active';

    /**
     * View helper entry point.
     *
     * Retrieves helper and optionally sets container to operate on.
     *
     * @param AbstractContainer|null $container [optional] container to operate on
     * @return self
     */
    public function __invoke(?AbstractContainer $container = null)
    {
        if (null !== $container) {
            $this->setContainer($container);
        }

        return $this;
    }

    /**
     * Renders menu.
     *
     * Implements {@link HelperInterface::render()}.
     *
     * If a partial view is registered in the helper, the menu will be rendered
     * using the given partial script. If no partial is registered, the menu
     * will be rendered as an 'ul' element by the helper's internal method.
     *
     * @param AbstractContainer|string|null $container [optional] container to render. Default is
     *     to render the container registered in the helper.
     * @return string
     * @see renderPartial()
     * @see renderMenu()
     *
     */
    public function render($container = null): Partial|string
    {
        $this->parseContainer($container);
        $partial = $this->getPartial();
        if ($partial) {
            return $this->renderPartial($container, $partial);
        }

        return $this->renderMenu($container);
    }

    /**
     * Renders the deepest active menu within [$minDepth, $maxDepth], (called from {@link renderMenu()}).
     *
     * @param AbstractContainer $container container to render
     * @param string $ulClass CSS class for first UL
     * @param string $indent initial indentation
     * @param int|null $minDepth minimum depth
     * @param int|null $maxDepth maximum depth
     * @param bool $escapeLabels Whether or not to escape the labels
     * @param bool $addClassToListItem Whether or not page class applied to <li> element
     * @param string $liActiveClass CSS class for active LI
     * @return string
     */
    protected function renderDeepestMenu(
        AbstractContainer $container,
        string $ulClass,
        string $indent,
        ?int $minDepth,
        ?int $maxDepth,
        bool $escapeLabels,
        bool $addClassToListItem,
        string $liActiveClass,
    ) {
        $active = $this->findActive($container, ($minDepth ?? 0) - 1, $maxDepth);
        if (!isset($active['page'], $active['depth'])) {
            return '';
        }

        // special case if active page is one below minDepth
        if ($active['depth'] < ($minDepth ?? 0)) {
            if (!$active['page']->hasPages(!$this->renderInvisible)) {
                return '';
            }
        } elseif (!$active['page']->hasPages(!$this->renderInvisible)) {
            // found pages has no children; render siblings
            $active['page'] = $active['page']->getParent();
        } elseif (is_int($maxDepth) && ($active['depth'] + 1) > $maxDepth) {
            // children are below max depth; render siblings
            $active['page'] = $active['page']->getParent();
        }

        $escaper = $this->getView()->plugin('escapeHtmlAttr', \Laminas\View\Helper\EscapeHtmlAttr::class);
        $ulClass = $ulClass ? ' class="' . Value::stringify($escaper($ulClass)) . '"' : '';
        $html = $indent . '<ul' . $ulClass . '>' . PHP_EOL;

        foreach ($active['page'] ?? [] as $subPage) {
            if (!$this->accept($subPage)) {
                continue;
            }

            // render li tag and page
            $liClasses = [];

            // Is page active?
            if ($subPage->isActive(true)) {
                $liClasses[] = $liActiveClass;
            }

            // Add CSS class from page to <li>
            if ($addClassToListItem && $subPage->getClass()) {
                $liClasses[] = $subPage->getClass();
            }

            $liClass = empty($liClasses) ? '' : ' class="' . Value::stringify($escaper(implode(' ', $liClasses))) . '"';
            $html .= $indent . '    <li' . $liClass . '>' . PHP_EOL;
            $html .= $indent . '        ' . $this->htmlify($subPage, $escapeLabels, $addClassToListItem) . PHP_EOL;
            $html .= $indent . '    </li>' . PHP_EOL;
        }

        $html .= $indent . '</ul>';

        return $html;
    }

    /**
     * Renders helper.
     *
     * Renders a HTML 'ul' for the given $container. If $container is not given,
     * the container registered in the helper will be used.
     *
     * Available $options:
     *
     * @param AbstractContainer|null $container [optional] container to create menu from.
     *     Default is to use the container retrieved from {@link getContainer()}.
     * @param array $options [optional] options for controlling rendering
     * @return string
     */
    public function renderMenu(?AbstractContainer $container = null, array $options = [])
    {
        $this->parseContainer($container);
        $container ??= $this->getContainer();

        $options = $this->normalizeOptions($options);
        if (Value::truthy($options['onlyActiveBranch']) && !$options['renderParents']) {
            return $this->renderDeepestMenu(
                $container,
                $options['ulClass'],
                $options['indent'],
                $options['minDepth'],
                $options['maxDepth'],
                $options['escapeLabels'],
                $options['addClassToListItem'],
                $options['liActiveClass'],
            );
        }

        return $this->renderNormalMenu(
            $container,
            $options['ulClass'],
            $options['indent'],
            $options['minDepth'],
            $options['maxDepth'],
            $options['onlyActiveBranch'],
            $options['escapeLabels'],
            $options['addClassToListItem'],
            $options['liActiveClass'],
        );
    }

    /**
     * Renders a normal menu (called from {@link renderMenu()}).
     *
     * @param AbstractContainer $container container to render
     * @param string $ulClass CSS class for first UL
     * @param string $indent initial indentation
     * @param int|null $minDepth minimum depth
     * @param int|null $maxDepth maximum depth
     * @param bool $onlyActive render only active branch?
     * @param bool $escapeLabels Whether or not to escape the labels
     * @param bool $addClassToListItem Whether or not page class applied to <li> element
     * @param string $liActiveClass CSS class for active LI
     * @return string
     */
    protected function renderNormalMenu(
        AbstractContainer $container,
        string $ulClass,
        string $indent,
        ?int $minDepth,
        ?int $maxDepth,
        bool $onlyActive,
        bool $escapeLabels,
        bool $addClassToListItem,
        string $liActiveClass,
    ): string {
        $html = '';

        // find deepest active
        $found = $this->findActive($container, $minDepth, $maxDepth);

        $escaper = $this->getView()->plugin('escapeHtmlAttr', \Laminas\View\Helper\EscapeHtmlAttr::class);

        $foundPage = null;
        $foundDepth = 0;

        if (isset($found['page'], $found['depth'])) {
            $foundPage = $found['page'];
            $foundDepth = $found['depth'];
        }

        // create iterator
        $iterator = new RecursiveIteratorIterator($container, RecursiveIteratorIterator::SELF_FIRST);

        if (is_int($maxDepth)) {
            $iterator->setMaxDepth($maxDepth);
        }

        // iterate container
        $prevDepth = -1;
        foreach ($iterator as $page) {
            $depth = $iterator->getDepth();
            $isActive = $page->isActive(true);
            if ($depth < ($minDepth ?? 0) || !$this->accept($page)) {
                // page is below minDepth or not accepted by acl/visibility
                continue;
            } elseif ($onlyActive && !$isActive) {
                // page is not active itself, but might be in the active branch
                $accept = false;
                if ($foundPage) {
                    if ($foundPage->hasPage($page)) {
                        // accept if page is a direct child of the active page
                        $accept = true;
                    } elseif (Assert::notNull($foundPage->getParent())->hasPage($page)) {
                        // page is a sibling of the active page...
                        if (
                            !$foundPage->hasPages(!$this->renderInvisible)
                            || is_int($maxDepth) && ($foundDepth + 1) > $maxDepth
                        ) {
                            // accept if active page has no children, or the
                            // children are too deep to be rendered
                            $accept = true;
                        }
                    }
                }
                if (!$accept) {
                    continue;
                }
            }

            // make sure indentation is correct
            $depth -= $minDepth ?? 0;
            $myIndent = $indent . str_repeat('        ', Assert::notNegativeInteger($depth));
            if ($depth > $prevDepth) {
                // start new ul tag
                if ($ulClass && $depth === 0) {
                    $ulClass = ' class="' . Value::stringify($escaper($ulClass)) . '"';
                } else {
                    $ulClass = '';
                }
                $html .= $myIndent . '<ul' . $ulClass . '>' . PHP_EOL;
            } elseif ($prevDepth > $depth) {
                // close li/ul tags until we're at current depth
                for ($i = $prevDepth; $i > $depth; $i--) {
                    $ind = $indent . str_repeat('        ', Assert::notNegativeInteger($i));
                    $html .= $ind . '    </li>' . PHP_EOL;
                    $html .= $ind . '</ul>' . PHP_EOL;
                }
                // close previous li tag
                $html .= $myIndent . '    </li>' . PHP_EOL;
            } else {
                // close previous li tag
                $html .= $myIndent . '    </li>' . PHP_EOL;
            }

            // render li tag and page
            $liClasses = [];

            // Is page active?
            if ($isActive) {
                $liClasses[] = $liActiveClass;
            }

            // Add CSS class from page to <li>
            if ($addClassToListItem && $page->getClass()) {
                $liClasses[] = $page->getClass();
            }
            $liClass = empty($liClasses) ? '' : ' class="' . Value::stringify($escaper(implode(' ', $liClasses))) . '"';
            $html .=
                $myIndent
                . '    <li'
                . $liClass
                . '>'
                . PHP_EOL
                . $myIndent
                . '        '
                . $this->htmlify($page, $escapeLabels, $addClassToListItem)
                . PHP_EOL;

            // store as previous depth for next iteration
            $prevDepth = $depth;
        }

        if ($html) {
            // done iterating container; close open ul/li tags
            for ($i = $prevDepth + 1; $i > 0; $i--) {
                $myIndent = $indent . str_repeat('        ', $i - 1);
                $html .= $myIndent . '    </li>' . PHP_EOL . $myIndent . '</ul>' . PHP_EOL;
            }
            $html = rtrim($html, PHP_EOL);
        }

        return $html;
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
    public function renderPartialWithParams(
        array $params = [],
        ?AbstractContainer $container = null,
        array|string|null $partial = null,
    ) {
        return $this->renderPartialModel($params, $container, $partial);
    }

    /**
     * Renders the inner-most sub menu for the active page in the $container.
     *
     * This is a convenience method which is equivalent to the following call:
     * <code>
     * renderMenu($container, array(
     *     'indent'           => $indent,
     *     'ulClass'          => $ulClass,
     *     'minDepth'         => null,
     *     'maxDepth'         => null,
     *     'onlyActiveBranch' => true,
     *     'renderParents'    => false,
     *     'liActiveClass'    => $liActiveClass
     * ));
     * </code>
     *
     * @param AbstractContainer|null $container [optional] container to render.
     *     Default is to render the container registered in the helper.
     * @param string|null $ulClass [optional] CSS class to use for UL element.
     *     Default is to use the value from {@link getUlClass()}.
     * @param int|string|null $indent [optional] indentation as a string or number
     *     of spaces. Default is to use the value retrieved from
     *     {@link getIndent()}.
     * @param string|null $liActiveClass [optional] CSS class to use for UL
     *     element. Default is to use the value from {@link getUlClass()}.
     * @return string
     */
    public function renderSubMenu(
        ?AbstractContainer $container = null,
        ?string $ulClass = null,
        int|string|null $indent = null,
        ?string $liActiveClass = null,
    ) {
        return $this->renderMenu($container, [
            'indent' => $indent,
            'ulClass' => $ulClass,
            'minDepth' => null,
            'maxDepth' => null,
            'onlyActiveBranch' => true,
            'renderParents' => false,
            'escapeLabels' => true,
            'addClassToListItem' => false,
            'liActiveClass' => $liActiveClass,
        ]);
    }

    /**
     * Returns an HTML string containing an 'a' element for the given page if
     * the page's href is not empty, and a 'span' element if it is empty.
     *
     * Overrides {@link AbstractHelper::htmlify()}.
     *
     * @param AbstractPage $page page to generate HTML for
     * @param bool $escapeLabel Whether or not to escape the label
     * @param bool $addClassToListItem Whether or not to add the page class to the list item
     * @return string
     */
    public function htmlify(AbstractPage $page, bool $escapeLabel = true, bool $addClassToListItem = false): string
    {
        // get attribs for element
        $attribs = [
            'id' => $page->getId(),
            'title' => $this->translate($page->getTitle(), $page->getTextDomain()),
        ];

        if ($addClassToListItem === false) {
            $attribs['class'] = $page->getClass();
        }

        // does page have a href?
        $href = $page->getHref();
        if ($href) {
            $element = 'a';
            $attribs['href'] = $href;
            $attribs['target'] = $page->getTarget();
        } else {
            $element = 'span';
        }

        if ($page->isActive()) {
            $attribs['aria-current'] = 'page';
        }

        $html = '<' . $element . $this->htmlAttribs($attribs) . '>';
        $label = $this->translate($page->getLabel(), $page->getTextDomain());

        if ($escapeLabel === true) {
            $escaper = $this->getView()->plugin('escapeHtml', \Laminas\View\Helper\EscapeHtml::class);
            $html .= Value::stringify($escaper($label));
        } else {
            $html .= $label;
        }

        $html .= '</' . $element . '>';
        return $html;
    }

    /**
     * Normalizes given render options.
     *
     * @param array $options [optional] options to normalize
     * @return array
     */
    protected function normalizeOptions(array $options = [])
    {
        if (isset($options['indent'])) {
            $options['indent'] = $this->getWhitespace($options['indent']);
        } else {
            $options['indent'] = $this->getIndent();
        }

        if (isset($options['ulClass'])) {
            $options['ulClass'] = (string) $options['ulClass'];
        } else {
            $options['ulClass'] = $this->getUlClass();
        }

        if (array_key_exists('minDepth', $options)) {
            if (null !== $options['minDepth']) {
                $options['minDepth'] = (int) $options['minDepth'];
            }
        } else {
            $options['minDepth'] = $this->getMinDepth();
        }

        if ($options['minDepth'] === null || $options['minDepth'] < 0) {
            $options['minDepth'] = 0;
        }

        if (array_key_exists('maxDepth', $options)) {
            if (null !== $options['maxDepth']) {
                $options['maxDepth'] = (int) $options['maxDepth'];
            }
        } else {
            $options['maxDepth'] = $this->getMaxDepth();
        }

        $options['onlyActiveBranch'] ??= $this->getOnlyActiveBranch();

        $options['escapeLabels'] ??= $this->escapeLabels;

        $options['renderParents'] ??= $this->getRenderParents();

        $options['addClassToListItem'] ??= $this->getAddClassToListItem();

        if (isset($options['liActiveClass'])) {
            $options['liActiveClass'] = (string) $options['liActiveClass'];
        } else {
            $options['liActiveClass'] = $this->getLiActiveClass();
        }

        return $options;
    }

    /**
     * Sets a flag indicating whether labels should be escaped.
     *
     * @param bool $flag [optional] escape labels
     * @return self
     */
    public function escapeLabels(bool $flag = true)
    {
        $this->escapeLabels = $flag;
        return $this;
    }

    /**
     * Enables/disables page class applied to <li> element.
     *
     * @param bool $flag [optional] page class applied to <li> element Default
     *     is true.
     * @return self fluent interface, returns self
     */
    public function setAddClassToListItem(bool $flag = true)
    {
        $this->addClassToListItem = $flag;
        return $this;
    }

    /**
     * Returns flag indicating whether page class should be applied to <li> element.
     *
     * By default, this value is false.
     *
     * @return bool whether parents should be rendered
     */
    public function getAddClassToListItem()
    {
        return $this->addClassToListItem;
    }

    /**
     * Sets a flag indicating whether only active branch should be rendered.
     *
     * @param bool $flag [optional] render only active branch.
     * @return self
     */
    public function setOnlyActiveBranch(bool $flag = true)
    {
        $this->onlyActiveBranch = $flag;
        return $this;
    }

    /**
     * Returns a flag indicating whether only active branch should be rendered.
     *
     * By default, this value is false, meaning the entire menu will be
     * be rendered.
     *
     * @return bool
     */
    public function getOnlyActiveBranch()
    {
        return $this->onlyActiveBranch;
    }

    /**
     * Sets which partial view script to use for rendering menu.
     *
     * @param array|string $partial partial view script or null. If an array
     *     is given, the first value is used for the partial view script.
     * @return self
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
     * Enables/disables rendering of parents when only rendering active branch.
     *
     * See {@link setOnlyActiveBranch()} for more information.
     *
     * @param bool $flag [optional] render parents when rendering active branch.
     * @return self
     */
    public function setRenderParents(bool $flag = true)
    {
        $this->renderParents = $flag;
        return $this;
    }

    /**
     * Returns flag indicating whether parents should be rendered when rendering only the active branch.
     *
     * By default, this value is true.
     *
     * @return bool
     */
    public function getRenderParents()
    {
        return $this->renderParents;
    }

    /**
     * Sets CSS class to use for the first 'ul' element when rendering.
     *
     * @param string $ulClass CSS class to set
     * @return self
     */
    public function setUlClass(string $ulClass)
    {
        $this->ulClass = $ulClass;
        return $this;
    }

    /**
     * Returns CSS class to use for the first 'ul' element when rendering.
     *
     * @return string
     */
    public function getUlClass()
    {
        return $this->ulClass;
    }

    /**
     * Sets CSS class to use for the active 'li' element when rendering.
     *
     * @param string $liActiveClass CSS class to set
     * @return self
     */
    public function setLiActiveClass(string $liActiveClass)
    {
        $this->liActiveClass = $liActiveClass;
        return $this;
    }

    /**
     * Returns CSS class to use for the active 'li' element when rendering.
     *
     * @return string
     */
    public function getLiActiveClass()
    {
        return $this->liActiveClass;
    }

    /**
     * Render a partial with the given "model".
     *
     * @param AbstractContainer|null $container
     * @param array|string|null $partial
     * @return string
     * @throws Exception\RuntimeException         If no partial provided.
     * @throws Exception\InvalidArgumentException If partial is invalid array.
     */
    protected function renderPartialModel(array $params, ?AbstractContainer $container, array|string|null $partial)
    {
        $this->parseContainer($container);
        $container ??= $this->getContainer();

        $partial ??= $this->getPartial();

        if (empty($partial)) {
            throw new Exception\RuntimeException('Unable to render menu: No partial view script provided');
        }

        $model = array_merge($params, ['container' => $container]);

        $partialHelper = $this->getView()->plugin('partial', \Laminas\View\Helper\Partial::class);
        if (is_array($partial)) {
            if (count($partial) !== 2) {
                throw new Exception\InvalidArgumentException('Unable to render menu: A view partial supplied as '
                . 'an array must contain one value: the partial view script');
            }

            return $partialHelper(Assert::stringNotEmpty($partial[0]), $model);
        }

        return $partialHelper($partial, $model);
    }
}
