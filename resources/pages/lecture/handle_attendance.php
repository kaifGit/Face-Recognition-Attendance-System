<?php
ob_clean();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/../../../database/database_connection.php';
require __DIR__ . '/send_mail.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceData = json_decode(file_get_contents("php://input"), true);
    $response = [];

    if (!empty($attendanceData) && is_array($attendanceData)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO tblattendance 
                (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked, notificationSent)
                VALUES (:studentID, :course, :unit, :attendanceStatus, :date, :notificationSent)
            ");

            $successCount = 0;
            $emailsSent = 0;

            foreach ($attendanceData as $data) {
                if (empty($data['studentID']) || empty($data['course']) || 
                    empty($data['unit']) || empty($data['attendanceStatus'])) {
                    continue;
                }

                $studentID = $data['studentID'];
                $course = $data['course'];
                $unit = $data['unit'];
                $attendanceStatus = ucfirst(strtolower($data['attendanceStatus']));
                $date = date("Y-m-d");

                $shouldNotify = ($attendanceStatus === 'Absent' || $attendanceStatus === 'Late');

                // Insert attendance
                $stmt->execute([
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit,
                    ':attendanceStatus' => $attendanceStatus,
                    ':date' => $date,
                    ':notificationSent' => 0
                ]);
                
                $successCount++;
                $lastInsertId = $pdo->lastInsertId();

                // Send email for absent/late students
                if ($shouldNotify) {
                    try {
                        // FIXED: Use 'registrationNumber' not 'studentRegistrationNumber'
                        $studentStmt = $pdo->prepare("
                            SELECT firstName, email 
                            FROM tblstudents 
                            WHERE registrationNumber = :regNo
                            LIMIT 1
                        ");
                        $studentStmt->execute([':regNo' => $studentID]);
                        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

                        if ($student && !empty($student['email'])) {
                            $courseStmt = $pdo->prepare("SELECT name FROM tblcourse WHERE courseCode = :code");
                            $courseStmt->execute([':code' => $course]);
                            $courseData = $courseStmt->fetch(PDO::FETCH_ASSOC);
                            $courseName = $courseData ? $courseData['name'] : $course;

                            sendAttendanceEmail(
                                $student['email'],
                                $student['firstName'],
                                $courseName,
                                $attendanceStatus
                            );

                            $updateStmt = $pdo->prepare("UPDATE tblattendance SET notificationSent = 1 WHERE attendanceID = :id");
                            $updateStmt->execute([':id' => $lastInsertId]);
                            
                            $emailsSent++;
                            error_log("✅ Email sent to: " . $student['email']);
                        } else {
                            error_log("❌ No email found for student: $studentID");
                        }
                    } catch (Exception $emailError) {
                        error_log("❌ Email failed for $studentID: " . $emailError->getMessage());
                    }
                }
            }

            $pdo->commit();
            
            $message = "Attendance saved for $successCount student(s)";
            if ($emailsSent > 0) {
                $message .= ". $emailsSent notification(s) sent";
            }
            
            $response['status'] = 'success';
            $response['message'] = $message;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $response['status'] = 'error';
            $response['message'] = "Error: " . $e->getMessage();
            error_log("Attendance Error: " . $e->getMessage());
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = "No attendance data received";
    }

    echo json_encode($response);
    exit;
}
?>
