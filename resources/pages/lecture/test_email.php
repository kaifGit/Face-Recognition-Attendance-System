<?php
require __DIR__ . '/database/database_connection.php';
require __DIR__ . '/resources/pages/lecture/send_mail.php';

// Test with a real student - FIXED column name
$stmt = $pdo->prepare("SELECT firstName, email FROM tblstudents WHERE registrationNumber = '24p-3116'");
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student && !empty($student['email'])) {
    echo "Student found: " . $student['firstName'] . "<br>";
    echo "Email: " . $student['email'] . "<br><br>";
    
    echo "Attempting to send email...<br>";
    
    $result = sendAttendanceEmail(
        $student['email'],
        $student['firstName'],
        'Computer Technology',
        'Absent'
    );
    
    if ($result) {
        echo "✅ Email sent successfully!";
    } else {
        echo "❌ Email failed to send";
    }
} else {
    echo "❌ Student not found or has no email address<br>";
    echo "Student data: ";
    print_r($student);
}
?>
