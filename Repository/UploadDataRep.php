<?php

namespace repository\Lenses;

use entity\Lenses\DicProductGroupAR as EntityDicProductGroup;
use entity\Lenses\ProductGroupAR    as EntityProductGroup;
use entity\Lenses\UploadDataAR      as EntityUploadData;
use Yii;

/**
 * Репозиторий "Пакеты загрузки продуктов"
 *
 * @author Constantin Ogloblin <cnst@mail.ru>
 * @since 1.0.0
 */
class UploadDataRep
{
    /**
     * Ссылка на AR-модель "Пакеты загрузки продуктов"
     *
     * @var EntityUploadData
     */
    protected $entityUploadData;

    /**
     * Ссылка на AR-модель "Словарь групп продуктов"
     *
     * @var EntityDicProductGroup
     */
    protected $entityDicProductGroup;

    /**
     * Ссылка на AR-модель "Группы продуктов"
     *
     * @var EntityProductGroup
     */
    protected $entityProductGroup;


    /**
     * Конструктор
     *
     * @param EntityUploadData      $entityUploadData
     * @param EntityDicProductGroup $entityDicProductGroup
     * @param EntityProductGroup    $entityProductGroup
     */
    public function __construct(
        EntityUploadData $entityUploadData,
        EntityDicProductGroup $entityDicProductGroup,
        EntityProductGroup $entityProductGroup
    )
    {
        $this->entityUploadData      = $entityUploadData;
        $this->entityDicProductGroup = $entityDicProductGroup;
        $this->entityProductGroup    = $entityProductGroup;
    }

    /**
     * Удаление записей старых загрузок
     *
     * @param integer $idSite - ИД сайта
     * @param string  $type   - тип продукции
     */
    public function deleteOld(int $idSite, string $type, $idUploadData)
    {
        $ids = (new \yii\db\Query())
            ->select('upload.id')
            ->from(
                $this->entityProductGroup::tableName() . ' page, '
                . $this->entityDicProductGroup::tableName() . ' dic, '
                . $this->entityUploadData::tableName() . ' upload'
            )
            ->where('dic.id = page.fkIdDicPageGroup and upload.fkIdSitePageGroup=page.id and page.fkIdSite=:idSite and dic.groupName=:type AND upload.id<>:id')
            ->addParams([':idSite' => $idSite, ':type' => $type, ':id' =>$idUploadData])
            ->all();

        $this->entityUploadData::deleteAll(['in', 'id', $ids]);
    }

    /**
     * Добавление записи
     *
     * @param integer $idSite      - ИД сайта
     * @param string  $type        - тип продукции
     * @param integer $idPageGroup - ИД группы продукции сайта
     * @return integer - ИД добавленной записи
     */
    public function insert(int $idSite, string $type, int $idPageGroup)
    {
        $obj = new $this->entityUploadData;
        $obj->key               = $idSite . '_' . $type . '_' . date('d_m_Y_H_i_s');
        $obj->fkIdSitePageGroup = $idPageGroup;
        $obj->createdAt         = gmdate("Y-m-d H:i:s");
        $obj->save();

        return Yii::$app->db->lastInsertID;
    }

    /**
     * Данные о пакете загрузке
     *
     * @param integer $id - ИД загрузки
     * @return array - массив данных пакета загрузки
     */
    public function getOne(int $id)
    {
        return (new \yii\db\Query())
            ->select('page.fkIdSite idSite, dic.groupName type')
            ->from(
                $this->entityProductGroup::tableName() . ' page, '
                . $this->entityDicProductGroup::tableName() . ' dic, '
                . $this->entityUploadData::tableName() . ' upload'
            )
            ->where('dic.id = page.fkIdDicPageGroup and upload.fkIdSitePageGroup=page.id AND upload.id=:id')
            ->addParams([':id' => $id])
            ->one();
        /*
        $request =
            'SELECT '
            . 'page.fkIdSite idSite '
            . ', dic.groupName type '
            . 'FROM '
            . 'lense_dic_site_page_group dic, '
            . 'lense_site_page_group page, '
            . 'lense_upload_data upload '
            . 'WHERE '
            . 'page.fkIdDicPageGroup=dic.id '
            . 'AND upload.fkIdSitePageGroup=page.id '
            . 'AND upload.id=:id '
        ;
        return Yii::$app->db->createCommand($request)
            ->bindValue(':id', $id)
            ->queryOne();
        */
    }

}
