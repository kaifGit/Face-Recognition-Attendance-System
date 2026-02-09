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

// Add new unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unit'])) {
    $unitName = $_POST['unit_name'] ?? '';
    $unitCode = $_POST['unit_code'] ?? '';
    $courseId = $_POST['course_id'] ?? '';
    
    if ($unitName && $unitCode && $courseId) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tblunit (name, unitCode, courseID, dateCreated) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$unitName, $unitCode, $courseId]);
            $_SESSION['message'] = "✓ Unit added successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please fill all fields!";
    }
    header("Location: manage_units");
    exit;
}

// Delete unit
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tblunit WHERE Id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['message'] = "✓ Unit deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting: " . $e->getMessage();
    }
    header("Location: manage_units");
    exit;
}

// Fetch all courses
$courses = getCourseNames();

// Fetch all units with course names
$stmt = $pdo->query("SELECT u.*, c.name as courseName, c.courseCode 
                     FROM tblunit u 
                     LEFT JOIN tblcourse c ON u.courseID = c.Id 
                     ORDER BY u.dateCreated DESC");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="resources/images/logo/attnlg.png" rel="icon">
    <title>Manage Units - Lecturer Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .manage-container { padding: 20px; max-width: 1200px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-card { background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #4CAF50; box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1); }
        .btn-primary { background-color: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background-color: #45a049; }
        .table-card { background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .units-table { width: 100%; border-collapse: collapse; }
        .units-table thead { background-color: #f8f9fa; }
        .units-table th { padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; color: #495057; }
        .units-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .units-table tbody tr:hover { background-color: #f8f9fa; }
        .btn-delete { color: #dc3545; cursor: pointer; font-size: 18px; }
        .btn-delete:hover { color: #c82333; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-course { background-color: #e7f3ff; color: #0066cc; }
    </style>
</head>
<body>
    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            <div class="manage-container">
                <div class="title">
                    <h2 class="section--title">Manage Course Units</h2>
                </div>

                <?php 
                if (isset($_SESSION['message'])) {
                    echo '<div class="alert alert-success"><i class="ri-checkbox-circle-line"></i><span>' . htmlspecialchars($_SESSION['message']) . '</span></div>';
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-error"><i class="ri-error-warning-line"></i><span>' . htmlspecialchars($_SESSION['error']) . '</span></div>';
                    unset($_SESSION['error']);
                }
                ?>

                <!-- Add Unit Form -->
                <div class="form-card">
                    <h3 style="margin-bottom: 20px;"><i class="ri-add-circle-line"></i> Add New Unit</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="course_id">Select Course *</label>
                            <select name="course_id" id="course_id" required>
                                <option value="">-- Select Course --</option>
                                <?php
                                $stmt = $pdo->query("SELECT Id, name, courseCode FROM tblcourse ORDER BY name");
                                $allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach($allCourses as $c) {
                                    echo "<option value='" . $c['Id'] . "'>" . htmlspecialchars($c['name']) . " (" . htmlspecialchars($c['courseCode']) . ")</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="unit_name">Unit Name *</label>
                            <input type="text" name="unit_name" id="unit_name" placeholder="e.g., Database Systems" required>
                        </div>

                        <div class="form-group">
                            <label for="unit_code">Unit Code *</label>
                            <input type="text" name="unit_code" id="unit_code" placeholder="e.g., BCT2301" required>
                        </div>

                        <button type="submit" name="add_unit" class="btn-primary">
                            <i class="ri-save-line"></i> Add Unit
                        </button>
                    </form>
                </div>

                <!-- Units List -->
                <div class="table-card">
                    <h3 style="margin-bottom: 20px;"><i class="ri-list-check"></i> Existing Units</h3>
                    
                    <?php if (empty($units)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 40px;">No units added yet. Add your first unit above!</p>
                    <?php else: ?>
                        <table class="units-table">
                            <thead>
                                <tr>
                                    <th>Unit Code</th>
                                    <th>Unit Name</th>
                                    <th>Course</th>
                                    <th>Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($units as $unit): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($unit['unitCode']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($unit['name']); ?></td>
                                    <td>
                                        <span class="badge badge-course">
                                            <?php echo htmlspecialchars($unit['courseName'] ?? 'N/A'); ?>
                                            (<?php echo htmlspecialchars($unit['courseCode'] ?? 'N/A'); ?>)
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($unit['dateCreated'])); ?></td>
                                    <td>
                                        <a href="?delete=<?php echo $unit['Id']; ?>" 
                                           onclick="return confirm('Delete this unit? This will also delete all marks associated with it!');"
                                           class="btn-delete" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>
</body>
</html>
