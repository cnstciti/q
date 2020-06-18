<?php

namespace adm\controllers;

use service\Dimex\Prepare   as ServiceDimex;
use service\StatusDecorator as Decorator;
use yii\base\Module;
use yii\data\ArrayDataProvider;

/**
 * Контроллер подготовки данных объектов "Пункты доставки Dimex"
 *
 * @author Constantin Ogloblin <cnst@mail.ru>
 * @since 1.0.0
 */
class DimexController extends \yii\web\Controller
{
    /**
     * Ссылка на сервис подготовки данных объектов "Пункты доставки Dimex"
     *
     * @var ServiceDimex
     */
    protected $serviceDimex;

    /**
     * Ссылка на декоратор статуса
     *
     * @var Decorator
     */
    protected $decorator;


    /**
     * Конструктор
     *
     * @param string       $id - the ID of this controller.
     * @param Module       $module - the module that this controller belongs to.
     * @param ServiceDimex $serviceDimex
     * @param Decorator    $decorator
     * @param array        $config - name-value pairs that will be used to initialize the object properties.
     */
    public function __construct(string $id, Module $module, ServiceDimex $serviceDimex, Decorator $decorator, array $config = [])
    {
        $this->serviceDimex = $serviceDimex;
        $this->decorator    = $decorator;
        parent::__construct($id, $module, $config);
    }

    /**
     * Действие "Главная страница"
     *
     * @return mixed
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Действие "Просмотр данных"
     *
     * @return mixed
     */
    public function actionView()
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $this->serviceDimex->collectionDataAsArray(),
            'pagination' => [
                'pageSize' => 500,
            ],
        ]);

        return $this->render('view', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Действие "Cохранение данных"
     *
     * @return mixed
     */
    public function actionSave()
    {
        $isExistData = $this->serviceDimex->existData();

        if (!$isExistData) {
            $this->serviceDimex->saveData();
        }

        return $this->render('save', [
            'msg' => $this->decorator->msgSaveStatus($isExistData),
        ]);
    }

    /**
     * Действие "Удаление всех данных"
     *
     * @return mixed
     */
    public function actionDelete()
    {
        $this->serviceDimex->deleteData();

        return $this->render('delete');
    }

}
