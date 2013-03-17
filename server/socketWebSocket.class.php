<?php
/**
 * WebSocket extension class of phpWebSockets
 *
 * @author Moritz Wutz <moritzwutz@gmail.com>
 * @version 0.1
 * @package phpWebSockets
 */

class socketWebSocket extends socket
{
	private $clients = array();
	private $handshakes = array();

	public function __construct()
	{
		parent::__construct();

		$this->run();
	}

	/**
	 * Runs the while loop, wait for connections and handle them
	 */
	private function run()
	{
		while(true)
		{
			# because socket_select gets the sockets it should watch from $changed_sockets and writes the changed sockets to that array
			# we have to copy the allsocket array to keep our connected sockets list
			$changed_sockets = $this->allsockets;

			# blocks execution until data is received from any socket
			$write=NULL;
			$exceptions=NULL;
			
			# On success socket_select() returns the number of socket resources contained in the modified arrays, 
			# which may be zero if the timeout expires before anything interesting happens. 
			$num_sockets = socket_select($changed_sockets,$write,$exceptions,0);
			# if ($num_sockets != 0){ $this->console('num_socket return value is ' . $num_sockets); }
			
			
			# foreach changed socket...
			foreach( $changed_sockets as $socket )
			{
				# master socket changed means there is a new socket request
				if( $socket==$this->master )
				{
					# if accepting new socket fails
					# new socket connection error
					if( ($client=socket_accept($this->master)) < 0 )
					{
						console('socket_accept() failed: reason: ' . socket_strerror(socket_last_error($client)));
						continue;
					}
					# if it is successful push the client to the allsockets array
					# a new websocket connection is built
					else
					{
						$this->allsockets[] = $client;

						# using array key from allsockets array, is that ok?
						# i want to avoid the often array_search calls	
						$socket_index = array_search($client,$this->allsockets);
						$this->console('The new scoket_index value is ' . (string)$socket_index . " <== array_search");
												
						$this->clients[$socket_index] = new stdClass;
						$this->clients[$socket_index]->socket_id = $client;
						$this->console($client . ' CONNECTED!');
						
					}
				}
				# client socket has sent data
				else
				{
					$socket_index = array_search($socket,$this->allsockets);

					# the client status changed, but theres no data ---> disconnect
					$bytes = @socket_recv($socket,$buffer,2048,0);
					
					# if received disconnect signal
					if( $bytes === 0 )
					{
						$this->disconnected($socket);
					}
					# there is data to be read
					# if client sends data 
					else
					{
						# this is a new connection, no handshake yet
						if( !isset($this->handshakes[$socket_index]) )
						{
							$this->do_handshake($buffer,$socket,$socket_index);
							
							// $this->send($socket,("handshake done! This client is ".(string)$socket));
						}
						# handshake already done, read data
						else
						{
						$action = $buffer;
							//$action = substr($buffer,1,$bytes-2); // remove chr(0) and chr(255)
							# $this->console("<{$action}"); # this mean data send in from client to server. 
							# the content of the data is action
								
								if( method_exists('socketWebSocketTrigger',$action) )
								{
									# such kind of message only response to sender client
									$this->send($socket,socketWebSocketTrigger::$action());
									//$f = array_search(" Resource id #9", $this->allsockets);
									//$this->send($f ,"veyr important!");
									
								}
								
								else if ($action == 'listall'){
									$this->send($socket,"List All available Websocket connections below:");
									foreach( $this->allsockets as $s )
									{
										$this->send($socket,(string)$s);
									}
								}
								
								else if ($action == 'howmany'){
									$this->console("Total # connections: " . count($this->allsockets));
								}
								
								else
								{
								$this->console('[iMessage]' . $action);
									# this kind of message broadcast to all.
									foreach( $this->allsockets as $s )
									{
										$abc = array_search($s,$this->allsockets);
						
										if ($abc != 0){
										# cuz when abc=0, means the top of element of the list, this gotta be the server instance
										# we do not write to server, unless client wants to, OK.
										
										# list out all open socket here in the console
										# removed for testing purposed
										# $this->console((string)$s);
										
											if ($s == $socket){
												# send to myself as it is
												# $this->send($s,"{$action}");
											}
											else{
												# used to attach sender ID and send to all clients
												# removed for testing purposes
												
												$this->send($s,"{$action}");
												
											}
										}
									}
								}
							
						}
					}
				}
			}
		}
	}

	/**
	 * Manage the handshake procedure
	 *
	 * @param string $buffer The received stream to init the handshake
	 * @param socket $socket The socket from which the data came
	 * @param int $socket_index The socket index in the allsockets array
	 */
	private function do_handshake($buffer,$socket,$socket_index)
	{
		$this->console('Requesting handshake...');

		list($resource,$host,$origin) = $this->getheaders($buffer);

		$this->console('Handshaking...');

		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
				"Upgrade: WebSocket\r\n" .
				"Connection: Upgrade\r\n" .
				"WebSocket-Origin: {$origin}\r\n" .
				"WebSocket-Location: ws://{$host}{$resource}\r\n\r\n" . chr(0);

		$this->handshakes[$socket_index] = true;

		socket_write($socket,$upgrade,strlen($upgrade));

		$this->console('Done handshaking...');
	}

	/**
	 * Extends the socket class send method to send WebSocket messages
	 *
	 * @param socket $client The socket to which we send data
	 * @param string $msg  The message we send
	 */
	protected function send($client,$msg)
	{
		# $this->console(">{$msg}");
		parent::send($client,chr(0).$msg.chr(255));
	}

	/**
	 * Disconnects a socket an delete all related data
	 *
	 * @param socket $socket The socket to disconnect
	 */
	private function disconnected($socket)
	{
		$index = array_search($socket, $this->allsockets);
		if( $index >= 0 )
		{
			unset($this->allsockets[$index]);
			unset($this->clients[$index]);
			unset($this->handshakes[$index]);
		}

		socket_close($socket);
		$this->console($socket." dissconnected!");
	}

	/**
	 * Parse the handshake header from the client
	 *
	 * @param string $req
	 * @return array resource,host,origin
	 */
	private function getheaders($req)
	{
		$req  = substr($req,4); /* RegEx kill babies */
		$res  = substr($req,0,strpos($req," HTTP"));
		$req  = substr($req,strpos($req,"Host:")+6);
		$host = substr($req,0,strpos($req,"\r\n"));
		$req  = substr($req,strpos($req,"Origin:")+8);
		$ori  = substr($req,0,strpos($req,"\r\n"));

		return array($res,$host,$ori);
	}

	/**
	 * Extends the parent console method.
	 * For now we just set another type.
	 *
	 * @param string $msg
	 * @param string $type
	 */
	protected function console($msg,$type='WebSocket')
	{
		parent::console($msg,$type);
	}
}

?>