<?php

use admin\assets\DecisionTreeAsset;
use common\models\recommendations\Condition;
use yii\helpers\Html;
use yii\bootstrap\Modal;
use yii\widgets\ActiveForm;
use common\models\bot\algorithm\Block;
use common\models\bot\algorithm\Algorithm;

DecisionTreeAsset::register($this);

$typeForContinue = [
    Condition::TYPE_FINISH,
    Condition::TYPE_DOCTORS,
    Condition::TYPE_FIND_DOCTORS,
    Condition::TYPE_FIND_POSTS,
    Condition::TYPE_DOCTOR,
    Condition::TYPE_POST,
    Condition::TYPE_ALGORITHM,
    Condition::TYPE_GOTO_SECTION,
    Condition::TYPE_SECTION,
    Block::TYPE_MSG,
    Block::TYPE_MSG_BOT,
    Block::TYPE_REMIND_REPEAT,
    Block::TYPE_REMIND_SINGLE,
    Block::TYPE_ADD_BONUS,
    Block::TYPE_MSG_INLINE,
];

/*
 * $firstCondition
 * $allCondition
 */
?>
    <div class="row">
        <?php if (count($conditions) > 0): ?>
            <div class="col-md-2">
                <div class="dropdown" style="position:relative;">
                    Уровней:
                    <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">
                        <?php
                        if (Yii::$app->request->get('level')) {
                            echo Yii::$app->request->get('level');
                        } else {
                            echo "нет";
                        }
                        ?>

                        <span class="caret"></span></button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenu1">
                        <li>
                            <?php echo Html::a(
                                'нет',
                                [
                                    'recommendations/decision-tree',
                                    'id' => $recommend->id,
                                ]
                            ); ?>
                        </li>
                        <li>
                            <?php echo Html::a(
                                '4',
                                [
                                    'recommendations/decision-tree',
                                    'id'               => $recommend->id,
                                    'startConditionId' => Yii::$app->request->get('startConditionId'),
                                    'level'            => '4',
                                ]
                            ); ?>
                        </li>
                        <li>
                            <?php echo Html::a(
                                '6',
                                [
                                    'recommendations/decision-tree',
                                    'id'               => $recommend->id,
                                    'startConditionId' => Yii::$app->request->get('startConditionId'),
                                    'level'            => '6',
                                ]
                            ); ?>
                        </li>
                        <li>
                            <?php echo Html::a(
                                '10',
                                [
                                    'recommendations/decision-tree',
                                    'id'               => $recommend->id,
                                    'startConditionId' => Yii::$app->request->get('startConditionId'),
                                    'level'            => '10',
                                ]
                            ); ?>
                        </li>
                        <li>
                            <?php echo Html::a(
                                '14',
                                [
                                    'recommendations/decision-tree',
                                    'id'               => $recommend->id,
                                    'startConditionId' => Yii::$app->request->get('startConditionId'),
                                    'level'            => '14',
                                ]
                            ); ?>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-md-10">
            <div style="margin-top:8px; font-size:80%;">
                <?= Algorithm::getTypeName($recommend->type) ?>, <?= $recommend->name ?>
            </div>
        </div>
    </div>
<?php


$bgColorDefault = $bgColor = '#FFFFE4';


function _shade($colorHex)
{

    if (empty($colorHex)) {
        return '';
    }

    $percentage = 20;

    $rgb = [
        hexdec(substr($colorHex, 1, 2)),
        hexdec(substr($colorHex, 3, 2)),
        hexdec(substr($colorHex, 5, 2)),
    ];

    $shade = [
        dechex(255 - (255 - $rgb[0]) + (255 - (255 - $rgb[0]) < 255 - $percentage ? $percentage : 0)),
        dechex(255 - (255 - $rgb[1]) + (255 - (255 - $rgb[1]) < 255 - $percentage ? $percentage : 0)),
        dechex(255 - (255 - $rgb[2]) + (255 - (255 - $rgb[2]) < 255 - $percentage ? $percentage : 0)),
    ];

    return '#' . implode('', $shade);
}


function _drawChildren($drawCondition, &$bgColors, $color)
{
    $bgColors[ $drawCondition->id ] = $color;

    $children = $drawCondition->getChildConditions();

    foreach ($children as $con) {

        $shadeColor = _shade($color);

        /* @var Condition $con */
        _drawChildren($con, $bgColors, $shadeColor);
    }
}

$result = [];

$colors = [];

$bgColors        = [];
$backGroundBlock = [];

$i = 0;


if (count($conditions) > 0) {

    /* @var Condition $condition */
    foreach ($conditions as $condition) {

        if (!empty($condition->bgColor)) {
            _drawChildren($condition, $bgColors, $condition->bgColor);
        }
    }

    foreach ($conditions as $condition) {

        $headerBlock = '';

        $parent = ($condition) ? $condition->parent : null;

        $color = 'yellow';
        if (!$parent) {
            $color = 'black';
        } elseif ($condition->type == Condition::TYPE_CONDITION) {
            $color = ($condition->isTrue) ? 'green' : 'red';
        } elseif ($condition->type == Condition::TYPE_EXIT) {
            $color = 'black';
        } elseif ($condition->type == Condition::TYPE_QUESTIONNAIRE) {
            $color = 'blue';
        } else {
            $color = 'DarkOrange';
        }

        if ($i == 0 AND $condition->idPrevCondition) {
            $headerBlock .= Html::a(
                    '<b><i>Наверх</i></b>',
                    [
                        'recommendations/decision-tree',
                        'id'               => $recommend->id,
                        'startConditionId' => $condition->idPrevCondition,
                        'level'            => Yii::$app->request->get('level'),
                    ]
                ) . '<br/><br/>';
        }

        if ($parent) {
            $headerBlock .= Html::a(
                    'Вставить узел',
                    [
                        'recommendations/create-condition',
                        'idRecommend' => $recommend->id,
                        'idCur'       => ($condition) ? $condition->id : null,
                        'idParent'    => ($condition) ? $condition->parentId : null,
                        "isInsert"    => true,
                    ],
                    [
                        'class' => 'itemInsert',
                    ]
                ) . '<br />';
        }

        $headerBlock .= 'ИД:' . $condition->id . Html::a(
                'Изменить',
                [
                    'recommendations/edit-condition',
                    "conditionId" => $condition->id,
                ]
            );


        $type = '';
        if ($condition->isTrue) {
            $type = '(ДА)';
        } elseif (!$condition->isTrue) {
            $type = '(НЕТ)';
        }

        $condition->setConditionContent();
        $text = $condition->content;
        $text = str_replace('<br/>', ' ', $text);
        $text = str_replace('<p>', ' ', $text);
        $text = str_replace('</p>', ' ', $text);

        $footerBtn = '';

        if ($condition->type == Condition::TYPE_CONDITION && !$condition->yesCondition) {
            $footerBtn .= (($footerBtn) ? '<br/>' : '') . Html::a(
                    '+ ДА',
                    [
                        'recommendations/create-condition',
                        'idRecommend' => $recommend->id,
                        'idParent'    => ($condition) ? $condition->id : null,
                        "isTrue"      => true,
                    ],
                    [
                        'class' => 'itemEdit',
                    ]
                );
        }

        if ($condition->type == Condition::TYPE_QUESTIONNAIRE) {
            $footerBtn .= (($footerBtn) ? '<br/>' : '') . Html::a(
                    '+ ВЕТКА',
                    [
                        'recommendations/create-condition',
                        'idRecommend' => $recommend->id,
                        'idParent'    => ($condition) ? $condition->id : null,
                        "isTrue"      => true,
                    ],
                    [
                        'class' => 'itemEdit',
                    ]
                );

            $footerBtn .= (($footerBtn) ? '<br/>' : '') . Html::a(
                    '+ ОТВЕТ',
                    [
                        'recommendations/create-condition',
                        'idRecommend' => $recommend->id,
                        'idParent'    => ($condition) ? $condition->id : null,
                        'isTrue'      => true,
                        'autoFill'    => true,
                    ],
                    [
                        'class' => 'itemEdit2',
                    ]
                );
        }


        if (!$condition->yesCondition && (in_array($condition->type, $typeForContinue))) {
            $footerBtn .= (($footerBtn) ? '<br/>' : '') . Html::a(
                    '+ Продолжить',
                    [
                        'recommendations/create-condition',
                        'idRecommend' => $recommend->id,
                        'idParent'    => ($condition) ? $condition->id : null,
                        "isTrue"      => true,
                    ],
                    [
                        'class' => 'itemEdit',
                    ]
                );
        }

        if ($condition->type == Condition::TYPE_CONDITION && !$condition->noCondition) {
            $footerBtn = (($footerBtn) ? '<br/>' : '') . $footerBtn . '&nbsp;' .
                         Html::a(
                             '+ НЕТ',
                             [
                                 'recommendations/create-condition',
                                 'idRecommend' => $recommend->id,
                                 'idParent'    => ($condition) ? $condition->id : null,
                                 "isTrue"      => false,
                             ],
                             [
                                 'class' => 'itemEdit',
                             ]
                         );
        }

        $footerBtn .= (($footerBtn) ? '<br/>' : '') . Html::a(
                'Задать цвет',
                [
                    'recommendations/edit-backgroundcolor',
                    'idRecommend' => $recommend->id,
                    'condition'   => ($condition) ? $condition->id : null,
                ],
                [
                    'class' => 'editBgColor',
                ]
            );

        if ($condition->isLastCondition) {
            $footerBtn .= '<br/><br/>' . Html::a(
                    '<b><i>Раскрыть</i></b>',
                    [
                        'recommendations/decision-tree',
                        'id'               => $recommend->id,
                        'startConditionId' => ($condition) ? $condition->id : null,
                        'level'            => Yii::$app->request->get('level'),
                    ]
                );
        }
        $footerBlock = $footerBtn;

        $block = $headerBlock .
                 '<br /><b>' .
                 $condition->typeName .
                 '</b><br />' .
                 $text .
                 //mb_substr($text, 0, 50, 'UTF-8') .
                 '<br />' .
                 $footerBlock;

        $tips = $text;

        $result[ $i ] = [['v' => (string) $condition->id, 'f' => $block], (string) $condition->parentId, $tips];


        $colors[ $i ] = $color;

        $backGroundBlock[ $i ] =
            isset($bgColors[ $condition->id ]) && !empty($bgColors[ $condition->id ]) ? $bgColors[ $condition->id ] :
                $bgColorDefault;

        $i++;
    }
} else {

    echo Html::a(
        'Создать условие',
        [
            'recommendations/create-condition',
            'idRecommend' => $recommend->id,
            'idParent'    => null,
            "isTrue"      => true,
        ],
        [
            'class' => 'btn btn-primary itemEdit',
        ]
    );
}


$tree = $result;
?>


    <script type="text/javascript">

        function drawChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Name');
            data.addColumn('string', 'Parent');
            data.addColumn('string', 'ToolTip');

            var colors = <?= json_encode($colors) ?>;

            var bgColors = <?= json_encode($backGroundBlock) ?>;

            data.addRows(<?= json_encode($tree) ?>);


            colors.forEach(function (item, i, arr) {
                data.setRowProperty(i, 'style', 'border: 2px solid ' + item + ';background-color:' + bgColors[i] + ';background-image:none');
            });

            var chart = new google.visualization.OrgChart(document.getElementById('chart_div'));

            chart.draw(data, {allowHtml: true});

        }
    </script>


    <div id="chart_div" class="containerTree"></div>

    <style>
        .containerTree table {
            border-collapse: inherit !important;
        }
    </style>


<?php Modal::begin(
    [
        'id'      => 'dialogModalEdit',
        'header'  => '<h4 class="modal-title">Создать условие</h4>',
        'options' => ['style' => 'margin-top:30px;'],
    ]
);
?>
<?php $form = ActiveForm::begin(); ?>
    <h4>Выберите тип добавляемого элемента</h4>
<?php foreach (Block::getTypes() as $key => $value): ?>
    <div class="radio">
        <label>
            <input type="radio" class="typeSelector" name="conditionType" value="<?= $key ?>">
            <?= $value ?>
        </label>
    </div>
<?php endforeach; ?>

    <div class="radio">
        <label>
            <input type="radio" class="typeSelector" name="conditionType" value="<?= 999 ?>">
            <?= 'Скопировать содержимое из: ' ?>
        </label>
    </div>
    <div class="radio">
        <label>
            <input type="radio" class="typeSelector" name="conditionType" value="<?= 9999 ?>">
            <?= 'Скопировать содержимое (с подчиненными) из: ' ?>
        </label>
    </div>
    <div class="radio">
        <select name="conditionIdCopy">
            <option disabled>Выберите условие-источник</option>
            <?php foreach ($conditions as $cur): ?>
                <option value="<?= $cur->id ?>">
                    <?= $cur->id . ' ' . $cur->typeName ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>


<?= Html::submitButton('Создать', ['class' => 'btn btn-primary']) ?>

<?php ActiveForm::end(); ?>

<?php Modal::end(); ?>


<?php Modal::begin(
    [
        'id'      => 'dialogModalInsert',
        'header'  => '<h4 class="modal-title">Вставить узел</h4>',
        'options' => ['style' => 'margin-top:30px;'],
    ]
);
?>
<?php $form = ActiveForm::begin(); ?>

    <h4>Выберите тип ветки для дочерних элементов</h4>

    <div class="radio">
        <select name="conditionBranch" class="form-control">
            <option disabled>Выберите условие</option>
            <option value="1">ДА</option>
            <option value="0">НЕТ</option>
        </select>
    </div>

    <h4>Выберите тип добавляемого элемента</h4>
<?php foreach (Block::getTypes() as $key => $value): ?>
    <div class="radio">
        <label>
            <input type="radio" class="typeSelector" name="conditionType" value="<?= $key ?>">
            <?= $value ?>
        </label>
    </div>
<?php endforeach; ?>


<?= Html::submitButton('Создать', ['class' => 'btn btn-primary']) ?>

<?php ActiveForm::end(); ?>

<?php Modal::end(); ?>


<?php

$this->registerJs(
    "
   $(document).ready(function(){
        $(document).on('click','.itemEdit',function(event){
            event.preventDefault();

            var modal = $('#dialogModalEdit');
            modal.find('form').attr('action',$(this).attr('href'));

            modal.modal('show');
        });

        $(document).on('click','.itemInsert',function(event){
            event.preventDefault();

            var modal = $('#dialogModalInsert');
            modal.find('form').attr('action',$(this).attr('href'));

            modal.modal('show');
        });

        $(document).on('click','.editBgColor',function(event){
            event.preventDefault();

            var modal = $('#dialogModalEditBgColor');
            modal.find('form').attr('action',$(this).attr('href'));

            modal.modal('show');
        });
   });

    "

);

?>


<?php Modal::begin(
    [
        'id'      => 'dialogModalEditBgColor',
        'header'  => '<h4 class="modal-title">Изменить цвет заливки</h4>',
        'options' => ['style' => 'margin-top:30px;'],
    ]
); ?>

<?php $form = ActiveForm::begin(); ?>

    <h4>Выберите цвет заливки</h4>

    <div class="radio">
        <select name="conditionBgColor" class="form-control">
            <option disabled>Выберите цвет</option>
            <option value="#ff0000">красный</option>
            <option value="#FF8000">оранжевый</option>
            <option value="#ffff00">жёлтый</option>
            <option value="#008000">зелёный</option>
            <option value="#0000ff">голубой</option>
            <option value="#000080">синий</option>
            <option value="#800080">фиолетовый</option>
            <option value="0">сбросить</option>
        </select>
    </div>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>

<?php ActiveForm::end(); ?>

<?php Modal::end(); ?>