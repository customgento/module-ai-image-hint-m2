<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Observer;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Loads the AI hints of a storefront product collection (listings, widgets, related, up-sells, ...) in one query,
 * instead of one query per product image as it renders.
 */
class PreloadAiHintsObserver implements ObserverInterface
{
    public function __construct(
        private readonly AiImageHint $aiImageHint,
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->aiImageHint->preload($observer->getData('collection')->getItems());
    }
}
