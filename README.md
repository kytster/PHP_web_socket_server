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

API functions are placed at the end of script. Event functions, for now, just puts some info into STDOUT. They might be (and should be) rewritten to make them do something useful.

**void onConnect(array $inf, resource $con)** - the function is called when new connection is established.

  Arguments:
  
  _**$inf**_ - array with some information regarding the connection. Consist of the array depends of connection type ($inf['Protocol']) 
  
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
        
  _**$con**_ - resource, representing the connection.

**void onMessage(array $msg, resource $con)** - the function is called when new portion of data is arrived (in case of websocket connectioon the portion of data will be frame).
  
  Arguments:
  
  _**$msg**_ - array. Structure of array in case of websocket connection:
  
         Array(
               [final] => true or false //flag of the last frame in case of big message.
               [type] => text or binary //type of frame ("close","ping", and "pong" frames are processed by
                                   // the server and not call the onMessage function)
               [data] => xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx //data received.
        )
        In case of raw socket connection the array contain obly [data].
    
  _**$con**_ - resource, representing the connection.

**void onError(string $error_message, resource $con)** - the function is called when an error occures.

  Arguments:
  
  _**$error_message**_ - erorr description (if available) string.
  
  _**$con**_ - resource, representing the connection. onError function should not close the connection, the connection will be closed automatically.

**void onClose(resource $con)** - the function is called just before the connection is closed. 

  Arguments:
  
  _**$con**_ - resource, representing the connection.

**boolean/string sendMessage(array or string $msg, resource $con)** - sends data via established socket connection in accordance with the protocol (websocket or raw) assigned for the connection. Actually the function encodes data, places it to the output queue and place resource to the write array of stream_select. As a result data are sent in the next stream_select loop.  

  Arguments:
  
  _**$msg**_ - array or string, data to send. If $msg is array then $msg['data'] is treated as actual data to send.
  
  _**$con**_ - resource, representing the connection.
  
  Returned value:
  
  _boolean true_ - data sucsessfully encoded and queued for sending.
  
  _string error_message_ - an error occurred while encoding message, or connection is already broken.
  
 **resource/string openClientConnection(string $target,$protocol='raw',$url='/',
        $host='127.0.0.1:8000',$orig='http://localhost')**
