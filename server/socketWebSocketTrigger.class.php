<?php
/**
 * WebSocketTrigger class of phpWebSockets
 *
 * In this class you can define a method for every recieved command.
 * The returned string of the function will be send to the client.
 */

class socketWebSocketTrigger extends socketWebSocket
{
	function hello()
	{
		$a = 'hello, how are you?';
		return $a;
	}
	
	function what()
	{
		$a = 'can you come again?';
		return $a;
	}
	
	function hi()
	{
		$a = 'Hello Ran Wei';
		return $a;
	}	
	
	function name()
	{
		$a = 'my name is Web Socket';
		return $a;
	}	
	
	function today()
	{
		return date('Y-m-d');
	}	
	
	function age()
	{
		return "I am 10 years old";
	}			

}

?>