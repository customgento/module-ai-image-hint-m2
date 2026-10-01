<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Test\Unit\Plugin;

use CustomGento\AiImageLabel\Model\Source\AiContent;
use CustomGento\AiImageLabel\Plugin\AddAiLabelToConfigurableImagesPlugin;
use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Framework\Data\Collection;
use Magento\Framework\DataObject;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable as ListingSwatches;
use PHPUnit\Framework\TestCase;

class AddAiLabelToConfigurableImagesPluginTest extends TestCase
{
    public function testOnlyLabelledChildImagesGoIntoTheBadgeMap(): void
    {
        $gallery = new Collection($this->createStub(\Magento\Framework\Data\Collection\EntityFactoryInterface::class));
        $gallery->addItem(new DataObject(['id' => 1, 'file' => '/a/b/ai.jpg', 'ai_content' => AiContent::GENERATED]));
        $gallery->addItem(new DataObject(['id' => 2, 'file' => '/a/b/real.jpg', 'ai_content' => AiContent::NONE]));
        $child = $this->createStub(Product::class);
        $child->method('getMediaGalleryImages')->willReturn($gallery);
        $block = $this->createStub(Configurable::class);
        $block->method('getProduct')->willReturn($this->createStub(Product::class));
        $block->method('getAllowProducts')->willReturn([$child]);

        $urlBuilder = $this->createStub(UrlBuilder::class);
        $urlBuilder->method('getUrl')->willReturnCallback(static fn (string $file, string $area) => $area . $file);

        $labelProvider = $this->createMock(LabelProvider::class);
        $labelProvider->method('getLabel')->willReturnCallback(
            static fn (int $type): ?array => $type === AiContent::NONE
                ? null
                : ['type' => $type, 'color' => 'black', 'alt' => 'AI']
        );
        $labelProvider->method('getIconUrls')->willReturn(['full' => 'icon-full', 'mark' => 'icon-mark']);
        $labelProvider->expects($this->once())->method('getBadgeMapHtml')->with([[
            'thumb' => 'product_page_image_small/a/b/ai.jpg',
            'img' => 'product_page_image_medium/a/b/ai.jpg',
            'full' => 'product_page_image_large/a/b/ai.jpg',
            'aiIcon' => 'icon-full',
            'aiMark' => 'icon-mark',
            'aiAlt' => 'AI',
        ]])->willReturn('<map/>');

        $plugin = new AddAiLabelToConfigurableImagesPlugin($labelProvider, $urlBuilder);

        $this->assertSame('<div/><map/>', $plugin->afterToHtml($block, '<div/>'));
    }

    public function testCategoryListingSwatchesLoadNoChildGalleries(): void
    {
        $block = $this->createMock(ListingSwatches::class);
        $block->method('getProduct')->willReturn($this->createStub(Product::class));
        $block->expects($this->never())->method('getAllowProducts');

        $plugin = new AddAiLabelToConfigurableImagesPlugin(
            $this->createStub(LabelProvider::class),
            $this->createStub(UrlBuilder::class)
        );

        $this->assertSame('<div/>', $plugin->afterToHtml($block, '<div/>'));
    }

    /**
     * The product page renders both the options block and its swatch renderer for the same product.
     */
    public function testTheMapIsPrintedOncePerProduct(): void
    {
        $product = $this->createStub(Product::class);
        $options = $this->createStub(Configurable::class);
        $options->method('getProduct')->willReturn($product);
        $options->method('getAllowProducts')->willReturn([]);
        $swatches = $this->createMock(Configurable::class);
        $swatches->method('getProduct')->willReturn($product);
        $swatches->expects($this->never())->method('getAllowProducts');

        $labelProvider = $this->createStub(LabelProvider::class);
        $labelProvider->method('getBadgeMapHtml')->willReturn('<map/>');
        $plugin = new AddAiLabelToConfigurableImagesPlugin($labelProvider, $this->createStub(UrlBuilder::class));

        $this->assertSame('<div/><map/>', $plugin->afterToHtml($options, '<div/>'));
        $this->assertSame('<div/>', $plugin->afterToHtml($swatches, '<div/>'));
    }
}
