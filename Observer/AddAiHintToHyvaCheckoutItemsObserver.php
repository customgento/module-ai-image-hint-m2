<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Observer;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Escaper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Puts the AI mark on the item thumbnails of the Hyvä Checkout order summary. Its template prints plain thumbnail
 * urls, so the badge is added after each <img> whose src is the thumbnail of a labelled item. The thumbnail sits in
 * a `relative` container already. Runs on every render, also the Magewire updates, so the badge never goes missing.
 */
class AddAiHintToHyvaCheckoutItemsObserver implements ObserverInterface
{
    private const BLOCK_NAME = 'price-summary.cart-items';
    // the image id Hyva\Checkout\Magewire\Checkout\PriceSummary\CartItems renders the thumbnails with
    private const IMAGE_ID = 'product_thumbnail_image';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ItemResolverInterface $itemResolver,
        private readonly ImageHelper $imageHelper,
        private readonly AiImageHint $aiImageHint,
        private readonly Escaper $escaper,
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($observer->getData('block')?->getNameInLayout() !== self::BLOCK_NAME) {
            return;
        }

        $badges = $this->getBadgesByThumbnailUrl();
        if (!$badges) {
            return;
        }

        $transport = $observer->getData('transport');
        $transport->setData('html', preg_replace_callback(
            '/<img\b[^>]*>/',
            static fn (array $img): string => $img[0]
                . (preg_match('/\ssrc="([^"]*)"/', $img[0], $src) ? $badges[$src[1]] ?? '' : ''),
            (string)$transport->getData('html')
        ));
    }

    /**
     * Thumbnail url, escaped as the template prints it => badge html, for the labelled items of the quote.
     * The label is per product, so items of products sharing an image file may differ in it.
     */
    private function getBadgesByThumbnailUrl(): array
    {
        $badges = [];
        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $product = $this->itemResolver->getFinalProduct($item);
            if (!$product instanceof Product) {
                continue;
            }
            $badge = $this->aiImageHint->getBadgeHtml(
                $this->aiImageHint->getHintForProductImage($product, self::IMAGE_ID)
            );
            $url = $this->escaper->escapeUrl($this->imageHelper->init($product, self::IMAGE_ID)->getUrl());
            // one url, several items with different labels: their <img>s cannot be told apart, so none gets a badge
            $badges[$url] = isset($badges[$url]) && $badges[$url] !== $badge ? '' : $badge;
        }

        return array_filter($badges);
    }
}
