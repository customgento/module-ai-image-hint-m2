<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Test\Unit\ViewModel;

use CustomGento\AiImageHint\Model\ResourceModel\AiHint;
use CustomGento\AiImageHint\Model\Source\AiContent;
use CustomGento\AiImageHint\ViewModel\AiImageHint;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use PHPUnit\Framework\TestCase;

class AiImageHintTest extends TestCase
{
    /**
     * A listing loads its labels with the collection, so rendering its images asks the database nothing more.
     */
    public function testPreloadedProductsAreAnsweredWithoutAQueryPerImage(): void
    {
        $resource = $this->createMock(AiHint::class);
        $resource->expects($this->once())->method('getByProducts')->with('entity_id', [1, 2])->willReturn([
            ['link' => '1', 'value' => '/r/i/ring.jpg', 'ai_content' => AiContent::GENERATED, 'ai_hint_color' => 'white'],
        ]);
        $resource->expects($this->never())->method('getByFile');

        $viewModel = $this->createViewModel($resource);
        $ring = $this->product(1);
        $plain = $this->product(2);
        $viewModel->preload([$ring, $plain]);
        // a product loaded twice, as by a second collection, is not fetched again
        $viewModel->preload([$ring]);

        $this->assertSame('white', $viewModel->getHintForFile($ring, '/r/i/ring.jpg')['color']);
        $this->assertNull($viewModel->getHintForFile($ring, '/r/i/other.jpg'));
        $this->assertNull($viewModel->getHintForFile($plain, '/p/l/plain.jpg'));
    }

    public function testAProductOutsideACollectionStillLooksItsFileUp(): void
    {
        $resource = $this->createMock(AiHint::class);
        $resource->expects($this->once())->method('getByFile')->with('/r/i/ring.jpg', 'entity_id', 3)
            ->willReturn(['ai_content' => AiContent::MODIFIED, 'ai_hint_color' => 'black']);

        $hint = $this->createViewModel($resource)->getHintForFile($this->product(3), '/r/i/ring.jpg');

        $this->assertSame(AiContent::MODIFIED, $hint['type']);
    }

    private function createViewModel(AiHint $resource): AiImageHint
    {
        $metadata = $this->createStub(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createStub(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        return new AiImageHint(
            $resource,
            $this->createStub(ViewConfig::class),
            $this->createStub(AssetRepository::class),
            $this->createStub(Escaper::class),
            $this->createStub(ItemResolverInterface::class),
            $metadataPool,
        );
    }

    private function product(int $id): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturnCallback(
            static fn (?string $key = null): mixed => $key === 'entity_id' ? $id : null
        );

        return $product;
    }
}
