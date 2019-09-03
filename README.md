################################## WEBSOCKET ###################################

VERSION: 1.0

COMPATIBLE BROWSERS:
    - Chrome
    - Firefox
    - IE 11
    - Microsoft Edge

          ######################### MODULES ##########################

I) AServer

DESCRIPTION:
    - Abstract class using to manage infinite loop and clients' states (select,
      new client, delete client)

VIRTUAL PURE METHODS:

    /*
     * @brief                                       Callback read message's event.
     * @params [\Websocket\User] $user              User who receive message.
     * @params [STRING] $user                       Message receive on user's socket.
     * @description                                 When server read socket, it make message's packets. onReadMessage is call to each packet.
     */
    protected function                              onReadMessage(\Websocket\User &$user, string message);

VIRTUAL METHODS:

    /*
     * @brief                                       Callback new client's event.
     * @params [\Websocket\User] $user              New user.
     *
     * @forbidden methods                           \Websocket\AServer::changeUserID use \Websocket\User::setID;
     */
    protected function                              onNewClient(\Websocket\User &$user);
    
    /*
     * @brief                                       Callback event before user change ID.
     * @params [\Websocket\User] $user              User who want to have newID.
     * @params [newID] $newID                       NewID
     * @params [\Websocket\User|NULL] $oldUser      Current user with ID = newID or NULL.
     * @return                                      [BOOL::FALSE] stop event change ID. [MIXED] => continu event change ID.
     *
     * @forbidden methods                           \Websocket\AServer::changeUserID use \Websocket\User::setID;
     *                                              SetID to $user is useless because return TRUE set $newID to $user. You can set only $oldUser ID (Update need for futur's versions)
     */
    protected function                              onBeforeChangeID(\Websocket\User &$user, [int || string] $newID, \Websocket\User &$oldUser);
    
    /*
     * @brief                                       Callback event delete client. It event is doing after client's disconnection.
     * @params [\Websocket\User] $user              User we will delete.
     */
    protected function                              onDeleteClient(\Websocket\User $user)

    /*
     * @brief                                       Callback end read message's event.
     * @params [\Websocket\User] $user              User who calling event.
     * @description                                 (CF: onReadMessage description). This method is use after all message's packets were read.
     */
    protected function                              onEndReadMessage(\Websocket\User &$user);

CONFIGURATION METHODS:

    This methods must be use on inherit's constructor of before using \Websocket\AServer::connection;

    /*
     * @brief                                       Set option.
     * @params [MIXED] $value                       value of option.
     * @description                                 Set server's options among the following list :
     *                                              LOG => [STRING] Log file's name. This option can be use only after set bundle Log. CF: \Websocket\AServer::setBundleClassName
     *                                              STDOUT => [BOOL] state of active log's message on console or browser.
     *                                              STDERR => [BOOL] state of active error log's message on console or browser.
     *                                              PROTOCOL => [STRING] name of socket's protocol (cf: const \Websocket\AServer::_PROTOCOLS_).
     *                                                          Allowing protocols are "TCP" and "UDP".
     */
    public function                                 setOption($option, $value);

    /*
     * @brief                                       Change bundle class.
     * @params [STRING] $bundle                     Bundle to change among bundle list (ref: attributs)
     * @params [STRING] $className                  Name of new bundle class. It must inherit to default bundle.
     * @description                                 Set server's bundle among the following list :
     *                                              USER => All User's class inheriting from \Websocket\User.
     *                                              LOG => All Log's class inheriting from \Websocket\ILog.
     */
    public function                                 setBundleClassName(string $bundle, string $className);

MANAGEMENT SERVER CONNECTION'S METHODS:

    /*
     * @brief                                       Open connection of server's socket
     * @params [STRING] $address                    Hostname of connection.
     * @params [INT] $port                          Port of connection
     * @params [INT] $backlog                       Number of clients can be put on connection's stack.
     * @params [BOOL] $reuseaddr                    Reuse address socket's configuration. It permit to resuse hostname and port without close running older's server.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 connection(string $address = 'localhost', int $port = 0, int $backlog = 0, bool $reuseaddr = FALSE);

    /*
     * @brief                                       Close connection of server's socket and users.
     */
    public function                                 disconnection(void);

    /*
     * @brief                                       Loop's server
     * @params [INT] $tv_sec                        Timeout of select waiting in second.
     * @params [INT] $tv_usec                       Timeout of select waiting in microsecond.
     */
    public function                                 run(int $tv_sec = 0, int $tv_usec = 0);

    /*
     * @brief                                       Stop server's loop.
     */
    public function                                 stop(void);

USEFUL PROTECTED'S METHODS:

    /*
     * @brief                                       Print informations. Each string's parameters is new line.
     * @params ...                                  Messages to print on console if stdout's option is active.
     */
    protected function                              stdout(...);

    /*
     * @brief                                       Print informations. Each string's parameters is new line.
     * @params ...                                  Messages to print on console if stderr's option is active.
     */
    protected function                              stderr(...);

    /*
     * @brief                                       Print message on log's file if Log is define. All messages from stdout and stderr is writting.
     * @params $msg                                 Messages print on log's file.
     */
    protected function                              printLog(array<string> $aMessages);

    /*
     * @brief                                       Get next available key ID of user's arrays.
     * @return                                      [INT] Available key ID.
     */ 
    protected function                              getNextPrimaryKeyID(void);

    /*
     * @brief                                       Set or change user ID on server's arrays and User's objects.
     * @params [\Websocket\User] $user              User who change ID.
     * @params [INT|STRING] $newID                  New ID of user.
     */
    protected function                              changeUserID(\Websocket\User &$user, [string || int] $newID);

    /*
     * @brief                                       Use ID to get user's instance.
     * @params [INT] $id                            ID of user.
     * @return success                              [\Websocket\User &] Reference of user's instance.
     * @return failure                              false.
     */  
    protected function                              &getUserByID([string || int] $id);

    /*
     * @brief                                       using callback's function or method for each user.
     * @params [STRING|ARRAY] $callback             Compatible variable of "is_callable" function. Prototype is "void  callback(\Websocket\User &$user, mixed $param);"
     * @params [MIXED] $param                       Param you want to send on callback.
     */
    protected function                              foreachUsers($callback, $param = NULL);

    /* THE BOTH METHODS MUST BE USE IF YOU WANT TO WRITE OR CLOSE ANOTHER USER THAN CURRENT USER, YOU MUSTN'T USE \Websocket\User's METHODS */

    /*
     * @brief                                       Write message on write client's buffer and force him on write sockets server's array.
     * @params [\Websocket\User] $user              User who want to send message.
     * @params [STRING] $message                    Message to send.
     * @warning                                     It's must use this method to write on client's buffer.
     */
    protected function                              writeToUser(\Websocket\User &$user, string $message);

    /*
     * @brief                                       close user and force him on write sockets server's array.
     * @params [\Websocket\User] $user              User we want to close.
     */
    protected function                              closeToUser(\Websocket\User &$user);

II) User

DESCRIPTION:
    - Class using to manage user's socket, read buffer, write buffer and websocket's protocol.

CONFIGURATION METHODS:

    This methods must be use on inherit's constructor or on \Websocket\AServer::onNewClient event.

    /*
     * @brief                                       Change bundle class.
     * @params [STRING] $bundle                     Bundle to change among bundle list (ref: attributs)
     * @params [STRING] $className                  Name of new bundle class. It must inherit to default bundle.
     * @description                                 Set server's bundle among the following list :
     *                                              HANDSHAKE => All Handshake's class inheriting from \Websocket\Handshake.
     */
    public function                                 setBundleClassName(string $bundle, string $className);

    /*
     * @brief                                       Allow lenght of read's buffer.
     * @params [INT] $lenReadBuffer                 Lenght of read's buffer allow.
     */
    public function                                 setLengthReadBuffer(unsigned int $lenReadBuffer);

    /*
     * @brief                                       Set User's ID
     * @params [INT|STRING] $id                     User's ID
     *
     * @warning                                     This methods muse be use only on following server's methods :
     *                                              - \Websocket\AServer::onNewClient;
     *                                              - \Websocket\AServer::onBeforeChangeID;
     */
    public function                                 setID([string || int] $id);

GET DATAS METHODS:

    /*
     * @brief                                       Get User's ID
     * @return                                      User's ID
     */
    public function                                 getID(void);

    /*
     * @brief                                       Check if User is connected.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    public function                                 isConnected(void);

    /*
     * @brief                                       Get handshake HTTP's header.
     * @params [STRING] $keyHeaderHTTP              Key of header's value.
     * @return success                              [ARRAY] HTTP's header. [STRING] value of HTTP-s header at key $keyHeaderHTTP
     * @return failure                              FALSE
     */
    public function                                 getHeaderHTTP(string $keyHeaderHTTP = NULL);

MANAGEMENT CLIENT CONNECTION'S METHODS:

WARNING:
    - On Server event, You must use \Websocket\User's metods only for current user if event don't forbid it.
      Else, you must use equivalent methods of the server.


    /*
     * @brief                                       Set if current's state is connected, Close's state, push close message and send it before disconnection. Else, set disconnected state.
     */
    public function                                 close(void);

    /*
     * @brief                                       Set Disconnected state and close socket. It is recommended to use \Websocket\User::close to disconnect user.
     */
    public function                                 disconnection(void);

    /*
     * @brief                                       Push message on write's buffer. If handshake is done, all messages will be encode.
     * @params [STRING] $msg                        Message to send.
     * @params [STRING] $opcodeName                 Name of opcode to insert on message header's frame.
     * @return success                              TRUE
     * @return failure                              FALSE (bad opcode or user is disconnected)
     * @description                                 Opcode should be on the following list :
     *                                              - CONTINUOUS => Concatenate of last message. The last message mustn't be sending.
     *                                              - TEXT => Write message.
     *                                              - BINARY => Write message on binary. (Management not sure (need test)).
     *                                              - CLOSE => Using \Websocket\User::close method.
     *                                              - PING => Not manage on current's version.
     *                                              - PONG => auto reply of 'PING' request.
     */
    public function                                 write(string $msg, string $opcodeName = 'text');

III) Handshake

DESCRIPTION:
    - Class using to manage handshake between server and client.

HTTP CODE:

    - \Websocket\Handshake::_VALID_RESPONSE_HTTP_   =>  'Switching Protocols',
    - \Websocket\Handshake::_HTTP_BAD_REQUEST_      =>  'Bad Request',
    - \Websocket\Handshake::_HTTP_FORBIDDEN_        =>  'Forbidden',
    - \Websocket\Handshake::_HTTP_METHOD_NOT_ALLOW_ =>  'Method Not Allowed',
    - \Websocket\Handshake::_HTTP_UPGRADE_REQUIRED_ =>  'Upgrade Required\r\nSec-WebSocketVersion: 13'

OVERWRITE METHODS:

    /*
     * @brief                                       Method use to check header. Return HTTP Code to generate. (this method is first check method use).
     * @return success                              \Websocket\Handshake::_VALID_RESPONSE_HTTP_ = 101 => Valid HTTP
     * @return failure                              HTTP error. cf: HTTP CODE.
     */
    protected function                              checkHeader(void);
    
    /*
     * @brief                                       Method use to check origin. Return TRUE to continu or false to stop (HTTP \Websocket\Handshake::_HTTP_BAD_REQUEST_).
     * @params [STRING] $origin                     Origin of client's HTTP.
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    protected function                              checkOrigin(string $origin);
    
    /*
     * @brief                                       Method use to check host. Return TRUE to continu or false to stop. (HTTP \Websocket\Handshake::_HTTP_BAD_REQUEST_)
     * @params [STRING] $host                       Host of client's HTTP connection (Server's hostname).
     * @return success                              TRUE
     * @return failure                              FALSE
     */
    protected function                              checkHost(string $host);

    /*
     * @brief                                       Generate another variables on handshake's response.
     * @return                                      Array where key is the variable's name and value is its value.
     * @prototype
     * [array('varName' => 'varValue')]             addVarsToResponseHeader(void);
     */

     Default valid header's response is :

        "HTTP/1.1 101 Switching Protocols\r\n"
        "Upgrade: WebSocket\r\n"
        "Connection: Upgrade\r\n"
        "Sec-WebSocket-Accept: {SEC_WEB_SOCKET_ACCEPT}\r\n" // Websocket protocol's key.
        "Sec-WebSocket-Origin: {ORIGIN}\r\n"
        "Sec-WebSocket-Location: ws://{HOST}{RESOURCE}\r\n"
        "\r\n";

IV) Log

DESCRIPTION:
    - Class using to manage log's file.

OVERWRITE METHODS:

    /*
     * @brief                                       Set log's filename.
     * @params [STRING] $filename                   Log's filename.
     */
    public function                                 setFilename(string $filename);

    /*
     * @brief                                       Set log's filename.
     * @params [ARRAY(STRING)] $aMessages           Array of messages, each messages is line.
     */
    public function                                 write(array<string> $aMessages);
    
    
################################################################################