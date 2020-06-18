<?php
/**
 * @var \yii\web\View                  $this
 * @var \common\models\users\Dialog[]  $dialogs
 * @var \common\models\users\User      $user
 * @var \yii\data\ActiveDataProvider   $dataProvider
 * @var admin\models\MessageLastSearch $searchModel
 */

use kartik\select2\Select2;
use yii\web\JsExpression;
use yii\helpers\Url;
use kartik\export\ExportMenu;
use kartik\grid\GridView;
use yii\helpers\Html;
use common\models\users\User;
use common\models\users\Message;

$authorMessages = [];
if ($user) {
    $authorMessages = [$user->id => $user->fullName];;
}

$this->title = Yii::t('app', 'Сообщения');

\admin\assets\MessagesAsset::register($this);
?>
    <br/><br/><br/>
    <div class="row">
        <?php if (Yii::$app->session->hasFlash('noDialogs')): ?>
            <div class="alert alert-warning">
                <?= Yii::$app->session->getFlash('noDialogs') ?>
            </div>
        <?php endif; ?>
        <div class="col-md-1">
            <?php if ($dialogs): ?>
                <p style="display: inline-block; margin-top:7px;"><a href="<?= Url::to(['messages/index']) ?>">Назад</a>
                </p>
            <?php endif; ?>
        </div>
        <div class="col-md-3 text-right">
            <label for="userFio" style="display: inline-block; margin-top:7px;">Диалоги пользователя: </label>
        </div>
        <div class="col-md-8">
            <?= Select2::widget(
                [
                    'name'          => 'userFio',
                    'data'          => $authorMessages,
                    'pluginOptions' => [
                        'allowClear'         => true,
                        'minimumInputLength' => 1,
                        'ajax'               => [
                            'url'      => '/messages/get-users',
                            'dataType' => 'json',
                            'data'     => new JsExpression('function(params) { return {q:params.term}; }'),
                        ],
                        'escapeMarkup'       => new JsExpression('function (markup) { return markup; }'),
                        'templateResult'     => new JsExpression('function(city) { return city.text; }'),
                        'templateSelection'  => new JsExpression('function (city) { return city.text; }'),
                    ],
                    'pluginEvents'  => [
                        "change" => "function() {
                                    window.location.replace('/messages?id='+ this.value );
                               }",
                    ],
                ]
            ) ?>
        </div>
    </div>
    <br/>
<?php if ($dialogs): ?>

    <br/>
    <div class="Container">
        <div class="Dialog-Contacts">
            <div class="Dialog-ContactsList PatientsDialogsT">
                <?php foreach ($dialogs as $dialog): ?>
                    <div class="Dialog-ContactsRow<?= $dialog->existNotReaded ? ' Dialog-ContactsRowNewMsg' : '' ?>"
                         data-dialog-link="<?= Url::to(
                             ['messages/view', 'id' => $dialog->receiver->id, 'idUser' => $user->id]
                         ) ?>"
                         data-dialog-id="<?= $dialog->receiver->id ?>">
                        <img src="<?= $dialog->receiver->getSmallPhotoUrl() ?>" alt=""/>

                        <div class="Dialog-ContactsRowText">
                            <h3>
                                <?= $dialog->receiver->getFirstMiddleName() ?>
                            </h3>
                            <div class="Dialog-ContactsRowTextLastPost">
                                <div class="Dialog-ContactsRowTextLastPostCont">
                                    <img src="/static/img/redesign/TPSmallPhoto1.png" alt=""/>
                                    <p>
                                        <?= $dialog->lastMessage->message; ?>
                                    </p>
                                    <div class="LastPostContNew">
                                        <?= $dialog->countNotReaded > 0 ? "+ " . $dialog->countNotReaded : '' ?>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="Dialog-ContactsRowTime">
                            <?= date('H:i', $dialog->lastMessage->created) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
        <div class="Dialog-Chat" style=" ">

            <?php if (isset($messages)): ?>

                <?= $this->render(
                    '/messages/view',
                    [
                        'messages'    => $messages,
                        'receiver'    => $receiver,
                        'time'        => $time,
                        'currentUser' => $user,
                    ]
                ) ?>

            <?php endif; ?>

        </div>
    </div>
<?php else: ?>

    <?php
    $gridColumn = [
        [
            'label'     => Yii::t('app', 'Дата'),
            'format'    => 'raw',
            'attribute' => 'created',
            'value'     => function ($row) {
                return date('d.m.Y H:i', $row['created']);
            },
        ],
        [
            'label'          => Yii::t('app', 'Отправитель'),
            'attribute'      => 'senderUserId',
            'format'         => 'raw',
            'contentOptions' => ['style' => 'font-size:10px;'],
            'value'          => function ($row) {
                $user = User::findOne($row['senderUserId']);
                if ($user) {
                    $name = trim($user->getFullName());

                    return
                        $user->getTypeName() .
                        ', ' .
                        $user->id .
                        ', ' .
                        $user->email .
                        '<br/>' .
                        Html::a($name ? $name : 'имя не указано', ['messages/index', 'id' => $user->id]);
                }

                return "";
            },
        ],
        [
            'label'          => Yii::t('app', 'Получатель'),
            'attribute'      => 'receiverUserId',
            'format'         => 'raw',
            'contentOptions' => ['style' => 'font-size:10px;'],
            'value'          => function ($row) {
                $user = User::findOne($row['receiverUserId']);
                if ($user) {
                    $name = trim($user->getFullName());

                    return $user->getTypeName() .
                           ', ' .
                           $user->id .
                           ', ' .
                           $user->email .
                           '<br/>' .
                           Html::a($name ? $name : 'имя не указано', ['messages/index', 'id' => $user->id]);
                }

                return "";
            },
        ],
        [
            'label'     => Yii::t('app', 'Сообщение'),
            'format'    => 'raw',
            'attribute' => 'id',
            'value'     => function ($row) {
                $message = Message::findOne($row['id']);
                if ($message) {
                    return $message->message . '<br/>' . ($message->readed ? '' : 'не прочитано');
                }

                return "";
            },
        ],

    ];

    $fullExportMenu = ExportMenu::widget(
        [
            'dataProvider'     => $dataProvider,
            'columns'          => $gridColumn,
            'target'           => ExportMenu::TARGET_BLANK,
            //'fontAwesome' => true,
            'showConfirmAlert' => false,
            'pjaxContainerId'  => 'kv-pjax-container',
            'exportConfig'     => [
                ExportMenu::FORMAT_TEXT => false,
                ExportMenu::FORMAT_PDF  => false,
                ExportMenu::FORMAT_HTML => false,
            ],
            'dropdownOptions'  => [
                'label'       => 'Full',
                'class'       => 'btn btn-default',
                'itemsBefore' => [
                    '<li class="dropdown-header">Export All Data</li>',
                ],
            ],
        ]
    );
    ?>


    <?php echo GridView::widget(
        [
            'dataProvider' => $dataProvider,
            'filterModel'  => $searchModel,
            'pjax'         => false,
            'pjaxSettings' => ['options' => ['id' => 'kv-pjax-container', 'enablePushState' => true,]],
            'panel'        => [
                'type' => GridView::TYPE_PRIMARY,
            ],
            'responsive'   => true,
            'toolbar'      => [
                [
                    'content' =>
                        Html::a(
                            '<i class="glyphicon glyphicon-repeat"></i>',
                            ['messages/index'],
                            [
                                'data-pjax' => 0,
                                'class'     => 'btn btn-default',
                                'title'     => Yii::t('app', 'Сбросить фильтры'),
                            ]
                        ),
                ],
                $fullExportMenu,
                '{export}',
            ],
            'exportConfig' => [
                GridView::EXCEL => true,
                GridView::CSV   => true,
            ],
            'columns'      => $gridColumn,
        ]
    );
    ?>
<?php endif; ?>