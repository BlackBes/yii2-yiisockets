<?php

namespace blackbes\yiisockets\controllers;

use blackbes\yiisockets\models\SocketToken;
use Yii;
use yii\filters\VerbFilter;

/**
 * SocketsController implements the actions that helps provide sockets connections.
 */
class SocketsController extends \yii\web\Controller  {

    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Requesting one-time auth token.
     *
     * @return string
     */
    public function actionRequestToken() {
        if (!Yii::$app->user->isGuest) {
            $request = Yii::$app->request;
            if ($request->isAjax) {
                $key = 'tokens-' . Yii::$app->user->id;
                $token = SocketToken::createSocketToken($key);
                if (!empty($token)) {
                    return $token;
                } else {
                    Yii::$app->response->statusCode = 400;
                    return 'Error! Token not generated.';
                }
            } else {
                Yii::$app->response->statusCode = 400;
                return 'Error! Request must be AJAX.';
            }
        } else {
            Yii::$app->response->statusCode = 400;
            return 'Error! Client must be logged in.';
        }
    }

}
