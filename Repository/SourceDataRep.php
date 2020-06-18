<?php

namespace repository\Lenses;

use entity\Lenses\SourceDataAR as EntitySourceData;
use Yii;

/**
 * Репозиторий "Информация загрузки первоначальных данных"
 *
 * @author Constantin Ogloblin <cnst@mail.ru>
 * @since 1.0.0
 */
class SourceDataRep
{
    /**
     * Ссылка на AR-модель "Информация загрузки первоначальных данных"
     *
     * @var EntitySourceData
     */
    protected $entitySourceData;


    /**
     * Конструктор
     *
     * @param EntitySourceData $entitySourceData
     */
    public function __construct(
        EntitySourceData $entitySourceData
    )
    {
        $this->entitySourceData = $entitySourceData;
    }

    /**
     * Пакетное добавление записей
     *
     * @param array $data      - массив данных для сохранения
     * @param array $atributes - массив атрибутов (маппинг данных)
     */
    public function batchInsert(array $data, array $atributes)
    {
        Yii::$app->db->createCommand()->batchInsert(
            $this->entitySourceData::tableName(),
            $atributes,
            $data
        )->execute();
    }

    /**
     * Возвращает общее количество продуктов
     *
     * @return integer
     */
    public function countAll()
    {
        return $this->entitySourceData::find()
            ->count();
    }

    /**
     * Возвращает один продукт
     *
     * @return array
     */
    public function getOne()
    {
        return $this->entitySourceData::find()
            ->orderBy(['createdAt' => SORT_ASC])
            ->one();
    }

    /**
     * Удаление записи
     *
     * @param array $data - массив удаляемой информации
     */
    public function deleteRow($data)
    {
        $row = $this->entitySourceData::find()
            ->where('fkIdUploadData=:fkIdUploadData AND href=:href AND volume=:volume AND numberPack=:numberPack')
            ->addParams([':fkIdUploadData' => $data->fkIdUploadData, ':href' => $data->href, ':volume' => $data->volume, ':numberPack' => $data->numberPack])
            ->one();
        $row->delete();
    }

    /**
     * Число записей загрузки
     *
     * @param int $fkIdUploadData - ИД загрузки
     * @return int - количество записей загрузки
     */
    public function countRowsUpload($fkIdUploadData)
    {
        return $this->entitySourceData::find()
            ->where('fkIdUploadData=:fkIdUploadData')
            ->addParams([':fkIdUploadData' => $fkIdUploadData])
            ->count();
    }
}
