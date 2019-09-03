<?php

namespace                                           App\Websocket;

require_once                                        __DIR__ . '/../User.php';

class                                               WebsocketUser extends \Websocket\User
{
    static private                                  $_copy = array(); // Associate array where key is true ID and value is array of copy.
    private                                         $_trueID; // true ID is user's id from database. method instanciate make copy ID for server.
    
    public function                                 __construct($socket)
    {
        parent::__construct($socket);
        $this->setBundleClassName('Handshake', '\App\Websocket\WebsocketHandshake');
    }
    
    /*
    ** @brief                                       check if user is instanciate.
    */
    public function                                 isInstanciate()
    {
        return (isset($this->_trueID));
    }
    
    /*
     * @brief                                       instanciate user, set trueID = ID on database. The copy ID will be ID of websocket User.
     *                                              All copy ID will be store on static $_copy array to associate all true id to server id.
     *                                              This permit to have multi connection from one user. (Useful if you want chat on all page).
     */
    public function                                 instanciate($id, $token)
    {
        if (!$this->isInstanciate() && // Check if user is already instanciate
            (is_int($id) || (is_string($id) && ctype_digit($id))) && is_string($token))// Check params
        {
            /*
            ** Create on your database, users with id and token to submit on server.
            */
            $this->_trueID = intval($id);
            if (!array_key_exists($this->_trueID, self::$_copy))
            {
                $copyID = "{$this->_trueID}-0";
                self::$_copy[$this->_trueID] = array($copyID);
            }
            else
            {
                $i = 0;
                do
                {
                    $copyID = "{$this->_trueID}-{$i}";
                    ++$i;
                } while (in_array($copyID, self::$_copy[$this->_trueID]));
                self::$_copy[$this->_trueID][] = $copyID;
            }
            return $copyID;
        }
        return FALSE;
    }

    public function                                 getTrueID()
    {
        return $this->_trueID;
    }
    
    /*
    ** Next methods permit to manage array's copy.
    */
    
    public function                                 popFromCopy()
    {
        if ($this->isInstanciate() && array_key_exists($this->_trueID, self::$_copy))
        {
            $copyID = $this->getID();
            foreach (self::$_copy[$this->_trueID] as $key => $copy)
            {
                if ($copyID == $copy)
                {
                    unset(self::$_copy[$this->_trueID][$key]);
                    break;
                }
            }
        }
    }
    
    public function                                 getAllCopy()
    {
        return ($this->isInstanciate()) ? self::getAllCopyByTrueID($this->_trueID) : FALSE;
    }
    
    static public function                          getAllCopyByTrueID($trueID)
    {
        if (array_key_exists($trueID, self::$_copy))
        {
            $allCopy = self::$_copy[$trueID];
            return $allCopy;
        }
        return FALSE;
    }
}
