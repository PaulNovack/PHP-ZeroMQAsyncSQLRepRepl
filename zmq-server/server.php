<?php
// Suppress warnings
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);
require 'vendor/autoload.php';
$responses = 0;
use parallel\Runtime;

if (!defined('ZMQ::POLLIN')) {
    define('ZMQ_POLLIN', 1);
}
sleep(1);
echo "Server is initializing...\n";

// Database query closure
$executeQuery = function ($query) {
    $dsn = 'mysql:host=mysql-server;port=3306;dbname=testdb;charset=utf8mb4'; // MySQL connection details
    $username = 'root'; // MySQL username
    $password = 'password'; // MySQL password

    $maxRetries = 50; // Maximum number of retries
    $retryDelay = 1; // Delay in seconds between retries

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            // Establish PDO connection
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Execute the query
            $stmt = $pdo->query($query);

            if (!$stmt) {
                return ['error' => 'SQL error: ' . implode(", ", $pdo->errorInfo())];
            }

            // Fetch and return the results
            $data = $stmt->fetchAll();
            //echo "DATA:\n";
            //print_r($data);
            return $data;

        } catch (Exception $e) {
            // Check for "Too many connections" error
            if (strpos($e->getMessage(), 'Too many connections') !== false) {
                echo "Attempt $attempt: Too many connections. Retrying in $retryDelay second(s)...\n";

                // Sleep before retrying
                sleep($retryDelay);

                // Continue to the next attempt
                continue;
            }

            // If the error is not "Too many connections," throw it
            echo "ERROR:\n" . $e->getMessage() . "\n";
            return ['error' => $e->getMessage()];
        }
    }

    // If all retries fail
    return ['error' => 'Max retry attempts reached. Could not execute query.'];
};


// Initialize the database
function initializeDatabase() {
    $host = 'mysql-server';
    $port = '3306'; // Specify the custom MySQL port
    $username = 'root'; // Update with your MySQL username
    $password = 'password'; // Update with your MySQL password
    $database = 'testdb';

    try {
        $pdo = new PDO("mysql:host=$host;port=$port", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("TRUNCATE TABLE users");
        $stmt = $pdo->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        for ($i = 1; $i <= 5000; $i++) {
            $stmt->execute(["User $i", "user$i@example.com"]);
            if($i % 100 == 0){
                echo "inserting row: " . $i . PHP_EOL;
            }

        }

        echo "Database initialized and sample data added.\n";
    } catch (Exception $e) {
        echo "Error initializing database: " . $e->getMessage() . "\n";
        exit(1);
    }
}

initializeDatabase();

$context = new ZMQContext();
$socket = $context->getSocket(ZMQ::SOCKET_ROUTER);
$socket->bind("tcp://127.0.0.1:5555");

echo "Server is running on tcp://127.0.0.1:5555\n";

$runtimePool = [];
$maxThreads = 25;
$tasks = []; // Map of query_id to futures

while (true) {
    if ($socket->getSockOpt(ZMQ::SOCKOPT_EVENTS) & ZMQ_POLLIN) {
        $message = $socket->recvMulti();

        //echo "Received message:\n";
        //print_r($message);

        if (count($message) < 2) {
            echo "Malformed message: " . json_encode($message) . "\n";
            continue;
        }

        $routerId = $message[0];
        $payload = json_decode($message[2], true);

        if (!$payload || !isset($payload['id']) || !isset($payload['query'])) {
            echo "Invalid payload received: " . json_encode($payload) . "\n";
            $socket->sendMulti([$routerId, json_encode(['error' => 'Invalid request'])]);
            continue;
        }

        $queryId = $payload['id'];
        $query = $payload['query'];

        if (count($runtimePool) < $maxThreads) {
            $runtimePool[] = new Runtime();
        }

        $runtime = array_pop($runtimePool);
        $future = $runtime->run($executeQuery, [$query]);

        $tasks[$queryId] = [$routerId, $runtime, $future];
    }

    foreach ($tasks as $queryId => [$routerId, $runtime, $future]) {
        if ($future->done()) {
            $result = $future->value();

           // echo "Sending response for query ID: $queryId\n";
           // print_r(['id' => $queryId, 'data' => $result]);
            $responses++;
            $socket->sendMulti([$routerId, json_encode(['id' => $queryId, 'data' => $result])]);
            echo "Response Number: " . $responses . PHP_EOL;
            $runtimePool[] = $runtime;
            unset($tasks[$queryId]);
        }
    }

    usleep(100);
}
