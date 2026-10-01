<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Observer;

use CustomGento\AiImageLabel\Model\Source\AiContent;
use CustomGento\AiImageLabel\Model\Source\LabelColor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Swaps the admin media gallery template for our copy, which adds the AI label fields to the image
 * detail modal, and hands it the options to render - the template spells out no values of its own.
 *
 * Runs after Magento_ProductVideo's own template swap (see module.xml sequence).
 */
class ChangeGalleryTemplateObserver implements ObserverInterface
{
    public function __construct(
        private readonly AiContent $aiContent,
        private readonly LabelColor $labelColor,
    ) {
    }

    public function execute(Observer $observer): void
    {
        $block = $observer->getBlock();
        $block->setTemplate('CustomGento_AiImageLabel::helper/gallery.phtml');
        $block->setData('aiContentOptions', $this->aiContent->toOptionArray());
        $block->setData('aiLabelColorOptions', $this->labelColor->toOptionArray());
    }
}
