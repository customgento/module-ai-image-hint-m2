<?php

declare(strict_types=1);

namespace CustomGento\AiImageLabel\Model\ResourceModel;

use CustomGento\AiImageLabel\Model\Source\AiContent;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Owns the two AI label columns on the media gallery table: which select they are added to, and
 * every read and write of them. Nothing else in the module talks to the database.
 */
class AiLabel extends AbstractDb
{
    public const COLUMNS = ['ai_content', 'ai_label_color'];

    protected function _construct(): void
    {
        $this->_init(Gallery::GALLERY_TABLE, 'value_id');
    }

    /**
     * The two columns aliased onto a gallery select, so they travel with the media gallery into the
     * admin form and onto the storefront.
     */
    public function getColumnsFor(string $tableAlias): array
    {
        return array_combine(self::COLUMNS, array_map(fn($column) => $tableAlias . '.' . $column, self::COLUMNS));
    }

    /**
     * The labelled row for one product's gallery file such as "/a/b/example.jpg", null when the image
     * carries no label. Scoped to the product, since several products can share one file. The file
     * column is indexed, see db_schema.xml.
     */
    public function getByProductFile(string $linkField, int $linkFieldValue, string $file): ?array
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
                ->where('entity.' . $linkField . ' = ?', $linkFieldValue)
                ->where('gallery.ai_content > ?', AiContent::NONE)
        );

        return $row ?: null;
    }

    public function saveFlags(int $valueId, int $type, string $color): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['ai_content' => $type, 'ai_label_color' => $color],
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
                (string)$row['ai_label_color']
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
