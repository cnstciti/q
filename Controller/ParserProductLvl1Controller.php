<?php

namespace admlenses\controllers;

use service\Lenses\ParserLevel1\Service     as ServiceParserLevel1;
use service\Lenses\Provider\Factory         as FactoryProvider;
use service\Lenses\ProductGroup\Service as ServiceProductGroup;
use service\Lenses\Site\Service             as ServiceSite;
use Yii;
use yii\base\Module;
use yii\data\ArrayDataProvider;

/**
 *
 */
class ParserProductLvl1Controller extends \yii\web\Controller
{
    /**
     * Ссылка на фабрику поставщиков информации с сайтов-доноров
     *
     * @var FactoryProvider
     */
    protected $factoryProvider;

    /**
     * Ссылка на сервис сайтов
     *
     * @var ServiceSite
     */
    protected $serviceSite;

    /**
     * Ссылка на сервис групп продукции сайтов
     *
     * @var ServiceProductGroup
     */
    protected $serviceProductGroup;

    /**
     * Ссылка на сервис парсера - уровень 1
     *
     * @var ServiceParserLevel1
     */
    protected $serviceParserLevel1;


    /**
     * Конструктор
     *
     * @param string                  $id - the ID of this controller.
     * @param Module                  $module - the module that this controller belongs to.
     * @param ServiceSite             $serviceSite - ссылка на сервис сайтов
     * @param ServiceProductGroup $serviceProductGroup - ссылка на сервис сайтов
     * @param FactoryProvider         $factoryProvider - ссылка на фабрику поставщиков информации с сайтов-доноров
     * @param ServiceParserLevel1     $serviceParserLevel1 - ссылка на сервис парсера - уровень 1
     * @param array                   $config - name-value pairs that will be used to initialize the object properties.
     */
    public function __construct(
        string $id,
        Module $module,
        ServiceSite $serviceSite,
        ServiceProductGroup $serviceProductGroup,
        FactoryProvider $factoryProvider,
        ServiceParserLevel1 $serviceParserLevel1,
        array $config = []
    )
    {
        $this->serviceSite             = $serviceSite;
        $this->serviceProductGroup = $serviceProductGroup;
        $this->factoryProvider         = $factoryProvider;
        $this->serviceParserLevel1     = $serviceParserLevel1;
        parent::__construct($id, $module, $config);
    }

    /**
     * Список всех "активных" сайтов
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $this->serviceSite->getDataAsArray($this->serviceSite->getList()),
            'pagination' => [
                'pageSize' => 500,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Список групп продукции сайта
     *
     * @param int $idSite - ИД сайта
     * @return mixed
     */
    public function actionList($idSite)
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $this->serviceProductGroup->getDataAsArray($this->serviceProductGroup->getList($idSite)),
            'pagination' => [
                'pageSize' => 500,
            ],
        ]);

        return $this->render('list', [
            'dataProvider' => $dataProvider,
            'nameSite'     => $this->serviceSite->getOne($idSite)->name,
        ]);
    }

    /**
     * Действие "Просмотр данных загрузки продукции интернет-сайта".
     *
     * @param int $idSite - ИД сайта
     * @param string $type - Тип продукта
     * @return mixed
     */
    public function actionView($idSite, $type)
    {
        set_time_limit(3000);
        $page = $this->factoryProvider::build($idSite, $type);
        $data = $this->serviceParserLevel1->parser($page);

        $dataProvider = new ArrayDataProvider([
            'allModels' => $this->serviceParserLevel1->getDataAsArray($data),
            'pagination' => [
                'pageSize' => 500,
            ],
        ]);

        return $this->render('view', [
            'dataProvider' => $dataProvider,
            'nameSite'     => $this->serviceSite->getOne($idSite)->name,
            'nameGroup'    => $this->serviceProductGroup->getOne($idSite, $type)->name,
            'idSite'       => $idSite,
        ]);
    }

    /**
     * Действие "Запись данных загрузки продукции интернет-сайта".
     *
     * @param int $idSite - ИД сайта
     * @param string $type - Тип продукта
     * @return mixed
     */
    public function actionSave($idSite, $type)
    {
        set_time_limit(28800);
        Yii::$app->db->createCommand('SET SESSION wait_timeout = 28800;')->execute();
        $page = $this->factoryProvider::build($idSite, $type);
        $data = $this->serviceParserLevel1->parser($page);
        $this->serviceParserLevel1->save($idSite, $type, $data);

        return $this->render('save', [
            'nameSite'     => $this->serviceSite->getOne($idSite)->name,
            'nameGroup'    => $this->serviceProductGroup->getOne($idSite, $type)->name,
            'idSite'       => $idSite,
        ]);
    }

}
