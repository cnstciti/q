<?php

namespace admin\controllers;

use admin\components\BaseController;
use admin\forms\CsvForm;
use admin\models\DiagnosisSearch;
use common\models\dictionaries\Diagnosis;
use common\models\dictionaries\QuestionTag;
use common\models\dictionaries\translate\DiagnosisTranslate;
use Yii;
use yii\filters\AccessControl;
use yii\web\UploadedFile;
use yii\web\Response;
use admin\forms\TranslateForm;


/**
 * Class DiagnosesController
 *
 * Контроллер диагнозов
 *
 * @package admin\controllers
 */
class DiagnosesController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }


    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $this->can('diagnoses');
        }

        return true;
    }


    /**
     * Список диагнозов
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new DiagnosisSearch();

        return $this->render(
            'list',
            [
                'dataProvider' => $searchModel->search(Yii::$app->request->queryParams),
                'searchModel'  => $searchModel,
            ]
        );
    }


    /**
     * Список диагнозов
     *
     * @return string
     */
    public function actionDeleteUnUse()
    {
        $diases = Diagnosis::find()->where(['isDeleted' => false])->all();

        foreach ($diases as $diase) {
            /** @var Diagnosis $diase */
            if (!$diase->isUseInPatientProfile()) {
                $diase->delete();
            }
        }
    }


    /**
     * Создание/редактирование диагноза
     *
     * @param null $id
     *
     * @return string
     */
    public function actionEdit($id = null)
    {
        if (!$diagnosis = Diagnosis::findOne($id)) {
            $diagnosis = new Diagnosis();
        }

        if ($diagnosis->load(\Yii::$app->request->post()) && $diagnosis->validate()) {
            $diagnosis->save();
            $this->redirect('/diagnoses');
        }

        return $this->render('edit', ['diagnosis' => $diagnosis]);
    }


    public function actionEditTranslate($translateId = null)
    {
        if (!$translate = DiagnosisTranslate::findOne($translateId)) {
            $translate = new DiagnosisTranslate();
        }

        if (\Yii::$app->request->isAjax) {
            \Yii::$app->response->format = Response::FORMAT_JSON;

            return [
                'html' => $this->renderAjax('_modalEdit', ['translate' => $translate]),
            ];
        }


        if ($translate->load(\Yii::$app->request->post())) {
            if (!$diagnosis = Diagnosis::findOne($translate->diagnosisId)) {
                throw new NotFoundHttpException();
            }

            if ($translate->validate()) {
                $translate->save();
            }

            return $this->redirect(['diagnoses/edit', 'id' => $diagnosis->id, '#' => 'translates']);
        }

        return $this->redirect(['diagnoses/index']);
    }


    /**
     * Удаление диагноза
     *
     * @param $id
     */
    public function actionDelete($id)
    {
        /** @var Diagnosis $diagnosis */
        $diagnosis            = Diagnosis::findOne($id);
        $diagnosis->isDeleted = true;
        $diagnosis->save();
        $this->redirect('/diagnoses');
    }


    /**
     * Восстановление диагноза
     *
     * @param $id
     */
    public function actionRestore($id)
    {
        /** @var Diagnosis $diagnosis */
        $diagnosis            = Diagnosis::findOne($id);
        $diagnosis->isDeleted = false;
        $diagnosis->save();
        $this->redirect('/diagnoses');
    }


    /**
     * Загрузка из CSV
     *
     * @return string
     */
    public function actionUploadCsv()
    {
        $form = new CsvForm();

        if ($form->load(Yii::$app->request->post())) {
            $form->file = UploadedFile::getInstance($form, 'file');

            if ($form->validate()) {
                $diagnoses = $form->getData();

                $countAdded = Diagnosis::importList($diagnoses);

                Yii::$app->session->setFlash('csvImport', 'Добавлено новых элементов: ' . $countAdded);

                return Yii::$app->response->redirect(['diagnoses/upload-csv']);
            }
        }

        return $this->render('csv-upload', ['model' => $form]);
    }


    /**
     * Загрузка перевода из файла CSV
     *
     * @return Response
     */
    public function actionTranslatesUpload()
    {
        $form = new TranslateForm();

        if ($form->load(Yii::$app->request->post())) {
            $form->file = UploadedFile::getInstance($form, 'file');

            if ($form->validate()) {
                $countAdded = $form->saveAll(Diagnosis::className(), DiagnosisTranslate::className());
                Yii::$app->session->setFlash('csvImport', 'Добавлено новых переводов: ' . $countAdded);
            }
        }

        return $this->redirect(['diagnoses/index']);
    }


    /**
     * Загрузка из тегов
     *
     * @return string
     */
    public function actionUploadTag()
    {
        $request  = Yii::$app->request;
        $queryTag = null;

        if ($request->isPost) {
            $questionType = $request->post('questionType');

            $arrName = [];
            $query   = Diagnosis::find()->all();
            /** @var Diagnosis $diagnosis */
            foreach ($query as $diagnosis) {
                $arrName[] = $diagnosis->name;
            }

            $queryTag = QuestionTag::find()
                                   ->where(['"typeId"' => $questionType])
                                   ->andWhere(['"isDeleted"' => false])
                                   ->andWhere(['NOT IN', 'name', $arrName])
                                   ->orderBy('id ASC')
                                   ->all();
        }

        return $this->render('tag-upload', ['queryTag' => $queryTag]);
    }


    // сохраняем выбранные теги в заболевания
    public function actionAddTags()
    {
        $request = Yii::$app->request;

        if ($request->isPost) {
            $tags = $request->post('tags');
            foreach ($tags as $key => $value) {
                /** @var Diagnosis $diagnosis */
                $diagnosis            = new Diagnosis();
                $diagnosis->name      = $value;
                $diagnosis->isDeleted = false;
                $diagnosis->save();
            }
            $this->redirect('/diagnoses');
        }
    }

    /**
     * Автокомплит диагнозов
     *
     * @param null $q
     *
     * @return array
     */
    public function actionGetDiagnoses($q = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $qInvert = $this->correctLanguage($q);

        $results = Diagnosis::searchByName($q, $qInvert);

        return [
            'results' => $results,
        ];
    }

    private function correctLanguage($string)
    {
        $search = [
            'q'  => 'й',
            'w'  => 'ц',
            'e'  => 'у',
            'r'  => 'к',
            't'  => 'е',
            'y'  => 'н',
            'u'  => 'г',
            'i'  => 'ш',
            'o'  => 'щ',
            'p'  => 'з',
            '['  => 'х',
            ']'  => 'ъ',
            'a'  => 'ф',
            's'  => 'ы',
            'd'  => 'в',
            'f'  => 'а',
            'g'  => 'п',
            'h'  => 'р',
            'j'  => 'о',
            'k'  => 'л',
            'l'  => 'д',
            ';'  => 'ж',
            '\'' => 'э',
            'z'  => 'я',
            'x'  => 'ч',
            'c'  => 'с',
            'v'  => 'м',
            'b'  => 'и',
            'n'  => 'т',
            'm'  => 'ь',
            ','  => 'б',
            '.'  => 'ю',
            'Q'  => 'Й',
            'W'  => 'Ц',
            'E'  => 'У',
            'R'  => 'К',
            'T'  => 'Е',
            'Y'  => 'Н',
            'U'  => 'Г',
            'I'  => 'Ш',
            'O'  => 'Щ',
            'P'  => 'З',
            '{'  => 'Х',
            '}'  => 'Ъ',
            'A'  => 'Ф',
            'S'  => 'Ы',
            'D'  => 'В',
            'F'  => 'А',
            'G'  => 'П',
            'H'  => 'Р',
            'J'  => 'О',
            'K'  => 'Л',
            'L'  => 'Д',
            ':'  => 'Ж',
            '"'  => 'Э',
            'Z'  => 'Я',
            'X'  => 'Ч',
            'C'  => 'С',
            'V'  => 'М',
            'B'  => 'И',
            'N'  => 'Т',
            'M'  => 'Ь',
            '<'  => 'Б',
            '>'  => 'Ю',
        ];
        if (\Yii::$app->language !== 'ru-RU') {
            return $string;
        }

        $correct = strtr($string, $search);

        return $correct;
    }
}