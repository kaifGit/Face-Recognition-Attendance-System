<?php
require __DIR__ . '/resources/database/database_connection.php';

// Test data
$testData = [
    [
        'studentID' => '24p-3116',
        'course' => 'BCT',
        'unit' => 'BCT2411',
        'attendanceStatus' => 'Present'
    ]
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO tblattendance 
        (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked, notificationSent)
        VALUES (:studentID, :course, :unit, :attendanceStatus, :date, 0)
    ");
    
    $stmt->execute([
        ':studentID' => $testData[0]['studentID'],
        ':course' => $testData[0]['course'],
        ':unit' => $testData[0]['unit'],
        ':attendanceStatus' => $testData[0]['attendanceStatus'],
        ':date' => date("Y-m-d")
    ]);
    
    echo "✅ Test attendance saved! Check your database.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
