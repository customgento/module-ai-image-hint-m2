<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Block\Product\Image;
use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;

/**
 * Puts the AI mark on every product image a theme renders through core's ImageFactory - category and
 * search pages, related/upsell/cross-sell, widgets and the cart page - in Luma, Hyvä or any other theme,
 * without a template override.
 *
 * The factory knows the product and the image id, the block it builds does not, so the label rides
 * along on the block until it renders.
 */
class AddAiBadgeToProductImagePlugin
{
    public function __construct(
        private readonly LabelProvider $labelProvider,
    ) {
    }

    // ponytail: one indexed query per distinct file on a listing; preload per collection if uncached pages get slow
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreate(ImageFactory $subject, Image $result, Product $product, string $imageId): Image
    {
        return $result->setData(
            'ai_image_label',
            $this->labelProvider->getLabelForProductImage($product, $imageId)
        );
    }

    /**
     * Wraps the theme's own image markup instead of editing it, so whatever the template renders stays intact.
     */
    public function afterToHtml(Image $subject, string $result): string
    {
        $label = $subject->getData('ai_image_label');
        if (!$label || $result === '') {
            return $result;
        }

        return '<span class="ai-image-label-wrapper">' . $result
            . $this->labelProvider->getBadgeHtml($label, (string)$subject->getImageUrl()) . '</span>';
    }
}
