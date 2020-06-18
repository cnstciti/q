<?php

namespace site\controllers;

use common\models\Site;
use common\models\SiteSettings;
use common\models\users\Doctor;
use common\models\users\Promocode;
use common\models\users\SocialNetwork;
use common\models\users\UserHistory;
use MetzWeb\Instagram\Instagram;
use site\components\BaseController;
use site\forms\auth\LoginForm;
use site\forms\profile\LoadPhotosForm;
use site\forms\register\DoctorRegisterForm;
use site\forms\register\EnDoctorRegisterForm;
use site\forms\register\RegisterForm;
use site\models\User;
use common\models\dictionaries\Specialty;
use Yii;
use yii\base\Model;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;
use yii\widgets\ActiveForm;

/**
 * Class RegisterController
 *
 * Контроллер регистрации пользователей
 *
 * @package site\controllers
 */
class RegisterController extends BaseController
{
    public function behaviors()
    {

        return [
            'tracking' => [
                'class' => 'site\behaviors\TrackingBehavior',
            ],
            'access'   => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow'   => true,
                        'actions' => [
                            'index',
                            'email-notification',
                            'confirm-email',
                            'register-doctor',
                            'get-specialties',
                            'moderation',
                            'check-promo',
                            'register-en-doctor',
                        ],
                        'roles'   => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }


    /**
     * Форма регистрации пользователя
     * После заполнения отправляется письмо на email
     * со ссылкой на завершение регистрации
     */
    public function actionIndex()
    {
        $registerForm = new RegisterForm();

        if (Yii::$app->request->isAjax && $registerForm->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ActiveForm::validate($registerForm);
        }

        if ($registerForm->load(Yii::$app->request->post()) && $registerForm->validate()) {
            $user = $registerForm->createUser();
            if ($user->isPatient()) {
                Yii::$app->user->login($user);
                $returnUrl = Yii::$app->request->post('returnUrl');

                if ($returnUrl && strpos($returnUrl, 'http') === false) {
                    return $this->redirect($returnUrl);
                }


                $doctorProfile = Yii::$app->request->get('doctorProfile');
                if ($doctorProfile) {
                    $doctorId = (int) $doctorProfile;
                    /** @var Doctor $doctor */
                    $doctor = Doctor::findOne((int) $doctorId);
                    if ($doctor) {
                        $record            = new UserHistory();
                        $record->userId    = $user->id;
                        $record->objectId  = $doctor->userId;
                        $record->eventType = UserHistory::REG_FROM_DOCTOR;

                        $record->save();

                        $user->patient->addFriend($doctor->userId);

                        return $this->redirect('/profile/' . $doctor->userId);
                    }
                }

                if ($user->promocode && $user->patient->getPromoConsultCounter()) {
                    return $this->redirect(['questions/new']);
                }

                return $this->redirect(['/profile']);
                //return $this->redirect(['profile/index', 'registered' => true]);
                //return $this->redirect(['profile/index']);
            } else {
                $this->redirect(['register/email-notification']);
            }
        }

        $utmParams        = User::getUtmParams(\Yii::$app->session->get('__utm', null));
        $onlyRegisterForm = ($utmParams['utm_source'] == 'roche') ? true : false;

        //return $this->render('register', ['registerForm' => $registerForm]);
        return $this->render(
            '/auth/auth',
            [
                'registerForm'     => $registerForm,
                'onlyRegisterForm' => $onlyRegisterForm,
                'doctorProfile'    => Yii::$app->request->get('doctorProfile'),
            ]
        );
    }


    /**
     * Сообщение об отправке на email ссылки на завершение регистрации
     *
     * @return string
     */
    public function actionEmailNotification()
    {
        $this->view->params['registeredDoctor'] = true;

        return $this->render('registerEmailNotification');
    }


    /**
     * Подтверждение email врача
     *
     * @param $key
     *
     * @return Response
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionConfirmEmail($key)
    {
        if (!$user = User::getByRegisterKey($key)) {
            //throw new NotFoundHttpException(Yii::t('app', 'Страница не найдена'));
            return $this->redirect(['site/index']);
        }

        $user->isRegistered = true;
        if ($user->save(false)) {
            $user->deleteRegisterKey();

            //Для пациентов организаций
            if ($user->isPatient() && $user->patient->organizationId) {
                //Создадим пароль и отправим пациенту
                $password = $this->random_str('alphanum', 6);
                $user->setPassword($password);
                $user->save();
                Yii::$app->mailer
                    ->compose(
                        'registerPatientOrg',
                        [
                            'user'     => $user,
                            'login'    => $user->email,
                            'password' => $password,
                        ]
                    )
                    ->setFrom(Yii::$app->params['mailSender'])
                    ->setTo($user->email)//$user->email
                    ->setSubject(Yii::t('app', 'Регистрация на Qapsula'))
                    ->send();
            }

            Yii::$app->user->login($user);

            return $this->redirect(['register/finish']);
        }

        throw new ServerErrorHttpException(Yii::t('app', 'Произошла ошибка, попробуйте позже'));
    }


    /**
     * Завершение регистрации
     */
    public function actionFinish()
    {
        if ($this->user->isPatient() && $this->user->patient->organizationId) {
            return $this->redirect(['profile/register-finish']);
        }

        if (!$this->user->doctor->isNew()) {
            return $this->redirect(['profile/index']);
        }

        $this->layout = 'user';

        $user   = $this->user;
        $doctor = $this->user->doctor;

        $certificatesForm = new LoadPhotosForm();

        $user->setScenario('registerDoctor');
        $doctor->setScenario('register');

        if ($user->load(Yii::$app->request->post())) {

            $doctor->load(Yii::$app->request->post());
            if (!is_array($doctor->interestsIds)) {
                $doctor->interestsIds = [];
            }

            $certificatesForm->load(Yii::$app->request->post());

            $certificatesForm->files = UploadedFile::getInstances($certificatesForm, 'files');

            $userValid         = $user->validate();
            $doctorValid       = $doctor->validate();
            $certificatesValid = $certificatesForm->validate();

            if ($userValid && $doctorValid && $certificatesValid) {
                $doctor->approveStatus = Doctor::STATUS_UPDATED;
                if ($user->save() && $doctor->save()) {
                    $certificatesForm->saveAll($this->user);

                    return $this->redirect(['profile/index', 'registered' => true]);
                }
            }
        }

        return $this->render(
            'registerFinish',
            [
                'user'             => $user,
                'doctor'           => $doctor,
                'certificatesForm' => $certificatesForm,
            ]
        );
    }


    public function actionRegisterDoctor()
    {
        $step = Yii::$app->request->post('step');
        if (!$step) {
            $step = Model::SCENARIO_DEFAULT;
        }

        $registerForm = new DoctorRegisterForm();
        $registerForm->setScenario($step);

        if (Yii::$app->request->isAjax
            && $registerForm->load(Yii::$app->request->post())
            && !Yii::$app->request->post('last')
        ) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ActiveForm::validate($registerForm);
        }

        $registerForm->setScenario('full');

        if ($registerForm->load(Yii::$app->request->post()) && $registerForm->validate()) {
            $registerForm->files = UploadedFile::getInstances($registerForm, 'files');

            if ($registerForm->createUser()) {
                return $this->redirect(['moderation']);
            }
        }

        return $this->render('/auth/auth', ['doctorRegisterForm' => $registerForm]);
    }


    /**
     * Автокомплит специальностей
     *
     * @param null $q
     *
     * @return array
     */
    public function actionGetSpecialties($q = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $qInvert = $this->correctLanguage($q);

        $results = Specialty::searchByName($q, $qInvert);

        return [
            'results' => $results,
        ];
    }


    /**
     * Сообщение об отправке профиля врача на модерацию
     *
     * @return string
     */
    public function actionModeration()
    {
        return $this->render('registerDoctorSuccess');
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


    private function random_str($type = 'alphanum', $length = 8)
    {
        switch ($type) {
            case 'basic'    :
                return mt_rand();
                break;
            case 'alpha'    :
            case 'alphanum' :
            case 'num'      :
            case 'nozero'   :
                $seedings             = [];
                $seedings['alpha']    = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $seedings['alphanum'] = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $seedings['num']      = '0123456789';
                $seedings['nozero']   = '123456789';

                $pool = $seedings[ $type ];

                $str = '';
                for ($i = 0; $i < $length; $i++) {
                    $str .= substr($pool, mt_rand(0, strlen($pool) - 1), 1);
                }

                return $str;
                break;
            case 'unique'   :
            case 'md5'      :
                return md5(uniqid(mt_rand()));
                break;
        }
    }


    public function actionCheckPromo($value = null)
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        if (is_null($value)) {
            return $this->ajaxError('Error');
        }

        if('econsilium' == strtolower($value)) {
            return $this->ajaxResponse(
                ['result' => 'busy', 'message' => 'Для регистрации в проекте Econsilium вам необходимо перейти по <a href="https://econsilium.qapsula.com/register">ссылке</a>']
            );
        }

        $result = User::checkPromocode($value);

        if ($result !== false) {
            return $this->ajaxResponse(['message' => $result ? Yii::t('app', 'Промокод принят') : '']);
        }


        $promocode = Promocode::findCodeAndSetActivateStatus($value);
        if (!$promocode) {
            return ['message' => Yii::t('app', '')];
        }

        //Для отображание доп.поля "Номер помпы"
        $pompProgramId = SiteSettings::getValue('pompProgramId');

        if ($promocode->activateStatus != Promocode::ACTIVATE_STATUS_FREE) {
            return $this->ajaxResponse(
                ['result' => 'busy', 'message' => Yii::t('app', 'Промокод уже зарегистрирован')]
            );
        } elseif ($pompProgramId && $promocode->programId == $pompProgramId) {
            return $this->ajaxResponse(['result' => 'promocode']);
        } else {
            return $this->ajaxResponse(['message' => Yii::t('app', 'Промокод принят')]);
        }
    }


    public function actionRegisterEnDoctor()
    {
        if (Yii::$app->language == 'ru-RU') {
            return $this->redirect(['/auth/index']);
        }
        $registerForm = new EnDoctorRegisterForm();

        if (Yii::$app->request->isAjax && $registerForm->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ActiveForm::validate($registerForm);
        }

        if ($registerForm->load(Yii::$app->request->post()) && $registerForm->validate()) {
            if ($registerForm->createUser()) {
                return $this->redirect(['moderation']);
            }
        }

        return $this->render('/auth/register-en-doctor', ['registerForm' => $registerForm]);
    }

}