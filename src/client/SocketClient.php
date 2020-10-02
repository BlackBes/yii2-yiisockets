<?php

namespace blackbes\yiisockets\client;

use blackbes\yiisockets\models\SocketToken;
use Yii;

/**
 * Class, that implementing websocket connection for Yiisockets plugin.
 *
 * @property string $socket_mode Socket mode. ws or wss supported.
 * @property string $link link for connection, like localhost.com
 * @property string $port port for connection.
 */
class SocketClient {
    public $socket_mode;
    public $link;
    public $port;
    public $message;
    /**
     * Socket client constructor.
     *
     * @param string $link link for connection, like localhost.com
     * @param string $socket_mode Socket mode. ws or wss supported. Default is ws.
     * @param string $port port for connection. Default is 80 port.
     */
    public function __construct($link = 'localhost', $socket_mode = 'wss', $port = '80') {
        $this->socket_mode = $socket_mode;
        $this->link = $link;
        $this->port = $port;
    }

    /**
     * Call action on websocket server using action to route and data payload.
     *
     * @param string $action Action in Yii2-like style: controller/action
     * @param array $payload Data payload
     */
    public function send($action, $payload) {
        if(!empty($this->link)) {
            $new_token = SocketToken::createSocketToken(SocketToken::SERVER_SOCKET_CACHE);
            $this->message = ['action' => $action, 'data' => $payload];
            $connect_url = $this->socket_mode.'://'.$this->link.":".$this->port."?login-token=server&connect-token=".$new_token;

            \Ratchet\Client\connect($connect_url)->then(function ($conn) {
                $conn->send(json_encode($this->message));
                $conn->close();
            }, function ($e) {
                trigger_error("Could not connect: {$e->getMessage()}", E_USER_ERROR);
            });
        } else {
            trigger_error('Link can not be empty.', E_USER_ERROR);
        }
    }

}