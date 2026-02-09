<?php
// Add these at the top
ob_clean();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/../../database/database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceData = json_decode(file_get_contents("php://input"), true);
    $response = [];

    if (!empty($attendanceData) && is_array($attendanceData)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO tblattendance 
                (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked, notificationSent)
                VALUES (:studentID, :course, :unit, :attendanceStatus, :date, 0)
            ");

            $successCount = 0;

            foreach ($attendanceData as $data) {
                if (empty($data['studentID']) || empty($data['course']) || 
                    empty($data['unit']) || empty($data['attendanceStatus'])) {
                    continue;
                }

                $stmt->execute([
                    ':studentID' => $data['studentID'],
                    ':course' => $data['course'],
                    ':unit' => $data['unit'],
                    ':attendanceStatus' => ucfirst(strtolower($data['attendanceStatus'])),
                    ':date' => date("Y-m-d")
                ]);
                
                $successCount++;
            }

            $pdo->commit();
            $response['status'] = 'success';
            $response['message'] = "Attendance saved for $successCount student(s)";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $response['status'] = 'error';
            $response['message'] = "Error: " . $e->getMessage();
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = "No attendance data received";
    }

    echo json_encode($response);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}
?>
