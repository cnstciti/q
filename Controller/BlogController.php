<?php

namespace admin\controllers;

use admin\components\BaseController;
use admin\models\BlogSearch;
use Yii;
use yii\filters\AccessControl;
use common\models\Theme;
use yii\helpers\ArrayHelper;
use common\models\Blog;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

class BlogController extends BaseController
{
    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $this->can('feed-post');
        }

        return true;
    }


    #region Theme

    public function actionIndex()
    {
        $dataProvider = Theme::find();

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }


    public function actionEdit($id = null)
    {
        if (is_null($id)) {
            $theme = new Theme();
        } else {
            $theme = Theme::findOne($id);
        }

        if ($theme->load(\Yii::$app->request->post()) && $theme->validate()) {
            if (!is_array($theme->tagsIds)) {
                $theme->tagsIds = [];
            }

            $theme->save();

            return $this->redirect(['blog/index']);
        }


        $themesHierarchy = $this->_hierarchy(Theme::find()->all());


        return $this->render('edit', ['theme' => $theme, 'themesHierarchy' => $themesHierarchy]);
    }


    public function actionDelete($id = null)
    {
        if (is_null($id)) {
            throw new ServerErrorHttpException();
        }

        /** @var Theme $theme */
        if ($theme = Theme::findOne($id)) {
            $theme->isDeleted = true;
            $theme->save();
        }

        return $this->redirect(['blog/index']);
    }


    public function actionRestore($id = null)
    {
        if (is_null($id)) {
            throw new ServerErrorHttpException();
        }

        /** @var Theme $theme */
        if ($theme = Theme::findOne($id)) {
            $theme->isDeleted = false;
            $theme->save();
        }

        return $this->redirect(['blog/index']);
    }

    #endregion

    #region blog

    public function actionList()
    {
        $searchModel = new BlogSearch();
        $searchModel->type = null;
        $searchModel->status = null;

        return $this->render(
            'list',
            [
                'dataProvider' => $searchModel->search(Yii::$app->request->queryParams),
                'searchModel'  => $searchModel,
            ]
        );
    }


    public function actionEditBlog($id = null)
    {
        if (is_null($id)) {
            $blog = new Blog();
        } else {
            $blog = Blog::findOne($id);
        }

        if ($blog->load(\Yii::$app->request->post()) && $blog->validate()) {
            if (!is_array($blog->blogThemeIds)) {
                $blog->blogThemeIds = [];
            }

            //die(var_dump(\Yii::$app->request->post()));

            if ($blog->coverType == Blog::COVER_IMAGE) {
                $blog->coverFile = UploadedFile::getInstance($blog, 'coverFile');
            }
            $blog->save();

            return $this->redirect(['blog/list']);
        }

        $themesHierarchy = $this->_hierarchy(Theme::find()->all());

        return $this->render('edit-blog', ['feedPost' => $blog, 'themesHierarchy' => $themesHierarchy]);
    }


    public function actionDeleteBlog($id = null)
    {
        if (is_null($id)) {
            throw new ServerErrorHttpException();
        }

        /** @var Blog $blog */
        if ($blog = Blog::findOne($id)) {
            $blog->isDeleted = true;
            $blog->save();
        }

        return $this->redirect(['blog/list']);
    }


    public function actionRestoreBlog($id = null)
    {
        if (is_null($id)) {
            throw new ServerErrorHttpException();
        }

        /** @var Blog $blog */
        if ($blog = Blog::findOne($id)) {
            $blog->isDeleted = false;
            $blog->save();
        }

        return $this->redirect(['blog/list']);
    }


    #endregion


    private function _hierarchy($themes)
    {

        $out = [];

        foreach ($themes as $item) {
            $out[ $item->id ] = $item->name;
        }


        return $out;
    }
}