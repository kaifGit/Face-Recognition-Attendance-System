<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database & Functions ---
require_once __DIR__ . '/../../../database/database_connection.php';
require_once __DIR__ . '/../../lib/php_functions.php';

// --- Ensure lecturer is logged in ---
$lecturer = user();
if (!$lecturer) {
    header("Location: ../../../login.php");
    exit;
}

// --- Save marks if submitted ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $course = $_POST['course'] ?? "";
    $unit = $_POST['unit'] ?? "";
    $markType = $_POST['mark_type'] ?? "assignment";
    
    if (!empty($_POST['marks']) && $course && $unit && $markType) {
        // Whitelist allowed mark types for security
        $allowedTypes = ['assignment', 'quiz', 'final'];
        if (!in_array($markType, $allowedTypes)) {
            $_SESSION['error'] = "Invalid mark type selected!";
            header("Location: marks?course=$course&unit=$unit");
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            $successCount = 0;

            foreach ($_POST['marks'] as $studentId => $mark) {
                // Skip empty marks
                if ($mark === '' || $mark === null) {
                    continue;
                }

                // Validate mark is between 0-100
                if ($mark >= 0 && $mark <= 100) {
                    // First, check if record exists
                    $stmt = $pdo->prepare(
                        "SELECT assignment, quiz, final FROM tblmarks 
                         WHERE studentId = :sid AND courseCode = :course AND unitCode = :unit"
                    );
                    $stmt->execute([
                        ':sid' => $studentId,
                        ':course' => $course,
                        ':unit' => $unit
                    ]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing) {
                        // Update existing record - set the specific mark type and recalculate total
                        $newAssignment = ($markType == 'assignment') ? $mark : ($existing['assignment'] ?? 0);
                        $newQuiz = ($markType == 'quiz') ? $mark : ($existing['quiz'] ?? 0);
                        $newFinal = ($markType == 'final') ? $mark : ($existing['final'] ?? 0);
                        
                        // Calculate weighted total: Assignment(25%) + Quiz(25%) + Final(50%)
                        $newTotal = ($newAssignment * 0.25) + ($newQuiz * 0.25) + ($newFinal * 0.50);
                        
                        $stmt = $pdo->prepare(
                            "UPDATE tblmarks 
                             SET {$markType} = :mark, total = :total
                             WHERE studentId = :sid AND courseCode = :course AND unitCode = :unit"
                        );
                        $stmt->execute([
                            ':mark' => $mark,
                            ':total' => $newTotal,
                            ':sid' => $studentId,
                            ':course' => $course,
                            ':unit' => $unit
                        ]);
                    } else {
                        // Insert new record
                        $newAssignment = ($markType == 'assignment') ? $mark : 0;
                        $newQuiz = ($markType == 'quiz') ? $mark : 0;
                        $newFinal = ($markType == 'final') ? $mark : 0;
                        
                        // Calculate weighted total: Assignment(25%) + Quiz(25%) + Final(50%)
                        $newTotal = ($newAssignment * 0.25) + ($newQuiz * 0.25) + ($newFinal * 0.50);
                        
                        $stmt = $pdo->prepare(
                            "INSERT INTO tblmarks (studentId, courseCode, unitCode, assignment, quiz, final, total)
                             VALUES (:sid, :course, :unit, :assignment, :quiz, :final, :total)"
                        );
                        $stmt->execute([
                            ':sid' => $studentId,
                            ':course' => $course,
                            ':unit' => $unit,
                            ':assignment' => $newAssignment,
                            ':quiz' => $newQuiz,
                            ':final' => $newFinal,
                            ':total' => $newTotal
                        ]);
                    }
                    $successCount++;
                }
            }

            $pdo->commit();
            $_SESSION['message'] = "✓ " . ucfirst($markType) . " marks saved successfully for $successCount student(s)! Total calculated with weights (A:25%, Q:25%, F:50%).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error saving marks: " . $e->getMessage();
        }

        // Redirect to refresh and clear POST
        header("Location: marks?course=$course&unit=$unit&type=$markType");
        exit;
    } else {
        $_SESSION['error'] = "Please select course, unit, mark type and enter marks.";
    }
}


// --- Get selected course/unit/type from GET ---
$selectedCourse = $_GET['course'] ?? '';
$selectedUnit   = $_GET['unit'] ?? '';
$selectedType   = $_GET['type'] ?? 'assignment';

// --- Fetch all courses ---
$courses = getCourseNames();

// --- Fetch units for selected course ---
$units = [];
if (!empty($selectedCourse)) {
    // Get the numeric course ID from the courseCode
    $stmt = $pdo->prepare("SELECT Id FROM tblcourse WHERE courseCode = :course");
    $stmt->execute([':course' => $selectedCourse]);
    $courseId = $stmt->fetchColumn();
    
    if ($courseId) {
        $stmt = $pdo->prepare(
            "SELECT unitCode, name 
             FROM tblunit 
             WHERE courseID = :courseId 
             ORDER BY name"
        );
        $stmt->execute([':courseId' => $courseId]);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Fetch students for selected course ---
$students = [];
if (!empty($selectedCourse)) {
    $stmt = $pdo->prepare(
        "SELECT Id, firstName, lastName, registrationNumber
         FROM tblstudents
         WHERE courseCode = :course
         ORDER BY registrationNumber"
    );
    $stmt->execute([':course' => $selectedCourse]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="resources/images/logo/attnlg.png" rel="icon">
    <title>Evaluate Marks - Lecturer Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .marks-container { padding: 20px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .lecture-options { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .lecture-options select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 200px; background-color: white; cursor: pointer; }
        .lecture-options select:focus { outline: none; border-color: #4CAF50; }
        .mark-type-selector { background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ffc107; }
        .mark-type-selector label { font-weight: 600; color: #856404; margin-right: 10px; }
        .table-container { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .marks-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .marks-table thead { background-color: #f8f9fa; }
        .marks-table th { padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; color: #495057; }
        .marks-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .marks-table tbody tr:hover { background-color: #f8f9fa; }
        .marks-table input[type="number"] { width: 100px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; }
        .marks-table input[type="number"]:focus { outline: none; border-color: #4CAF50; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1); }
        .save-btn { margin-top: 20px; padding: 12px 30px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: background-color 0.3s; }
        .save-btn:hover { background-color: #45a049; }
        .save-btn:active { transform: scale(0.98); }
        .no-data { text-align: center; padding: 40px; color: #6c757d; }
        .no-data i { font-size: 48px; margin-bottom: 10px; opacity: 0.5; }
        .instruction-text { background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #004085; display: flex; align-items: center; gap: 10px; }
        .marks-summary { display: flex; gap: 10px; font-size: 12px; color: #666; }
        .marks-summary span { padding: 4px 8px; background: #f0f0f0; border-radius: 3px; }
        .marks-summary span.total-mark { background: #d4edda; color: #155724; font-weight: bold; }
        .info-badge { background: #e7f3ff; color: #004085; padding: 8px 12px; border-radius: 4px; font-size: 13px; display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            <div class="marks-container">
                <div class="title">
                    <h2 class="section--title">Evaluate Student Marks</h2>
                </div>

                <?php 
                if (isset($_SESSION['message'])) {
                    echo '<div class="alert alert-success">
                            <i class="ri-checkbox-circle-line"></i>
                            <span>' . htmlspecialchars($_SESSION['message']) . '</span>
                          </div>';
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-error">
                            <i class="ri-error-warning-line"></i>
                            <span>' . htmlspecialchars($_SESSION['error']) . '</span>
                          </div>';
                    unset($_SESSION['error']);
                }
                ?>

                <div class="instruction-text">
                    <i class="ri-information-line"></i>
                    <span>Select a course, unit, and mark type to view and enter marks for enrolled students. <strong>Total is calculated automatically!</strong></span>
                </div>

                <form method="GET" class="lecture-options">
                    <select name="course" required onchange="this.form.submit()">
                        <option value="">-- Select Course --</option>
                        <?php
                        foreach($courses as $c) {
                            $selected = ($selectedCourse == $c['courseCode']) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($c['courseCode']) . "' $selected>" 
                                 . htmlspecialchars($c['name']) . "</option>";
                        }
                        ?>
                    </select>

                    <?php if ($selectedCourse && !empty($units)): ?>
                        <select name="unit" required onchange="this.form.submit()">
                            <option value="">-- Select Unit --</option>
                            <?php 
                            foreach($units as $u) {
                                $selected = ($selectedUnit == $u['unitCode']) ? "selected" : "";
                                echo "<option value='" . htmlspecialchars($u['unitCode']) . "' $selected>" 
                                     . htmlspecialchars($u['name']) . "</option>";
                            }
                            ?>
                        </select>
                    <?php endif; ?>

                    <?php if ($selectedCourse && $selectedUnit): ?>
                        <select name="type" required onchange="this.form.submit()">
                            <option value="assignment" <?php echo $selectedType == 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                            <option value="quiz" <?php echo $selectedType == 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                            <option value="final" <?php echo $selectedType == 'final' ? 'selected' : ''; ?>>Final Exam</option>
                        </select>
                    <?php endif; ?>
                </form>

                <?php if ($selectedCourse && $selectedUnit): ?>
                    <div class="mark-type-selector">
                        <i class="ri-file-edit-line"></i>
                        <label>Currently Entering:</label>
                        <strong><?php echo ucfirst($selectedType); ?> Marks</strong>
                       <div class="info-badge">
    <i class="ri-calculator-line"></i> Total = (Assignment × 25%) + (Quiz × 25%) + (Final × 50%) = 100%
</div>

                    </div>

                    <?php if (empty($students)): ?>
                        <div class="table-container">
                            <div class="no-data">
                                <i class="ri-user-line"></i>
                                <p><strong>No students enrolled in this course.</strong></p>
                                <p>Please check if students are registered for this course.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <form method="POST">
                                <input type="hidden" name="course" value="<?php echo htmlspecialchars($selectedCourse); ?>">
                                <input type="hidden" name="unit" value="<?php echo htmlspecialchars($selectedUnit); ?>">
                                <input type="hidden" name="mark_type" value="<?php echo htmlspecialchars($selectedType); ?>">
                                
                                <table class="marks-table">
                                    <thead>
    <tr>
        <th>#</th>
        <th>Registration Number</th>
        <th>Student Name</th>
        <th>
            <?php 
            $maxMarks = ['assignment' => 100, 'quiz' => 100, 'final' => 100];
            echo ucfirst($selectedType) . " Mark (Max: " . $maxMarks[$selectedType] . ")"; 
            ?>
        </th>
        <th>All Marks Summary</th>
    </tr>
</thead>

                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach($students as $s):
                                            // Fetch all marks for this student
                                            $stmt = $pdo->prepare(
                                                "SELECT assignment, quiz, final, total FROM tblmarks 
                                                 WHERE studentId = :sid 
                                                 AND courseCode = :course 
                                                 AND unitCode = :unit"
                                            );
                                            $stmt->execute([
                                                ':sid' => $s['Id'], 
                                                ':course' => $selectedCourse, 
                                                ':unit' => $selectedUnit
                                            ]);
                                            $allMarks = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            $currentMark = $allMarks[$selectedType] ?? '';
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($s['registrationNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($s['firstName'] . " " . $s['lastName']); ?></td>
                                            <td>    
                                                <input type="number" 
                                                       name="marks[<?php echo $s['Id']; ?>]" 
                                                       min="0" 
                                                       max="100" 
                                                       step="0.01"
                                                       value="<?php echo $currentMark !== '' ? htmlspecialchars($currentMark) : ''; ?>"
                                                       placeholder="0.00">
                                            </td>
                                            <td>
                                                <div class="marks-summary">
                                                    <span title="Assignment">A: <?php echo $allMarks['assignment'] ?? '0'; ?></span>
                                                    <span title="Quiz">Q: <?php echo $allMarks['quiz'] ?? '0'; ?></span>
                                                    <span title="Final">F: <?php echo $allMarks['final'] ?? '0'; ?></span>
                                                    <span class="total-mark" title="Total (Auto-calculated)">Total: <?php echo $allMarks['total'] ?? '0'; ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <button type="submit" name="save_marks" class="save-btn">
                                    <i class="ri-save-line"></i> 
                                    Save <?php echo ucfirst($selectedType); ?> Marks & Update Totals
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <script>
        // Add confirmation before leaving if marks were entered but not saved
        let formChanged = false;
        const markInputs = document.querySelectorAll('input[type="number"]');
        
        markInputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        const form = document.querySelector('form[method="POST"]');
        if (form) {
            form.addEventListener('submit', () => {
                formChanged = false;
            });
        }

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>
