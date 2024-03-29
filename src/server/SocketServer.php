<?php

namespace blackbes\yiisockets\server;

use blackbes\yiisockets\BaseController;
use React\EventLoop\LoopInterface;
use Yii;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use yii\db\Exception;

global $groups;

/**
 * Socket Server implementation with Yii2-like routing and groups support.
 *
 * This router use controller/action routing system, so, basically,
 * you need setup namespace of your websocket controllers and name they in the way, Yii2 controllers work.
 *
 * And, to make this things work, your controller, should extend sockets/BaseController.
 *
 * @property string $controllers_namespace
 */
class SocketServer implements MessageComponentInterface {
    public $controllers_namespace = "";
    public $validation_function   = [];
    public $loop;

    /**
     * Server constructor.
     * @param array $validation_function Validation function credentials.
     * @param LoopInterface $loop
     * @param string $controllers_namespace Namespace of all sockets controllers. "app\sockets\" by default.
     */
    public function __construct($validation_function, LoopInterface $loop, $controllers_namespace = "app\sockets\\") {
        $this->controllers_namespace = $controllers_namespace;
        $this->loop = $loop;
        if (is_array($validation_function)) {
            $this->validation_function = $validation_function;

            $GLOBALS['groups'] = [];
            $GLOBALS['groups']['clients'] = new \SplObjectStorage;
            $GLOBALS['groups']['_servers'] = new \SplObjectStorage;
        } else {
            trigger_error('Validation function should be a array.', E_USER_ERROR);
        }
    }

    /**
     * Implementing of Ratchet`s standard onOpen function.
     *
     * It verify client`s login credentials based on custom verification function and establish connection, or not.
     *
     * @param ConnectionInterface $conn user`s connection.
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->testDBConnection();

        $params = $this->requestGetParameters($conn);
        //$GLOBALS['groups']['clients']->attach($conn);
        //echo "New connection! ({$conn->resourceId})\n";

        if (!empty($params['data'])) {
            $raw_data = urldecode($params['data']);
            $data = json_decode($raw_data, true);

            //if($login_token != 'server') {
            if (is_array($data)) {
                $validation_data = call_user_func($this->validation_function, $data);

                if ($validation_data != false) {
                    if (is_array($validation_data)) {
                        if(array_key_exists('client', $validation_data)) {
                            $user = $validation_data['client'];
                            Yii::$app->user->setIdentity($user);

                            $role = 'client';
                            if(array_key_exists('role', $validation_data)) {
                                $role = $validation_data['role'];
                            }

                            //Attaching new params to Conn interface. ITS NOT DEFAULT ConnectionInterface PARAMETERS!
                            $conn->client_id = $user->id;
                            $conn->role = $role;

                            $GLOBALS['groups']['clients']->attach($conn);

                            if (!isset($GLOBALS['groups']['_client_' . $user->id])) {
                                $GLOBALS['groups']['_client_' . $user->id] = new \SplObjectStorage();
                            }
                            if (count($GLOBALS['groups']['_client_' . $user->id]) == 0) {
                                $GLOBALS['groups']['_client_' . $user->id]->attach($conn);

                            } else {
                                $GLOBALS['groups']['_client_' . $user->id]->attach($conn);
                            }
                            $this->writeInfo("New connection! ({$conn->resourceId})");
                            $this->callOpenCallbacks($conn);
                        } else {
                            trigger_error('Validation return data should contain "client" key.', E_USER_ERROR);
                        }
                    } else {
                        trigger_error('Validation return data should be an array.', E_USER_ERROR);
                    }
                } else {
                    $this->writeError("Wrong user login token in connection {$conn->resourceId}.");
                    $this->sendError($conn, 'Wrong user login token.');
                }
            } else {
                trigger_error('Data should be a valid JSON.', E_USER_ERROR);
            }
            /*} else {
                if (SocketToken::verifySocketToken(SocketToken::SERVER_SOCKET_CACHE, $connect_token)) {
                    $GLOBALS['groups']['_servers']->attach($conn);
                    $this->writeInfo("New connection from server! ({$conn->resourceId})");
                } else {
                    trigger_error('Wrong connection token.', E_USER_ERROR);
                }
            }*/
        } else {
            trigger_error('Login credentials can not be empty.', E_USER_ERROR);
        }
    }

    /**
     * Implementing of Ratchet`s standard onMessage function.
     *
     * It prepare and verify routing and calls selected controller and action.
     *
     * @param ConnectionInterface $from user`s connection.
     * @param string $msg JSON raw data, that comes from client.
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $this->testDBConnection();
        $data = json_decode($msg); //для приема сообщений в формате json
        $this->writeInfo($msg);
        if (empty($data)) {
            $this->writeWarning("Data payload is not valid JSON or not exist.");
            return $from->close();
        }

        if (isset($data->action)) {
            if (!empty($data->action)) {
                $action_parts = explode('/', $data->action);
                $controller = '';
                $action = '';

                if (count($action_parts) == 1) {
                    $controller = $action_parts[0];
                    $action = 'index';
                } elseif (count($action_parts) == 2) {
                    $controller = (!empty($action_parts[0])) ? $action_parts[0] : "index";
                    $action = (!empty($action_parts[1])) ? $action_parts[1] : "index";
                } elseif (count($action_parts) >= 3) {
                    if (empty($action_parts[0])) {
                        $controller = (!empty($action_parts[1])) ? $action_parts[1] : "index";
                        $action = (!empty($action_parts[2])) ? $action_parts[2] : "index";
                    } elseif (empty($action_parts[2])) {
                        $controller = (!empty($action_parts[0])) ? $action_parts[0] : "index";
                        $action = (!empty($action_parts[1])) ? $action_parts[1] : "index";
                    } else {
                        $controller = $action_parts[0];
                        $action = $action_parts[1];
                    }
                }

                $controller_name_parts = explode('-', $controller);
                $controller_name = '';

                foreach ($controller_name_parts as $el) {
                    $controller_name .= ucfirst($el);
                }

                $action_name_parts = explode('-', $action);
                $action_name = '';

                foreach ($action_name_parts as $el) {
                    $action_name .= ucfirst($el);
                }

                if (class_exists($this->controllers_namespace . $controller_name . 'Controller')) {
                    $c_name_string = $controller_name . 'Controller';
                    $controller_full_path = $this->controllers_namespace . $c_name_string;
                    $a_name_string = 'action' . $action_name;
                    $request_data = new \stdClass();
                    if (isset($data->data)) {
                        $request_data = $data->data;
                    }

                    $cont = new $controller_full_path($from, $this->loop, $request_data);
                    if (method_exists($cont, $a_name_string)) {
                        $cont->$a_name_string();
                    } else {
                        trigger_error('There is no ' . $action_name . ' action in ' . $controller_name . ' controller.', E_USER_ERROR);
                    }
                } else {
                    trigger_error('There is no ' . $controller_name . ' controller.', E_USER_ERROR);
                }
            } else {
                trigger_error('Action can not be empty.', E_USER_ERROR);
            }
        } else {
            trigger_error('There must be action parameter.', E_USER_ERROR);
        }
    }

    /**
     * Implementing of Ratchet`s standard onClose function.
     *
     * It detaching user from main (clients) group.
     *
     * @param ConnectionInterface $conn user`s connection.
     */
    public function onClose(ConnectionInterface $conn) {
        $this->removeConnectionFromGroups($conn);
        //$user_id = BaseController::getClientId($conn);

        //if (count($GLOBALS['groups']['_client_' . $user_id]) == 0) {
            //$bc = new BaseController($conn, new \stdClass());
            //$bc->sendToGroupExcludeUser('im-offline', ['user_id' => $user_id]);
        //}

        $this->writeInfo("Connection {$conn->resourceId} has disconnected");
    }

    /**
     * Removing selected connection from all groups, that exist on server
     *
     * @param ConnectionInterface $conn user`s connection.
     */
    public function removeConnectionFromGroups($conn) {
        $this->callCloseCallbacks($conn);
        foreach ($GLOBALS['groups'] as $key => $group) {
            foreach ($group as $client) {
                if ($client == $conn) {
                    $this->writeInfo("Detaching user from group {$key}!");
                    $group->detach($client);
                }
            }
        }
        $this->writeInfo("All groups cleared from {$conn->resourceId} connection!");
    }

    /**
     * Implementing of Ratchet`s standard onError function.
     *
     * It handles all errors, that was caused by this class and send this error to client.
     * To trigger this function, use trigger_error('Error text here', E_USER_ERROR);
     *
     * @param ConnectionInterface $conn user`s connection.
     * @param \Exception $e exception.
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        if($this->sendError($conn, $e->getMessage())) {
            $this->writeError($e->getMessage());
            print_r($e->getTraceAsString());
            $this->removeConnectionFromGroups($conn);

            $this->loop->addTimer(0, function () use($conn) {
                $conn->close();
            });
        }
    }

    /**
     * Just sending error to client.
     *
     * @param ConnectionInterface $conn user`s connection.
     * @param string $text Error text
     */
    public function sendError(ConnectionInterface $conn, $text) {
        $json_data = ["errorText" => $text];
        $json_data = json_encode($json_data, JSON_UNESCAPED_UNICODE);
        $json_data = json_encode($json_data, JSON_UNESCAPED_UNICODE);
        if($conn->send('{"status": "2", "data": '.$json_data.'}')) {
            return true;
        }
        return false;
    }

    /**
     * This function taking query of uri and breaks stringified get parameters into a key=>value array.
     *
     * @param ConnectionInterface $conn user`s connection.
     * @return array
     */
    public function requestGetParameters(ConnectionInterface $conn) {
        $query = $conn->httpRequest->getUri()
            ->getQuery();

        $query_parts = explode('&', $query);
        $clean_parameters = [];

        foreach ($query_parts as $part) {
            if (!empty($part)) {
                $els = explode('=', $part);
                $clean_parameters[$els[0]] = $els[1];
            }
        }

        return $clean_parameters;
    }

    /**
     * This function test db connection and reconnect to db if DB has gone away.
     *
     */
    public function testDBConnection() {
        try {
            $result = \Yii::$app->db->createCommand("DO 1")
                ->execute();
        } catch (Exception $e) {
            print_r('MySQL Has gone away. Reconnecting...');
            \Yii::$app->db->close();
            \Yii::$app->db->open();
        }
    }

    /**
     * This function triggers _OnOpen callbacks at all Socket controllers.
     *
     * @param ConnectionInterface $conn user`s connection.
     */
    public function callOpenCallbacks(ConnectionInterface $conn) {
        $namespace_parts = explode('\\', $this->controllers_namespace);
        $app_path = '';
        foreach ($namespace_parts as $part) {
            if (!empty($part) && $part != 'app') {
                $app_path .= $part . "/";
            }
        }
        $files = glob(\Yii::$app->basePath . '/' . $app_path . '*.php');
        foreach ($files as $file) {
            require_once($file);

            $class = basename($file, '.php');

            $class_full_name = $this->controllers_namespace . $class;

            if (class_exists($class_full_name)) {
                $obj = new $class_full_name($conn, $this->loop, new \stdClass());
                if (method_exists($obj, '_OnOpen')) {
                    $obj->_OnOpen();
                }
            }
        }
    }

    /**
     * This function triggers _OnClose callbacks at all Socket controllers.
     *
     * @param ConnectionInterface $conn user`s connection.
     */
    public function callCloseCallbacks(ConnectionInterface $conn) {
        $namespace_parts = explode('\\', $this->controllers_namespace);
        $app_path = '';
        foreach ($namespace_parts as $part) {
            if (!empty($part) && $part != 'app') {
                $app_path .= $part . "/";
            }
        }
        $files = glob(\Yii::$app->basePath . '/' . $app_path . '*.php');
        foreach ($files as $file) {
            require_once($file);

            $class = basename($file, '.php');

            $class_full_name = $this->controllers_namespace . $class;

            if (class_exists($class_full_name)) {
                $obj = new $class_full_name($conn, $this->loop, new \stdClass());
                if (method_exists($obj, '_OnClose')) {
                    $obj->_OnClose();
                }
            }
        }
    }

    /**
     * Sending structured info text to server`s console.
     *
     * @param string $text
     */
    public function writeInfo($text) {
        echo '[Info] ' . date('H:i:s') . " : " . $text . "\n";
    }

    /**
     * Sending structured warning text to server`s console.
     *
     * @param string $text
     */
    public function writeWarning($text) {
        echo '[Warning] ' . date('H:i:s') . " : " . $text . "\n";
    }

    /**
     * Sending structured error text to server`s console.
     *
     * @param string $text
     */
    public function writeError($text) {
        echo '[Error] ' . date('H:i:s') . " : " . $text . "\n";
    }
}

