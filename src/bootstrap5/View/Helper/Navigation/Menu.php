<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\View\Helper\Navigation;

use Jield\Stdlib\Value;
use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Page\AbstractPage;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Helper\HtmlAttributes;
use RecursiveIteratorIterator;
use Webmozart\Assert\Assert;

use function implode;
use function is_int;
use function str_repeat;
use function trim;

final class Menu
{
    private string $ulClass = 'nav';
    private bool $renderInvisible = false;

    public function __construct(
        private readonly EscapeHtml $escapeHtml,
        private readonly EscapeHtmlAttr $escapeHtmlAttr,
        private readonly HtmlAttributes $htmlAttributes,
        private readonly ?TranslatorInterface $translator = null,
    ) {}

    /**
     * @param AbstractContainer $container
     * @param array<string, mixed> $options
     */
    public function __invoke(AbstractContainer $container, array $options = []): string
    {
        /** @mago-var mixed $ulClass */
        $ulClass = $options['ulClass'] ?? $this->ulClass;
        /** @mago-var mixed $indent */
        $indent = $options['indent'] ?? '';
        /** @mago-var mixed $minDepth */
        $minDepth = $options['minDepth'] ?? 0;
        /** @mago-var mixed $maxDepth */
        $maxDepth = $options['maxDepth'] ?? null;
        /** @mago-var mixed $onlyActive */
        $onlyActive = $options['onlyActive'] ?? false;
        /** @mago-var mixed $escapeLabels */
        $escapeLabels = $options['escapeLabels'] ?? true;
        /** @mago-var mixed $addClassToListItem */
        $addClassToListItem = $options['addClassToListItem'] ?? false;
        /** @mago-var mixed $liActiveClass */
        $liActiveClass = $options['liActiveClass'] ?? 'active';
        $this->renderInvisible = Value::truthy($options['renderInvisible'] ?? $this->renderInvisible);

        return $this->renderNormalMenu(
            container: $container,
            ulClass: Assert::string($ulClass),
            indent: Assert::string($indent),
            minDepth: Assert::integer($minDepth),
            maxDepth: Assert::nullOrInteger($maxDepth),
            onlyActive: Assert::boolean($onlyActive),
            escapeLabels: Assert::boolean($escapeLabels),
            addClassToListItem: Assert::boolean($addClassToListItem),
            liActiveClass: Assert::string($liActiveClass),
        );
    }

    private function renderNormalMenu(
        AbstractContainer $container,
        string $ulClass,
        string $indent,
        int $minDepth,
        ?int $maxDepth,
        bool $onlyActive,
        bool $escapeLabels,
        bool $addClassToListItem,
        string $liActiveClass,
    ): string {
        $html = '';

        $found = $this->findActive($container, $minDepth, $maxDepth);
        $foundPage = $found['page'] ?? null;
        $foundDepth = $found['depth'] ?? 0;

        $iterator = new RecursiveIteratorIterator(iterator: $container, mode: RecursiveIteratorIterator::SELF_FIRST);

        if (is_int($maxDepth)) {
            $iterator->setMaxDepth($maxDepth);
        }

        $prevDepth = -1;

        foreach ($iterator as $key => $page) {
            $depth = $iterator->getDepth();
            $isActive = $page->isActive(true);

            if ($depth === 0) {
                $page->setClass(trim('nav-link ' . (string) $page->getClass()));
            } elseif ($depth > 0) {
                $page->setClass(trim('dropdown-item ' . (string) $page->getClass()));
            }

            $parent = $page->getParent();
            if ($parent?->hasChildren() && $depth === 0) {
                $page->setClass(trim('dropdown-toggle ' . (string) $page->getClass()));
                $page->setId($key);
                $page->setOptions(['role' => 'button', 'data-bs-toggle' => 'dropdown', 'aria-expanded' => false]);
            }

            if ($depth < $minDepth || !$this->accept($page)) {
                continue;
            }

            if ($onlyActive && !$isActive) {
                $accept = false;
                if ($foundPage instanceof AbstractPage) {
                    if ($foundPage->hasPage($page)) {
                        $accept = true;
                    } else {
                        $foundParent = $foundPage->getParent();
                        if ($foundParent && $foundParent->hasPage($page)) {
                            if (
                                !$foundPage->hasPages(!$this->renderInvisible)
                                || is_int($maxDepth) && ($foundDepth + 1) > $maxDepth
                            ) {
                                $accept = true;
                            }
                        }
                    }
                }
                if (!$accept) {
                    continue;
                }
            }

            $depth -= $minDepth;
            $myIndent = $indent . str_repeat('        ', Assert::notNegativeInteger($depth));

            if ($depth > $prevDepth) {
                if ($ulClass !== '' && $depth === 0) {
                    $ulClass = ' class="' . Value::stringify($this->escapeHtmlAttr->__invoke($ulClass)) . '"';
                } else {
                    $ulClass = ' class="dropdown-menu" aria-labelledby="' . $key . '"';
                }
                $html .= $myIndent . '<ul' . $ulClass . '>' . PHP_EOL;
            } elseif ($prevDepth > $depth) {
                for ($i = $prevDepth; $i > $depth; $i--) {
                    $ind = $indent . str_repeat('        ', Assert::notNegativeInteger($i));
                    $html .= $ind . '    </li>' . PHP_EOL;
                    $html .= $ind . '</ul>' . PHP_EOL;
                }
                $html .= $myIndent . '    </li>' . PHP_EOL;
            } else {
                $html .= $myIndent . '    </li>' . PHP_EOL;
            }

            $liClasses = ['nav-item'];

            if ($depth === 0 && $parent?->hasChildren()) {
                $liClasses[] = 'dropdown';
            }

            if ($isActive) {
                $liClasses[] = $liActiveClass;
            }

            if ($addClassToListItem && $page->getClass()) {
                $liClasses[] = $page->getClass();
            }

            $liClass = ' class="' . Value::stringify($this->escapeHtmlAttr->__invoke(implode(' ', $liClasses))) . '"';
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

            $prevDepth = $depth;
        }

        if ($html !== '') {
            for ($i = $prevDepth + 1; $i > 0; $i--) {
                $myIndent = $indent . str_repeat('        ', $i - 1);
                $html .= $myIndent . '    </li>' . PHP_EOL . $myIndent . '</ul>' . PHP_EOL;
            }
            $html = rtrim($html, PHP_EOL);
        }

        return $html;
    }

    private function htmlify(AbstractPage $page, bool $escapeLabel = true, bool $addClassToListItem = false): string
    {
        $attribs = [
            'id' => $page->getId(),
            'title' => $this->translate($page->getTitle(), $page->getTextDomain()),
        ];

        if (null !== $page->get('role')) {
            $attribs['role'] = $page->get('role');
        }
        if (null !== $page->get('data-bs-toggle')) {
            $attribs['data-bs-toggle'] = $page->get('data-bs-toggle');
        }
        if (null !== $page->get('aria-expanded')) {
            $attribs['aria-expanded'] = $page->get('aria-expanded');
        }

        if (!$addClassToListItem) {
            $attribs['class'] = $page->getClass();
        }

        $href = $page->getHref();
        if ($href) {
            $element = 'a';
            $attribs['href'] = $href;
            $attribs['target'] = $page->getTarget();
        } else {
            $element = 'span';
        }

        $html = '<' . $element . $this->htmlAttribs(Value::scalarMap($attribs)) . '>';
        $label = $this->translate($page->getLabel(), $page->getTextDomain());

        if ($escapeLabel) {
            $html .= Value::stringify($this->escapeHtml->__invoke($label));
        } else {
            $html .= $label;
        }

        $html .= '</' . $element . '>';
        return $html;
    }

    /**
     * @param array<string, scalar|null> $attribs
     */
    private function htmlAttribs(array $attribs): string
    {
        return (string) ($this->htmlAttributes)($attribs);
    }

    private function accept(AbstractPage $page): bool
    {
        if (!$this->renderInvisible && !$page->isVisible()) {
            return false;
        }

        return true;
    }

    /**
     * @return array{page: AbstractPage, depth: int}|null
     */
    private function findActive(AbstractContainer $container, int $minDepth, ?int $maxDepth): ?array
    {
        $found = null;

        $iterator = new RecursiveIteratorIterator(iterator: $container, mode: RecursiveIteratorIterator::SELF_FIRST);

        if (is_int($maxDepth)) {
            $iterator->setMaxDepth($maxDepth);
        }

        foreach ($iterator as $page) {
            $depth = $iterator->getDepth();
            if ($depth < $minDepth || !$this->accept($page)) {
                continue;
            }

            if (!$page->isActive(true)) {
                continue;
            }

            if ($found === null || $depth >= $found['depth']) {
                $found = ['page' => $page, 'depth' => $depth];
            }
        }

        return $found;
    }

    private function translate(?string $text, ?string $textDomain = null): string
    {
        if ($text === null) {
            return '';
        }

        if ($this->translator === null) {
            return $text;
        }

        return $this->translator->translate($text, $textDomain ?? TranslatorInterface::DEFAULT_TEXT_DOMAIN);
    }
}
