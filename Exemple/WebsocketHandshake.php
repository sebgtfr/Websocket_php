<?php

namespace                                           App\Websocket;

require_once                                        __DIR__ . '/../Handshake.php';

class                                               WebsocketHandshake extends \Websocket\Handshake
{
    /*
     * @brief                                       check if origin on connection is on the same hostname to server.
     */
    protected function                              checkOrigin($origin)
    {
        return boolval($_SERVER['SERVER_NAME'] == parse_url($origin, PHP_URL_HOST));
    }
}