<?php

namespace                                           Websocket;

/*
 * @Description                                     Interface of log.
 *
 * 
 * @author                                          Sébastien Le Maire
 * @Creation                                        16/10/2017
 * @Version                                         1.0
 * @Update                                          16/10/2017
*/
interface                                           ILog
{
    const                                           _DEFAULT_LOG_FILENAME_ = 'websocket_server_log.txt';
    
    /*
     * @brief                                       Set log's filename.
     * @params [STRING] $filename                   Log's filename.
     */
    public function                                 setFilename($filename);
    
    /*
     * @brief                                       Set log's filename.
     * @params [ARRAY(STRING)] $aMessages           Array of messages, each messages is line.
     */
    public function                                 write($aMessages);
}
