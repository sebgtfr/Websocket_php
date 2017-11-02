<?php

namespace                                           Websocket;

require_once                                        __DIR__ . '/ILog.php';
require_once                                        __DIR__ . '/User.php';

/*
 * @Description                                     AServer object, it's abstract class of websocket's server.
 *
 * 
 * @author                                          S�bastien Le Maire
 * @Creation                                        16/10/2017
 * @Version                                         1.0
 * @Update                                          16/10/2017
*/
abstract class                                      AServer
{
    /* STATIC PRIVATE */
    static private                                  $_serverAlreadyConfig = false;

    /* CONST*/
    const                                           _MAX_PORT_ = 65535; /* Hightest port programs can use. */
    const                                           _DEFAULT_TICKS_ = 1;
    const                                           _DEFAULT_PROTOCOL_ = 'TCP';
    const                                           _PROTOCOLS_ = array
    (
        'TCP'                                       => SOL_TCP,
        'UDP'                                       => SOL_UDP
    );
    
    /* BUNDLE PARENTS' CLASSES */
    const                                           _PARENT_USER_CLASSNAME_ = '\Websocket\User';
    const                                           _PARENT_LOG_CLASSNAME_ = '\Websocket\ILog';
    
    /* BUNDLE SERVER'S CLASSES */
    private                                         $_userClassName = self::_PARENT_USER_CLASSNAME_;
    private                                         $_logClassName;
    
    /* PROTOCOLS */
    private                                         $_protocol = self::_DEFAULT_PROTOCOL_;
    
    /* PRINT INFORMATIONS' SERVER */
    private                                         $_log;
    private                                         $_stdout = false;
    private                                         $_stderr = false;
    
    /* ATTRIBUTS' SERVER */
    private                                         $_socket; /* Socket of server */
    private                                         $_running; /* Running's state of server */
    private                                         $_primaryKeyID; /* primary key use to generate user's indexes */
    private                                         $_lastChangeID = NULL; /* Last ID change use on changeID recursive's method */
    private                                         $_oldChangeID = NULL; /* Save of old ID use for print */
    private                                         $_intervalSec = NULL;
    private                                         $_intervalUsec = 0;

    /* USERS' ATTRIBUTS */
    private                                         $_users; /* Array of users */
    private                                         $_aSocketRead; /* Array of read sockets */
    private                                         $_aSocketWrite; /* Array of write sockets */
    
    /* METHODS */
    
    /*
     * @brief                                       Constructor of Server.
     */
    public function                                 __construct()
    {
        if (self::$_serverAlreadyConfig === FALSE)
        {
            /* PHP's configuration */
            error_reporting(E_ALL); /* Very Strict error */
            ini_set('default_socket_timeout', 15); /* Timeout socket 15s */
            set_time_limit(0); /* Delete script process's timeout (unlimited time) */
            ob_implicit_flush(true); /* Send system's buffer to browser */
            self::$_serverAlreadyConfig = TRUE;
        }
    }
    
    /*
     * @brief                                       Destructor of Server.
     */
    public function                                 __destruct()
    {
        $this->disconnection();
    }
    
    /* CONFIGURATION'S METHOD */
    
    /*
     * @brief                                       Set option.
     * @params [MIXED] $value                       value of option.
     * @description                                 Set server's options among the following list :
     *                                              LOG => [STRING] Log file's name. This option can be use only after set bundle Log. CF: self::setBundleClassName.
     *                                              STDOUT => [BOOL] state of active log's message on console or browser.
     *                                              STDERR => [BOOL] state of active error log's message on console or browser.
     *                                              PROTOCOL => [STRING] name of socket's protocol (cf: const \Websocket\AServer::_PROTOCOLS_).
     */
    public function                                 setOption($option, $value)
    {
        if (is_string($option))
        {
            switch (strtoupper($option))
            {
                case 'LOG':
                    if (is_string($value) && isset($this->_log))
                    {
                        $this->_log->setFilename($value);
                    }
                break;
                case 'STDOUT':
                case 'STDERR':
                    if (is_bool($value))
                    {
                        $attributName = '_' . strtolower($option);
                        $this->$attributName = $value;
                    }
                break;
                case 'PROTOCOL':
                    $protocol = strtoupper($value);
                    if (array_key_exists($protocol, self::_PROTOCOLS_))
                    {
                        $this->_protocol = $protocol;
                    }
                break;
                default:
                break;
            }
        }
    }
    
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
                    if ($attribut == '_logClassName')
                    {
                        $this->_log = new $this->_logClassName();
                    }
                }
            }
        }
    }
    
    /* SOCKET'S METHODS */
    
    /*
     * @brief                                       Open connection of server's socket
     * @params [STRING] $address                    Hostname of connection.
     * @params [INT] $port                          Port of connection
     * @params [INT] $backlog                       Number of clients can be put on connection's stack.
     * @params [BOOL] $reuseaddr                    Reuse address socket's configuration. It permit to resuse hostname and port without close running older's server.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 connection($address = 'localhost', $port = 0, $backlog = 0, $reuseaddr = FALSE)
    {
        $ret = true;
        if (!isset($this->_socket))
        {
            if (($this->_socket = @socket_create(AF_INET, SOCK_STREAM, self::_PROTOCOLS_[$this->_protocol])) !== false)
            {
                if (socket_set_option($this->_socket, SOL_SOCKET, SO_REUSEADDR, $reuseaddr) !== false)
                {
                    if (is_string($address) && !empty($address))
                    {
                        $port = (is_int($port) && $port > 0 && $port <= self::_MAX_PORT_) ? $port : 0;
                        if (@socket_bind($this->_socket, $address, $port) !== false)
                        {
                            if (@socket_listen($this->_socket, (is_int($backlog) && $backlog > 0) ? $backlog : 0) !== false)
                            {
                                $this->stdout('Server Started : ' . date('Y-m-d H:i:s'), "Listening on   : {$address}:{$port}");
                                $this->_primaryKeyID = 0;
                                $this->_users = array();
                                $this->_aSocketRead = array();
                                $this->_aSocketWrite = array();
                            }
                            else
                            {
                                self::stderr('Listening of socket failed : ' . socket_strerror(socket_last_error($this->_socket)));
                                $ret = false;
                            }
                        }
                        else
                        {
                            $this->stderr('Binding of address:port and socket failed : ' . socket_strerror(socket_last_error($this->_socket)));
                            $ret = false;
                        }
                    }
                    else
                    {
                        $this->stderr('Missing host.');
                        $ret = false;
                    }
                }
                else
                {
                    $this->stderr('Set reuse address\'s option failed : ' . socket_strerror(socket_last_error($this->_socket)));
                    $ret = false;
                }
                if ($ret === false)
                {
                    socket_close($this->_socket);
                    unset($this->_socket);
                }
            }
            else
            {
                $this->stderr('Socket\'s creation failed : ' . socket_strerror(socket_last_error($this->_socket)));
                $ret = false;
            }
        }
        else
        {
            $this->stderr('Socket already create.');
            $ret = false;
        }
        return $ret;
    }
    
    /*
     * @brief                                       Close connection of server's socket and users.
     */
    public function                                 disconnection()
    {
        if (isset($this->_socket))
        {
            if (is_array($this->_users))
            {
                foreach ($this->_users as $user)
                {
                    $this->deleteClient($user);
                }
                unset($this->_users);
            }
            if (isset($this->_aSocketRead))
            {
                unset($this->_aSocketRead);
            }
            if (isset($this->_aSocketWrite))
            {
                unset($this->_aSocketWrite);
            }
            socket_close($this->_socket);
            unset($this->_socket);
            $this->_running = FALSE;
        }
    }
    
    /*
     * @brief                                       Loop's server
     * @params [INT] $ticks                         Number of ticks in second before call \Websocket\AServer::onTicks method.
     * @params [INT] $tv_sec                        Timeout of select waiting in second.
     * @params [INT] $tv_usec                       Timeout of select waiting in microsecond.
     */
    public function                                 run($tv_sec = NULL, $tv_usec = 0)
    {
        $this->_running = isset($this->_socket);
        $this->setInterval($tv_sec, $tv_usec);
        $tv_sec = $this->_intervalSec;
        $tv_usec = $this->_intervalUsec;
        while ($this->_running)
        {
            if ($this->_intervalSec !== NULL)
            {
                // Now et old
            }
            $aSocketRead = $this->_aSocketRead;
            $aSocketRead[] = $this->_socket;
            $aSocketWrite = $this->_aSocketWrite;
            $this->_aSocketWrite = array();
            if (socket_select($aSocketRead, $aSocketWrite, $except, $tv_sec, $tv_usec) !== FALSE)
            {
                foreach ($aSocketWrite as $socketWrite)
                {
                    if (($user = &$this->getUserBySocket($socketWrite)) !== FALSE)
                    {
                        if (($user->flush()) === false)
                        {
                            $this->stderr("Send buffer to client \"{$user->getID()}\" failed : " . socket_strerror(socket_last_error($user->getSocket())));
                            $user->disconnection();
                        }
                        if (!$user->isConnected())
                        {
                            $this->deleteClient($user);
                        }
                        else if ($user->isWritting())
                        {
                            $this->_aSocketWrite[$user->getID()] = $user->getSocket();
                        }
                    }
                    else
                    {
                        $this->forceDeleteSocketOnServer($socketWrite);
                    }
                }
                foreach ($aSocketRead as $socketRead)
                {
                    if ($socketRead == $this->_socket)
                    {
                        $this->newClient();
                    }
                    else if (($user = &$this->getUserBySocket($socketRead)) !== FALSE)
                    {
                        if (($messages = $user->read()) !== FALSE)
                        {
                            if (!empty($messages))
                            {
                                if (!$this->handshake($user, $messages[0]))
                                {
                                    foreach ($messages as $message)
                                    {
                                        $this->onReadMessage($user, $message);
                                    }
                                    $this->onEndReadMessage($user);
                                }
                            }
                        }
                        else if (!$user->isConnected())
                        {
                            $socket = $user->getSocket();
                            $this->stderr("Client \"{$user->getID()}\" failed to read : " . (($socket !== FALSE) ? socket_strerror(socket_last_error($socket)) : ''));
                        }
                        if (!$user->isConnected())
                        {
                            $this->deleteClient($user);
                        }
                        else if ($user->isWritting())
                        {
                            $this->_aSocketWrite[$user->getID()] = $user->getSocket();
                        }
                    }
                    else
                    {
                        $this->forceDeleteSocketOnServer($socketRead);
                    }
                }
            }
            else
            {
                $this->_running = FALSE;
            }
        }
    }
    
    /* Run loop methods */
    
    /*
     * @brief                                       New client's event.
     */
    private function                                newClient()
    {
        if (($socket = @socket_accept($this->_socket)) !== FALSE)
        {
            if (@socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1) !== FALSE)
            {
                $user = new $this->_userClassName($socket);
                $this->onNewClient($user);
                if ($user->isConnected())
                {
                    $usetID = $user->getID();
                    $this->changeUserID($user, ($usetID !== FALSE) ? $usetID : $this->getNextPrimaryKeyID());
                    $this->stdout('Client "' . $user->getID() . '" is connected.');
                }
            }
            else
            {
                $this->stderr('Set Keep Alive on client\'s socket failed : ' . socket_strerror(socket_last_error()));
                @socket_close($socket);
            }
        }
        else
        {
            $this->stderr('Connection of socket failed : ' . socket_strerror(socket_last_error()));
        }
    }
    
    /*
     * @brief                                       Delete client's event.
     * @params [\Websocket\User] $user              User to delete.
     */
    private function                                deleteClient(&$user)
    {
        $this->stdout("Client \"{$user->getID()}\" is disconnected.");
        $user->disconnection();
        $this->popUserOnServerArrays($user);
        $this->onDeleteClient($user);
    }
    
    /*
     * @brief                                       Make handshake's event if user don't have handshake.
     * @params [\Websocket\User] $user              User to make handshake.
     * @params [STRING] $buffer                     buffer to add on handshake's buffer.
     * @return success                              TRUE => handshake haven't send critical error.
     * @return failure                              FALSE => handshake send critical error.
     */
    private function                                handshake(&$user, $buffer)
    {
        if ($user->getState() == $user->getConstantState('HANDSHAKE'))
        {
            if (($ret = $user->handshake($buffer)) === TRUE)
            {
                $this->stdout("Client \"{$user->getID()}\" get client handshake's request.");
            }
            else if (!$user->handshakeIsIncomplet())
            {
                $this->stderr("Client \"{$user->getID()}\" get client handshake's request failed : {$user->getHandshakeError()}");
                $this->deleteClient($user);
            }
            return TRUE;
        }
        return FALSE;
    }
    
    /* Useful management socket method */
    
    /*
     * @brief                                       Get User ID By int or user's instance.
     * @params [\Websocket\User|INT] $userOrUserID  ID of User of User's instance.
     */
    private function                                getUserIdByIntOrUserInstance($userOrUserID)
    {
        if (is_int($userOrUserID) || is_string($userOrUserID))
        {
            return $userOrUserID;
        }
        else if (is_object ($userOrUserID) && is_a($userOrUserID, $this->_userClassName))
        {
            return $userOrUserID->getID();
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Get next available key ID
     * @return                                      Available key ID.
     */ 
    protected function                              getNextPrimaryKeyID()
    {
        while (array_key_exists($this->_primaryKeyID, $this->_users))
        {
            ++$this->_primaryKeyID;
        }
        return $this->_primaryKeyID;
    }
    
    /*
     * @brief                                       push user on server's arrays.
     * @params [\Websocket\User] $user              User to add on server's arrays.
     */
    private function                                pushUserOnServerArrays(&$user)
    {
        if ((($userID = $user->getID()) !== FALSE) && !array_key_exists($userID, $this->_users))
        {
            $this->_users[$userID] = $user;
            $this->_aSocketRead[$userID] = $user->getSocket();
            if ($user->isWritting())
            {
                $this->_aSocketWrite[$userID] = $user->getSocket();
            }
        }
    }
    
    /*
     * @brief                                       pop user on server's arrays.
     * @params [\Websocket\User] $user              User to delete on server's arrays.
     */
    private function                                popUserOnServerArrays($userOrUserID)
    {
        if ((($userID = $this->getUserIdByIntOrUserInstance($userOrUserID)) !== FALSE) && array_key_exists($userID, $this->_users))
        {
            unset($this->_users[$userID]);
            unset($this->_aSocketRead[$userID]);
            if (array_key_exists($userID, $this->_aSocketWrite))
            {
                unset($this->_aSocketWrite[$userID]);
            }
            while ($this->_primaryKeyID > 0 && !array_key_exists($this->_primaryKeyID, $this->_users))
            {
                --$this->_primaryKeyID;
            }
        }
    }
    
    /*
     * @brief                                       Set or change user ID on server's arrays and User's objects.
     * @params [\Websocket\User] $user              User who change ID.
     * @params [INT|STRING] $newID                  New ID of user.
     */
    protected function                              changeUserID(&$user, $newID)
    {
        if (is_int($newID) || is_string($newID) && is_object($user) && is_a($user, $this->_userClassName) && $user !== FALSE)
        {
            $userID = $user->getID();
            $oldUser = (array_key_exists($newID, $this->_users)) ? $this->_users[$newID] : NULL;
            if ($oldUser === NULL || ($oldUser !== NULL && $oldUser->getSocket() != $user->getSocket()))
            {
                if ($this->onBeforeChangeID($user, $newID, $oldUser) !== FALSE)
                {
                    if (($this->_lastChangeID === NULL || $userID != $this->_lastChangeID) && $userID !== FALSE)
                    {
                        $oldID = ($this->_oldChangeID === NULL) ? $userID : $this->_oldChangeID;
                        $this->popUserOnServerArrays($userID);
                        $this->stdout("Client of ID \"{$oldID}\" change to ID \"{$newID}\".");
                        $this->_lastChangeID = $userID;
                        $this->_oldChangeID = $newID;
                    }
                    $this->popUserOnServerArrays($newID);
                    $user->setID($newID);
                    $this->pushUserOnServerArrays($user);
                    if ($oldUser !== NULL)
                    {
                        if ($oldUser->isConnected())
                        {
                            $oldUserID = $oldUser->getID();
                            $this->changeUserID($oldUser, ($oldUserID === $newID) ? (($userID !== FALSE && $userID != $newID) ? $userID : $this->getNextPrimaryKeyID()) : $oldUserID);
                        }
                    }
                }
                else if ($userID === FALSE)
                {
                    $this->changeUserID($user, $this->getNextPrimaryKeyID());
                }
            }
        }
        $this->_lastChangeID = NULL;
        $this->_oldChangeID = NULL;
    }
    
    /*
     * @brief                                       Use ID to get user's instance.
     * @params [INT] $id                            ID of user.
     * @return success                              Instance of user.
     * @return failure                              false.
     */  
    protected function                              &getUserByID($id)
    {
        $ret = FALSE;
        if (array_key_exists($id, $this->_users))
        {
            $ret = &$this->_users[$id];
        }
        return $ret;
    }
    
    /*
     * @brief                                       Use socket to get user's instance.
     * @params [$RESSOURCE] $socket                 Socket's reference of user.
     * @return success                              Instance of user.
     */  
    private function                                &getUserBySocket($socket)
    {
        if (isset($socket))
        {
            foreach ($this->_aSocketRead as $key => $userSocket)
            {
                if ($userSocket == $socket)
                {
                    return $this->_users[$key];
                }
            }
        }
        $ret = FALSE;
        return $ret;
    }
    
    private function                                forceDeleteSocketOnServer($socket)
    {
        $aIdToDelete = array();
        foreach ($this->_aSocketRead as $id => $socketRead)
        {
            if ($socket == $socketRead)
            {
                $aIdToDelete[] = $id;
            }
        }
        foreach ($aIdToDelete as $idToDelete)
        {
            unset($this->_aSocketRead[$idToDelete]);
        }
    }
    
    /*
     * @brief                                       using callback's function or method for each user.
     * @params [STRING|ARRAY] $callback             Compatible variable of "is_callable" function. Prototype is "void  callback(\Websocket\User &$user, mixed $param);"
     * @params [MIXED] $param                       Param you want to send on callback.
     */
    protected function                              foreachUsers($callback, $param = NULL)
    {
        if (is_callable($callback, true, $callable_name))
        {
            foreach ($this->_users as &$user)
            {
                $callable_name($user, $param);
            }
        }
    }
    
    /*
     * @brief                                       Write message on write client's buffer and force him on write sockets server's array.
     * @params [\Websocket\User] $user              User we want to send message.
     * @params [STRING] $message                    Message to send.
     */
    protected function                              writeToUser(&$user, $message)
    {
        if (is_object($user) && is_a($user, $this->_userClassName) && is_string($message) && !empty($message) && $user !== FALSE)
        {
            $user->write($message);
            $this->_aSocketWrite[$user->getID()] = $user->getSocket();
        }
    }
    
    /*
     * @brief                                       close user and force him on write sockets server's array.
     * @params [\Websocket\User] $user              User we want to close.
     */
    protected function                              closeToUser(&$user)
    {
        if (is_object($user) && is_a($user, $this->_userClassName) && $user !== FALSE)
        {
            $user->close();
            $this->_aSocketWrite[$user->getID()] = $user->getSocket();
        }
    }


    /*
     * @brief                                       Stop server's loop.
     */
    public function                                 stop()
    {
        $this->_running = FALSE;
    }
    
    protected function                              setInterval($tv_sec = NULL, $tv_usec = 0)
    {
        if ((is_int($tv_sec) && $tv_sec >= 0) || $tv_sec === NULL)
        {
            $this->_intervalSec = $tv_sec;
        }
        if (is_int($tv_usec) && $tv_usec >= 0)
        {
            $this->_intervalUsec = $tv_usec;
        }
    }


    /* PRINT DATAS' METHODS */
    
    /*
     * @brief                                       Print informations.
     * @params ...                                  Messages to print on console
     */
    protected function                              stdout()
    {
        $argv = func_get_args();
        $this->printMessages($argv, $this->_stdout);
    }
    
    /*
     * @brief                                       Print error.
     * @params ...                                  Messages to print on console
     */
    protected function                              stderr()
    {
        $argv = func_get_args();
        $this->printMessages($argv, $this->_stderr);
    }
    
    /*
     * @brief                                       Print message on log's file.
     * @params [ARRAY<STRING>] $msg                 Messages print on log's file.
     */
    protected function                              printLog($aMessages)
    {
        if (isset($this->_log) && is_array($aMessages))
        {
            $this->_log->write($aMessages);
        }
    }
    
    /*
     * @brief                                       Print informations.
     * @params [&ARRAY(string)] &$aMessages         Reference of array who contain messages to print on console.
     * @params [BOOL] $outputOn                     Boolean allow to print on console.
     */
    private function                                printMessages(&$aMessages, $outputOn)
    {
        if (is_array($aMessages))
        {
            if ($outputOn)
            {
                $crnl = chr(13).chr(10); /* CR (Carriage Return) NL (New Line) = '\r\n' */
                $sMessage = '';
                foreach ($aMessages as &$message)
                {
                    if (is_string($message) && !empty($message))
                    {
                        $sMessage .= "{$message}{$crnl}";
                    }
                }
                echo $sMessage;
            }
            $this->printLog($aMessages);
        }
    }
    
    /* EVENT'S METHODS */
    
    /* PURE VIRTUAL METHODS */
    
    /*
     * @brief                                       Callback event read message.
     * @params [\Websocket\User] $user              User who calling event.
     * @params [STRING] $user                       Message read on socket.
     * @prototype
     * 
     * [VOID]                                       onReadMessage(\Websocket\User $user, string message);
     */
    abstract protected function                     onReadMessage(&$user, $message);
    
    /* VIRTUAL METHODS */
    
    /*
     * @brief                                       Callback event new client.
     * @params [\Websocket\User] $user              User who calling event.
     */
    protected function                              onNewClient(&$user)
    {
        
    }
    
    /*
     * @brief                                       Callback event before user change ID.
     * @params [\Websocket\User] $user              User who want to have newID.
     * @params [newID] $newID                       NewID
     * @params [\Websocket\User|NULL] $oldUser      Current user with ID = newID or NULL.
     * @return                                      [BOOL::FALSE] stop event change ID. [MIXED] => continu event change ID.
     */
    protected function                              onBeforeChangeID(&$user, $newID, &$oldUser)
    {
        return TRUE;
    }
    
    /*
     * @brief                                       Callback event delete client. It event is doing after client's disconnection.
     * @params [\Websocket\User] $user              User who will delete.
     * @prototype
     * 
     * [VOID]                                       onDeleteClient(\Websocket\User $user);
     */
    protected function                              onDeleteClient($user)
    {
        
    }
    
    /*
     * @brief                                       Callback end event read message.
     * @params [\Websocket\User] $user              User who calling event.
     * @prototype
     * 
     * [VOID]                                       onEndReadMessage(\Websocket\User $user);
     */
    protected function                              onEndReadMessage(&$user)
    {
        
    }
    
    /*
     * @brief                                       Callback call each time define with tv_sec and tv_usec;
     *                                              in second. By default, this methods is use each second.
     * @params [\Websocket\User] $user              User who calling event.
     * @prototype
     * 
     * [VOID]                                       onInterval(void);
     */
    protected function                              onInterval()
    {
        
    }
}