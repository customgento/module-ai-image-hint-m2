<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\Model\ResourceModel\AiHint;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\DB\Select;

/**
 * Makes the AI hint columns travel with the media gallery, in the admin form and on the storefront.
 */
class GalleryResourcePlugin
{
    public function __construct(
        private readonly AiHint $aiHintResource,
    ) {
    }

    /**
     * Core selects only value_id/file/media_type from the gallery table, add our two columns.
     */
    public function afterCreateBatchBaseSelect(Gallery $subject, Select $result): Select
    {
        return $result->columns(AiHint::COLUMNS, $subject->getMainTableAlias());
    }

    /**
     * Core copies value/media_type/disabled only, so a duplicated product would lose its AI labelling.
     * The result maps each original value_id to the value_id of its copy.
     */
    public function afterDuplicate(Gallery $subject, array $result): array
    {
        $this->aiHintResource->copyFlags($result);

        return $result;
    }
}
