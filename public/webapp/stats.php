<?php
require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
    
    $connection = DriverManager::getConnection([
        'url' => sprintf(
            'mysql://%s:%s@%s/%s',
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_HOST'],
            $_ENV['DB_NAME']
        )
    ]);
    
    $stats = [];
    
    // Total submissions
    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM submissions");
    $stmt->execute();
    $stats['total'] = (int)$stmt->fetch()['count'];
    
    // Pending
    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending'] = (int)$stmt->fetch()['count'];
    
    // Approved
    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'approved'");
    $stmt->execute();
    $stats['approved'] = (int)$stmt->fetch()['count'];
    
    // Rejected
    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM submissions WHERE status = 'rejected'");
    $stmt->execute();
    $stats['rejected'] = (int)$stmt->fetch()['count'];
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    echo json_encode([
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0
    ]);
}