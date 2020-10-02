<?php
namespace blackbes\yiisockets;
use Yii;
use yii\base\BootstrapInterface;
use yii\helpers\Url;
use blackbes\yiisockets\models\SocketToken;
class Bootstrap implements BootstrapInterface{
    //Метод, который вызывается автоматически при каждом запросе
    public function bootstrap($app)
    {
        $app->setModule('yiisockets', ['class' => 'blackbes\yiisockets\Module']);

        $app->getUrlManager()->addRules([
            'request-socket-token' => 'yiisockets/sockets/request-token',
        ], false);

        if ($app instanceof \yii\web\Application) {
            if (!$app->user->isGuest) {
                if($app->request->isGet && !$app->request->isAjax) {
                    $app->view->registerJs('let requestTokenUrl = "' . Url::toRoute(['/']) . '";', 2);
                    AssetsBundle::register($app->view);
                    $key = 'tokens-' . $app->user->id;
                    SocketToken::clearSocketTokens(SocketToken::SERVER_SOCKET_CACHE);
                    SocketToken::clearSocketTokens($key);
                    $new_token = SocketToken::createSocketToken($key);
                    $auth_token =Yii::$app->user->identity->auth_key;

                    Yii::$app->view->registerMetaTag([
                        'name'    => 'socket-token',
                        'content' => $new_token
                    ]);
                    Yii::$app->view->registerMetaTag([
                        'name'    => 'login-token',
                        'content' => $auth_token
                    ]);
                }
            }
        }
    }
}