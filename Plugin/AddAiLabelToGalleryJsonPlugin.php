<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Block\Product\View\Gallery;
use WeakMap;

/**
 * Adds the label straight into the gallery JSON the product page already builds, so a theme that wants
 * its own markup can read image.aiIcon / image.aiMark - no second data structure, no lookup.
 *
 * The gallery collection already carries our two columns (see GalleryResourcePlugin), and the JSON "full"
 * value is the item's large_image_url, so the label is matched without a database query.
 *
 * The same labels also go out as a theme independent image url => badge map next to the gallery, for
 * view/frontend/web/js/ai-image-label.js to draw the badge on whatever gallery the theme renders.
 */
class AddAiLabelToGalleryJsonPlugin
{
    // gallery block => ['core' => core's JSON, 'json' => ours, 'images' => labelled items], see below
    private WeakMap $galleries;

    public function __construct(
        private readonly LabelProvider $labelProvider,
    ) {
        $this->galleries = new WeakMap();
    }

    public function afterGetGalleryImagesJson(Gallery $subject, string $result): string
    {
        // Hyvä's template asks twice: core builds the same JSON again, the labelling is done once
        $done = $this->galleries[$subject] ?? null;
        if ($done && $done['core'] === $result) {
            return $done['json'];
        }

        $labels = $this->collectLabels($subject);
        $images = $labels ? json_decode($result, true) : null;
        $json = $result;
        if (is_array($images)) {
            foreach ($images as &$image) {
                $label = $labels[$this->withoutQuery($image['full'] ?? '')] ?? null;
                if ($label) {
                    $icons = $this->labelProvider->getIconUrls($label);
                    $image['aiIcon'] = $icons['full'];
                    $image['aiMark'] = $icons['mark'];
                    $image['aiAlt'] = $label['alt'];
                }
            }
            unset($image);
            // core encodes with the default flags, and the blob is printed raw inside a <script> element -
            // the escaped slashes are what stops a "</script>" in an alt text from closing it
            $json = json_encode($images, JSON_THROW_ON_ERROR);
        }
        $this->galleries[$subject] = ['core' => $result, 'json' => $json, 'images' => is_array($images) ? $images : []];

        return $json;
    }

    /**
     * Taken from the JSON above, so the urls are exactly the ones the theme puts on its <img> tags.
     *
     * Appended to the template output rather than to toHtml(), so it is part of what block_html caches when
     * a theme caches the gallery. Only a block whose template printed that JSON gets a map: no second gallery
     * build, and no copy from blocks that never draw the gallery, such as ProductVideo's product.info.media.video.
     */
    public function afterFetchView(Gallery $subject, string $result): string
    {
        $images = $this->galleries[$subject]['images'] ?? [];
        if (!$images) {
            return $result;
        }
        // once, should the template render a nested one of the same block
        unset($this->galleries[$subject]);

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
                $labels[$this->withoutQuery((string)$image->getData('large_image_url'))] = $label;
            }
        }

        return $labels;
    }

    /**
     * The url as both sides of the label lookup see it.
     *
     * Core appends "?___store=<code>" to the JSON urls when the store code is in the url, on top of any
     * query an image url plugin (CDN, versioning) already put on large_image_url, so both sides drop it.
     */
    private function withoutQuery(string $url): string
    {
        return explode('?', $url)[0];
    }
}
