<?php

namespace                                           App\Websocket;

require_once                                        __DIR__ . '/../AServer.php';
require_once                                        __DIR__ . '/../Log/Log.php';

use                                                 App\Management\MessagesManagement;
use                                                 App\Management\UsersManagement;

class                                               WebsocketServer extends \Websocket\AServer
{
    public function                                 __construct()
    {
        parent::__construct();
        $this->setBundleClassName('User', '\App\Websocket\WebsocketUser');
        $this->setBundleClassName('Log', '\Websocket\Log');
        $this->setOption('LOG', __DIR__ . '/' . \Websocket\Log::_DEFAULT_LOG_FILENAME_);
    }
    
    /*
     * @brief                                       Callback event read message.
     * @params [\Websocket\User] $user              User who calling event.
     */
    protected function                              onReadMessage(&$user, $message)
    {
        if ((($cmd = json_decode($message, TRUE)) !== NULL) && array_key_exists('command', $cmd) && is_string($cmd['command']) &&
            (array_key_exists('params', $cmd)))
        {
            if ($user->isInstanciate()) // if instanciate, you can make commande. Else try to instanciate.
            {
                if ((($modelUser = $user->getModelSession()) !== NULL) && (boolval($modelUser->is_banned) === FALSE))
                {
                    if (method_exists($this, $cmd['command']))
                    {
                        $command = $cmd['command'];
                        $this->$command($user, $command, $cmd['params']);
                    }
                    $user->deleteModelSession();
                }
                else
                {
                    $this->destroySession($user);
                }
            }
            else if (($cmd['command'] != 'instantiateUserSession') || (!is_array ($cmd['params'])) ||
                    (!array_key_exists('id', $cmd['params'])) || (!array_key_exists('token', $cmd['params'])) ||
                    (!$this->instantiateUserSession($user, $cmd['params']['id'], $cmd['params']['token'])))
            {
                $user->close();
            }
        }
    }
    
    protected function                              onDeleteClient($user)
    {
        $user->popFromCopy();
    }
    
    /* COMMANDS */
    
    /*
     * @brief                                       instantiate user's session. This the first command must be use by all user.
     * @params [\Websocket\WebsocketUser] $user     User try to instanciate.
     * @params [int|string] $id                     id of user
     */
    private function                                instantiateUserSession(&$user, $id, $token)
    {
        if (is_string($copyID = $user->instanciate($id, $token)))
        {
            $this->changeUserID($user, $copyID);
            return TRUE;
        }
        return FALSE;
    }
    
    /*
     * @brief                                       Send message to chat if user isn't muted.
     * @params [\Websocket\WebsocketUser] $user     User try to send message.
     * @params [string] $cmd                        Command use.
     * @params [array] $message                     message to send on chat.
     */
    private function                                sendChatMessage(&$user, $command, $message)
    {
        $status = 'failure';
        if (is_string($message) && (($modelUser = $user->getModelSession()) !== NULL) &&
            (boolval($modelUser->is_muted_chat) === FALSE))
        {
            $messageTrim = trim($message);
            if ((($lenMessage = strlen($messageTrim)) > 0)  && ($lenMessage <= 255))
            {
                $status = 'success';
                $jsonMessage = array('command' => $command, 'login' => $modelUser->login, 'isAdmin' => boolval($modelUser->is_admin), 'message' => $messageTrim, 'status' => 'newMessage');
                $userID = $user->getID();
                $this->foreachUsers(function(&$eachUser, $eachMessage) use ($userID) // \Websocket\AServer::foreachUsers loop all server's users.
                {
                    if ($eachUser->isInstanciate() && $eachUser->getID() != $userID) // Check if user is instanciate and different to current user.
                    {
                        $this->writeToUser($eachUser, $eachMessage); // Write message on user. you must use \Websocket\AServer::writeToUser() method to close user. Current user can also use write user's method.
                    }
                }, json_encode($jsonMessage));
                // Create message on database.
            }
        }
        $user->write(json_encode(array('command' => $command, 'status' => $status))); // Send state of message to current user.
    }
    
    /*
     * @brief                                       Destroy all user's session from server. This methods send close message to all copy.
     * @params [\Websocket\WebsocketUser] $user     User try to destroy him session.
     * @params [string] $cmd                        Command use.
     * @params [NULL] $params                       Not use, just follow the command's prototype.
     */
    private function                                destroySession(&$user, $command = NULL, $params = NULL)
    {
        if (($allCopy = $user->getAllCopy()) !== FALSE) // Get all copy of current user
        {
            foreach ($allCopy as $copy)
            {
                if (($instanceUser = &$this->getUserByID($copy)) !== FALSE) // Get user by ID on server
                {
                    $this->closeToUser($instanceUser); // Send close message. Except for current user, you must use \Websocket\AServer::closeToUser() method to close user. Current user can also use close user's method.
                }
            }
        }
    }
    
    /*
     * @brief                                       Destroy all user's session from server. This methods send close message to all copy.
     * @params [\Websocket\WebsocketUser] $user     User try to destroy him session.
     * @params [string] $cmd                        Command use.
     * @params [NULL] $params                       Not use, just follow the command's prototype.
     */
    private function                                stopServer(&$user, $command ,$params)
    {
        /* You can check on database if user is admin before doing this action */
        $this->stop();
    }
    
    
}