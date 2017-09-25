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

###onConnect(array $inf, resource $con)]

###onMessage(array $msg, resource $con)

###onError(string $error_message, resource $con)

###onClose(resource $con)

###sendMessage(array or string $msg, resource $con)

###openClientConnection(string $target,$protocol='raw',$url='/',$host='127.0.0.1:8000',$orig='http://localhost')
