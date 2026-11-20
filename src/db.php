<?php
function connectDatabase(): PDO
{
    $conn = new PDO(
        'mysql:host=db;port=3306;dbname=peliprojekti;charset=utf8mb4',
        'user',
        'pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    return $conn;
}