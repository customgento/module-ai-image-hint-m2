<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;

/**
 * Clicking a swatch on a category or search page makes the theme fetch the child's images from
 * swatches/ajax/media and swap the tile image to one of them - Luma via jQuery, Hyvä via fetch(). This adds
 * an image url => badge map of the child's labelled images to that response, which ai-image-label.js reads,
 * so the swapped in image carries its own label instead of the parent's.
 *
 * The product's media gallery already carries our two columns (see GalleryResourcePlugin), and the response
 * keys its gallery by value id, so the labels are matched without a query.
 *
 * A labelled response is also flagged with a Server-Timing entry, which the browser exposes on the request's
 * PerformanceResourceTiming: the script only reads a response again when it carries labels.
 */
class AddAiLabelToSwatchMediaPlugin
{
    public function __construct(
        private readonly LabelProvider $labelProvider,
        private readonly ResponseInterface $response,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetProductMediaGallery(SwatchHelper $subject, array $result, Product $product): array
    {
        $badges = [];
        foreach ((array)($product->getData('media_gallery')['images'] ?? []) as $image) {
            $urls = $result['gallery'][$image['value_id'] ?? ''] ?? null;
            $label = $this->labelProvider->getLabel(
                (int)($image['ai_content'] ?? 0),
                $image['ai_label_color'] ?? null
            );
            if (!$urls || !$label) {
                continue;
            }
            $badge = ['icon' => $this->labelProvider->getIconUrls($label)['mark'], 'alt' => $label['alt']];
            foreach (['large', 'medium', 'small'] as $size) {
                $badges[$urls[$size]] = $badge;
            }
        }

        if ($badges) {
            $result['ai_image_label'] = $badges;
            if ($this->response instanceof HttpInterface) {
                $this->response->setHeader('Server-Timing', 'ai-image-label');
            }
        }

        return $result;
    }
}
