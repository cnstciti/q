<?php
namespace admin\models;


use common\models\Question;
use common\models\users\Message;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\data\SqlDataProvider;
use yii\db\Query;

class PersonalQuestionsFilter extends Model
{
    public $patient = null;

    public $message = null;

    public $doctor  = null;


    public function rules()
    {
        return [
            [
                [
                    'patient',
                    'message',
                    'doctor',
                ],
                'safe',
            ],
        ];
    }


    public function search($params)
    {
        $this->load($params);

        $sql =
            'FROM (SELECT MAX(id) as "lastMsgId" FROM messages GROUP BY CAST(CASE WHEN "senderUserId" > "receiverUserId" THEN "receiverUserId" ELSE "senderUserId" END as varchar), CAST(CASE WHEN "senderUserId" > "receiverUserId" THEN "senderUserId" ELSE "receiverUserId" END as varchar) ) AS d ' .
            'LEFT JOIN messages ON (d."lastMsgId" = messages.id)  ' .
            'LEFT JOIN users u1 ON (messages."senderUserId" = u1.id)  ';
        if ($this->doctor) {
            $sql .= 'INNER JOIN users u2 ON (u2.id = messages."receiverUserId") ';
        }
        $sqlWhere =
            ' u1.type = :type_patient  ' .
            ' AND messages."sourceId" IS NULL ' .
            ' AND (messages.status = :message_status1 OR messages.status = :message_status2 OR messages.status IS NULL) ';

        $params                  = [];
        $params[':type_patient'] = User::TYPE_PATIENT;
        $params[':message_status1'] = 0;
        $params[':message_status2'] = Message::STATUS_SMS;

        if ($this->patient) {
            if (strlen($sqlWhere) > 0) {
                $sqlWhere .= ' AND ';
            }
            $sqlWhere .= ' (u1."lastName" LIKE :paramUser1 OR u1."firstName" LIKE :paramUser1 OR u1."middleName" LIKE :paramUser1) ';

            $params[':paramUser1'] = "%$this->patient%";
        }

        if ($this->doctor) {
            if (strlen($sqlWhere) > 0) {
                $sqlWhere .= ' AND ';
            }
            $sqlWhere .= ' (u2."lastName" LIKE :paramUser2 OR u2."firstName" LIKE :paramUser2 OR u2."middleName" LIKE :paramUser2) ';

            $params[':paramUser2'] = "%$this->doctor%";
        }
        if ($this->message) {
            if (strlen($sqlWhere) > 0) {
                $sqlWhere .= ' AND ';
            }
            $sqlWhere .= ' (m.message LIKE :paramMessage) ';

            $params[':paramMessage'] = "%$this->message%";
        }
        if (strlen($sqlWhere) > 0) {
            $sqlWhere = ' WHERE ' . $sqlWhere;
        }

        $totalCount = \Yii::$app->db->createCommand(
            'SELECT COUNT(*)  ' . $sql . $sqlWhere,
            $params
        )
                                    ->queryScalar();

        $paramForQuery = $params;


        $dataProvider = new SqlDataProvider(
            [
                'sql'        => 'SELECT d."lastMsgId" id, messages."receiverUserId", messages."senderUserId", messages.message, messages.amount, messages.status, messages.created ' .
                                $sql .
                                $sqlWhere,
                'params'     => $paramForQuery,
                'totalCount' => $totalCount,
                'sort'       => [
                    'defaultOrder' => [
                        'created' => SORT_ASC,
                    ],
                    'attributes'   => [
                        'created',
                        'amount',
                        'message',
                        'patient' => [
                            'asc'     => [
                                'messages."senderUserId"' => SORT_ASC,
                            ],
                            'desc'    => [
                                'messages."senderUserId"' => SORT_DESC,
                            ],
                            'default' => SORT_DESC,
                        ],
                        'doctor'  => [
                            'asc'     => [
                                'messages."receiverUserId"' => SORT_ASC,
                            ],
                            'desc'    => [
                                'messages."receiverUserId"' => SORT_DESC,
                            ],
                            'default' => SORT_DESC,
                        ],
                    ],
                ],
            ]
        );


        return $dataProvider;
    }


}