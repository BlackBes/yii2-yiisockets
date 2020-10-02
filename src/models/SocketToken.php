<?php

namespace blackbes\yiisockets\models;

use Yii;

/**
 * This is the model class for websocket tokens handling.
 *
 */
class SocketToken extends \yii\db\ActiveRecord {

    const SERVER_SOCKET_CACHE = 'tokens-server';

    /**
     * Generate websocket token for user. Only for Web Part Usage.
     * @param $key
     *
     * @return mixed
     */
    public static function createSocketToken($key) {
        if (Yii::$app instanceof \yii\web\Application) {
            if (!Yii::$app->user->isGuest) {
                if (!empty($key)) {
                    $cache = Yii::$app->cache;

                    $tokens = $cache->get($key);

                    if ($tokens === false) {
                        $tokens = [];
                    }

                    $token_payload = [];
                    $token_payload['token'] = Yii::$app->security->generateRandomString();
                    $token_payload['timestamp'] = date('Y-m-d H:i:s');

                    array_push($tokens, $token_payload);

                    $cache->set($key, $tokens);

                    return $token_payload['token'];
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Verify user`s websocket token. In case of success - delete token from cache.
     *
     * @param string $key
     * @param string $token
     *
     * @return boolean
     */
    public static function verifySocketToken($key, $token) {
        if (!empty($key)) {
            if (!empty($token)) {
                $cache = Yii::$app->cache;

                $tokens = $cache->get($key);

                if ($tokens !== false) {

                    foreach ($tokens as $key => $value) {
                        if ($value['token'] == $token) {
                            unset($tokens[$key]);
                            $cache->set($key, $tokens);
                            return true;
                        }
                    }

                    if (in_array($token, $tokens)) {
                        SocketToken::deleteElement($token, $tokens);
                        $cache->set($key, $tokens);
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Checking for outdated socket tokens for spesific user.
     *
     * @param string $cache_key
     *
     * @return boolean
     */
    public static function clearSocketTokens($cache_key) {
        if (!Yii::$app->user->isGuest) {
            if (!empty($cache_key)) {
                $cache = Yii::$app->cache;

                $tokens = $cache->get($cache_key);
                $clear_tokens = [];
                if ($tokens !== false) {
                    foreach ($tokens as $key => $value) {
                        $minutes = (time() - strtotime($value['timestamp'])) / 60;
                        if ($minutes <= 1) {
                            $clear_tokens[] = $value;
                        }
                    }
                    $cache->set($cache_key, $clear_tokens);
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Remove an element from an array.
     *
     * @param string|int $element
     * @param array $array
     */
    public static function deleteElement($element, &$array) {
        $index = array_search($element, $array);
        if ($index !== false) {
            unset($array[$index]);
        }
    }
}
