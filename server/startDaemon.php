<?php
/**
 * Main Script of phpWebSockets
 *
 * Run this file in a shell or windows cmd to start the socket server. 
 * 
 */

ob_implicit_flush(true);

require 'socket.class.php';
require 'socketWebSocket.class.php';
require 'socketWebSocketTrigger.class.php';

$WebSocket = new socketWebSocket('localhost',8000);

?>