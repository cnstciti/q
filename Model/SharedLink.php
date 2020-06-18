<?php


namespace common\models;

use common\models\dictionaries\MedicalType;
use common\models\users\Patient;
use common\models\users\patient\MedicalPhoto;
use common\models\users\User;
use execut\yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Url;
use Yii;

/**
 * Class SharedLink
 *
 * @property int          $id                   ID записи
 * @property int          $userId
 * @property int          $created
 * @property string       $link
 * @property int          $type
 * @property int          $resourceId
 *
 * @property User         $user                 AR User
 * @property MedicalPhoto $photo                AR Файла
 * @property MedicalType  $album                AR Папки
 */
class SharedLink extends ActiveRecord
{
    // Медицинская карта
    const TYPE_ALL_PATIENT_FILES = 1;

    // Папка мед.карты
    const TYPE_PATIENT_FOLDER = 2;

    //Файл мед.карты
    const TYPE_PATIENT_FILE = 3;


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sharedLinks';
    }


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created', 'type', 'userId', 'resourceId'], 'integer'],
            [['link'], 'string', 'max' => 255],
        ];
    }


    /**
     * @inheritDoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            //Проверим, что у пользователя есть доступ к указанному ресурсу
            if ($this->resourceId && !self::hasAccessToResources($this->type, $this->resourceId, $this->userId)) {
                //Попытка открыть доступ пользователем, который не является владельцем файла
                return false;
            }
            $this->created = time();
            $this->link    = self::generateCode(12);
        }

        return parent::beforeSave($insert);
    }


    public static function hasAccessToResources($resourceType, $resourceId, $userId)
    {
        if (in_array($resourceType, [self::TYPE_ALL_PATIENT_FILES, self::TYPE_PATIENT_FOLDER])) {
            //Это категория доступа (медкарта/папка)
            return true;
        }
        if ($resourceType == self::TYPE_PATIENT_FILE && $resource = MedicalPhoto::findOne($resourceId)) {
            if ($resource->patient->userId == $userId) {
                //Доступ открывает пользователь, который является владельцем файла
                return true;
            }
        }

        return false;
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => Yii::t('app', 'ID'),
            'userId'     => Yii::t('app', 'Пользователь'),
            'created'    => Yii::t('app', 'Дата создания'),
            'link'       => Yii::t('app', 'GUID'),
            'type'       => Yii::t('app', 'Тип'),
            'resourceId' => Yii::t('app', 'Ресурс'),
        ];
    }


    public static function getType()
    {
        $typeName = [
            static::TYPE_ALL_PATIENT_FILES => Yii::t('app', 'Медицинская карта'),
            static::TYPE_PATIENT_FOLDER    => Yii::t('app', 'Папка мед. карты'),
            static::TYPE_PATIENT_FILE      => Yii::t('app', 'Файл мед. карты'),
        ];

        return $typeName;
    }


    /**
     * Связка с пользователем
     *
     * @return ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'userId']);
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPhoto()
    {
        if ($this->type == static::TYPE_PATIENT_FILE) {
            return $this->hasOne(MedicalPhoto::className(), ['id' => 'resourceId']);
        } else {
            return null;
        }
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAlbum()
    {
        if ($this->type == static::TYPE_PATIENT_FOLDER) {
            return $this->hasOne(MedicalType::className(), ['id' => 'resourceId']);
        } else {
            return null;
        }
    }


    public function getAlbumPhotos($resourceId = null)
    {
        if ($this->type == static::TYPE_PATIENT_FOLDER) {
            $files = MedicalPhoto::find()->where(
                [
                    'isDeleted' => false,
                    'type'      => $this->resourceId,
                    'patientId' => $this->user->patient->id,
                ]
            )->orderBy('id')->all();

            return $files;
        } elseif ($this->type == static::TYPE_ALL_PATIENT_FILES && $resourceId) {
            $files = MedicalPhoto::find()->where(
                [
                    'isDeleted' => false,
                    'type'      => $resourceId,
                    'patientId' => $this->user->patient->id,
                ]
            )->orderBy('id')->all();

            return $files;
        } else {
            return null;
        }
    }


    /**
     * Возвращает текстовое представление типа
     *
     * @return mixed
     */
    public static function getTypeName($idType)
    {
        $typeName = static::getType();

        return $typeName[ $idType ];
    }


    /**
     * Возвращает описание ресурса
     *
     * @return string
     */
    public function getName()
    {
        if ($this->type == static::TYPE_ALL_PATIENT_FILES) {
            return Yii::t('app', 'Медицинская карта');
        } elseif ($this->type == static::TYPE_PATIENT_FOLDER) {
            return $this->album->translatedName;
        } elseif ($this->type == static::TYPE_PATIENT_FILE) {
            return $this->photo->getPhotoDescription();
        }

        return Yii::t('app', 'Тип не указан');
    }


    public function generateCode($length = 7)
    {
        $chars    = 'ABDEFGHKNQRSTYZ23456789';
        $numChars = strlen($chars);

        do {
            $string = '';
            for ($i = 0; $i < $length; $i++) {
                $string .= substr($chars, rand(1, $numChars) - 1, 1);
            }

            $code = SharedLink::findOne(['link' => $string]);
            if (!$code) {
                return $string;
            }
        } while (0);
    }


    /**
     * Открыта ли ссылка на этот файл
     *
     * @param $photo MedicalPhoto
     *
     * @return bool
     */
    public function isAccessPhoto($photo)
    {
        $arrAlbums = [];

        if ($this->type == self::TYPE_PATIENT_FOLDER) {
            $arrAlbums[] = $this->resourceId;
        } elseif ($this->type == self::TYPE_ALL_PATIENT_FILES) {
            foreach ($this->user->patient->albums as $album) {
                $arrAlbums[] = $album->typeId;
            }
        }

        foreach ($arrAlbums as $album) {
            $files = MedicalPhoto::find()->where(
                [
                    'isDeleted' => false,
                    'type'      => $album,
                    'patientId' => $this->user->patient->id,
                ]
            )->orderBy('id')->all();

            foreach ($files as $file) {
                if ($file->id == $photo->id) {
                    return true;
                }
            }
        }

        return false;
    }


    public function getAbsolutePath()
    {
        $baseUrl = isset(Yii::$app->params['baseUrl']) ? Yii::$app->params['baseUrl'] : Url::base(true);

        return $baseUrl . '/shared/' . $this->link;
    }
}