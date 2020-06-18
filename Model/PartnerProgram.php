<?php

namespace common\models\billing;

use common\models\dictionaries\QuestionTag;
use common\models\poll\Poll;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\HtmlPurifier;

/**
 * Class PartnerProgram
 *
 * @property int $id                                          ID
 * @property string $name                                        Название
 * @property string $description                                 Описание
 * @property int $questionCost                                Стоимость вопроса
 * @property int $messageCost                                 Стоимость сообщения
 * @property int $callCost                                    Стоимость звонка
 * @property bool $isDeleted                                   Признак удаления
 * @property string $color                                       Цвет
 * @property string $agreement                                   Соглашение
 * @property int $countFreeChat                               Кол-во бесплатных чатов,
 *                                                                                       доступных после регистрации
 * @property string $utmSource                                   UTM для новых пользователей
 * @property bool $enableCall                                  Доступна аудио-видео
 *           консультация
 * @property integer $startAlgorithmId                            Приветственный алгоритм
 * @property integer $doctorPoll                                  Есть опрос у врача
 * @property integer $patientPoll                                 Есть опрос у пациента
 * @property bool $enableStartAlgorithmOnlyOne                 Активировать стартовый
 *                                                                                       алгоритм для КО только один
 *                                                                                       раз
 * @property integer $maxConsultPerHour                           Макс кол-во консультаций за
 *                                                                                       1 час одним врачом
 * @property string $maxConsultPerHourByDoctors                  Макс кол-во консультаций за
 *                                                                                       1 час одним врачом (под ид
 *                                                                                       врачей)
 * * @property string $compensationType                     Тип компенсации
 *
 * @property bool $onlyInvite                                  Доступ только по приглашению
 *
 * @property PartnerProgramExternalService[] $externalService                             AR внешних услуг
 * @property string $accessType [integer]
 * @property \yii\db\ActiveQuery $tags
 * @property PartnerProgramPriorityInfo $priorityInfo
 * @property bool $hasLanding [boolean]
 * @property bool $autoCalculation [boolean]
 */
class PartnerProgram extends ActiveRecord
{
    public $maxConsultByDoctorsArray = [];


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'partnerPrograms';
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                          => 'ID',
            'name'                        => 'Название',
            'description'                 => 'Описание',
            'questionCost'                => 'Стоимость вопроса (консультации)',
            'messageCost'                 => 'Стоимость сообщения',
            'callCost'                    => 'Стоимость звонка',
            'isDeleted'                   => 'Признак удаления',
            'color'                       => 'Цвет',
            'agreement'                   => 'Соглашение',
            'countFreeChat'               => 'Кол-во бесплатных чатов, доступных после регистрации',
            'utmSource'                   => 'UTM для новых пользователей',
            'enableCall'                  => 'Доступна аудио-видео консультация',
            'startAlgorithmId'            => 'Приветственный алгоритм',
            'doctorPoll'                  => 'Есть опрос у врача',
            'patientPoll'                 => 'Есть опрос у пациента',
            'maxConsultPerHour'           => 'Макс кол-во ответов в час',
            'maxConsultPerHourByDoctors'  => 'Макс кол-во консультаций за 1 час для конкретных врачей',
            'enableStartAlgorithmOnlyOne' => 'Активировать приветственный алгоритм только один раз',
            'onlyInvite'                  => 'Доступ только по приглашению',
            'hasLanding'                  => 'Есть лэндинг',
            'compensationType'            => 'Условия компенсации для врачей',
        ];
    }


    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->isDeleted = false;
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes) {
        if ($insert) {
            $priority = new PartnerProgramPriorityInfo();
            $priority->programId = $this->id;

            $max_priority = $priority->find()->orderBy('priority DESC')->limit(1)->one()->priority;
            $priority->priority = ++$max_priority;

            $priority->ignorePriority = false;
            $priority->save();
        }

        parent::afterSave($insert, $changedAttributes);
    }


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->countFreeChat = 0;
    }


    public function afterFind()
    {
        parent::afterFind();
        $this->setMaxConsultByDoctorsArray();
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [
                [
                    'name',
                    'isDeleted',
                    'description',
                    'color',
                    'utmSource',
                    'enableCall',
                    'maxConsultPerHour',
                    'maxConsultPerHourByDoctors',
                    'enableStartAlgorithmOnlyOne',
                    'onlyInvite',
                    'hasLanding',
                    'autoCalculation'
                ],
                'safe',
            ],
            [
                [
                    'questionCost',
                    'messageCost',
                    'callCost',
                    'countFreeChat',
                    'startAlgorithmId',
                    'doctorPoll',
                    'patientPoll',
                    'compensationType'
                ],
                'integer',
            ],
            [
                [
                    'autoCalculation'
                ],
                'boolean',
            ],
            [
                [
                    'agreement',
                ],
                'filter',
                'filter' => function ($value) {
                    return HtmlPurifier::process($value);
                },
            ],
        ];
    }


    /**
     * Возвращает название цветов
     */
    public static function getColors()
    {
        $colors = [
            '69FA43' => 'Светло-зеленый',
            '93E6A9' => 'Темно-зеленый',
            '8E5D54' => 'Темно-коричневый',
            'FF69B4' => 'Розовый'
        ];

        return $colors;
    }


    public function getExternalService()
    {
        return $this->hasMany(
            PartnerProgramExternalService::class,
            ['partnerProgramId' => 'id']
        )
                    ->where(['isDeleted' => false]);
    }


    /**
     * Это Партнерская программа в рамках проектов с компанией "РОШ"
     *
     * @return bool
     */
    public function isRocheProgram()
    {
        return in_array($this->id, [5, 7, 8]);
    }


    public static function bayerProgramId()
    {
        return 4;
    }


    public function getMaxConsultByDoctor($doctorId)
    {
        if ($this->maxConsultByDoctorsArray && isset($this->maxConsultByDoctorsArray[ $doctorId ])) {
            return $this->maxConsultByDoctorsArray[ $doctorId ];
        }

        return $this->maxConsultPerHour;
    }

    /**
     * Тэги, связанные с партнерской программой
     * @return ActiveQuery
     */
    public function getTags() {
        return $this->hasMany(QuestionTag::class, ['id' => 'tagId'])
            ->viaTable('partnerProgramsTags', ['programId' => 'id']);
    }

    public function getPriorityInfo() {
        return $this->hasOne(PartnerProgramPriorityInfo::class, ['programId' => 'id']);
    }

    public function getCompensationType() {
        return $this->hasOne(PartnerProgramCompensationType::class, ['id' => 'compensationType']);
    }

    public function getPolls() {
        return $this->hasMany(Poll::class, ['id' => 'pollId'])
            ->viaTable('partnerProgramsPolls', ['programId' => 'id']);
    }

    private function setMaxConsultByDoctorsArray()
    {
        if (!$this->maxConsultPerHourByDoctors) {
            return;
        }

        $arrDoctors = explode(';', $this->maxConsultPerHourByDoctors);
        if (!$arrDoctors || count($arrDoctors) == 0) {
            return;
        }

        foreach ($arrDoctors as $item) {
            $arrDoctor = explode('-', $item);
            if (!$arrDoctor || count($arrDoctor) != 2) {
                return;
            }
            $this->maxConsultByDoctorsArray[ $arrDoctor[0] ] = $arrDoctor[1];
        }
    }
}