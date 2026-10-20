<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Plugin;

use CustomGento\AiImageHint\Model\Source\AiContent;
use CustomGento\AiImageHint\Model\Source\HintColor;
use Magento\Catalog\Block\Adminhtml\Product\Helper\Form\Gallery\Content;
use Magento\Framework\View\Element\Template;

/**
 * Adds the AI hint fields to the Image Detail modal of the admin product gallery. Core's own template renders
 * unchanged, whichever version is installed, so its store view handling stays intact; the fields go in right before
 * its Image Size field, which every 2.4 version has. Should a version lack it, the modal shows no AI fields.
 */
class AddAiFieldsToAdminGalleryPlugin
{
    private const MARKER = '<div class="admin__field admin__field-inline field-image-size" data-role="size">';

    public function __construct(
        private readonly AiContent $aiContent,
        private readonly HintColor $hintColor,
    ) {
    }

    public function afterToHtml(Content $subject, string $result): string
    {
        $position = strpos($result, self::MARKER);
        if ($position === false) {
            return $result;
        }

        $data = [
            'gallery' => $subject,
            'ai_content_options' => $this->aiContent->toOptionArray(),
            'ai_hint_color_options' => $this->hintColor->toOptionArray(),
        ];

        // the fields belong in the modal's underscore template, the script must stay outside of it
        return substr_replace($result, $this->render($subject, 'gallery-ai-fields', $data), $position, 0)
            . $this->render($subject, 'gallery-ai-script', $data);
    }

    private function render(Content $subject, string $template, array $data): string
    {
        $data['template'] = 'CustomGento_AiImageHint::helper/' . $template . '.phtml';

        return $subject->getLayout()->createBlock(Template::class, '', ['data' => $data])->toHtml();
    }
}
