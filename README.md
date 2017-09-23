# PHP web socket server
Non blocking socket server on PHP.
The server is intended for windows, so no forks, multithreading, etc..
Non blocking cycle is built around stream_select, which is used not only for reading from sockets, but for writing also.
The server supports web socket protocol version 13 and raw sockets.
## API
