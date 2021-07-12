<?php

namespace app\commands;

use app\models\Users;
use blackbes\yiisockets\server\SocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\App;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

class SocketController extends \yii\console\Controller {

    public function actionStartSocket($port = 8088) {

        $loop = LoopFactory::create();
        $socket = new Reactor("0.0.0.0:".$port, $loop);

        $server = new IoServer(new HttpServer(new WsServer(new SocketServer([
            'app\\commands\\SocketController',
            'validation'
        ], $loop))), $socket, $loop);

        $server->run();
    }

    public static function validation($params) {
        if(array_key_exists('login-token', $params)) {
            $user = Users::findIdentityByAccessToken($params['login-token']);
            if (!empty($user)) {
                return ['client' => $user];
            }
            return false;
        } else {
            return false;
        }
    }
}
