<?php

namespace service\Lenses\Delivery;

use dto\Lenses\Delivery                  as DtoDelivery;
use collection\BaseCollection            as CollectionDelivery;
use repository\Lenses\DeliveryRep        as RepositoryDelivery;
use service\Lenses\Provider\BaseDelivery as Delivery;
use service\Lenses\DeliveryPrice\Service as ServiceDeliveryPrice;
use yii\helpers\ArrayHelper;

/**
 * Сервис "Доставка продуктов"
 *
 * @author Constantin Ogloblin <cnst@mail.ru>
 * @since 1.0.0
 */
class Service
{
    /**
     * Ссылка на коллекцию "Доставка продуктов"
     *
     * @var CollectionDelivery
     */
    protected $collectionDelivery;

    /**
     * Ссылка на репозиторий "Доставка продуктов"
     *
     * @var RepositoryDelivery
     */
    protected $repositoryDelivery;

    /**
     * Ссылка на сервис "Стоимости доставки продуктов"
     *
     * @var ServiceDeliveryPrice
     */
    protected $serviceDeliveryPrice;


    /**
     * Конструктор
     *
     * @param CollectionDelivery $collectionDelivery
     * @param RepositoryDelivery $repositoryDelivery
     * @param ServiceDeliveryPrice $serviceDeliveryPrice
     */
    public function __construct(
        CollectionDelivery $collectionDelivery,
        RepositoryDelivery $repositoryDelivery,
        ServiceDeliveryPrice $serviceDeliveryPrice
    )
    {
        $this->collectionDelivery   = $collectionDelivery;
        $this->repositoryDelivery   = $repositoryDelivery;
        $this->serviceDeliveryPrice = $serviceDeliveryPrice;
    }

    /**
     * Возвращает коллекцию доставки продуктов
     *
     * @param Delivery $source
     * @return CollectionDelivery
     */
    public function load(Delivery $source)
    {
        $data = $source->load();
        foreach ($data as $object) {
            $this->collectionDelivery->add(new DtoDelivery(
                $object['idSite'],
                $object['idCityFrom'],
                $object['idCityTo'],
                $object['price'],
                $object['commentPrice'],
                $object['method'],
                $object['period'],
                $object['boundary'],
                $object['schedule'],
                $object['comment']
            ));
        }

        return $this->collectionDelivery;
    }

    /**
     * Возвращает данные в виде массива
     *
     * @param object|array|string $object - объект, который конвертируется в массив
     * @return array
     */
    public function getDataAsArray($object)
    {
        return ArrayHelper::toArray($object, [
            DtoDelivery::class => DtoDelivery::atributes(DtoDelivery::class)
        ]);
    }

    /**
     * Сохраняет данные о доставке продукции в БД
     *
     * @param int                $idSite - ИД сайта
     * @param CollectionDelivery $data - коллекция доставки продукции
     */
    public function save(int $idSite, CollectionDelivery $data)
    {
        // удаляем все цены доставки
        $ids = $this->repositoryDelivery->listBySite($idSite);
        $result = [];
        array_walk_recursive($ids, function($v) use (&$result) {
            $result[] = $v;
        });
        $this->serviceDeliveryPrice->deleteData(implode(',', $result));

        foreach ($data as $object) {
            $lastInsertID = $this->repositoryDelivery->checkUnic($object->idSite, $object->idCityFrom, $object->idCityTo);
            // записи нет, добавляем ее в таблицу
            if (!$lastInsertID) {
                $lastInsertID = $this->repositoryDelivery->insert($object);
            }
            // cохраняем новые цены
            $this->serviceDeliveryPrice->insert($lastInsertID, $object);
        }
    }

}
