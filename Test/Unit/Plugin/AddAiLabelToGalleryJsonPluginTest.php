<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Test\Unit\Plugin;

use CustomGento\AiImageLabel\Plugin\AddAiLabelToGalleryJsonPlugin;
use CustomGento\AiImageLabel\Model\Source\AiContent;
use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Block\Product\View\Gallery;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class AddAiLabelToGalleryJsonPluginTest extends TestCase
{
    private const URL = 'https://shop.test/media/catalog/product/cache/abc/a/b/example.jpg';

    public function testLabelIsMergedIntoTheImageItBelongsTo(): void
    {
        $images = json_decode($this->encode([
            ['full' => self::URL, 'caption' => 'Ring'],
            ['full' => 'https://shop.test/media/catalog/product/d/a/other.jpg', 'caption' => 'Other'],
        ]), true);

        $this->assertSame('icon-full', $images[0]['aiIcon']);
        $this->assertSame('icon-mark', $images[0]['aiMark']);
        $this->assertSame('AI', $images[0]['aiAlt']);
        $this->assertArrayNotHasKey('aiIcon', $images[1]);
    }

    public function testLabelIsMatchedWhenTheStoreCodeIsInTheUrl(): void
    {
        $images = json_decode($this->encode([['full' => self::URL . '?___store=de', 'caption' => 'Ring']]), true);

        $this->assertSame('icon-full', $images[0]['aiIcon']);
    }

    public function testLabelIsMatchedWhenTheImageUrlAlreadyHasAQuery(): void
    {
        $url = self::URL . '?v=1';
        $images = json_decode($this->plugin()->afterGetGalleryImagesJson(
            $this->block(AiContent::GENERATED, $url),
            (string)json_encode([['full' => $url . '?___store=de', 'caption' => 'Ring']])
        ), true);

        $this->assertSame('icon-full', $images[0]['aiIcon']);
    }

    /**
     * The JSON is printed raw inside a <script> element, so the escaped slashes core emits are the
     * only thing stopping an alt text from closing that element.
     */
    public function testACaptionCannotCloseTheScriptElement(): void
    {
        $encoded = $this->encode([
            ['full' => self::URL, 'caption' => '</script><img src=x onerror=alert(1)>'],
        ]);

        $this->assertStringNotContainsString('</script>', $encoded);
    }

    public function testAGalleryWithoutLabelsIsReturnedUntouched(): void
    {
        $result = $this->encode([['full' => self::URL, 'caption' => 'Ring']], AiContent::NONE);

        $this->assertSame((string)json_encode([['full' => self::URL, 'caption' => 'Ring']]), $result);
    }

    public function testOnlyTheBlockThatPrintedTheGalleryJsonGetsTheBadgeMap(): void
    {
        $plugin = $this->plugin();
        $gallery = $this->block();
        $plugin->afterGetGalleryImagesJson($gallery, (string)json_encode([['full' => self::URL]]));

        $this->assertSame('<div/><map/>', $plugin->afterFetchView($gallery, '<div/>'));
        // e.g. Magento_ProductVideo's video block, whose template never prints the gallery JSON
        $this->assertSame('<div/>', $plugin->afterFetchView($this->block(), '<div/>'));
    }

    /**
     * Hyvä's gallery template calls getGalleryImagesJson() twice.
     */
    public function testTheSameGalleryJsonIsLabelledOnce(): void
    {
        $block = $this->createMock(Gallery::class);
        $block->expects($this->once())->method('getGalleryImages')->willReturn([
            new DataObject(['large_image_url' => self::URL, 'ai_content' => AiContent::GENERATED]),
        ]);
        $plugin = $this->plugin();
        $json = (string)json_encode([['full' => self::URL]]);

        $first = $plugin->afterGetGalleryImagesJson($block, $json);

        $this->assertSame($first, $plugin->afterGetGalleryImagesJson($block, $json));
    }

    private function encode(array $images, int $aiContent = AiContent::GENERATED): string
    {
        return $this->plugin()->afterGetGalleryImagesJson($this->block($aiContent), (string)json_encode($images));
    }

    private function plugin(): AddAiLabelToGalleryJsonPlugin
    {
        $labelProvider = $this->createStub(LabelProvider::class);
        $labelProvider->method('getLabel')->willReturnCallback(
            static fn (int $type): ?array => $type === AiContent::NONE
                ? null
                : ['type' => $type, 'color' => 'black', 'alt' => 'AI']
        );
        $labelProvider->method('getIconUrls')->willReturn(['full' => 'icon-full', 'mark' => 'icon-mark']);
        $labelProvider->method('getBadgeMapHtml')->willReturn('<map/>');

        return new AddAiLabelToGalleryJsonPlugin($labelProvider);
    }

    private function block(int $aiContent = AiContent::GENERATED, string $largeImageUrl = self::URL): Gallery
    {
        $block = $this->createStub(Gallery::class);
        $block->method('getGalleryImages')->willReturn([
            new DataObject(['large_image_url' => $largeImageUrl, 'ai_content' => $aiContent]),
        ]);

        return $block;
    }
}
