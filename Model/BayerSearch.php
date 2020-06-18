<?php


namespace admin\models;


use common\models\users\UserHistory;
use yii\base\Model;
use yii\data\SqlDataProvider;

class BayerSearch extends Model
{
    public $created   = null;

    public $utmSource = null;


    public function rules()
    {
        return [
            [
                [
                    'created',
                    'utmSource',
                ],
                'safe',
            ],
        ];
    }


    public function search($params, $pollId)
    {

        $start_time = (isset($params['start']))? $params['start']: 0;
        $end_time = (isset($params['end']))? $params['end']: 0;


        $this->load($params);

        $sql = 'FROM "usersPolls" up ' .
               'LEFT JOIN users u1 ON (u1.id = up."objectId") ' .
               //Объект опроса - в данном случае это пациент
               'LEFT JOIN users u2 ON (u2.id = up."userId") ' .
               //Тот, кто отвечает на вопросы, в данном случае врач
               'LEFT JOIN "usersHistory" uh ON ' .
               '((uh."userId" = up."userId") AND (uh."objectId" = up."objectId") AND (uh."eventType" = ' .
               UserHistory::ADD_BONUS_BAYER_CONSULT .
               '))';

        $sqlWhere = '';
        $params   = [];

        $sqlWhere               .= ' up."objectId" IS NOT NULL ';
        $sqlWhere               .= ' AND up."pollId" = :paramPollId ';

        $params[':paramPollId'] = $pollId;

        if (!is_null($this->created) &&
            strpos($this->created, ' - ') !== false
        ) {
            if (strlen($sqlWhere) > 0) {
                $sqlWhere .= ' AND ';
            }
            list($dateStart, $dateFinish) = explode(' - ', $this->created);
            $sqlWhere .= " up.created BETWEEN :paramDate1 AND :paramDate2 ";

        }

        if ($start_time && $end_time){
            $params[':paramDate1'] = $start_time;
            $params[':paramDate2'] = $end_time;

            if (strlen($sqlWhere) > 0) {
                $sqlWhere .= ' AND ';
                $sqlWhere .= " up.created BETWEEN :paramDate1 AND :paramDate2 ";
            }

        }


        if (!is_null($this->utmSource)) {
            if (strlen($sqlWhere) > 0) {
                $sqlWhere .= ' AND ';
            }
            $sqlWhere .= ' u1."utmSource" iLIKE :utmParam ';

            $params[':utmParam'] = '%' . trim($this->utmSource) . '%';
        }

        if (strlen($sqlWhere) > 0) {
            $sqlWhere = ' WHERE ' . $sqlWhere;
        }

        $totalCount = \Yii::$app->db->createCommand(
            'SELECT COUNT(*) ' . $sql . $sqlWhere,
            $params
        )
                                    ->queryScalar();

        $paramForQuery = $params;


        $dataProvider = new SqlDataProvider(
            [
                'sql'    => 'SELECT up.created, up.id, uh.id "bonusId", uh.created "bonusCreated", u1."utmSource", ' .
                            'u1."id" "patientUserId", u1."lastName" "lastNamePatient", u1."firstName" "firstNamePatient", u1."middleName" "middleNamePatient", ' .
                            'u2."id" "doctorUserId", u2."lastName" "lastNameDoctor", u2."firstName" "firstNameDoctor", u2."middleName" "middleNameDoctor" ' .
                            $sql .
                            $sqlWhere,
                'params' => $paramForQuery,

                'totalCount' => $totalCount,
                'sort'       => [
                    'defaultOrder' => [
                        'created' => SORT_DESC,
                        'id'      => SORT_DESC,
                    ],
                    'attributes'   => [
                        'id',
                        'created',
                        'utmSource',
                        'doctorPollCreated' => [
                            'asc'  => [
                                '"usersPolls".id' => SORT_ASC,
                            ],
                            'desc' => [
                                '"usersPolls".id' => SORT_DESC,
                            ],
                        ],
                        'userPatient' => [
                            'asc'     => [
                                '"lastNamePatient"'   => SORT_ASC,
                                '"firstNamePatient"'  => SORT_ASC,
                                '"middleNamePatient"' => SORT_ASC,
                                '"patientUserId"'     => SORT_ASC,
                            ],
                            'desc'    => [
                                '"lastNamePatient"'   => SORT_DESC,
                                '"firstNamePatient"'  => SORT_DESC,
                                '"middleNamePatient"' => SORT_DESC,
                                '"patientUserId"'     => SORT_DESC,
                            ],
                            'default' => SORT_DESC,
                        ],
                        'userDoctor'  => [
                            'asc'     => [
                                '"lastNameDoctor"'   => SORT_ASC,
                                '"firstNameDoctor"'  => SORT_ASC,
                                '"middleNameDoctor"' => SORT_ASC,
                                '"doctorUserId"'     => SORT_ASC,
                            ],
                            'desc'    => [
                                '"lastNameDoctor"'   => SORT_DESC,
                                '"firstNameDoctor"'  => SORT_DESC,
                                '"middleNameDoctor"' => SORT_DESC,
                                '"doctorUserId"'     => SORT_DESC,
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