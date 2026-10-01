<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Test\Unit\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use CustomGento\AiImageLabel\Model\Source\AiContent;
use CustomGento\AiImageLabel\Plugin\AddAiLabelToSwatchMediaPlugin;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Swatches\Helper\Data as SwatchHelper;
use PHPUnit\Framework\TestCase;

class AddAiLabelToSwatchMediaPluginTest extends TestCase
{
    private const MEDIA = [
        'large' => 'l/ai.jpg',
        'medium' => 'm/ai.jpg',
        'small' => 's/ai.jpg',
        'gallery' => [
            7 => ['large' => 'l/ai.jpg', 'medium' => 'm/ai.jpg', 'small' => 's/ai.jpg'],
            8 => ['large' => 'l/real.jpg', 'medium' => 'm/real.jpg', 'small' => 's/real.jpg'],
        ],
    ];

    private ?HttpInterface $response = null;

    public function testEverySizeOfALabelledChildImageGetsTheMark(): void
    {
        $result = $this->afterGetProductMediaGallery([
            ['value_id' => '7', 'ai_content' => (string)AiContent::GENERATED, 'ai_label_color' => 'black'],
            ['value_id' => '8', 'ai_content' => (string)AiContent::NONE, 'ai_label_color' => null],
        ]);

        $this->assertSame(['l/ai.jpg', 'm/ai.jpg', 's/ai.jpg'], array_keys($result['ai_image_label']));
        $this->assertSame(['icon' => 'icon-mark', 'alt' => 'AI'], $result['ai_image_label']['m/ai.jpg']);
        // what Luma and Hyvä read is left as core built it
        $this->assertSame(self::MEDIA, array_diff_key($result, ['ai_image_label' => true]));
    }

    public function testAChildWithoutLabelsGetsTheResponseUntouched(): void
    {
        $this->response = $this->createMock(HttpInterface::class);
        $this->response->expects($this->never())->method('setHeader');

        $this->assertSame(self::MEDIA, $this->afterGetProductMediaGallery([
            ['value_id' => '8', 'ai_content' => (string)AiContent::NONE, 'ai_label_color' => null],
        ]));
    }

    /**
     * ai-image-label.js only requests a swatch response again when it is flagged as labelled.
     */
    public function testALabelledResponseIsFlaggedForTheScript(): void
    {
        $this->response = $this->createMock(HttpInterface::class);
        $this->response->expects($this->once())->method('setHeader')->with('Server-Timing', 'ai-image-label');

        $this->afterGetProductMediaGallery([
            ['value_id' => '7', 'ai_content' => (string)AiContent::GENERATED, 'ai_label_color' => 'black'],
        ]);
    }

    private function afterGetProductMediaGallery(array $images): array
    {
        $labelProvider = $this->createStub(LabelProvider::class);
        $labelProvider->method('getLabel')->willReturnCallback(
            static fn (int $type): ?array => $type === AiContent::NONE
                ? null
                : ['type' => $type, 'color' => 'black', 'alt' => 'AI']
        );
        $labelProvider->method('getIconUrls')->willReturn(['full' => 'icon-full', 'mark' => 'icon-mark']);

        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturn(['images' => $images]);

        $response = $this->response ?? $this->createStub(HttpInterface::class);

        return (new AddAiLabelToSwatchMediaPlugin($labelProvider, $response))
            ->afterGetProductMediaGallery($this->createStub(SwatchHelper::class), self::MEDIA, $product);
    }
}
