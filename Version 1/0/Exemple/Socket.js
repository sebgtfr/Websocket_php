
/*
** Socket Object
** 
** This object is a singleton who have some useful methods.
**
** The first one is Socket::sendCommand($name, $param = null); send JSON object like {"command": $name,"params": $params}.
** This methods stack command and try to send it. This methods make connection to server. if it lose, it try again every each second. (Futur update is to set number of try).
** If methods sending failed, onClose's socket methods is use. You can make method Socket::deleteCommand($name) method to delete the first
** method find on stack with name = $name or Socket::deleteAllCommand($name) doing same as Socket::deleteCommand($name) but delete all.
**
** The second on is Socket::pushOnClose($callback) permit to add callback on onclose socket's event. (You can delete all command to exemple).
**
** The last one is Socket::pushCommand($name, $event) permit to use $event callback all time the socket receive JSON packet
** with param "command" = $name. $event must be prototype "function (responseJSON)";
**
** EXEMPLE :
**
** var                                              g_socket = Socket.getInstance();
**
** g_socket.pushCommand('sendChatMessage', function (response) // response is JSON object like {"command":"sendChatMessage", "message": $message}
** {
**      #make function to write response.message on chatbox.
** });
**
** ButtonChatSendMessage.onclick = function()
** {
**      g_socket.sendCommand('sendChatMessage', $message); // Send JSON string like "{{"command":"sendChatMessage", "params": $message}}"
** };
**
** g_socket.pushOnClose(function ()
** {
**      g_socket.deleteAllCommand('sendChatMessage'); // Delete all chat message on stack's command.
** };
*/

Socket._instance = null;

function                                            Socket(host, port, id, token)
{
    if (typeof(host) === 'string' && typeof(port) === 'number' &&
        typeof(id) === 'number' && typeof(token) === 'string')
    {
        this._host = host;
        this._port = port;
        this._id = id;
        this._token = token;
        this._stackCommand = [];
        this._stackTimeout = null;
        this._socket;
        this._command = [];
        this._onClose = [];
        this.connection();
    }
    return this;
}

Socket.getInstance = function (host, port, id, token)
{
    if (Socket._instance === null)
    {
        Socket._instance = new Socket(host, port, id, token);
    }
    return Socket._instance;
};

Socket.prototype.connection = function ()
{
    if (!this.isTryConnection())
    {
        this._socket = new WebSocket("ws://" + this._host + ":" + this._port + "/southLanWebsocketServer");
        this._socket.onopen = function(e)
        {
            var                                     socket = Socket.getInstance();
            var                                     dataCommand = socket.makeCommand('instantiateUserSession', {'id': socket._id, 'token': socket._token});

            socket.send(dataCommand);
        };

        this._socket.onmessage = function(e)
        {
            var                                             response = JSON.parse(e.data);
            var                                             socket = Socket.getInstance();
            
            if (typeof(response.command) === 'string')
            {
                for (var i = 0; i < socket._command.length; ++i)
                {
                    if (socket._command[i]._name === response.command)
                    {
                        if (socket._command[i]._onmessage !== null)
                        {
                            socket._command[i]._onmessage(response);
                        }
                        i = socket._command.length;
                    }
                }
            }
        };

        this._socket.onclose = function(e)
        {
            for (var i = 0; i < Socket._instance._onClose.length; ++i)
            {
                Socket._instance._onClose[i]();
            }
        };
    }
};

Socket.prototype.isConnected = function()
{
    return ((typeof(this._socket) === 'object') && (this._socket !== null) && (typeof(this._socket.readyState) === 'number') && (this._socket.readyState === 1));
};

Socket.prototype.isTryConnection = function()
{
    return ((typeof(this._socket) === 'object') && (this._socket !== null) && (typeof(this._socket.readyState) === 'number') && (this._socket.readyState === 0));
};

Socket.prototype.close = function()
{
    if (this.isConnected())
    {
        this._socket.close();
    }
};

Socket.prototype.makeCommand = function (command, params)
{
    if (typeof(params) === 'undefined')
    {
        params = null;
    }
    if (typeof(command) === 'string')
    {
        let                                         objCommand = {};

        objCommand.command = command;
        objCommand.params = params;
        return JSON.stringify(objCommand);
    }
    return null;
};

Socket.prototype.send = function (data)
{
    this._socket.send(data);
};

Socket.prototype.unStackCommand = function ()
{    
    if (!this.isConnected())
    {
        this.connection();
        if (this._stackTimeout === null)
        {
            this._stackTimeout = setTimeout(function()
            {
                Socket._instance._stackTimeout = null;
                Socket._instance.unStackCommand();
            }, 1000);
        }
    }
    else if ((this._stackTimeout === null) && (this._stackCommand.length > 0))
    {
        this.send(this._stackCommand.shift());
        this.unStackCommand();
    }
};

Socket.prototype.sendCommand = function (command, params)
{
    var                                             dataCommand = this.makeCommand(command, params);
    
    if (dataCommand !== null)
    {
        this._stackCommand.push(dataCommand);
        this.unStackCommand();
    }
};

Socket.prototype.pushCommand = function(name, event)
{
    var                                             command = new Socket.Command(name, event);
    
    if (command !== null)
    {
        var                                         alreadyExist = false;
        
        for (var i = 0; alreadyExist === false && i < this._command.length; ++i)
        {
            if (this._command[i]._name === name)
            {
                alreadyExist = true;
            }
        }
        if (alreadyExist === false)
        {
            this._command.push(command);
        }
    }
};

Socket.prototype.deleteCommand = function (name)
{
    if (typeof(name) === 'string')
    {        
        for (var i = 0; i < this._stackCommand.length; ++i)
        {
            var                                     command = JSON.parse(this._stackCommand[i]);
            
            if (command.command === name)
            {
                this._stackCommand.splice(i, 1);
                break ;
            }
        }
    }
};

Socket.prototype.deleteAllCommand = function (name)
{
    if (typeof(name) === 'string')
    {
        for (var i = 0; i < this._stackCommand.length; ++i)
        {
            var                                     command = JSON.parse(this._stackCommand[i]);
            
            if (command.command === name)
            {
                this._stackCommand.splice(i, 1);
                --i;
            }
        }
    }
};

Socket.prototype.pushOnClose = function (callbackOnClose)
{
    if (typeof(callbackOnClose) === 'function')
    {
        this._onClose.push(callbackOnClose);
    }
};

/* ********** Socket::Command ********** */

Socket.Command = function (name, onmessage)
{
    if (typeof(name) === 'string')
    {
        this._name = name;
        this._onmessage = (typeof(onmessage) === 'function') ? onmessage : null;
        return this;
    }
    return null;
};