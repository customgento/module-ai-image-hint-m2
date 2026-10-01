<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The EU AI Act labelling a gallery image can carry. The single definition of the stored values,
 * used by the admin gallery form, the save plugin and the frontend view model.
 */
class AiContent implements OptionSourceInterface
{
    public const NONE = 0;
    public const GENERATED = 1;
    public const MODIFIED = 2;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::NONE, 'label' => __('No AI used')],
            ['value' => self::GENERATED, 'label' => __('AI generated')],
            ['value' => self::MODIFIED, 'label' => __('AI modified')],
        ];
    }
}
