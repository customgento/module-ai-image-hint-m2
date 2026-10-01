<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Checkout\CustomerData\ItemInterface;
use Magento\Quote\Model\Quote\Item;

/**
 * Adds the label to the cart section data, so the JS rendered minicart can show the icon (drawn by
 * view/frontend/web/js/ai-image-label.js, which reads the section from the browser cache). The item
 * resolver is the one core's DefaultItem uses to pick the thumbnail, so the label belongs to the
 * image that is actually rendered.
 */
class AddAiLabelToCartItemDataPlugin
{
    private const IMAGE_ID = 'mini_cart_product_thumbnail';

    public function __construct(
        private readonly LabelProvider $labelProvider,
        private readonly ItemResolverInterface $itemResolver,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetItemData(ItemInterface $subject, array $result, Item $item): array
    {
        $product = $this->itemResolver->getFinalProduct($item);
        if (!$product instanceof Product) {
            return $result;
        }

        $label = $this->labelProvider->getLabelForProductImage($product, self::IMAGE_ID);
        if ($label) {
            $result['ai_image_label'] = [
                'alt' => $label['alt'],
                'icon' => $this->labelProvider->getIconUrls($label)['mark'],
            ];
        }

        return $result;
    }
}
