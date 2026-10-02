<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Test\Unit\Plugin;

use CustomGento\AiImageHint\Plugin\AddAiHintToGalleryJsonPlugin;
use CustomGento\AiImageHint\Model\Source\AiContent;
use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Catalog\Block\Product\View\Gallery;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class AddAiHintToGalleryJsonPluginTest extends TestCase
{
    private const URL = 'https://shop.test/media/catalog/product/cache/abc/d/a/ring.jpg';

    /**
     * Matched by position, so the ?___store= suffix of "Add Store Code to URLs" does not matter.
     */
    public function testHintIsMergedIntoTheImageAtTheSamePosition(): void
    {
        $images = json_decode($this->encode([
            ['full' => self::URL . '?___store=default', 'caption' => 'Ring'],
            ['full' => 'https://shop.test/media/catalog/product/d/a/other.jpg', 'caption' => 'Other'],
        ], [AiContent::GENERATED, AiContent::NONE]), true);

        $this->assertSame('icon-full', $images[0]['aiIcon']);
        $this->assertSame('icon-mark', $images[0]['aiMark']);
        $this->assertSame('AI', $images[0]['aiAlt']);
        $this->assertArrayNotHasKey('aiIcon', $images[1]);
    }

    /**
     * The JSON is printed raw inside a <script> element, so the escaped slashes core emits are the
     * only thing stopping an alt text from closing that element.
     */
    public function testACaptionCannotCloseTheScriptElement(): void
    {
        $encoded = $this->encode(
            [['full' => self::URL, 'caption' => '</script><img src=x onerror=alert(1)>']],
            [AiContent::GENERATED]
        );

        $this->assertStringNotContainsString('</script>', $encoded);
    }

    public function testAGalleryWithoutLabelsIsReturnedUntouched(): void
    {
        $json = (string)json_encode([['full' => self::URL, 'caption' => 'Ring']]);

        $this->assertSame($json, $this->encode(json_decode($json, true), [AiContent::NONE]));
    }

    public function testAListThatNoLongerMatchesTheGalleryIsReturnedUntouched(): void
    {
        $json = (string)json_encode([['full' => self::URL], ['full' => self::URL]]);

        $this->assertSame($json, $this->encode(json_decode($json, true), [AiContent::GENERATED]));
    }

    private function encode(array $items, array $aiContents): string
    {
        $viewModel = $this->createStub(AiImageHint::class);
        $viewModel->method('getHint')->willReturnCallback(
            static fn (int $type): ?array => $type === AiContent::NONE
                ? null
                : ['type' => $type, 'color' => 'black', 'alt' => 'AI']
        );
        $viewModel->method('getIconUrls')->willReturn(['full' => 'icon-full', 'mark' => 'icon-mark']);

        $product = $this->createStub(Product::class);
        $product->method('getMediaGalleryImages')->willReturn(array_map(
            static fn (int $aiContent): DataObject => new DataObject(['ai_content' => $aiContent]),
            $aiContents
        ));
        $block = $this->createStub(Gallery::class);
        $block->method('getProduct')->willReturn($product);

        return (new AddAiHintToGalleryJsonPlugin($viewModel))
            ->afterGetGalleryImagesJson($block, (string)json_encode($items));
    }
}
