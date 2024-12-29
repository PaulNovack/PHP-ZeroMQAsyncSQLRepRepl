# PHP-ZeroMQAsyncSQLReqRepl

## Deprecated had reliablility issues with implementint with PHP-Parallels

For a more durable server use the c++ version here: https://github.com/PaulNovack/CppZeroMQAsynchSQLServer

PHP Server running parallels and zeroMQ can segfault in certain conditions.   The c++ version is much more stable.

This implements a ZeroMQ server that can recieve SQL queries from ZeroMQ messages and process them in threads using PHP Parallel extension.

PHP Clients send a Unique Identifier with a query that the client can later retrieve the results.   This allows clients to send sql calls to be executed in a non blocking manner and retrieve the results later on after they have executed and there are results.

In the example client.php script. There are 1000 sql selects with a random 1 to 5 second delay. They are sent to the PHP ZeroMQ server and then the results are retrieved for each unique query identifier and then the client exits.

Queues are completely handled by ZeroMQ. There is a setting for the max threads in the code.  Above that requests will just stay in zeroMQ's internal queue.

If the mySQL server has to many connections the threads will retry every second until there are available connections.  Thus ensuring sql commands eventually get executed.

## Running

### Build the containers

docker compose build

### Start the containers

docker compose up

### Run the client that is in the php-zmq-parallel-server

docker exec -it php-zmq-parallel-server bash

cd app

php client.php

