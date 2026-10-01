<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Icon colour for the label, picked per image so the mark stays readable on the photo behind it.
 * The first option is the default for images that have never been labelled.
 */
class LabelColor implements OptionSourceInterface
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
}
