<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use mihaildev\ckeditor\CKEditor;
use common\models\bot\algorithm\Block;
use common\models\bot\algorithm\BlockInlineCommand;
use kartik\select2\Select2;
use common\models\bot\algorithm\Algorithm;
use admin\assets\BlockInlineCommandAsset;

/**
 * @var  yii\web\View $this
 * @var Condition     $condition
 * @var boolean       $validateByType
 */


BlockInlineCommandAsset::register($this);
?>

<?php $form = ActiveForm::begin(['action' => ['set-finish-condition', 'conditionId' => $condition->id]]); ?>

<div class="form-group">
    <label>Параметры для напоминания</label>
    <p>Например: пн,ср,чт 19:05</p>
    <p>Дни недели: пн, ср, чт, пт, сб, вс. кд - каждый день</p>
    <div class="row">
        <div class="col-md-4">
            <input type="text" name="conditionTitle" value="<?= $condition->title ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <a href="javascript:;" class="btn btn-link btn-check-remind">Проверить</a>
        </div>
    </div>
    <div id="div-check-remind"></div>
</div>

<div class="form-group">
    <label>Сообщение в случае успешного создания напоминания</label>
    <?= CKEditor::widget(
        [
            'name'          => 'conditionBody',
            'value'         => $condition->body,
            'editorOptions' =>
                [
                    'preset'               => 'basic',
                    'filebrowserUploadUrl' => \yii\helpers\Url::to(['my-posts/upload-image']),
                    'height'               => 150,
                ],
        ]
    ); ?>
</div>


<div class="row">
    <div class="col-md-12">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary', 'style' => 'display:inline-block;']) ?>
        <?php if ($validateByType): ?>
            <div class="alert alert-danger" role="alert" style='display:inline-block;'>Проверьте правильность
                заполнения!
            </div>
        <?php endif; ?>
    </div>
</div>
<?php ActiveForm::end(); ?>

