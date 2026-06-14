<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/utilities/notifications.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to vote']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$complaint_id = isset($input['complaint_id']) ? intval($input['complaint_id']) : 0;
$vote_type = isset($input['vote_type']) ? sanitizeInput($input['vote_type']) : '';
$csrf_token = isset($input['csrf_token']) ? sanitizeInput($input['csrf_token']) : '';

// Validate CSRF token
if (!validateCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Validate vote type
if (!in_array($vote_type, ['upvote', 'downvote'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote type']);
    exit();
}

// Check if complaint exists and is published
try {
    $stmt = db()->prepare("SELECT status FROM complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }
    
    if (!in_array($complaint['status'], ['published', 'resolved'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot vote on this complaint']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

try {
    // Start transaction
    db()->beginTransaction();
    
    // Check if user already voted
    $stmt = db()->prepare("
        SELECT vote_type 
        FROM votes 
        WHERE complaint_id = ? AND user_id = ?
    ");
    $stmt->execute([$complaint_id, $_SESSION['student_id']]);
    $existing_vote = $stmt->fetch();
    
    if ($existing_vote) {
        if ($existing_vote['vote_type'] === $vote_type) {
            // Remove vote (toggle)
            $stmt = db()->prepare("
                DELETE FROM votes 
                WHERE complaint_id = ? AND user_id = ?
            ");
            $stmt->execute([$complaint_id, $_SESSION['student_id']]);
            
            // Update complaint vote count
            if ($vote_type === 'upvote') {
                $stmt = db()->prepare("
                    UPDATE complaints 
                    SET upvotes = upvotes - 1 
                    WHERE id = ?
                ");
            } else {
                $stmt = db()->prepare("
                    UPDATE complaints 
                    SET downvotes = downvotes - 1 
                    WHERE id = ?
                ");
            }
            $stmt->execute([$complaint_id]);
            
            $message = 'Vote removed';
            $voted = false;
        } else {
            // Change vote type
            $stmt = db()->prepare("
                UPDATE votes 
                SET vote_type = ? 
                WHERE complaint_id = ? AND user_id = ?
            ");
            $stmt->execute([$vote_type, $complaint_id, $_SESSION['student_id']]);
            
            // Update both counts
            if ($vote_type === 'upvote') {
                $stmt = db()->prepare("
                    UPDATE complaints 
                    SET upvotes = upvotes + 1, downvotes = downvotes - 1 
                    WHERE id = ?
                ");
            } else {
                $stmt = db()->prepare("
                    UPDATE complaints 
                    SET upvotes = upvotes - 1, downvotes = downvotes + 1 
                    WHERE id = ?
                ");
            }
            $stmt->execute([$complaint_id]);
            
            $message = 'Vote updated';
            $voted = true;
        }
    } else {
        // New vote
        $stmt = db()->prepare("
            INSERT INTO votes (complaint_id, user_id, vote_type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$complaint_id, $_SESSION['student_id'], $vote_type]);
        
        // Update complaint vote count
        if ($vote_type === 'upvote') {
            $stmt = db()->prepare("
                UPDATE complaints 
                SET upvotes = upvotes + 1 
                WHERE id = ?
            ");
        } else {
            $stmt = db()->prepare("
                UPDATE complaints 
                SET downvotes = downvotes + 1 
                WHERE id = ?
            ");
        }
        $stmt->execute([$complaint_id]);
        
        $message = 'Vote recorded';
        $voted = true;
    }
    
    // Get updated vote counts
    $stmt = db()->prepare("
        SELECT upvotes, downvotes 
        FROM complaints 
        WHERE id = ?
    ");
    $stmt->execute([$complaint_id]);
    $counts = $stmt->fetch();
    
    // Log activity
    logActivity(
        'VOTE_' . strtoupper($vote_type),
        "Complaint #{$complaint_id}",
        $_SESSION['student_id'],
        'user'
    );

    // Send notification to complaint owner if appropriate
    try {
        $stmt = db()->prepare("SELECT user_id FROM complaints WHERE id = ?");
        $stmt->execute([$complaint_id]);
        $complaint_owner = $stmt->fetchColumn();
        if ($complaint_owner && $complaint_owner != $_SESSION['student_id'] && $voted) {
            // only notify when a vote is added or changed, not removed
            $notifMsg = $vote_type === 'upvote' ? 'Your complaint received an upvote' : 'Your complaint received a downvote';
            // send via helper
            sendInAppNotification(
                $complaint_owner,
                'info',
                ucfirst($vote_type),
                $notifMsg,
                '',
                $complaint_id,
                'complaint'
            );
        }
    } catch (PDOException $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
    
    // Commit transaction
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'upvotes' => $counts['upvotes'],
        'downvotes' => $counts['downvotes'],
        'voted' => $voted,
        'vote_type' => $voted ? $vote_type : null
    ]);
    exit();
    
} catch (PDOException $e) {
    // Rollback transaction
    db()->rollBack();
    
    error_log("Vote error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to record vote'
    ]);
    exit();
}
?>
