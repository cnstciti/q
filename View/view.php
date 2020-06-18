<?php
/**
 * @var \yii\web\View             $this
 * @var \common\models\users\User $user
 */

use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use kartik\export\ExportMenu;
use yii\bootstrap\Modal;
use yii\widgets\ActiveForm;
use admin\forms\AddOrderForm;
use admin\forms\EditOrderForm;
use \common\models\billing\Order;
use \common\models\calls\Call;

$this->title                   = 'Взаиморасчеты с пользователем ' . $user->getFullName();
$this->params['breadcrumbs'][] = [
    'label' => 'Взаиморасчеты с пользователем',
    'url'   => ['balance/view', 'id' => $user->id],
];

if (!isset($form)) {
    $form = new AddOrderForm();
}

$editForm = new EditOrderForm();

$gridColumn = [
    [
        'label'     => 'Дата',
        'format'    => 'raw',
        'attribute' => 'created',
        'value'     => function ($item) {
            return date('d.m.Y H:i', $item['created']);
        },
    ],
    [
        'label'     => 'Описание',
        'attribute' => 'description',
    ],
    [
        'label'     => 'Приход',
        'attribute' => 'income',
        'hAlign'    => 'center',
    ],
    [
        'label'     => 'Расход',
        'attribute' => 'expense',
        'hAlign'    => 'center',
    ],
    [
        'format' => 'raw',
        'value'  => function ($item) {
            if ($item['type'] == 'messages') {
                return Html::a(
                    'Отменить',
                    '#',
                    [
                        'data-pjax'    => 0,
                        'class'        => 'serviceEdit',
                        'title'        => Yii::t('app', 'Отменить'),
                        'data-orderid' => $item['callId'],
                    ]
                );
            }

            if ($item['type'] == 'order') {
                return Html::a(
                    'Изменить',
                    '#',
                    [
                        'data-pjax'    => 0,
                        'class'        => 'orderEdit',
                        'title'        => Yii::t('app', 'Изменить'),
                        'data-orderid' => $item['orderid'],
                    ]
                );
            }

            if ($item['type'] == 'call' && $item['isUserCall']) {
                if ($item['status'] == Call::STATUS_COMPLETED){
                    return  Html::a(
                        'Отменить',
                        '#',
                        [
                            'data-pjax'    => 0,
                            'class'        => 'cancel-call-link',
                            'title'        => Yii::t('app', 'Отменить'),
                            'data-callid' => $item['callId'],
                        ]
                    );    
                } elseif ($item['status'] == Call::STATUS_CANCELLED){
                    return  Html::a(
                        'Восстановить',
                        ['calls/restore', 'id' => $item['callId']],
                        [
                            'data-pjax'    => 0,
                            'title'        => Yii::t('app', 'Восстановить'),
                        ]
                    );
                }
                
            }
            
            return '';
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

<?php if (Yii::$app->session->hasFlash('updateBalance')): ?>
    <div class="alert alert-success">
        <?= Yii::$app->session->getFlash('updateBalance') ?>
    </div>
<?php endif; ?>

    <div id="baseInfo">
        <h2><?= $user->getFullName() ?></h2>
        <p>
            Всего оплачено: <?= $dataProvider->getSum('amount') ?> |
            Куплено звонков: <?= $dataProvider->getSum('income') ?> |
            Потрачено звонков: <?= $dataProvider->getSum('expense') ?> |
            Остаток звонков: <?= $user->patient->callsBalance ?>
            &nbsp;&nbsp;
            <a href="<?= Url::to(['balance/update', 'id' => $user->id]) ?>">Пересчитать баланс</a>
        </p>
        <form method="get">
            Показывать:
            &nbsp;&nbsp;
            <div class="radio-inline">
                <label>
                    <input type="radio" name="showAllOrders" value="true"<?= $showAllOrders == 'true' ||
                                                                             $showAllOrders === null ? ' checked' :
                        '' ?>>
                    Все заказы
                </label>
            </div>
            <div class="radio-inline">
                <label>
                    <input type="radio" name="showAllOrders" value="false"<?= $showAllOrders == 'false' ? ' checked' :
                        '' ?>>
                    Только оплаченные
                </label>
            </div>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <div class="radio-inline">
                <label>
                    <input type="radio" name="showAllCalls" value="true"<?= $showAllCalls == 'true' ||
                                                                            $showAllCalls === null ? ' checked' : '' ?>>
                    Все звонки
                </label>
            </div>
            <div class="radio-inline">
                <label>
                    <input type="radio" name="showAllCalls" value="false"<?= $showAllCalls == 'false' ? ' checked' :
                        '' ?>>
                    Только завершенные
                </label>
            </div>
            &nbsp;&nbsp;
            <button class="btn btn-default">Применить</button>
        </form>
        <br/>
        <?php echo GridView::widget(
            [
                'dataProvider' => $dataProvider,
                'pjax'         => true,
                'pjaxSettings' => ['options' => ['id' => 'kv-pjax-container']],
                'panel'        => [
                    'type'    => GridView::TYPE_PRIMARY,
                    'heading' => 'Взаиморасчеты с пользователем',
                ],
                'responsive'   => true,
                'toolbar'      => [
                    [
                        'content' =>
                            Html::button(
                                '<i class="glyphicon glyphicon-plus"></i> Добавить заказ',
                                [
                                    'data-pjax'   => 1,
                                    'class'       => 'btn btn-success',
                                    'title'       => Yii::t('app', 'Добавить заказ'),
                                    'data-target' => '#dialogModal',
                                    'data-toggle' => 'modal',
                                ]
                            ) . ' ' .
                            Html::a(
                                '<i class="glyphicon glyphicon-repeat"></i>',
                                ['balance/view', 'id' => $user->id],
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
                'rowOptions'   => function ($model) {
                    if ($model['expense']) {
                        return ['style' => 'color: #a94442; background-color: #f2dede;'];
                    }
                    if ($model['income']) {
                        return ['style' => 'color: #3c763d; background-color: #dff0d8;'];
                    }
                },
                'columns'      => $gridColumn,
            ]
        );
        ?>
    </div>

<?php Modal::begin(
    [
        'id'      => 'dialogModal',
        'header'  => '<h4 class="modal-title">Создать заказ (' . $user->getFullName() . ')</h4>',
        'options' => ['style' => 'margin-top:30px;'],
    ]
);
?>

<?php $aForm = ActiveForm::begin(
    [
        'action'  => Url::to(['balance/add-order']),
        'options' => ['data-pjax' => '1'],
        'id'      => 'callCostForm',
    ]
);
?>

<?= $aForm->field($form, 'userId')->hiddenInput(['value' => $user->id])->label(false) ?>

<?= $aForm->field($form, 'countCalls')->textInput() ?>

<?= $aForm->field($form, 'amount')->textInput() ?>

<?= $aForm->field($form, 'description')->textarea() ?>

    <button class="btn btn-primary">Добавить</button>

<?php ActiveForm::end(); ?>

<?php Modal::end(); ?>

<?php Modal::begin(
    [
        'id'      => 'dialogModalEdit',
        'header'  => '<h4 class="modal-title">Редактирование заказа</h4>',
        'options' => ['style' => 'margin-top:30px;'],
    ]
);
?>

<?php $eForm = ActiveForm::begin(
    [
        'action'  => Url::to(['balance/edit-order']),
        'options' => ['data-pjax' => '1'],
        'id'      => 'orderEditForm',
    ]
);
?>

<?= $eForm->field($editForm, 'id')->hiddenInput()->label(false) ?>

<?= $eForm->field($editForm, 'userId')->hiddenInput(['value' => $user->id])->label(false) ?>
    <div class="form-group field-editorderform-status">
        <label class="control-label" for="editorderform-status">Статус</label>
        <select name="EditOrderForm[status]" id="editorderform-status" class="form-control">
            <option value="<?= Order::STATUS_REGISTERED ?>">Зарегистрирован</option>
            <option value="<?= Order::STATUS_IN_PROGRESS ?>">Обработка</option>
            <option value="<?= Order::STATUS_AUTHORIZED ?>">Оформление</option>
            <option value="<?= Order::STATUS_FAILED ?>">Сбой</option>
            <option value="<?= Order::STATUS_ACKNOWLEDGED ?>">Оплачен</option>
            <option value="<?= Order::STATUS_NOT_ACKNOWLEDGED ?>">Не оформлен</option>
            <option value="<?= Order::STATUS_NOT_AUTHORIZED ?>">Не оплачен</option>
            <option value="<?= Order::STATUS_CANCELLED ?>">Отменен</option>
            <option value="<?= Order::STATUS_REFUNDED ?>">Возвращен</option>
        </select>

        <div class="help-block"></div>
    </div>


<?= $eForm->field($editForm, 'countCalls')->textInput() ?>

<?= $eForm->field($editForm, 'amount')->textInput() ?>

<?= $eForm->field($editForm, 'description')->textarea() ?>

    <button class="btn btn-primary">Сохранить</button>

<?php ActiveForm::end(); ?>

<?php Modal::end(); ?>

<?php
    $this->registerJsFile('/static/lib/bootboxjs/bootbox.min.js', ['depends' => ['yii\web\JqueryAsset']]);
?>

<?php

$this->registerJs(
    "
   $(document).ready(function(){
        $(document).on('click', '.cancel-call-link', function (event) {
            event.preventDefault();

            var id = $(this).data('callid');
                
            bootbox.prompt(
                \"Укажите причину отмены звонка\",
                function (value) {
                    if (value) {
                        $.post(
                            '/calls/cancel-call',
                            {
                                callId: id,
                                reasonText: value,
                            },
                            function (data) {
                                if (data.result == 'ok'){
                                    location.reload();
                                }   
                            }
                        );
                    }
                }
            );
        });
            
        $(document).on('click','.orderEdit',function(event){
            event.preventDefault();

            var modal = $('#dialogModalEdit');

            var orderId = $(this).data('orderid');

            var amount      = $('#editorderform-amount');
            var countCalls  = $('#editorderform-countcalls');
            var description = $('#editorderform-description');
            var id          = $('#editorderform-id');
            var status      = $('#editorderform-status');

            amount.val(''); countCalls.val(''); description.val(''); id.val(''); status.val([]);

            $.post(
                '/balance/get-order',
                {
                    'id': orderId
                }
            )
            .done(function(data){
                if(data.result != 'ok'){
                    alert(data.error);
                    return false;
                }

                id.val(data.order.id);
                amount.val(data.order.amount);
                countCalls.val(data.order.countCalls);
                description.val(data.order.description);
                status.val(data.order.status);

                modal.find('.modal-title').html('Редактирование заказа № ' + data.order.id);

                modal.modal('show');
            })
            .fail(function() {
	            console.log('server error');
	        });
        });
        
        
        $(document).on('click','.serviceEdit',function(event){
            event.preventDefault();

             if (confirm('Отменить запись? Операция необратима!')) {
                    var orderId = $(this).data('orderid');

                    $.post(
                        '/balance/cancel-messages',
                        {
                            'id': orderId
                        }
                    )
                    .done(function(data){
                        if(data.result != 'ok'){
                            alert(data.error);
                            return false;
                        }
        
                        location.reload();
                    })
                    .fail(function() {
                        console.log('server error');
                    });
                }
        });
   });
    "

);


?>