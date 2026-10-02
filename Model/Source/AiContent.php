<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The EU AI Act labelling a gallery image can carry. The single definition of the stored values,
 * used by the admin gallery form, by the save plugin's validation and by the frontend view model.
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

    /**
     * The stored values, for validating what the product form posted.
     */
    public function getValues(): array
    {
        return array_column($this->toOptionArray(), 'value');
    }
}
