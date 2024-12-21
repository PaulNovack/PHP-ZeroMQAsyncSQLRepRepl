# PHP-ZeroMQAsyncSQLReqRepl

This implements a ZeroMQ server that can recieve SQL queries from ZeroMQ messages and process them in threads using PHP Parallel extension in threads.

Clients send a Unique Identifier with a query that the client can later recieve the results.   This allows clients to send sql calls to be executed in a non blocking manner.

In the example client.php 1000 sql selects with random 1 to 5 second delays in the sql are sent to the server and then the results are retrieved for each unique query identifier and then the client exits.

## Running

### Build the containers

docker compose build

### Start the containers

docker compose up

### Run the client that is in the php-zmq-parallel-server

docker exec -it php-zmq-parallel-server bash
cd app
php client.php

