# PHP web socket server

Non blocking socket server on PHP.

The server is intended for windows, so no forks, multithreading, etc..

Non blocking cycle is built around stream_select, which is used not only for reading from sockets, but for writing also.

The server supports web socket protocol version 13 and raw sockets. 

The type of connection (web socket or raw socket connection) is defined by the first message, client sent after connecting. If the first message contains "Upgrade: websocket" header, then the connection is to be web socket. Otherwise, the raw connection is assumed.

The script also provides creating of client web-socket and/or raw-socket connections via the same stream_select cycle. So, it is possible to organise some kind of web-socket proxy or "web-to-raw" (and vice-versa) socket converter.

The script should be run under CLI. It is convenient to configure script to run as service using appropriate utility (i. g. NSSM - http://nssm.cc)

## API

There are four event functions, which are called in case of appropriate event: onConnect, onMessage, onError, and onClose. And also the function for sending message via opened socket connection and the function for openinig a new client connection either web socket or raw socket.

Event functions are placed at the end of script. For now they just puts some info into STDOUT. They might be rewritten to make them  do something useful.

**void onConnect(array $inf, resource $con)** - the function is called when new connection is established.

  Arguments:
  
  **$inf** - array with some information regarding the connection. Consist of the array depends of connection type ($inf['Protocol']) 
  
  for the websocket server connection (when new client is connected):
        
          Array (
                  [method] => GET
                  [url] => /
                  [Host] => xxxxxxxxxx
                  [User-Agent] => xxxxxxxxx
                  [Accept] => text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
                  [Accept-Language] => xxxxxxxxxxxx
                  [Accept-Encoding] => gzip, deflate
                  [Sec-WebSocket-Version] => 13
                  [Origin] => http://xxxxxxxxxx
                  [Sec-WebSocket-Extensions] => permessage-deflate
                  [Sec-WebSocket-Key] => xxxxxxxxxxxxxxxxxxxxxxx
                  [Connection] => keep-alive, Upgrade
                  [Upgrade] => websocket
                  [Protocol] => websocket server
          )
        
  for the websocket client connection (when connected to the server):
        
          Array (
                  [Upgrade] => websocket
                  [Connection] => Upgrade
                  [Sec-WebSocket-Accept] => Wwi5YhN+AEPMSbD/K0A4DFneKlU=
                  [Protocol] => websocket client
          )

  for the raw socket server connection (when new client is connected):
        
          Array (
              [Protocol] => raw
          )
          
            Note: in case of raw socket the fanction is called not when the socket accepts connection, 
                  but when the first data arrives (just before calling onMessage).
        
  for the raw socket client connection the function is not called.
        
  **$con** - resource representing the connection.

**onMessage(array $msg, resource $con)** - the function is called when new portion of data is arrived (in case of websocket connectioon the portion of data will be frame).
  
  Arguments:
  
  **$msg** - array. Structure of array in case of websocket connection:
    Array(
      [final] => true or false //flag of the last frame in case of big message.
      [type] => text or binary //type of frame 
            //("close","ping", and "pong" frames are processed by the server and not call the onMessage function)
      [data] => xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx //data received.
    )
    In case of raw socket connection the array contain obly the [data] key.
    
   **$con** - resource representing the connection.

**onError(string $error_message, resource $con)**

**onClose(resource $con)**

**sendMessage(array or string $msg, resource $con)**

**openClientConnection(string $target,$protocol='raw',$url='/',$host='127.0.0.1:8000',$orig='http://localhost')**
