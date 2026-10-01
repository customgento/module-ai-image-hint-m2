<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\ResourceModel\AiLabel;
use CustomGento\AiImageLabel\Model\Source\AiContent;
use CustomGento\AiImageLabel\Model\Source\LabelColor;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\CreateHandler;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Persists the per image AI label settings submitted with the product form.
 *
 * Core never touches these columns, so writing them after the core save cannot conflict.
 */
class SaveAiFlagsPlugin
{
    public function __construct(
        private readonly AiLabel $aiLabelResource,
        private readonly MetadataPool $metadataPool,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        CreateHandler $subject,
        Product $result,
        Product $product,
        array $arguments = []
    ): Product {
        if ($product->getIsDuplicate()) {
            // handled by GalleryResourcePlugin::afterDuplicate, the value ids here still point at the original
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
                $this->writeFlags((int)$image['value_id'], $image);
            }
        }

        return $result;
    }

    /**
     * An absent key means the form part was not submitted (import, API, ...) - leave the stored value alone.
     */
    private function isSubmittedFlag(mixed $image): bool
    {
        return is_array($image)
            && array_key_exists('ai_content', $image)
            && empty($image['removed'])
            && !empty($image['value_id']);
    }

    private function writeFlags(int $valueId, array $image): void
    {
        $type = (int)$image['ai_content'];
        $type = in_array($type, [AiContent::GENERATED, AiContent::MODIFIED], true) ? $type : AiContent::NONE;
        $color = ($image['ai_label_color'] ?? '') === LabelColor::WHITE ? LabelColor::WHITE : LabelColor::BLACK;

        // the colour is kept even when the type is NONE, nothing reads it then and it survives a toggle
        $this->aiLabelResource->saveFlags($valueId, $type, $color);
    }

    private function getOwnValueIds(Product $product): array
    {
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        return $this->aiLabelResource->getValueIdsOfProduct($linkField, (int)$product->getData($linkField));
    }
}
