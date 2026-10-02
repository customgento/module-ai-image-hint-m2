<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use Magento\Checkout\Block\Cart\Sidebar;

/**
 * Swaps the Luma minicart's image template for ours, which renders the original one plus the badge.
 * A theme that already picks another template keeps it, and shows no badge.
 */
class AddAiHintToMinicartImagePlugin
{
    private const CORE_TEMPLATE = 'Magento_Catalog/product/image_with_borders';

    public function afterGetImageHtmlTemplate(Sidebar $subject, string $result): string
    {
        return $result === self::CORE_TEMPLATE ? 'CustomGento_AiImageHint/minicart-image' : $result;
    }
}
