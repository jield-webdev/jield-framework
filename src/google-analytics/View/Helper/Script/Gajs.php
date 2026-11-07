<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\View\Helper\Script;

use Jield\GoogleAnalytics\Analytics\CustomVariable;
use Jield\GoogleAnalytics\Analytics\Ecommerce\Item;
use Jield\GoogleAnalytics\Analytics\Ecommerce\Transaction;
use Jield\GoogleAnalytics\Analytics\Event;
use Jield\GoogleAnalytics\Analytics\Tracker;
use Override;

use function json_encode;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:too-many-methods(1)
class Gajs implements ScriptInterface
{
    protected Tracker $tracker;

    #[Override]
    public function setTracker(Tracker $tracker): Gajs
    {
        $this->tracker = $tracker;

        return $this;
    }

    #[Override]
    public function getCode(): ?string
    {
        // Do not render when tracker is disabled
        if (!$this->tracker->isEnabled()) {
            return null;
        }

        $script = $this->getVarCreate();

        $script .= $this->getLoadScript();

        $script .= $this->prepareSetAccount();
        $script .= $this->prepareTrackEvents();
        $script .= $this->prepareTransactions();
        $script .= $this->prepareCustomVariables();

        return $script;
    }

    protected function getLoadScript(): string
    {
        return <<<SCRIPT
            window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());\n
            SCRIPT;
    }

    protected function getVarCreate(): string
    {
        return '';
    }

    protected function push(string $methodName, array|string $values = ''): string
    {
        $values = is_array(value: $values) ? json_encode(value: $values) : sprintf('"%s"', $values);

        return sprintf("gtag('%s',%s);" . PHP_EOL, $methodName, $values);
    }

    protected function prepareSetAccount(): string
    {
        return $this->push(methodName: 'config', values: $this->tracker->getId());
    }

    protected function prepareCustomVariables(): string
    {
        $customVariables = $this->tracker->getCustomVariables();
        $output = '';

        foreach ($customVariables as $variable) {
            $output .= $this->prepareCustomVariable(customVariable: $variable);
        }

        return $output;
    }

    protected function prepareCustomVariable(CustomVariable $customVariable): string
    {
        $data = [
            $customVariable->getIndex(),
            $customVariable->getName(),
            $customVariable->getValue(),
            $customVariable->getScope(),
        ];

        return $this->push(methodName: 'setCustomVar', values: $data);
    }

    protected function prepareTrackEvents(): string
    {
        $events = $this->tracker->getEvents();
        $output = '';

        foreach ($events as $event) {
            $output .= $this->prepareTrackEvent(event: $event);
        }

        return $output;
    }

    protected function prepareTrackEvent(Event $event): string
    {
        return $this->push(methodName: 'trackEvent', values: [
            $event->getCategory(),
            $event->getAction(),
            $event->getLabel(),
            $event->getValue(),
        ]);
    }

    protected function prepareTransactions(): string
    {
        $transactions = $this->tracker->getTransactions();
        $output = '';

        foreach ($transactions as $transaction) {
            $output .= $this->prepareTransaction(transaction: $transaction);
        }

        if ($output !== '') {
            $output .= $this->push(methodName: 'trackTrans');
        }

        return $output;
    }

    protected function prepareTransaction(Transaction $transaction): string
    {
        return (
            $this->push(methodName: 'addTrans', values: [
                $transaction->getId(),
                $transaction->getAffiliation(),
                $transaction->getTotal(),
                $transaction->getTax(),
                $transaction->getShipping(),
                $transaction->getCity(),
                $transaction->getState(),
                $transaction->getCountry(),
            ]) . $this->prepareTransactionItems(transaction: $transaction)
        );
    }

    protected function prepareTransactionItems(Transaction $transaction): string
    {
        $output = '';
        $items = $transaction->getItems();

        foreach ($items as $item) {
            $output .= $this->prepareTransactionItem(transaction: $transaction, item: $item);
        }

        return $output;
    }

    protected function prepareTransactionItem(Transaction $transaction, Item $item): string
    {
        return $this->push(methodName: 'addItem', values: [
            $transaction->getId(),
            $item->getSku(),
            $item->getProduct(),
            $item->getCategory(),
            $item->getPrice(),
            $item->getQuantity(),
        ]);
    }
}
