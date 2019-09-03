<?php

require 'WebsocketServer.php';

$server = new WebsocketServer();

if ($server->connection($request->server('SERVER_ADDR'), 8080, 20))
{
	$server->run();
	$server->disconnection();
	echo 'Server die proprely';
}