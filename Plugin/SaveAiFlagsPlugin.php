<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\Model\ResourceModel\AiHint;
use CustomGento\AiImageHint\Model\Source\AiContent;
use CustomGento\AiImageHint\Model\Source\HintColor;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\CreateHandler;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Persists the per image AI hint settings submitted with the product form.
 *
 * Only the admin Image Detail modal posts them, as images[<id>][ai_hint], and only for images whose labelling was
 * changed there. A product saved any other way carries just the loaded ai_content column and is left alone.
 * Core never touches these columns, so writing them after the core save cannot conflict.
 */
class SaveAiFlagsPlugin
{
    public function __construct(
        private readonly AiHint $aiHintResource,
        private readonly AiContent $aiContent,
        private readonly HintColor $hintColor,
        private readonly MetadataPool $metadataPool,
    ) {
    }

    public function afterExecute(
        CreateHandler $subject,
        Product $result,
        Product $product,
        array $arguments = []
    ): Product {
        if ($product->getIsDuplicate() || $product->isLockedAttribute('media_gallery')) {
            // a duplicate is handled by GalleryResourcePlugin::afterDuplicate, the value ids here still point at the
            // original; a locked gallery core did not save either
            return $result;
        }

        $value = $product->getData('media_gallery');
        $images = is_array($value) ? ($value['images'] ?? null) : null;
        if (!is_array($images)) {
            return $result;
        }

        $submitted = array_filter($images, [$this, 'isSubmittedFlag']);
        if (!$submitted) {
            return $result;
        }

        // the form posts value ids, so never trust them: only touch rows that belong to this product
        $ownValueIds = $this->getOwnValueIds($product);
        foreach ($submitted as $image) {
            if (in_array((int)$image['value_id'], $ownValueIds, true)) {
                $this->writeFlags((int)$image['value_id'], $image['ai_hint']);
            }
        }

        return $result;
    }

    private function isSubmittedFlag(mixed $image): bool
    {
        return is_array($image)
            && is_array($image['ai_hint'] ?? null)
            && empty($image['removed'])
            && !empty($image['value_id']);
    }

    private function writeFlags(int $valueId, array $hint): void
    {
        $type = (int)($hint['ai_content'] ?? AiContent::NONE);
        $type = in_array($type, $this->aiContent->getValues(), true) ? $type : AiContent::NONE;

        $color = (string)($hint['ai_hint_color'] ?? '');
        $color = in_array($color, $this->hintColor->getValues(), true) ? $color : HintColor::BLACK;

        // the colour is kept even when the type is NONE, nothing reads it then and it survives a toggle
        $this->aiHintResource->saveFlags($valueId, $type, $color);
    }

    private function getOwnValueIds(Product $product): array
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        return $this->aiHintResource->getValueIdsOfProduct($linkField, (int)$product->getData($linkField));
    }
}
