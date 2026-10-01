<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Test\Unit\Plugin;

use CustomGento\AiImageLabel\Model\LabelProvider;
use CustomGento\AiImageLabel\Plugin\AddAiLabelToListingSwatchesPlugin;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable as ListingSwatches;
use PHPUnit\Framework\TestCase;

class AddAiLabelToListingSwatchesPluginTest extends TestCase
{
    /**
     * Layered navigation by colour: the tile shows the preselected child's image straight from jsonConfig.
     */
    public function testThePreselectedChildsLabelsArePrintedWithTheSwatches(): void
    {
        $map = ['m/ai.jpg' => ['icon' => 'icon-mark', 'alt' => 'AI']];
        $labelProvider = $this->createMock(LabelProvider::class);
        $labelProvider->expects($this->once())->method('getDataBlockHtml')->with($map)->willReturn('<map/>');
        $plugin = new AddAiLabelToListingSwatchesPlugin($labelProvider);
        $block = $this->createStub(ListingSwatches::class);
        $config = (string)json_encode(['preSelectedGallery' => ['medium' => 'm/ai.jpg', 'ai_image_label' => $map]]);

        $this->assertSame($config, $plugin->afterGetJsonConfig($block, $config));
        $this->assertSame('<div/><map/>', $plugin->afterFetchView($block, '<div/>'));
    }

    public function testUnfilteredListingsGetNoMap(): void
    {
        $plugin = new AddAiLabelToListingSwatchesPlugin($this->createStub(LabelProvider::class));
        $block = $this->createStub(ListingSwatches::class);

        $plugin->afterGetJsonConfig($block, (string)json_encode(['attributes' => []]));

        $this->assertSame('<div/>', $plugin->afterFetchView($block, '<div/>'));
    }
}
