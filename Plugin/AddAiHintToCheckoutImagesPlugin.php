<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Checkout\Model\Cart\ImageProvider;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

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

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetImages(ImageProvider $subject, array $result, mixed $cartId): array
    {
        // the quote core just loaded for the same images, cached by the repository; no item options needed here
        $quote = $this->quoteRepository->getActive($cartId);
        if (!$quote instanceof Quote) {
            return $result;
        }
        foreach ($quote->getAllVisibleItems() as $item) {
            $hint = isset($result[$item->getItemId()])
                ? $this->aiImageHint->getCartItemHint($item, self::IMAGE_ID)
                : null;
            if ($hint) {
                $result[$item->getItemId()]['ai_image_hint'] = $hint;
            }
        }

        return $result;
    }
}
