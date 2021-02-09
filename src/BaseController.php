<?php

namespace blackbes\yiisockets;

use Ratchet\ConnectionInterface;

/**
 * This is the base controller for sockets routing system.
 *
 * @property ConnectionInterface $conn
 * @property \stdClass $request
 */
class BaseController {

    /**
     * @var ConnectionInterface Client`s connection
     */
    public $conn;

    /**
     * @var \stdClass Raw data
     */
    public $request;

    /**
     * @var int Id of client, that was connected. Extracted from User identity.
     */
    public $client_id;

    /**
     * @var string Role or just type of client.
     */
    public $role;

    /**
     * Base constructor.
     * @param ConnectionInterface $conn connection of current client, that was connected.
     * @param \stdClass $request_data data payload that was recieved from cliemt.
     */
    public function __construct(ConnectionInterface $conn, \stdClass $request_data) {
        $client_id = 0;
        $role = 'client';

        if(isset($conn->client_id)) {
            $client_id = $conn->client_id;
        }

        if(isset($conn->role)) {
            $role = $conn->role;
        }

        $this->conn = $conn;
        $this->request = $request_data;
        $this->client_id = $client_id;
        $this->role = $role;

    }

    /**
     * Send data to client.
     * @param ConnectionInterface $conn user`s connection.
     * @param string $action action name to call on client`s side.
     * @param mixed $data data payload to send.
     * @param bool $is_json option, to send data as json string
     */
    public function send(ConnectionInterface $conn, $action, $data, $is_json = false) {
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        if($is_json) {
            $json_data = json_encode($json_data, JSON_UNESCAPED_UNICODE);
        }
        $conn->send('{"status": "1", "action":"' . $action . '", "data": ' . $json_data . '}');
    }

    /**
     * Throws custom error to client.
     * @param ConnectionInterface $conn user`s connection.
     * @param string $error_text error message.
     */
    public function sendError(ConnectionInterface $conn, $error_text) {
        $conn->send('{"status": "2", "data": {"errorText": "' . $error_text . '"}}');
    }

    /**
     * Send data to clients, that stored in some group, excluding current connection.
     * @param string $action action name to call on client`s side.
     * @param mixed $data data payload to send.
     * @param string $group_id (optional) id of the existing group.
     * @param bool $is_json option, to send data as json string
     * @return bool
     */
    public function sendToGroupExcludeClient($action, $data, $group_id = 'clients', $is_json = false) {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $el) {
                if ($el != $this->conn) {
                    $this->send($el, $action, $data, $is_json);
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Send data to clients, that stored in some group, excluding current user.
     * @param string $action action name to call on client`s side.
     * @param mixed $data data payload to send.
     * @param string $group_id (optional) id of the existing group.
     * @param bool $is_json option, to send data as json string
     * @return bool
     */
    public function sendToGroupExcludeUser($action, $data, $group_id = 'clients', $is_json = false) {
        $user_id = self::getClientId($this->conn);
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $el) {
                if ($el != $this->conn) {
                    $iter_user_id = self::getClientId($el);
                    if ($iter_user_id != $user_id) {
                        $this->send($el, $action, $data, $is_json);
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Send data to clients, that stored in some group.
     * @param string $action action name to call on client`s side.
     * @param mixed $data data payload to send.
     * @param string $group_id (optional) id of the existing group. Default 'clients' group will be used if nothing was provided.
     * @param bool $is_json option, to send data as json string
     * @return bool
     */
    public function sendToGroup($action, $data, $group_id = 'clients', $is_json = false) {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $el) {
                $this->send($el, $action, $data, $is_json);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add client`s ConnectionInterface to specific group.
     * @param ConnectionInterface $conn user`s connection.
     * @param string $group_id id of the group. If group not exist, it will create one.
     */
    public function addToGroup(ConnectionInterface $conn, $group_id) {
        if (!array_key_exists($group_id, $GLOBALS['groups'])) {
            $GLOBALS['groups'][$group_id] = new \SplObjectStorage();
        }
        $GLOBALS['groups'][$group_id]->attach($conn);
        return true;
    }

    /**
     * Remove client`s ConnectionInterface to specific group.
     * @param ConnectionInterface $conn user`s connection.
     * @param string $group_id id of the existing group.
     */
    public function removeFromGroup(ConnectionInterface $conn, $group_id) {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            $GLOBALS['groups'][$group_id]->detach($conn);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check, if client consists in selected group
     *
     * @param ConnectionInterface $conn user`s connection.
     * @param string $group_id id of the existing group.
     * @return boolean
     */
    public static function isInGroup(ConnectionInterface $conn, $group_id) {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $client) {
                if ($client == $conn) {
                    return true;
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * Get all connections from specific group. Clients group is default.
     *
     * @param string $group_id id of the existing group.
     * @return mixed
     */
    public static function GetGroup($group_id = 'clients') {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            return $GLOBALS['groups'][$group_id];
        } else {
            return false;
        }
    }

    /**
     * Trying to get client`s id, that is stored in Yii2 users table.
     *
     * @param ConnectionInterface $conn user`s connection.
     * @return mixed
     */
    public static function getClientId($conn) {
        if (isset($conn->client_id)) {
            return $conn->client_id;
        } else {
            return false;
        }
    }

    /**
     * Trying to get data from request, send NULL if not found.
     *
     * @param string $data_name name of field, to extract from request.
     * @return mixed
     */
    public function getData($data_name) {
        if(property_exists($this->request, $data_name)) {
            return $this->request->$data_name;
        } else {
            return null;
        }
    }
}
