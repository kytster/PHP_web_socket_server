# PHP web socket server

Non blocking socket server on PHP.

The server is intended for windows, so no forks, multithreading, etc..

Non blocking cycle is built around stream_select, which is used not only for reading from sockets, but for writing also.

The server supports web socket protocol version 13 and raw sockets. 

The type of connection (web socket or raw socket connection) is defined by the first message, client sent after connecting.If the first message contains "Upgrade: websocket" header, then the connection is to be web socket. Otherwise, the raw connection is assumed.

The script also provides creating of client web-socket and/or raw-socket connections via the same stream_select cycle. So, it is possible to organise some kind of web-socket proxy or "web-to-raw" (and vice-versa) socket converter.

The script should be run under CLI. It is convenient to configure script to run as service using appropriate utility (i. g. NSSM - http://nssm.cc)

## API
