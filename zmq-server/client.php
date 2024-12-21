<?php
$startTime = microtime(true);
require 'vendor/autoload.php';

$context = new ZMQContext();
$socket = $context->getSocket(ZMQ::SOCKET_DEALER);

$clientId = uniqid("client_");
$socket->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $clientId);
$socket->connect("tcp://127.0.0.1:5555");

echo "Client ID: $clientId\n";

$queries = [];

for($i=0;$i < 1000;$i++){
    $totalRows = 5000; // Adjust this to the total number of rows in the table
    $randomOffset = mt_rand(0, $totalRows - 1);
    $query = "SELECT SLEEP(RAND() * 5), users.* FROM users LIMIT 1 OFFSET $randomOffset";
    $queries[] = $query;
}


$queryMap = [];
foreach ($queries as $query) {
    $queryId = uniqid("query_");
    $queryMap[$queryId] = $query;
    $socket->sendMulti(['', json_encode(['id' => $queryId, 'query' => $query])]);
    echo "Sent query ($queryId): $query\n";
}

$receivedResponses = [];
while (count($receivedResponses) < count($queries)) {
    $response = $socket->recvMulti();
    print_r($response) . PHP_EOL;
    $payload = json_decode($response[0], true);
    if (isset($payload['id']) && isset($queryMap[$payload['id']])) {
        $queryId = $payload['id'];
        $receivedResponses[$queryId] = $payload['data'];
        echo "Response for query ($queryId):\n";
        print_r($payload['data']);
    } else {
        echo "Received response with unknown query ID.\n";
    }
}

// End time
$endTime = microtime(true);

echo "Ran: " . sizeof($queries) . ' SQL queries' . PHP_EOL;
// Calculate and display elapsed time
$elapsedTime = ($endTime - $startTime) * 1000; // Convert seconds to milliseconds
echo "Script executed in $elapsedTime milliseconds.\n";
