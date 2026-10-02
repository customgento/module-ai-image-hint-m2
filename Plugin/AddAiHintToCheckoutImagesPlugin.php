<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Checkout\Model\Cart\ImageProvider;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Adds the hint to window.checkoutConfig.imageData, which the Luma checkout order summary renders its
 * thumbnails from: imageData[item id].ai_image_hint = {icon, alt}.
 */
class AddAiHintToCheckoutImagesPlugin
{
    // the image id ImageProvider renders the thumbnails with
    private const IMAGE_ID = 'mini_cart_product_thumbnail';

    public function __construct(
        private readonly AiImageHint $aiImageHint,
        private readonly CartRepositoryInterface $quoteRepository,
    ) {
    }

    public function afterGetImages(ImageProvider $subject, array $result, mixed $cartId): array
    {
        // the quote core just loaded for the same images, cached by the repository; no item options needed here
        foreach ($this->quoteRepository->getActive($cartId)->getAllVisibleItems() as $item) {
            $hint = isset($result[$item->getItemId()]) ? $this->aiImageHint->getCartItemHint($item, self::IMAGE_ID) : null;
            if ($hint) {
                $result[$item->getItemId()]['ai_image_hint'] = $hint;
            }
        }

        return $result;
    }
}
