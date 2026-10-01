<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable as ListingSwatches;
use WeakMap;

/**
 * Picking an option on a configurable product swaps the gallery to the child's images, which come from
 * the options block's own JSON (spConfig.images), not from the gallery block. This prints the badge map
 * for those child images too, so ai-image-label.js finds them the same way as the parent's.
 *
 * The urls are built the way core's ConfigurableProduct\Helper\Data::getGalleryImages() builds them for
 * that JSON, but only for labelled images. Also covers the swatch renderer, which extends this block,
 * but not its category/search listing variant: core sends no child gallery there, and the product page
 * urls of this map would never match a listing image anyway.
 *
 * The product page renders both the options block and its swatch renderer for the same product, so the map
 * is printed once per product.
 */
class AddAiLabelToConfigurableImagesPlugin
{
    // configurable product => true once its map is printed
    private WeakMap $printed;

    public function __construct(
        private readonly LabelProvider $labelProvider,
        private readonly UrlBuilder $urlBuilder,
    ) {
        $this->printed = new WeakMap();
    }

    public function afterToHtml(Configurable $subject, string $result): string
    {
        $configurable = $subject->getProduct();
        if ($result === ''
            || $subject instanceof ListingSwatches
            || !$configurable instanceof Product
            || isset($this->printed[$configurable])
        ) {
            return $result;
        }
        $this->printed[$configurable] = true;

        $images = [];
        foreach ($subject->getAllowProducts() as $product) {
            if ($product instanceof Product) {
                array_push($images, ...$this->getLabelledImages($product));
            }
        }

        return $result . $this->labelProvider->getBadgeMapHtml($images);
    }

    /**
     * The child's labelled images, shaped like the product gallery JSON items.
     */
    private function getLabelledImages(Product $product): array
    {
        $images = [];
        foreach ($product->getMediaGalleryImages() as $image) {
            $label = $this->labelProvider->getLabel(
                (int)$image->getData('ai_content'),
                $image->getData('ai_label_color')
            );
            if (!$label) {
                continue;
            }
            $icons = $this->labelProvider->getIconUrls($label);
            $file = (string)$image->getData('file');
            $images[] = [
                'thumb' => $this->urlBuilder->getUrl($file, 'product_page_image_small'),
                'img' => $this->urlBuilder->getUrl($file, 'product_page_image_medium'),
                'full' => $this->urlBuilder->getUrl($file, 'product_page_image_large'),
                'aiIcon' => $icons['full'],
                'aiMark' => $icons['mark'],
                'aiAlt' => $label['alt'],
            ];
        }

        return $images;
    }
}
