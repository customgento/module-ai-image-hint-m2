<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Block\Product\View\Gallery;

/**
 * Adds the label straight into the gallery JSON the product page already builds, so a theme that wants
 * its own markup can read image.aiIcon / image.aiMark - no second data structure, no lookup.
 *
 * The gallery collection already carries our two columns (see GalleryResourcePlugin), and the JSON "full"
 * value is the item's large_image_url, so the label is matched without a query or a URL parse.
 *
 * The same labels also go out as a theme independent image url => badge map next to the gallery, for
 * view/frontend/web/js/ai-image-label.js to draw the badge on whatever gallery the theme renders.
 */
class AddAiLabelToGalleryJsonPlugin
{
    public function __construct(
        private readonly LabelProvider $labelProvider,
    ) {
    }

    public function afterGetGalleryImagesJson(Gallery $subject, string $result): string
    {
        $labels = $this->collectLabels($subject);
        if (!$labels) {
            return $result;
        }

        $images = json_decode($result, true);
        if (!is_array($images)) {
            return $result;
        }

        foreach ($images as &$image) {
            // core appends "?___store=<code>" to the urls when the store code is in the url
            $label = $labels[explode('?', $image['full'] ?? '')[0]] ?? null;
            if ($label) {
                $icons = $this->labelProvider->getIconUrls($label);
                $image['aiIcon'] = $icons['full'];
                $image['aiMark'] = $icons['mark'];
                $image['aiAlt'] = $label['alt'];
            }
        }

        // core encodes with the default flags, and the blob is printed raw inside a <script> element -
        // the escaped slashes are what stops a "</script>" in an alt text from closing it
        return json_encode($images, JSON_THROW_ON_ERROR);
    }

    /**
     * Read back from the JSON above, so the urls are exactly the ones the theme puts on its <img> tags.
     */
    public function afterToHtml(Gallery $subject, string $result): string
    {
        $images = json_decode($subject->getGalleryImagesJson(), true) ?: [];

        return $result . $this->labelProvider->getBadgeMapHtml($images);
    }

    /**
     * Large image url => label, for the gallery images that carry a label.
     */
    private function collectLabels(Gallery $subject): array
    {
        $images = $subject->getGalleryImages();
        if (!is_iterable($images)) {
            return [];
        }

        $labels = [];
        foreach ($images as $image) {
            $label = $this->labelProvider->getLabel(
                (int)$image->getData('ai_content'),
                $image->getData('ai_label_color')
            );
            if ($label) {
                $labels[(string)$image->getData('large_image_url')] = $label;
            }
        }

        return $labels;
    }
}
