<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Catalog\Block\Product\View\Gallery;

/**
 * Adds the hint straight into the gallery JSON the product page already builds, so the template
 * only has to read image.aiIcon / image.aiMark - no second data structure, no lookup in JS.
 *
 * Core builds one JSON item per product gallery image, in the collection's order, and that collection already carries
 * our two columns (see GalleryResourcePlugin): items are matched by position, not by url, which core may suffix with
 * ?___store=, and without building the image urls a second time.
 */
class AddAiHintToGalleryJsonPlugin
{
    public function __construct(
        private readonly AiImageHint $aiImageHint,
    ) {
    }

    public function afterGetGalleryImagesJson(Gallery $subject, string $result): string
    {
        $hints = $this->collectHints($subject);
        if (!$hints) {
            return $result;
        }

        $items = json_decode($result, true);
        // another plugin changed the list, so positions no longer line up: no hint beats a wrong one
        if (!is_array($items) || count($items) !== count($hints)) {
            return $result;
        }

        foreach ($items as $index => &$item) {
            $hint = $hints[$index];
            if ($hint) {
                $icons = $this->aiImageHint->getIconUrls($hint);
                $item['aiIcon'] = $icons['full'];
                $item['aiMark'] = $icons['mark'];
                $item['aiAlt'] = $hint['alt'];
            }
        }

        // core encodes with the default flags, and the blob is printed raw inside a <script> element -
        // the escaped slashes are what stops a "</script>" in an alt text from closing it
        return json_encode($items, JSON_THROW_ON_ERROR);
    }

    /**
     * The hint or null per gallery image, in core's order; empty when no image carries a label.
     */
    private function collectHints(Gallery $subject): array
    {
        $images = $subject->getProduct()->getMediaGalleryImages();
        if (!is_iterable($images)) {
            return [];
        }

        $hints = [];
        foreach ($images as $image) {
            $hints[] = $this->aiImageHint->getHint(
                (int)$image->getData('ai_content'),
                $image->getData('ai_hint_color')
            );
        }

        return array_filter($hints) ? $hints : [];
    }
}
