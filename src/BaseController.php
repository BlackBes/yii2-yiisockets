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

    public $conn;
    public $request;
    public $is_server;

    /**
     * Base constructor.
     * @param ConnectionInterface $conn connection of current client, that was connected.
     * @param \stdClass $request_data data payload that was recieved from cliemt.
     * @param bool $is_server if connection was produced by server
     */
    public function __construct(ConnectionInterface $conn, \stdClass $request_data, $is_server) {
        $this->conn = $conn;
        $this->request = $request_data;
        $this->is_server = $is_server;
    }

    /**
     * Send data to client.
     * @param ConnectionInterface $conn user`s connection.
     * @param string $action action name to call on client`s side.
     * @param mixed $data data payload to send.
     */
    public function send(ConnectionInterface $conn, $action, $data) {
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
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
     * @return bool
     */
    public function sendToGroupExcludeClient($action, $data, $group_id = 'clients') {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $el) {
                if ($el != $this->conn) {
                    $this->send($el, $action, $data);
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
     * @return bool
     */
    public function sendToGroupExcludeUser($action, $data, $group_id = 'clients') {
        $user_id = self::getClientId($this->conn);
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $el) {
                if ($el != $this->conn) {
                    $iter_user_id = self::getClientId($el);
                    if ($iter_user_id != $user_id) {
                        $this->send($el, $action, $data);
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
     * @return bool
     */
    public function sendToGroup($action, $data, $group_id = 'clients') {
        if (array_key_exists($group_id, $GLOBALS['groups'])) {
            foreach ($GLOBALS['groups'][$group_id] as $el) {
                $this->send($el, $action, $data);

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
            return false;
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
}
