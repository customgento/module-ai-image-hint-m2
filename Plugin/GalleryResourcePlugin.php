<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Plugin;

use CustomGento\AiImageLabel\Model\ResourceModel\AiLabel;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\DB\Select;

/**
 * Makes the AI label columns travel with the media gallery, in the admin form and on the storefront.
 */
class GalleryResourcePlugin
{
    public function __construct(
        private readonly AiLabel $aiLabelResource,
    ) {
    }

    /**
     * Core selects only value_id/file/media_type from the gallery table, add our two columns.
     */
    public function afterCreateBatchBaseSelect(Gallery $subject, Select $result): Select
    {
        return $result->columns($this->aiLabelResource->getColumnsFor($subject->getMainTableAlias()));
    }

    /**
     * Core copies value/media_type/disabled only, so a duplicated product would lose its AI labelling.
     * The result maps each original value_id to the value_id of its copy.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDuplicate(Gallery $subject, array $result): array
    {
        $this->aiLabelResource->copyFlags($result);

        return $result;
    }
}
