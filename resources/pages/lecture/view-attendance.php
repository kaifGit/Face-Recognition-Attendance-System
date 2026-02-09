<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../database/database_connection.php';
require_once __DIR__ . '/../../lib/php_functions.php';

$lecturer = user();
if (!$lecturer) {
    header("Location: ../../../login.php");
    exit;
}

$courseCode = isset($_GET['course']) ? $_GET['course'] : '';
$unitCode = isset($_GET['unit']) ? $_GET['unit'] : '';

$coursename = "";
if (!empty($courseCode)) {
    $stmt = $pdo->prepare("SELECT name FROM tblcourse WHERE courseCode = :code");
    $stmt->execute([':code' => $courseCode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $coursename = $result['name'];
    }
}

$unitname = "";
if (!empty($unitCode)) {
    $stmt = $pdo->prepare("SELECT name FROM tblunit WHERE unitCode = :code");
    $stmt->execute([':code' => $unitCode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $unitname = $result['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/attnlg.png" rel="icon">
    <title>View Attendance - Lecturer Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .attendance-table table { width: 100%; border-collapse: collapse; }
        .attendance-table th, .attendance-table td { padding: 10px; text-align: center; border: 1px solid #ddd; }
        .attendance-table thead { background-color: #f8f9fa; font-weight: 600; }
        .attendance-table tbody tr:hover { background-color: #f5f5f5; }
        .no-data { text-align: center; padding: 40px; color: #6c757d; }
        .export-btn { margin: 20px 0; padding: 12px 25px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .export-btn:hover { background-color: #218838; }
        .lecture-options { display: flex; gap: 15px; margin-bottom: 25px; }
        .lecture-options select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 250px; }
        .info-box { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #004085; }
    </style>
</head>

<body>
    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            
            <div class="title">
                <h2 class="section--title">View Attendance Records</h2>
            </div>

            <?php if (!empty($coursename) && !empty($unitname)): ?>
                <div class="info-box">
                    <strong>Department:</strong> <?php echo htmlspecialchars($coursename); ?> &nbsp;|&nbsp; 
                    <strong>Course:</strong> <?php echo htmlspecialchars($unitname); ?>
                </div>
            <?php endif; ?>

            <form class="lecture-options" id="selectForm" method="GET">
                <select required name="course" id="courseSelect" onchange="this.form.submit()">
                    <option value="">Select Department</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        $selected = ($courseCode == $course["courseCode"]) ? "selected" : "";
                        echo '<option value="' . htmlspecialchars($course["courseCode"]) . '" ' . $selected . '>' 
                             . htmlspecialchars($course["name"]) . '</option>';
                    }
                    ?>
                </select>

                <?php if (!empty($courseCode)): ?>
                    <select required name="unit" id="unitSelect" onchange="this.form.submit()">
                        <option value="">Select Course</option>
                        <?php
                        $stmt = $pdo->prepare("SELECT Id FROM tblcourse WHERE courseCode = :code");
                        $stmt->execute([':code' => $courseCode]);
                        $courseId = $stmt->fetchColumn();
                        
                        if ($courseId) {
                            $stmt = $pdo->prepare("SELECT unitCode, name FROM tblunit WHERE courseID = :id ORDER BY name");
                            $stmt->execute([':id' => $courseId]);
                            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($units as $unit) {
                                $selected = ($unitCode == $unit["unitCode"]) ? "selected" : "";
                                echo '<option value="' . htmlspecialchars($unit["unitCode"]) . '" ' . $selected . '>' 
                                     . htmlspecialchars($unit["name"]) . '</option>';
                            }
                        }
                        ?>
                    </select>
                <?php endif; ?>
            </form>

            <?php if (!empty($courseCode) && !empty($unitCode)): ?>
                <?php
                $stmtDates = $pdo->prepare("SELECT DISTINCT dateMarked FROM tblattendance WHERE course = :courseCode AND unit = :unitCode ORDER BY dateMarked");
                $stmtDates->execute([':courseCode' => $courseCode, ':unitCode' => $unitCode]);
                $distinctDates = $stmtDates->fetchAll(PDO::FETCH_ASSOC);

                $stmtStudents = $pdo->prepare("SELECT DISTINCT studentRegistrationNumber FROM tblattendance WHERE course = :courseCode AND unit = :unitCode ORDER BY studentRegistrationNumber");
                $stmtStudents->execute([':courseCode' => $courseCode, ':unitCode' => $unitCode]);
                $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <button class="export-btn" onclick="exportTableToExcel('attendanceTable', '<?php echo htmlspecialchars($unitCode); ?>_<?php echo date('Y-m-d'); ?>', '<?php echo htmlspecialchars($coursename); ?>', '<?php echo htmlspecialchars($unitname); ?>')">
                    <i class="ri-file-excel-2-line"></i> Export Attendance As Excel
                </button>

                <div class="table-container">
                    <div class="attendance-table" id="attendanceTable">
                        <?php if (empty($students) || empty($distinctDates)): ?>
                            <div class="no-data">
                                <i class="ri-calendar-close-line" style="font-size: 48px; opacity: 0.5;"></i>
                                <p><strong>No attendance records found</strong></p>
                                <p>No attendance has been recorded for this department and course combination.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Registration No</th>
                                        <?php
                                        foreach ($distinctDates as $dateRow) {
                                            echo "<th>" . date('M d, Y', strtotime($dateRow['dateMarked'])) . "</th>";
                                        }
                                        ?>
                                        <th>Total Present</th>
                                        <th>Attendance %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($students as $studentRow) {
                                        echo "<tr>";
                                        echo "<td><strong>" . htmlspecialchars($studentRow['studentRegistrationNumber']) . "</strong></td>";

                                        $presentCount = 0;
                                        $totalClasses = count($distinctDates);

                                        foreach ($distinctDates as $dateRow) {
                                            $date = $dateRow['dateMarked'];

                                            $stmtAttendance = $pdo->prepare(
                                                "SELECT attendanceStatus FROM tblattendance 
                                                WHERE studentRegistrationNumber = :regNo 
                                                AND dateMarked = :date 
                                                AND course = :courseCode 
                                                AND unit = :unitCode"
                                            );
                                            $stmtAttendance->execute([
                                                ':regNo' => $studentRow['studentRegistrationNumber'],
                                                ':date' => $date,
                                                ':courseCode' => $courseCode,
                                                ':unitCode' => $unitCode,
                                            ]);
                                            $attendance = $stmtAttendance->fetch(PDO::FETCH_ASSOC);

                                            if ($attendance) {
                                                $status = $attendance['attendanceStatus'];
                                                if (strtolower($status) == 'present') {
                                                    echo "<td style='background-color: #d4edda; color: #155724;'>✓ Present</td>";
                                                    $presentCount++;
                                                } else {
                                                    echo "<td style='background-color: #f8d7da; color: #721c24;'>✗ Absent</td>";
                                                }
                                            } else {
                                                echo "<td style='background-color: #f8d7da; color: #721c24;'>✗ Absent</td>";
                                            }
                                        }

                                        $percentage = $totalClasses > 0 ? round(($presentCount / $totalClasses) * 100, 1) : 0;
                                        $percentageColor = $percentage >= 75 ? '#28a745' : ($percentage >= 50 ? '#ffc107' : '#dc3545');

                                        echo "<td><strong>$presentCount / $totalClasses</strong></td>";
                                        echo "<td style='color: $percentageColor; font-weight: bold;'>$percentage%</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="ri-information-line" style="font-size: 48px; opacity: 0.5;"></i>
                    <p><strong>Please select a department and course to view attendance records</strong></p>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <?php js_asset(['min/js/filesaver', 'min/js/xlsx', 'active_link']); ?>

    <script>
        function exportTableToExcel(tableId, filename = '', courseCode = '', unitCode = '') {
            var table = document.getElementById(tableId);
            if (!table) {
                alert('No attendance data to export');
                return;
            }

            var currentDate = new Date();
            var formattedDate = currentDate.toLocaleDateString();

            var headerContent = 'Attendance for Course: ' + courseCode + ' | Unit: ' + unitCode + ' | Date: ' + formattedDate;
            
            var wb = XLSX.utils.table_to_book(table, {
                sheet: "Attendance"
            });
            
            var wbout = XLSX.write(wb, {
                bookType: 'xlsx',
                bookSST: true,
                type: 'binary'
            });
            
            var blob = new Blob([s2ab(wbout)], {
                type: 'application/octet-stream'
            });
            
            if (!filename.toLowerCase().endsWith('.xlsx')) {
                filename += '.xlsx';
            }

            saveAs(blob, filename);
        }

        function s2ab(s) {
            var buf = new ArrayBuffer(s.length);
            var view = new Uint8Array(buf);
            for (var i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
            return buf;
        }
    </script>
</body>
</html>
