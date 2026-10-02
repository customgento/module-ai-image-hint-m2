<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Catalog\Block\Product\Image;
use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;

/**
 * Puts the AI mark on every product image a theme renders in PHP through core's ImageFactory: category and search
 * listings, product widgets, related / upsell / cross-sell, the cart page, wishlist, compare - in Luma, Hyvä or
 * any other theme, without a template change.
 *
 * The factory knows the product and the image id, the block it builds does not, so the hint rides along on the
 * block until it renders. The theme's image markup is left as it is and wrapped together with the badge. The
 * wrapper keeps the rendered image url, so js/ai-image-hint.js can hide the badge while a swatch shows another image.
 */
class AddAiHintToProductImagePlugin
{
    public function __construct(
        private readonly AiImageHint $aiImageHint,
        private readonly Escaper $escaper,
    ) {
    }

    // ponytail: one indexed query per distinct file on an uncached listing page; preload per collection if that gets slow
    public function afterCreate(ImageFactory $subject, Image $result, Product $product, string $imageId): Image
    {
        return $result->setData('ai_image_hint', $this->aiImageHint->getHintForProductImage($product, $imageId));
    }

    public function afterToHtml(Image $subject, string $result): string
    {
        $hint = $subject->getData('ai_image_hint');
        if (!$hint || $result === '') {
            return $result;
        }

        return sprintf(
            '<span class="ai-image-hint-wrap" data-ai-image="%s">%s%s</span>',
            $this->escaper->escapeHtmlAttr((string)$subject->getImageUrl()),
            $result,
            $this->aiImageHint->getBadgeHtml($hint)
        );
    }
}
