<?php

namespace                                           Websocket;

require_once                                        __DIR__ . '/../ILog.php';

class                                               Log implements \Websocket\ILog
{    
    /* PRIVATE ATTRIBUTS */
    private                                         $_filename = \Websocket\ILog::_DEFAULT_LOG_FILENAME_;
    
    /*
     * @brief                                       Change log's filename.
     * @params [STRING] $filename                   name of file.
     */
    public function                                 setFilename($filename)
    {
        if (is_string($filename) && !empty($filename))
        {
            $this->_filename = $filename;
        }
    }
    
    /*
     * @brief                                       write on log's file
     * @params [STRING] $msg                        Message to write on log's file.
     * @return success                              true
     * @return failure                              false
     */
    public function                                 write($aMessages)
    {
        $header = date('l jS \of F Y H:i:s e') . " => ";
        $crnl = chr(13).chr(10); /* CR (Carriage Return) NL (New Line) = '\r\n' */
        $logMessage = '';
        foreach ($aMessages as &$message)
        {
            if (is_string($message) && !empty($message))
            {
                $logMessage .= "{$header}{$message}{$crnl}";
            }
        }
        if (!empty($logMessage))
        {
            file_put_contents($this->_filename, $logMessage, FILE_APPEND);
        }
    }
}
