    <?php
require '../../database/database_connection.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $course = $_POST['course'];
    $unit = $_POST['unit'];
    $quiz = $_POST['quiz'];
    $assignment = $_POST['assignment'];
    $final = $_POST['final'];

    $stmt = $pdo->prepare("INSERT INTO tblmarks 
        (studentID, course, unit, quizMarks, assignmentMarks, finalExamMarks)
        VALUES (:studentID, :course, :unit, :quizMarks, :assignmentMarks, :finalMarks)
        ON DUPLICATE KEY UPDATE quizMarks = :quizMarks, assignmentMarks = :assignmentMarks, finalExamMarks = :finalMarks
    ");

    foreach($quiz as $studentID => $quizMark){
        $stmt->execute([
            ':studentID' => $studentID,
            ':course' => $course,
            ':unit' => $unit,
            ':quizMarks' => $quizMark,
            ':assignmentMarks' => $assignment[$studentID],
            ':finalMarks' => $final[$studentID]
        ]);
    }

    echo "Marks submitted successfully!";
}
