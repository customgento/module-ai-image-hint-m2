<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Icon colour for the hint, picked per image so the mark stays readable on the photo behind it.
 * The first option is the default for images that have never been labelled.
 */
class HintColor implements OptionSourceInterface
{
    public const BLACK = 'black';
    public const WHITE = 'white';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::BLACK, 'label' => __('Black')],
            ['value' => self::WHITE, 'label' => __('White')],
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
