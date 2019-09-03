<?php

namespace                                           Websocket;

include_once                                        __DIR__ . '/Handshake.php';

/*
 * @Description                                     AServer object, it's abstract class of websocket's server.
 *
 * 
 * @author                                          Sébastien Le Maire
 * @Creation                                        17/10/2017
 * @Version                                         1.0
 * @Update                                          17/10/2017
*/
class                                               User
{
    /* CONST */
    const                                           _DEFAULT_LENGHT_READ_BUFFER_ = 2048;
    
    /* STATE */
    const                                           _INITIALISATION_ = 1; /* User is create */
    const                                           _HANDSHAKE_ = 2; /* User get Handshake's client */
    const                                           _SEND_HANDSHAKE_RESPONSE_ = 3; /* User make response to client */
    const                                           _CONNECTED_ = 4; /* User has send response */
    const                                           _CLOSE_ = 5; /* User send close message before disconnection */
    const                                           _DISCONNECTED_ = 6; /* User is disconnect */
    
    /* OPCODE */
    const                                           _OPCODE_CONTINUOUS_ = 0x0; /* Continuous message */
    const                                           _OPCODE_TEXT_ = 0x1; /* Text message */
    const                                           _OPCODE_BINARY_ = 0x2; /* Binary message */
    const                                           _OPCODE_CLOSE_ = 0x8; /* Close connection from client */
    const                                           _OPCODE_PING_ = 0x9; /* Ping (need to send Pong) */
    const                                           _OPCODE_PONG_ = 0xA; /* Pong */
    
    /* ATTRIBUTS */
    private                                         $_id = FALSE;
    private                                         $_socket;
    private                                         $_state;
    
    /* READ ATTRIBUTS */
    private                                         $_lenReadBuffer = self::_DEFAULT_LENGHT_READ_BUFFER_;
    private                                         $_bufferRead = '';
    private                                         $_bufferReadDecode = '';
    private                                         $_lenLastMessage = 0;
    
    /* WRITE ATTRIBUTS */
    private                                         $_bufferWrite = '';
    
    /* BUNDLE PARENTS' CLASSES */
    const                                           _PARENT_HANDSHAKE_CLASSNAME_ = '\Websocket\Handshake';
    
    /* BUNDLE SERVER'S CLASSES */
    private                                         $_handshakeClassName = self::_PARENT_HANDSHAKE_CLASSNAME_;
    
    /* HANDSHAKE DATAS */
    private                                         $_handshake;
    private                                         $_handshakeBuffer = '';
    
    /* METHODS */
    
    /*
     * @brief                                       Constructor of User
     * @params [RESSOUCE] $socket                   User's socket.
     */
    public function                                 __construct($socket)
    {
        $this->_socket = $socket;
        $this->_state = self::_INITIALISATION_;
    }
    
    /* CONFIGURATION'S METHOD */
    
    /*
     * @brief                                       Change bundle class.
     * @params [STRING] $bundle                     Bundle to change among bundle list (ref: attributs)
     * @params [STRING] $className                  Name of new bundle class. It must inherit to default bundle.
     */
    public function                                 setBundleClassName($bundle, $className)
    {
        if (is_string($bundle) && is_string($className))
        {
            $constName = 'self::_PARENT_' . strtoupper($bundle) . '_CLASSNAME_';
            if (defined($constName))
            {
                $constValue = constant($constName);
                $className = ($className[0] == '\\') ? $className : "\\$className";
                if (($className == $constValue) || is_subclass_of($className, $constValue))
                {
                    $attribut = '_' . strtolower($bundle) . 'ClassName';
                    $this->$attribut = $className;
                }
            }
        }
    }
    
    /*
     * @brief                                       Allow lenght of read's buffer.
     * @params [INT] $lenReadBuffer                 Lenght of read's buffer allow.
     */
    public function                                 setLengthReadBuffer($lenReadBuffer)
    {
        if (is_int($lenReadBuffer) && $lenReadBuffer > 0)
        {
            $this->_lenReadBuffer = $lenReadBuffer;
        }
    }
    
    /* ACCESSOR */
    
    /*
     * @brief                                       Get User's ID
     * @return                                      User's ID
     */
    public function                                 getID()
    {
        return $this->_id;
    }
    
    /*
     * @brief                                       Set User's ID
     * @params [INT|STRING] $id                     User's ID
     */
    public function                                 setID($id)
    {
        if (is_int($id) || is_string($id))
        {
            $this->_id = $id;
        }
    }
    
    /*
     * @brief                                       Get User's socket
     * @return                                      User's socket
     */
    public function                                 getSocket()
    {
        return isset($this->_socket) ? $this->_socket : FALSE;
    }
    
    /*
     * @brief                                       Get User's state
     * @return                                      User's state
     */
    public function                                 getState()
    {
        return $this->_state;
    }
    
    /*
     * @brief                                       Get User's ID
     * @params [STRING] $stateName                  Name of state
     * @return success                              Constant's value
     * @return failure                              FALSE
     */
    public function                                 getConstantState($stateName)
    {
        if (is_string($stateName) && !empty($stateName))
        {
            $stateName = ('self::_' . strtoupper($stateName) . '_');
            if (defined($stateName))
            {
                return constant($stateName);
            }
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Get User's ID
     * @params [STRING] $stateName                  Name of Opcode.
     * @return success                              Constant's value
     * @return failure                              FALSE
     */
    public function                                 getOpcode($opcodeName)
    {
        if (is_string($opcodeName) && !empty($opcodeName))
        {
            $opcodeName = ('self::_OPCODE_' . strtoupper($opcodeName) . '_');
            if (defined($opcodeName))
            {
                return constant($opcodeName);
            }
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Check if User is connected.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 isConnected()
    {
        return ($this->_state != self::_DISCONNECTED_);
    }
    
    /*
     * @brief                                       Check if user is writting
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 isWritting()
    {
        return (!empty($this->_bufferWrite));
    }
    
    /* CONNECTION'S METHODS */
    
    /*
     * @brief                                       Set if current's state is connected, Close's state, push close message. Else, set disconnected state.
     */
    public function                                 close()
    {
        if ($this->_state == self::_CONNECTED_)
        {
            $this->_bufferRead = '';
            $this->write('', 'close'); // Write close
            $this->_state = self::_CLOSE_;
        }
        else
        {
            $this->disconnection();
        }
    }
    
    /*
     * @brief                                       Set Disconnected state and close socket. 
     */
    public function                                 disconnection()
    {
        if ($this->isConnected())
        {
            socket_close($this->_socket);
            $this->_state = self::_DISCONNECTED_;
        }
    }
    
    /* READ SOCKET'S METHODS */
    
    /*
     * @brief                                       Read socket.
     * @return success                              [ARRAY] => array of messages.
     * @return failure                              FALSE => User disconnected or failure of socket_recv forced server to disconnect user.
     */
    public function                                 read()
    {
        if ($this->isConnected() && (($bytes = @socket_recv($this->_socket, $buffer, $this->_lenReadBuffer, 0)) !== FALSE))
        {
            $readPacket = array();
            if ($this->_state <= self::_HANDSHAKE_)
            {
                $this->_state = self::_HANDSHAKE_;
                $readPacket[] = $buffer;
            }
            else
            {
                $this->_bufferRead .= $buffer;
                if ($this->_state == self::_CONNECTED_)
                {
                    $splitLoop = TRUE;
                    $lenMessage = strlen($this->_bufferRead);
                    while ($splitLoop)
                    {
                        if (($decodedMessage = $this->decodeMessage($this->_bufferRead)) !== FALSE)
                        {
                            switch ($decodedMessage['opcode'])
                            {
                                case self::_OPCODE_CONTINUOUS_:
                                case self::_OPCODE_TEXT_:
                                case self::_OPCODE_BINARY_:
                                    $this->_bufferReadDecode .= $decodedMessage['message'];
                                break;
                                case self::_OPCODE_CLOSE_:
                                    $this->disconnection();
                                    $splitLoop = FALSE;
                                break;
                                case self::_OPCODE_PING_:
                                    $this->write('', 'pong');
                                break;
                                case self::_OPCODE_PONG_:

                                break;
                            }
                            if (($decodedMessage['end'] || $decodedMessage['opcode'] == self::_OPCODE_CLOSE_) && !empty($this->_bufferReadDecode))
                            {
                                $readPacket[] = $this->_bufferReadDecode;
                                $this->_bufferReadDecode = '';
                            }
                            $this->_bufferRead = ($decodedMessage['package-len'] < $lenMessage) ? substr($this->_bufferRead, $decodedMessage['package-len']) : '';
                        }
                        else
                        {
                            $splitLoop = FALSE;
                        }
                    }
                }
            }
            return $readPacket;
        }
        else if (is_bool($bytes))
        {
            $this->disconnection();
        }
        return FALSE;
    }
    
    /* WRITE SOCKET'S METHODS */
    
    /*
     * @brief                                       Push message on write's buffer. If handshake is done, all messages will be encode.
     * @params [STRING] $msg                        Message to send.
     * @params [STRING] $opcodeName                 Name of opcode to insert on message header's frame.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 write($msg, $opcodeName = 'text')
    {
        if (((($opcode = $this->getOpcode($opcodeName)) !== FALSE) || ($this->_state == self::_HANDSHAKE_)) && $this->_state < self::_CLOSE_)
        {
            if ($this->_state == self::_HANDSHAKE_)
            {
                $this->_bufferWrite .= $msg;
            }
            else if ($opcode != self::_OPCODE_PONG_)
            {
                $this->_bufferWrite .= $this->encodeMessage($msg, $opcode);
            }
            else
            {
                $currentLenLastMessage = $this->_lenLastMessage;
                $encodedMsg = $this->encodeMessage($msg, $opcode);
                $this->_lenLastMessage = $currentLenLastMessage + strlen($encodedMsg);
                $this->_bufferWrite = $encodedMsg . $this->_bufferWrite;
            }
            return TRUE;
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Send write buffer's content on socket.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 flush()
    {
        if ($this->isWritting() && $this->isConnected() && $this->_state >= self::_SEND_HANDSHAKE_RESPONSE_)
        {
            $len = strlen($this->_bufferWrite);
            if (($bytes = socket_write($this->_socket, $this->_bufferWrite, $len)) === FALSE)
            {
                return FALSE;
            }
            if ($bytes < $len)
            {
                $this->_bufferWrite = substr($this->_bufferWrite, $bytes);
                if ($len - $bytes < $this->_lenLastMessage)
                {
                    $this->_lenLastMessage = 0;
                }
            }
            else if ($this->_state < self::_CONNECTED_)
            {
                if ($this->_handshake->getLastCodeHTTP() != 101)
                {
                    $this->disconnection();
                }
                else
                {
                    $this->_state = self::_CONNECTED_;
                    $this->_bufferWrite = '';
                    $this->_lenLastMessage = 0;
                }
            }
            else if ($this->_state == self::_CLOSE_)
            {
                $this->disconnection();
            }
            else
            {
                $this->_bufferWrite = '';
                $this->_lenLastMessage = 0;
            }
        }
        return TRUE;
    }
    
    /* PROTOCOL WEBSOCKET */
    
    /*
     * @brief                                       decode message
     * @params [STRING] $msg                        msg to decode.
     * @return success                              [ARRAY] All data from decoded messages.
     * @return failure                              FALSE
     */
    private function                                decodeMessage($msg)
    {
        $lenEncodeMessage = strlen($msg);
        $retFailure = FALSE;
        if ($lenEncodeMessage > 2)
        {
            $firstByte = ord($msg[0]);
            $secondByte = ord($msg[1]);        
            $header = array
            (
                'end' => chr($firstByte & 128), // MASK 1000.0000 (First byte of the left)
                'rsv1' => $firstByte & 64, // MASK 0100.0000 (Second byte of the left)
                'rsv2' => $firstByte & 32, // MASK 0010.0000 (Third byte of the left)
                'rsv3' => $firstByte & 16, // MASK 0001.0000 (Fouth byte of the left)
                'opcode' => $firstByte & 15, // MASK 0000.1111 (Four bytes of the right)
                'mask' => $secondByte & 128, // MASK 1000.0000 (First byte of the left)
                'payload-len' => $secondByte & 127, // MASK 0111.1111 (seven bytes of the right)
                'masking-key' => '', // If MASK exist, Set mask key.
                'metadatas-header-len' => 0, // Lenght of metadatas' header.
                'package-len' => 0, // Lenght of all message's package.
                'message-len' => 0, // Lenght of message.
                'message' => '' // Decode data.
            );

            switch ($header['payload-len'])
            {
                case 126: /* If 126, lenght is storage on 16 next bits */
                    if ($lenEncodeMessage < 4)
                    {
                        return $retFailure;
                    }
                    $header['message-len'] = (ord($msg[2]) * 256) + ord($msg[3]);
                    $beginMaskKey = 4;
                break;
                case 127: /* If 127, lenght is storage on 64 next bits */
                    if ($lenEncodeMessage < 10)
                    {
                        return $retFailure;
                    }
                    $header['message-len'] = ord($msg[2]) * 4294967296 * 16777216 // 2^56
                                        + ord($msg[3]) * 4294967296 * 65536 // 2^48
                                        + ord($msg[4]) * 4294967296 * 256 // 2^40
                                        + ord($msg[5]) * 4294967296 // 2^32
                                        + ord($msg[6]) * 16777216 // 2^24
                                        + ord($msg[7]) * 65536 // 2^16
                                        + ord($msg[8]) * 256 // 2^8
                                        + ord($msg[9]);
                    $beginMaskKey = 10;
                break;
                default: /* else, lenght is storage on payloadLen */
                    $header['message-len'] = $header['payload-len'];
                    $beginMaskKey = 2;
                break;
            }
            if ($header['mask'])
            {
                if ($lenEncodeMessage < $beginMaskKey + 4)
                {
                    return $retFailure;
                }
                $header['masking-key'] = $msg[$beginMaskKey] . $msg[$beginMaskKey + 1] . $msg[$beginMaskKey + 2] . $msg[$beginMaskKey + 3];
                $header['metadatas-header-len'] = $beginMaskKey + 4;
            }
            else
            {
                $header['metadatas-header-len'] = $beginMaskKey;
            }
            $endMessage = $header['message-len'] + $header['metadatas-header-len'];
            if ($lenEncodeMessage < $endMessage)
            {
                return $retFailure;
            }
            $iMask = 0;
            for ($i = $header['metadatas-header-len']; $i < $endMessage; ++$i)
            {
                $header['message'] .= ($header['mask']) ? ($msg[$i] ^ $header['masking-key'][$iMask]) : $msg[$i];
                $iMask = ($iMask + 1) % 4;
            }
            $header['package-len'] = $header['metadatas-header-len'] + $header['message-len'];
            return $header;
        }
        return $retFailure;
    }
    
    static private function                         convertIntToBytesString($int, $nbRec)
    {
        $bytes = chr($int & 0xFF);
        if ($int > 0)
        {
            $int = intval(floor($int / 256));
        }
        if ($nbRec > 0)
        {
            $bytes = self::convertIntToBytesString($int, $nbRec - 1) . $bytes;
        }
        return $bytes;
    }
    
    /*
     * @brief                                       encode message.
     * @params [STRING] $msg                        Message to encode.
     * @params [INT] $opcode                        opcode to write.
     * @return                                      [STRING] encoded package's message.
     */
    private function                                encodeMessage($msg, $opcode)
    {
        $lenMessage = strlen($msg);
        $firstByte = 128 + $opcode;
        if ($opcode == self::_OPCODE_CONTINUOUS_)
        {
            $len = strlen($this->_bufferWrite);
            if ($len >= $this->_lenLastMessage)
            {
                $offset = $len - $this->_lenLastMessage;
                $this->_bufferWrite[$offset] = chr(ord($this->_bufferWrite[$offset]) - 128);
            }
            else
            {
                $firstByte += self::_OPCODE_TEXT_;
            }
        }
        $payload = '';
        if ($lenMessage < 126)
        {
            $secondByte = $lenMessage;
        }
        else if ($lenMessage < 65536)
        {
            $secondByte = 126;
            $payloadLen = 2;
        }
        else
        {
            $secondByte = 127;
            $payloadLen = 8;
        }
        if (isset($payloadLen))
        {
            $payload = self::convertIntToBytesString($lenMessage, $payloadLen - 1);
            $lenMessage += $payloadLen;
        }
        $this->_lenLastMessage = 2 + $lenMessage;
        return chr($firstByte) . chr($secondByte) . $payload . $msg;
    }
    
    /*
     * @brief                                       Parse handshake from client and make response.
     * @params                                      [STRING] handshake's message from client.
     * @return success                              TRUE
     * @return failure                              FALSE [Use method "handshakeIsIncomplet" to know if server must continu to wait response}
     */
    public function                                 handshake($message)
    {
        $this->_handshake = new $this->_handshakeClassName();
        $message = $this->_handshakeBuffer . $message;
        if ($this->_handshake->parse($message) !== false)
        {
            if (($response = $this->_handshake->getResponse($message)) !== FALSE)
            {
                $this->write($response);
                unset($this->_handshakeBuffer);
                $this->_state = self::_SEND_HANDSHAKE_RESPONSE_;
                return TRUE;
            }
        }
        else if ($this->handshakeIsIncomplet())
        {
            $this->_handshakeBuffer = $message;
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Check if parsing's error is error 'MISSING_END'.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 handshakeIsIncomplet()
    {
        return ($this->_handshake->getLastCodeError() == $this->_handshake->getContantError('MISSING_END'));
    }
    
    /*
     * @brief                                       Get handshake error.
     * @return success                              [STRING] error's message.
     * @return failure                              FALSE
     */
    public function                                 getHandshakeError()
    {
        return isset($this->_handshake) ? $this->_handshake->getError() : FALSE;
    }
    
    /*
     * @brief                                       Get handshake HTTP's header.
     * @params [STRING] $keyHeaderHTTP              Key of header's value.
     * @return success                              [ARRAY] HTTP's header. [STRING] value of HTTP-s header at key $keyHeaderHTTP
     * @return failure                              FALSE
     */
    public function                                 getHeaderHTTP($keyHeaderHTTP = NULL)
    {
        return ($this->handshakeIsInit()) ? $this->_handshake->getHeaderData($keyHeaderHTTP) : FALSE;
    }
    
}
