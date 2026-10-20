<?php

declare(strict_types=1);

namespace CustomGento\AiImageHint\Model\ResourceModel;

use CustomGento\AiImageHint\Model\Source\AiContent;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Owns the two AI hint columns on the media gallery table: which select they are added to, and
 * every read and write of them. Nothing else in the module talks to the database.
 */
class AiHint extends AbstractDb
{
    public const COLUMNS = ['ai_content', 'ai_hint_color'];

    protected function _construct(): void
    {
        $this->_init(Gallery::GALLERY_TABLE, 'value_id');
    }

    /**
     * Core returns false for a resource model without a connection, which this one never is.
     */
    public function getConnection(): AdapterInterface
    {
        $connection = parent::getConnection();
        if (!$connection) {
            throw new \LogicException('The media gallery table has no database connection.');
        }

        return $connection;
    }

    /**
     * The labelled row of one product's gallery file such as "/a/b/example.jpg", null when the image carries
     * no label. Scoped to the product like its own gallery, so a file shared by several products is labelled
     * per product everywhere. The file column is indexed, see db_schema.xml.
     */
    public function getByFile(string $file, string $linkField, int $linkFieldValue): ?array
    {
        $connection = $this->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['gallery' => $this->getMainTable()], self::COLUMNS)
                ->join(
                    ['entity' => $this->getTable(Gallery::GALLERY_VALUE_TO_ENTITY_TABLE)],
                    'entity.value_id = gallery.value_id',
                    []
                )
                ->where('gallery.value = ?', $file)
                ->where('gallery.ai_content > ?', AiContent::NONE)
                ->where('entity.' . $linkField . ' = ?', $linkFieldValue)
        );

        return $row ?: null;
    }

    /**
     * Every labelled row of several products' galleries in one query, with the product's link field value as
     * "link". For product listings, which would otherwise ask getByFile once per product.
     */
    public function getByProducts(string $linkField, array $linkFieldValues): array
    {
        $connection = $this->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from(['gallery' => $this->getMainTable()], array_merge(['value'], self::COLUMNS))
                ->join(
                    ['entity' => $this->getTable(Gallery::GALLERY_VALUE_TO_ENTITY_TABLE)],
                    'entity.value_id = gallery.value_id',
                    ['link' => $linkField]
                )
                ->where('gallery.ai_content > ?', AiContent::NONE)
                ->where('entity.' . $linkField . ' IN (?)', $linkFieldValues)
        );
    }

    public function saveFlags(int $valueId, int $type, string $color): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['ai_content' => $type, 'ai_hint_color' => $color],
            ['value_id = ?' => $valueId]
        );
    }

    /**
     * Carries the labelling over to a duplicated product, given a map of original value id => copy.
     */
    public function copyFlags(array $originalToCopy): void
    {
        if (!$originalToCopy) {
            return;
        }

        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getMainTable(), array_merge(['value_id'], self::COLUMNS))
                ->where('value_id IN (?)', array_keys($originalToCopy))
                ->where('ai_content > ?', AiContent::NONE)
        );

        foreach ($rows as $row) {
            $this->saveFlags(
                (int)$originalToCopy[$row['value_id']],
                (int)$row['ai_content'],
                (string)$row['ai_hint_color']
            );
        }
    }

    /**
     * The gallery rows that belong to one product, so a posted value id can never be used to write
     * a label onto somebody else's image.
     */
    public function getValueIdsOfProduct(string $linkField, int $linkFieldValue): array
    {
        $connection = $this->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->getTable(Gallery::GALLERY_VALUE_TO_ENTITY_TABLE), ['value_id'])
                ->where($linkField . ' = ?', $linkFieldValue)
        ));
    }
}
