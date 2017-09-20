# PHP web socket server
Non blocking socket server on PHP.
The server is intended for windows, so no forks, multithreading, etc..
Non blocking cycle is built around stream_select. stream_select is used not only for reading from sockets, but for writing into them as well.
## API
