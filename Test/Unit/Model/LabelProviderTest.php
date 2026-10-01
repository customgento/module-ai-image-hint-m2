<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Test\Unit\Model;

use CustomGento\AiImageLabel\Model\ResourceModel\AiLabel;
use CustomGento\AiImageLabel\Model\LabelProvider;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use PHPUnit\Framework\TestCase;

class LabelProviderTest extends TestCase
{
    public function testTheBadgeMapCoversEveryUrlTheThemeMayRender(): void
    {
        $html = $this->labelProvider()->getBadgeMapHtml([
            ['thumb' => 't.jpg', 'img' => 'i.jpg', 'full' => 'f.jpg', 'aiIcon' => 'icon', 'aiMark' => 'mark', 'aiAlt' => 'AI'],
            ['thumb' => 'x.jpg', 'img' => 'y.jpg', 'full' => 'z.jpg'],
        ]);
        preg_match('#<script type="application/json" class="ai-image-label-data">(.*)</script>#', $html, $match);
        $map = json_decode($match[1], true);

        $this->assertEqualsCanonicalizing(['t.jpg', 'i.jpg', 'f.jpg'], array_keys($map));
        $this->assertSame('mark', $map['t.jpg']['icon']);
        $this->assertSame('icon', $map['f.jpg']['icon']);
    }

    public function testImagesWithoutLabelsGetNoBadgeMap(): void
    {
        $this->assertSame('', $this->labelProvider()->getBadgeMapHtml([
            ['thumb' => 'x.jpg', 'img' => 'y.jpg', 'full' => 'z.jpg'],
        ]));
    }

    private function labelProvider(): LabelProvider
    {
        return new LabelProvider(
            $this->createStub(AiLabel::class),
            $this->createStub(ViewConfig::class),
            $this->createStub(AssetRepository::class),
            $this->createStub(Escaper::class),
            $this->createStub(MetadataPool::class),
        );
    }
}
