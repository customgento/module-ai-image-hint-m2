<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable as ListingSwatches;
use WeakMap;

/**
 * On a category or search page filtered by a swatch attribute (layered navigation, e.g. ?color=58) the theme
 * shows the matching child's image right away. It takes that image from the listing's jsonConfig
 * (preSelectedGallery), not from a swatches/ajax/media request, so ai-image-label.js never sees its labels.
 *
 * preSelectedGallery is built by the swatch helper, which AddAiLabelToSwatchMediaPlugin already labels, so
 * this only prints those labels as a data block next to the swatches. Appended to the template output, so it
 * is part of what block_html caches for this block.
 */
class AddAiLabelToListingSwatchesPlugin
{
    // listing block => the image url => badge map of its preselected child
    private WeakMap $preselected;

    public function __construct(
        private readonly LabelProvider $labelProvider,
    ) {
        $this->preselected = new WeakMap();
    }

    public function afterGetJsonConfig(ListingSwatches $subject, string $result): string
    {
        if (str_contains($result, '"ai_image_label"')) {
            $config = json_decode($result, true);
            $map = $config['preSelectedGallery']['ai_image_label'] ?? null;
            if (is_array($map)) {
                $this->preselected[$subject] = $map;
            }
        }

        return $result;
    }

    public function afterFetchView(ListingSwatches $subject, string $result): string
    {
        $map = $this->preselected[$subject] ?? null;
        if (!$map) {
            return $result;
        }
        unset($this->preselected[$subject]);

        return $result . $this->labelProvider->getDataBlockHtml($map);
    }
}
