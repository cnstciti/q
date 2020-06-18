<?php

namespace repository\Lenses;

use dto\Lenses\Page      as DtoPage;
use entity\Lenses\PageAR as EntityPage;
use Yii;

/**
 * Репозиторий "Страницы продуктов"
 *
 * @author Constantin Ogloblin <cnst@mail.ru>
 * @since 1.0.0
 */
class PageRep
{
    /**
     * Ссылка на AR-модель "Страницы продуктов"
     *
     * @var EntityPage
     */
    protected $entityPage;


    /**
     * Конструктор
     *
     * @param EntityPage $entityPage
     */
    public function __construct(EntityPage $entityPage)
    {
        $this->entityPage = $entityPage;
    }


    /**
     * Возвращает ИД записи - по ссылке на страницу продукта и-магазина и объему продукта
     *
     * @param integer $idSite - ИД сайта
     * @param string  $href   - ссылка на страницу продукта и-магазина
     * @param integer $volume - объем продукта
     * @return integer
     */
    public function findIDByHref(int $idSite, string $href, int $volume)
    {
        return (new \yii\db\Query())
            ->select('id')
            ->from($this->entityPage::tableName())
            ->where('fkIdSite=:idSite AND href=:href AND volume=:volume')
            ->addParams([':idSite' => $idSite, ':href' => $href, ':volume' => $volume])
            ->scalar();
    }

    /**
     * Возвращает ИД записи - по мастер-продукту и объему продукта
     *
     * @param integer $idSite          - ИД сайта
     * @param integer $idMasterProduct - ИД мастер-продукта
     * @param integer $volume          - объем продукта
     * @return integer
     */
    public function findIDByIdBase(int $idSite, int $idMasterProduct, int $volume)
    {
        return (new \yii\db\Query())
            ->select('id')
            ->from($this->entityPage::tableName())
            ->where('fkIdSite=:idSite AND fkIdBase=:idMasterProduct AND volume=:volume')
            ->addParams([':idSite' => $idSite, ':idMasterProduct' => $idMasterProduct, ':volume' => $volume])
            ->scalar();
    }

    /**
     * Добавление записи, возращает ИД добавленной записи
     *
     * @param DtoPage $object - объект для вставки
     * @return integer
     */
    public function insert(DtoPage $object)
    {
        $obj = new $this->entityPage;
        $obj->fkIdUploadData = $object->fkIdUploadData;
        $obj->fkIdBase       = $object->fkIdBase;
        $obj->fkIdSite       = $object->fkIdSite;
        $obj->href           = $object->href;
        $obj->volume         = $object->volume;
        $obj->createdAt      = $object->createdAt;
        $obj->save();

        return Yii::$app->db->lastInsertID;
    }

    /**
     * Удаление записей старых загрузок
     *
     * @param int $idSite - ИД сайта
     * @param string $type - тип продукции
     * @param int $idUploadData - ИД загрузки
     */
    public function deleteOldUloadData($idSite, $type, $idUploadData)
    {
        $request =
            'DELETE FROM '
            . $this->entityPage::tableName()
            . ' WHERE '
            . '`fkIdUploadData` IN ( '
            . 'SELECT '
            . 'upload.`id` '
            . 'FROM '
            . '`lense_dic_site_page_group` dic, '
            . '`lense_site_page_group` page, '
            . '`lense_upload_data` upload '
            . 'WHERE '
            . 'page.`fkIdDicPageGroup`=dic.`id` '
            . 'AND upload.`fkIdSitePageGroup`=page.`id` '
            . 'AND page.`fkIdSite`=:idSite '
            . 'AND dic.`groupName`=:groupName '
            . 'AND upload.`id`<>:id '
            . ') ';

        return Yii::$app->db->createCommand($request)
            ->bindValue(':idSite', $idSite)
            ->bindValue(':groupName', $type)
            ->bindValue(':id', $idUploadData)
            ->execute();
    }

    /**
     * Обновление записи.
     */
    public function updateUpload($id, $fkIdUploadData)
    {
        $request =
            'UPDATE '
            . $this->entityPage::tableName()
            . ' SET '
            . '`fkIdUploadData`=:fkIdUploadData '
            . 'WHERE '
            . '`id`=:id ';
        return Yii::$app->db->createCommand($request)
            ->bindValue(':id', $id)
            ->bindValue(':fkIdUploadData', $fkIdUploadData)
            ->execute();
    }

}
