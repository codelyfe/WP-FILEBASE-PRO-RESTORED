<?php

class WPFB_RPC {

    static function isWPFBClass($class) {
        return strpos($class, 'WPFB_') === 0;
    }

    static function rmClassPrefix($class) {
        return substr($class, 5);
    }

    public static function Call($function) {
        $args = func_get_args();
        array_shift($args);
        return (self::rpcCall($function, $args, false));
    }

    public static function CallSafe($function) {
        $args = func_get_args();
        array_shift($args);

        try {
            return (self::rpcCall($function, $args, false));
        } catch (Exception $e) {
            return call_user_func_array($function, $args);
        }
    }

    /**
     * 
     * @param callable $function
     * @param callable $callback
     * @return type
     */
    public static function CallAsync($function, $callback) {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        return self::rpcCall($function, $args, true, $callback);
    }

    private static function rpcCall($func, &$args, $async, $async_callback = null) {
        $wpfb_classes = array_map(array(__CLASS__, 'rmClassPrefix'), array_filter(get_declared_classes(), array(__CLASS__, 'isWPFBClass')));

        $post_data = array('cs' => $wpfb_classes, 'fn' => serialize($func), 'ag' => serialize($args));

        if ($async)
            $post_data['cb'] = empty($async_callback) ? '' : serialize($async_callback); // MUST be ''!

        $post_data['no'] = wp_hash(wp_nonce_tick() . serialize($post_data), 'nonce');

        $cookies = array();
        if (($debug = !empty($_COOKIE['XDEBUG_SESSION'])))
            $cookies[] = new WP_Http_Cookie(array('name' => 'XDEBUG_SESSION', 'value' => $_COOKIE['XDEBUG_SESSION']));


        // 		array('method' => 'POST' redirection  user-agent decompress  sslverify stream $r['headers'] = array();)
        // TODO: use HttpStreams (and no cURL!) cURl blocks!
        $response = wp_remote_post(WPFB_Core::PluginUrl('rpc.php'), array('timeout' => ($async) ? 1 : 60, 'blocking' => !$async, 'body' => $post_data, 'cookies' => $cookies));

        if ($async)
            return true;

        if (is_wp_error($response))
            throw new WPFB_RPCException($response->get_error_message());

        if (is_array($response) && (!empty($response['errors']) || (!$async && $response['response']['code'] != 200)))
            throw new WPFB_RPCException('RPC Call Failed: ' . (!empty($response['response']['code']) ? $response['response']['code'] : '') . ' ' . @implode(', ', $response['errors']), E_USER_WARNING);

        if (!$async) {
            $data = unserialize($response['body']);


            if (!is_array($data) || !isset($data['r'])) {
                WPFB_Core::LogMsg("RPC Test failed with response: ".print_r($response, true));
                throw new WPFB_RPCException('Invalid RPC response or deserialization failed! (url:' . WPFB_Core::PluginUrl('rpc.php') . ',body:`' . substr($response['body'], 0, 200) . '`...)');
            }
            unset($response);

            if (!empty($data['o']))
                echo $data['o'];

            return $data['r'];
        } else
            return true;
    }

}

class WPFB_RPCException extends Exception {

    public function __construct($err = null, $isDebug = FALSE) {
        if (is_null($err)) {
            $el = error_get_last();
            $this->message = $el['message'];
            $this->file = $el['file'];
            $this->line = $el['line'];
        } else
            $this->message = $err;
        self::log_error($err);
        if ($isDebug) {
            self::display_error($err, TRUE);
        }
    }

    public static function log_error($err) {
        error_log($err, 0);
    }

    public static function display_error($err, $kill = FALSE) {
        print_r($err);
        if ($kill === FALSE) {
            die();
        }
    }

}
