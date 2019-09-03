<?php

namespace                                           Websocket;

class                                               Handshake
{
    /* CONST */
    const                                           _MAGIC_STRING_ = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'; /* Magic string use for handshake response's protocol */
    const                                           _MAGIC_HASH_ = 'sha1'; /* Hash's algorithm use for handshake response's protocol */
    
    /* ERRORS*/
    const                                           _ERROR_HTTP_TYPE_ = 0;
    const                                           _ERROR_HTTP_OBSOLETE_VERSION_ = 1;
    const                                           _ERROR_PARSING_HTTP_LINE_ = 2;
    const                                           _ERROR_NO_PARSING_ = 3;
    const                                           _ERROR_MISSING_END_ = 4;
    const                                           _ERRORS_ = array
    (
        self::_ERROR_HTTP_TYPE_ =>                  'HTTP Method doesn\'t type "GET".',
        self::_ERROR_HTTP_OBSOLETE_VERSION_ =>      'HTTP\'s version is obsolete.',
        self::_ERROR_PARSING_HTTP_LINE_ =>          'An error occurred during HTTP parsing\'s line.',
        self::_ERROR_NO_PARSING_ =>                 'No valid parsing was performed.',
        self::_ERROR_MISSING_END_ =>                'No valid ending buffer.'
    );
    
    const                                           _VALID_RESPONSE_HTTP_ = 101;
    const                                           _HTTP_BAD_REQUEST_ = 400;
    const                                           _HTTP_FORBIDDEN_ = 403;
    const                                           _HTTP_METHOD_NOT_ALLOW_ = 405;
    const                                           _HTTP_UPGRADE_REQUIRED_ = 426;
    const                                           _RESPONSE_HTTP_ = array
    (
        self::_VALID_RESPONSE_HTTP_ =>              'Switching Protocols',
        self::_HTTP_BAD_REQUEST_ =>                 'Bad Request',
        self::_HTTP_FORBIDDEN_ =>                   'Forbidden',
        self::_HTTP_METHOD_NOT_ALLOW_ =>            'Method Not Allowed',
        self::_HTTP_UPGRADE_REQUIRED_ =>            'Upgrade Required\r\nSec-WebSocketVersion: 13'
    );    
    
    /* ATTRIBUTS */
    private                                         $_header;
    private                                         $_lastCodeError;
    private                                         $_lastCodeHTTP;
    
    /* VIRTUAL METHOD */
    
    /*
     * @brief                                       Generate another variables on handshake's response.
     * @return                                      Array where key is the variable's name and value is its value.
     * @prototype
     * [array('varName' => 'varValue')]             addVarsToResponseHeader();
     */
    
    /* PUBLIC FUNCTION */
    
    /*
     * @brief                                       Get error's message from error's buffer.
     * @return success                              [STRING] error's message.
     * @return failure                              FALSE
     */
    public function                                 getLastCodeError()
    {
        return (isset($this->_lastCodeError)) ? $this->_lastCodeError : FALSE;
    }
    
    /*
     * @brief                                       Get error's message from error's array by code error. Use last code error if $codeError isn't int.
     * @return success                              [STRING] error's message.
     * @return failure                              FALSE
     */
    public function                                 getError($codeError = null)
    {
        if (is_int($codeError) && array_key_exists($codeError, self::_ERRORS_))
        {
            return self::_ERRORS_[$codeError];
        }
        return (isset($this->_lastCodeError)) ? self::_ERRORS_[$this->_lastCodeError] : FALSE;
    }
    
    /*
     * @brief                                       Get User's ID
     * @params [STRING] $stateName                  Name of Opcode.
     * @return success                              Constant's value
     * @return failure                              FALSE
     */
    public function                                 getContantError($errorName)
    {
        if (is_string($errorName) && !empty($errorName))
        {
            $errorName = ('self::_ERROR_' . strtoupper($errorName) . '_');
            if (defined($errorName))
            {
                return constant($errorName);
            }
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Get HTTP's code of the last response generate.
     * @return success                              [INT] HTTP's code.
     * @return failure                              FALSE
     */
    public function                                 getLastCodeHTTP()
    {
        return (isset($this->_lastCodeHTTP)) ? $this->_lastCodeHTTP : FALSE;
    }
    
    /*
     * @brief                                       Check if last HTTP's code was valid.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 lastCodeHTTPIsValid()
    {
        if (isset($this->_lastCodeHTTP))
        {
            return ($this->_lastCodeHTTP == self::_VALID_RESPONSE_HTTP_);
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Parse handshake client's header to fill header's data.
     * @params [STRING] $buffer                     Handshake client's header.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 parse($buffer)
    {
        if (isset($buffer) && is_string($buffer))
        {
            $crnl = chr(13).chr(10); /* \r\n */
            $lenBuffer = strlen($buffer);
            if ($lenBuffer > 4 && $this->isEndLine($buffer, $lenBuffer, $lenBuffer - 2, $crnl) && $this->isEndLine($buffer, $lenBuffer, $lenBuffer - 4, $crnl))
            {
                $this->_header = array();
                if (($start = $this->parseHeaderHTTP($buffer, $lenBuffer, $crnl)) !== FALSE)
                {
                    $this->parseHeaderLines($buffer, $lenBuffer, $start, $crnl);
                    return TRUE;
                }
            }
            else
            {
                $this->_lastCodeError = self::_ERROR_MISSING_END_;
            }
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Generate handshake server's response from last header's data generate by method \Websocket\Handshake::parse();
     *                                              You can create \Websocket\Handshake::addVarsToResponseHeader() virtual method to add another variable on response.
     * @return success                              [STRING] handshake header's response.
     * @return failure                              FALSE
     */
    public function                                 getResponse()
    {
        if (!empty($this->_header))
        {
            $crnl = chr(13).chr(10);
            $typeHTTP = $this->getHeaderData('HTTP-TYPE');
            $versionHTTP = $this->getHeaderData('HTTP-VERSION');
            $SecWebSocketAccept = $this->generateSecWebSocketAccept();
            $codeHTTP = self::_HTTP_BAD_REQUEST_;
            if (($typeHTTP !== FALSE && $versionHTTP !== FALSE && $SecWebSocketAccept !== FALSE) &&
                (($upgrade = $this->getHeaderData('Upgrade')) !== FALSE && is_string($upgrade) && strtolower($upgrade) == 'websocket') &&
                (($connection = $this->getHeaderData('Connection')) !== FALSE && is_string($connection) && strpos(strtolower($connection), 'upgrade') !== FALSE) &&
                (($codeHTTP = $this->checkHeader()) === self::_VALID_RESPONSE_HTTP_))
            {
                $codeHTTP = self::_HTTP_UPGRADE_REQUIRED_;
                if (($version = $this->getHeaderData('Sec-WebSocket-Version')) !== FALSE && is_string($version) && $version == '13')
                {
                    $codeHTTP = self::_HTTP_FORBIDDEN_;
                    if ((($origin = $this->getHeaderData('Origin')) !== FALSE && is_string($origin) && $this->checkOrigin($origin)) &&
                        (($host = $this->getHeaderData('Host')) !== FALSE && is_string($host) && $this->checkHost($host)))
                    {
                        $codeHTTP = self::_HTTP_METHOD_NOT_ALLOW_;
                        if (($resource = $this->getHeaderData('HTTP-RESOURCE')) !== FALSE && is_string($resource))
                        {
                            $response = $this->makeResponseHTTP(self::_VALID_RESPONSE_HTTP_).
                                        "Upgrade: WebSocket{$crnl}".
                                        "Connection: Upgrade{$crnl}".
                                        "Sec-WebSocket-Accept: {$SecWebSocketAccept}{$crnl}".
                                        "Sec-WebSocket-Origin: {$origin}{$crnl}".
                                        "Sec-WebSocket-Location: ws://{$host}{$resource}{$crnl}";                                
                            if (method_exists($this, 'addVarsToResponseHeader') && is_array(($anotherVars = $this->addVarsToResponseHeader())))
                            {
                                foreach ($anotherVars as $anotherVarsKey => $anotherVarsValue)
                                {
                                    if (is_string($anotherVarsKey) && !empty($anotherVarsKey) && is_string($anotherVarsValue) && !empty($anotherVarsValue))
                                    {
                                        $response .= "{$anotherVarsKey}: {$anotherVarsValue}{$crnl}";
                                    }
                                }
                            }
                            if (isset($this->_lastCodeError))
                            {
                                unset($this->_lastCodeError);
                            }
                            return $response.$crnl;
                        }
                    }
                }
            }
            $response = $this->makeResponseHTTP($codeHTTP);
            if (isset($this->_lastCodeError))
            {
                unset($this->_lastCodeError);
            }
            return $this->makeResponseHTTP($codeHTTP) . $crnl;
        }
        else
        {
            $this->_lastCodeError = self::_ERROR_NO_PARSING_;
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Make HTTP response's line
     * @params [INT] $codeHTTP                      code HTTP of HTTP line generate.
     * @params success                              [STRING] HTTP line of $codeHTTP
     * @params failure                              [STRING] HTTP line of code 400
     */
    private function                                makeResponseHTTP($codeHTTP)
    {
        if (!is_int($codeHTTP) || !array_key_exists($codeHTTP, self::_RESPONSE_HTTP_))
        {
            $codeHTTP = 400;
        }
        $this->_lastCodeHTTP = $codeHTTP;
        return "HTTP/1.1 {$codeHTTP} ".self::_RESPONSE_HTTP_[$codeHTTP].chr(13).chr(10);
    }


    /* Override protected's methods */
    
    /*
     * @brief                                       Override method use to check header. return HTTP code.
     * @return success                              101 => Valid HTTP
     * @return failure                              HTTP error:
     *                                              \Websocket\Handshake::_HTTP_BAD_REQUEST_        => 'Bad Request',
     *                                              \Websocket\Handshake::_HTTP_FORBIDDEN_          => 'Forbidden',
     *                                              \Websocket\Handshake::_HTTP_METHOD_NOT_ALLOW_   => 'Method Not Allowed',
     *                                              \Websocket\Handshake::_HTTP_UPGRADE_REQUIRED_   => 'Upgrade Required\r\nSec-WebSocketVersion: 13'
     */
    protected function                              checkHeader()
    {
        return self::_VALID_RESPONSE_HTTP_;
    }
    
    /*
     * @brief                                       Override method use to check origin. Return TRUE to continu or false to stop (HTTP \Websocket\Handshake::_HTTP_BAD_REQUEST_).
     * @params [STRING] $origin                     Origin of client's HTTP.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    protected function                              checkOrigin($origin)
    {
        return TRUE;
    }
    
    /*
     * @brief                                       Override method use to check host. Return TRUE to continu or false to stop. (HTTP \Websocket\Handshake::_HTTP_BAD_REQUEST_)
     * @params [STRING] $host                       Host of client's HTTP connection (Server's hostname).
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    protected function                              checkHost($host)
    {
        return TRUE;
    }

    /*
     * @brief                                       get data from last header's data generate by method \Websocket\Handshake::parse();
     * @return success                              [STRING] header data's value || [ARRAY(STRING)] array of header datas' value (if client handshake's header send duplicate variables) || [NULL] All header's array.
     * @return failure                              FALSE
     */
    public function                                 getHeaderData($key = NULL)
    {
        if (isset($this->_header))
        {
            if (is_string($key))
            {
                $key = strtoupper($key);
                foreach ($this->_header as $headerKey => $headerValue)
                {
                    if ($key == $headerKey)
                    {
                        return $headerValue;
                    }
                }
            }
            else if ($key === NULL)
            {
                return $this->_header;
            }
        }
        return FALSE;
    }
    
    /* PROTECTED FUNCTION */
    
    /*
     * @brief                                       Using Sec-WebSocket-Key header's varible, _MAGIC_STRING_ and _MAGIC_HASH_ to generate 'Sec-WebSocket-Accept' variable handshake server's response.
     * @return success                              [STRING] 'Sec-WebSocket-Accept' variable handshake server's response
     * @return failure                              FALSE
     */
    protected function                              generateSecWebSocketAccept()
    {
        if (($key = $this->getHeaderData('Sec-WebSocket-Key')) !== FALSE)
        {
            return (is_string($key) & !empty($key)) ? (base64_encode(hash(static::_MAGIC_HASH_, $key . static::_MAGIC_STRING_, true))) : FALSE;
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Push variable on header's array.
     * @params [STRING] $key                        Key of header's variable. This key will be convert to uppercase's string.
     * @params [STRING] $value                      Value of header's variable.
     */
    protected function                              pushHeader($key, $value)
    {
        if (is_string($key) && !empty($key) && is_string($value) && !empty($value))
        {
            $key = strtoupper($key);
            if (($currentValue = $this->getHeaderData($key)) === FALSE)
            {
                $this->_header[$key] = $value;
            }
            else if (is_string($currentValue))
            {
                $this->_header[$key] = array($currentValue, $value);
            }
            else if (is_array($currentValue))
            {
                $this->_header[$key][] = $value;
            }
        }
    }

    /* PRIVATE FUNCTION */
    
    /*
     * @brief                                       Parse HTTP first line of handshake client's header to generate HTTP variable on header datas' array.
     * @params [STRING] $buffer                     Buffer with parse string's contents.
     * @params [INT] $lenBuffer                     Size of buffer.
     * @params [STRING] $crnl                       Endline's pathern. (CRNL = Carriage return + New Line = "\r\n")
     * @return success                              [INT] offset where handshake client header's variables begin. Use in parameter of method \Websocket\Handshake::parseHeaderLines();
     * @return failure                              FALSE
     */
    private function                                parseHeaderHTTP($buffer, $lenBuffer, $crnl)
    {
        $headerHTTP = array();
        $indexHTTP = 0;
        $changeIndexHTTP = FALSE;
        $headerHTTP[$indexHTTP] = '';
        $i = 0;
        while ($i < $lenBuffer && !$this->isEndLine($buffer, $lenBuffer, $i, $crnl))
        {
            if ($buffer[$i] != ' ')
            {
                $changeIndexHTTP = TRUE;
                $headerHTTP[$indexHTTP] .= $buffer[$i];
            }
            else if ($changeIndexHTTP)
            {
                $changeIndexHTTP = FALSE;
                ++$indexHTTP;
                $headerHTTP[$indexHTTP] = '';
            }
            ++$i;
        }
        if ($headerHTTP[0] != 'GET')
        {
            $this->_lastCodeError = self::_ERROR_HTTP_TYPE_;
            return FALSE;
        }
        $nbHeaderData = count($headerHTTP);
        if ($nbHeaderData == 3 || $nbHeaderData == 4)
        {
            $http = explode('/', $headerHTTP[2]);
            $type = '';
            $version = '';
            if ((count($http) == 2))
            {
                $type = $http[0];
                $version = $http[1];
            }
            else if (array_key_exists(4, $headerHTTP))
            {
                $type = $headerHTTP[2];
                $version = $headerHTTP[3];
            }
            else
            {
                $this->_lastCodeError = self::_ERROR_PARSING_HTTP_LINE_;
                return FALSE;
            }
            if (floatval($version) < 1.1)
            {
                $this->_lastCodeError = self::_ERROR_HTTP_OBSOLETE_VERSION_;
                return FALSE;
            }
            $this->_header['HTTP-RESOURCE'] = $headerHTTP[1];
            $this->_header['HTTP-TYPE'] = $type;
            $this->_header['HTTP-VERSION'] = $version;
            return $i + 2;
        }
        $this->_lastCodeError = self::_ERROR_PARSING_HTTP_LINE_;
        return FALSE;
    }
    
    /*
     * @brief                                       Parse handshake client's header to generate header data's array. Pathern's line check on this function is "KEY: VALUE\r\n";
     * @params [STRING] $buffer                     Buffer with parse string's contents.
     * @params [INT] $lenBuffer                     Size of buffer.
     * @params [INT] $start                         Offset of buffer's content where we need to begin parse.
     * @params [STRING] $crnl                       Endline's pathern. (CRNL = Carriage return + New Line = "\r\n")
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    private function                                parseHeaderLines($buffer, $lenBuffer, $start, $crnl)
    {
        $key = '';
        $value = '';
        $fillKey = TRUE;
        $startWrite = FALSE;
        for ($i = $start; $i < $lenBuffer; ++$i)
        {
            if ($this->isEndLine($buffer, $lenBuffer, $i, $crnl))
            {
                $this->pushHeader($key, $value);
                ++$i;
                $key = '';
                $value = '';
                $fillKey = TRUE;
            }
            else if ($buffer[$i] != ' ' || $startWrite)
            {
                if ($fillKey)
                {
                    if ($buffer[$i] == ':')
                    {
                        $fillKey = FALSE;
                        $startWrite = FALSE;
                    }
                    else
                    {
                        $startWrite = TRUE;
                        $key .= $buffer[$i];
                    }
                }
                else
                {
                    $startWrite = TRUE;
                    $value .= $buffer[$i];
                }
            }
        }
    }
    
    /*
     * @brief                                       Check if character index of $index's offset of buffer size $lenBuffer is endline. endline meant to meet CRNL (Carriage return + New Line = "\r\n").
     * @params [STRING] $buffer                     Buffer with parse string's contents.
     * @params [INT] $lenBuffer                     Size of buffer.
     * @params [INT] $index                         Offset of buffer's content where we need to check endline's pathern.
     * @params [STRING] $crnl                       Endline's pathern. (CRNL = Carriage return + New Line = "\r\n")
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    private function                                isEndLine($buffer, $lenBuffer, $index, $crnl)
    {
        $nextIndex = $index + 1;
        return ($nextIndex < $lenBuffer && ($buffer[$index] == $crnl[0] && $buffer[$nextIndex] == $crnl[1]));
    }
}
