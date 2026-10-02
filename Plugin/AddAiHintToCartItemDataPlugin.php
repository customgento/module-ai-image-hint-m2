<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Checkout\CustomerData\ItemInterface;
use Magento\Quote\Model\Quote\Item;

/**
 * Adds the hint to the thumbnail of each item in the cart customer data section, which both the Luma and the
 * Hyvä minicart render: product_image.ai_image_hint = {icon, alt}.
 */
class AddAiHintToCartItemDataPlugin
{
    private const IMAGE_ID = 'mini_cart_product_thumbnail';

    public function __construct(
        private readonly AiImageHint $aiImageHint,
    ) {
    }

    public function afterGetItemData(ItemInterface $subject, array $result, Item $item): array
    {
        $hint = isset($result['product_image']) ? $this->aiImageHint->getCartItemHint($item, self::IMAGE_ID) : null;
        if ($hint) {
            $result['product_image']['ai_image_hint'] = $hint;
        }

        return $result;
    }
}
