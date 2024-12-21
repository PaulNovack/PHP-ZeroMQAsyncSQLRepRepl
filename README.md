# PHP-ZeroMQAsyncSQLReqRepl

This implements a ZeroMQ server that can recieve SQL queries from ZeroMQ messages and process them in threads using PHP Parallel extension in threads.

PHP Clients send a Unique Identifier with a query that the client can later recieve the results.   This allows clients to send sql calls to be executed in a non blocking manner and retrieve the results later on after they have executed and there are results.

In the example client.php script. There are 1000 sql selects with a random 1 to 5 second delay. They are sent to the PHP ZeroMQ server and then the results are retrieved for each unique query identifier and then the client exits.

Queues are completly handled by ZeroMQ with a setting for the max threads in the code.

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

