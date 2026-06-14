<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// echo "LOADED SUBMIT.PHP";
// exit;


// Check if user is logged in as student
if (!isStudent()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_index = $_SESSION['student_index'];
$student_name = $_SESSION['student_name'];

// Load categories
$categories = [];
try {
    $stmt = db()->prepare("SELECT id, name, description, color FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error loading categories: " . $e->getMessage());
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            // Get form data
            $title = sanitizeInput($_POST['title']);
            $category_id = intval($_POST['category_id']);
            $description = sanitizeInput($_POST['description']);
            $urgency = sanitizeInput($_POST['urgency']);
            $location = sanitizeInput($_POST['location'] ?? '');
            $anonymous = isset($_POST['anonymous']) ? 1 : 0;
            
            // Validate required fields
            if (empty($title) || empty($category_id) || empty($description)) {
                $error = 'Please fill in all required fields.';
            } elseif (strlen($title) < 10) {
                $error = 'Title must be at least 10 characters long.';
            } elseif (strlen($description) < 50) {
                $error = 'Description must be at least 50 characters long.';
            } else {
                // Generate unique complaint code
                $complaint_code = generateComplaintCode();
                
                // Handle file uploads
                $attachments = [];
                if (!empty($_FILES['attachments']['name'][0])) {
                    require_once '../../includes/utilities/file_upload.php';
                    
                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = sanitizeFileName($_FILES['attachments']['name'][$key]);
                            $file_size = $_FILES['attachments']['size'][$key];
                            $file_type = $_FILES['attachments']['type'][$key];
                            
                            // Validate file
                            if (validateUploadedFile($tmp_name, $file_name, $file_size, $file_type)) {
                                $upload_result = uploadComplaintFile($tmp_name, $file_name, $student_id);
                                if ($upload_result['success']) {
                                    $attachments[] = [
                                        'filename' => $upload_result['filename'],
                                        'original_name' => $file_name,
                                        'file_type' => $file_type,
                                        'file_size' => $file_size
                                    ];
                                }
                            }
                        }
                    }
                }
                
                // Start transaction
                db()->beginTransaction();
                
                // Insert complaint
                $stmt = db()->prepare("
                    INSERT INTO complaints (
                        complaint_code, user_id, category_id, title, description, 
                        urgency, location, is_anonymous,attachments, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $complaint_code, $student_id, $category_id, $title, $description,
                    $urgency, $location, $anonymous, json_encode($attachments)
                ]);
                
                $complaint_id = db()->lastInsertId();

                // In-app notifications for admins (category admin + all super admins)
                notifyAdminsOfNewComplaint($complaint_id, $complaint_code, $title, $category_id, $urgency);
                
                // Insert attachments if any
                // if (!empty($attachments)) {
                //     $stmt = db()->prepare("
                //         INSERT INTO complaints(attachments)
                //         VALUES (?)
                //     ");
                    
                //     foreach ($attachments as $attachment) {
                //         $stmt->execute([$file_name]);

                //     }
                // }
                
                // Commit transaction
                db()->commit();
                
                // Send notification to category admin
                $stmt = db()->prepare("
                    SELECT a.email, a.full_name 
                    FROM admins a 
                    JOIN categories c ON a.id = c.admin_id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$category_id]);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    require_once '../../config/mail_config.php';
                    $mailer = getMailer();
                    // Send email notification
                    $coomplaint_url = APP_URL . '/pages/admin/complaints.php';
                    $mail_sent = $mailer->sendEmailNotification(
                        $admin['email'],
                        $admin['full_name'],
                        $complaint_code,
                        $title,
                        array_column($categories, 'name', 'id')[$category_id] ?? 'Unknown',
                        $urgency,
                        $complaint_id,
                        $coomplaint_url
                    );
                    

                    if ($mail_sent) {
                        error_log("Notification email sent to admin: " . $admin['email']);
                    }
                }
                
                // Log the submission
                logActivity(
                    'complaint_submitted',
                    "Submitted complaint #{$complaint_code}",
                    $student_id,
                    'student'
                );
                
                $success = 'Complaint submitted successfully! Your complaint ID is: ' . $complaint_code;
                
                // Clear form if needed
                if (!isset($_POST['submit_another'])) {
                    $_POST = [];
                }
            }
            
        } catch (Exception $e) {
            // Rollback if transaction active
            try { if (db() && method_exists(db(), 'inTransaction') && db()->inTransaction()) { db()->rollBack(); } } catch (\Throwable $ex) {}
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            error_log("Complaint submission error: {$msg} | Trace: {$trace}");
            // TEMPORARY: include exception message to help debugging (remove in production)
            $error = 'Failed to submit complaint. Please try again. Debug: ' . $msg;
        }
// ...existing code...
//         } catch (PDOException $e) {
// -            db()->rollBack();
// -            error_log("Complaint submission error: " . $e->getMessage());
// -            $error = 'Failed to submit complaint. Please try again.';
// +            // Rollback if transaction active
// +            try { if (db() && method_exists(db(), 'inTransaction') && db()->inTransaction()) { db()->rollBack(); } } catch (\Throwable $ex) {}
// +            $msg = $e->getMessage();
// +            $trace = $e->getTraceAsString();
// +            error_log("Complaint submission error: {$msg} | Trace: {$trace}");
// +            // TEMPORARY: include exception message to help debugging (remove in production)
// +            $error = 'Failed to submit complaint. Please try again. Debug: ' . $msg;
//          }
 // ...existing code...
}}

// Generate CSRF token
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta name="app-url" content="/complaint-system">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
    <style>
        .submit-container {
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
        }

        .submission-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3rem;
            position: relative;
        }

        .submission-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }


        /*=============== DARK THEME CODE HERE========== */
        
/* Dark Theme Variables */
* {
    --primary-color: #7c93fb;
    --primary-dark: #667eea;
    --secondary-color: #9f7aea;
    
    /* Background Colors */
    --bg-primary: #1a202c;
    --bg-secondary: #2d3748;
    --bg-tertiary: #4a5568;
    
    /* Text Colors */
    --text-primary: #f7fafc;
    --text-secondary: #e2e8f0;
    --text-muted: #a0aec0;
    
    /* Border Colors */
    --border-color: #4a5568;
    --border-light: #2d3748;
    
    /* Shadow */
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.25);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.25);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.3);
    
    /* Glassmorphism */
    --glass-bg: rgba(26, 32, 44, 0.95);
    --glass-border: rgba(255, 255, 255, 0.1);
}

/* ...existing code... */
/* Fix: ensure radio/checkbox stays on top of category card */
.category-options .category-card { position: relative; z-index: 1; }
.category-options .category-card input[type="radio"],
.category-options .category-card input[type="checkbox"] {
    position: relative;
    z-index: 5;
    margin: 0.25rem;
    pointer-events: auto;
    background: transparent;
}
/* if labels use pseudo elements, ensure they don't capture clicks */
.category-options .category-card label { position: relative; z-index: 4; pointer-events: none; }
.category-options .category-card input[type="radio"] + label,
.category-options .category-card input[type="checkbox"] + label { pointer-events: auto; }
/* ...existing code... */

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: var(--text-secondary);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.active .step-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
            color: white;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.2);
        }

        .step-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .complaint-form {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-label .required {
            color: #f56565;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-help {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

           
                .category-options {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                    gap: 1rem;
                }
        
                .category-option {
                    position: relative;
                    display: flex;
                    flex-direction: column;
                }
        
                .category-option input[type="radio"] {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    width: 20px;
                    height: 20px;
                    opacity: 1;
                    z-index: 10;
                    cursor: pointer;
                    margin: 0;
                }
        
                .category-card {
                    padding: 1rem;
                    border: 2px solid var(--border-color);
                    border-radius: var(--radius-md);
                    background: var(--bg-secondary);
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-align: center;
                    padding-top: 2.5rem;
                    flex-grow: 1;
                    position: relative;
                    z-index: 1;
                }
        
                .category-card:hover {
                    border-color: var(--primary-color);
                    transform: translateY(-2px);
                }
        
                .category-option input[type="radio"]:checked + .category-card {
                    border-color: var(--primary-color);
                    background: rgba(102, 126, 234, 0.1);
                    box-shadow: 0 4px 6px rgba(102, 126, 234, 0.1);
                    border-width: 3px;
                }
        
                .category-icon {
                    width: 48px;
                    height: 48px;
                    border-radius: var(--radius-md);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 0.75rem;
                    color: white;
                    font-size: 1.25rem;
                }
        
                .category-name {
                    font-weight: 600;
                    margin-bottom: 0.25rem;
                    color: var(--text-primary);
                }
        
                .category-desc {
                    font-size: 0.875rem;
                    color: var(--text-secondary);
                }
        
                .urgency-options {
                    display: flex;
                    gap: 1rem;
                    flex-wrap: wrap;
                }
        
                .urgency-option {
                    flex: 1;
                    min-width: 120px;
                    position: relative;
                    display: flex;
                    flex-direction: column;
                }
        
                .urgency-option input[type="radio"] {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    width: 18px;
                    height: 18px;
                    opacity: 1;
                    z-index: 10;
                    cursor: pointer;
                    margin: 0;
                }
        
                .urgency-card {
                    padding: 1rem;
                    border: 2px solid var(--border-color);
                    border-radius: var(--radius-md);
                    background: var(--bg-secondary);
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-align: center;
                    padding-top: 2.5rem;
                    flex-grow: 1;
                    position: relative;
                    z-index: 1;
                }
        
                .urgency-card:hover {
                    transform: translateY(-2px);
                }
        
                .urgency-option input[type="radio"]:checked + .urgency-card {
                    border-color: var(--primary-color);
                    background: rgba(102, 126, 234, 0.1);
                    border-width: 3px;
                }
        
                .urgency-critical .urgency-card {
                    border-left: 4px solid #f56565;
                }
        
                .urgency-high .urgency-card {
                    border-left: 4px solid #ed8936;
                }
        
                .urgency-medium .urgency-card {
                    border-left: 4px solid #ecc94b;
                }
        
                .urgency-low .urgency-card {
                    border-left: 4px solid #48bb78;
                }
        
                .urgency-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 0.75rem;
                    color: white;
                    font-size: 1rem;
                }
        
                .urgency-critical .urgency-icon { background: #f56565; }
                .urgency-high .urgency-icon { background: #ed8936; }
                .urgency-medium .urgency-icon { background: #ecc94b; }
                .urgency-low .urgency-icon { background: #48bb78; }
        
                .urgency-name {
                    font-weight: 600;
                    margin-bottom: 0.25rem;
                    color: var(--text-primary);
                }
        
                .urgency-desc {
                    font-size: 0.75rem;
                    color: var(--text-secondary);
                }

        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 3rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-secondary);
        }

        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .upload-text {
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .upload-text strong {
            color: var(--primary-color);
        }

        .file-list {
            margin-top: 1rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-icon {
            color: var(--primary-color);
        }

        .file-name {
            font-weight: 500;
        }

        .file-size {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .file-remove {
            color: #f56565;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 1rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
        }

        .checkbox-label {
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .char-counter {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .char-counter.warning {
            color: #ed8936;
        }

        .char-counter.error {
            color: #f56565;
        }

        .success-card {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            animation: slideDown 0.5s ease;
        }

        .success-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .success-code {
            font-family: monospace;
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            margin: 1rem 0;
            display: inline-block;
        }

        .whats-next {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--glass-border);
            margin-top: 2rem;
        }

        .whats-next h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .next-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .next-step {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }

        .next-step:hover {
            transform: translateY(-2px);
            background: var(--bg-tertiary);
        }

        .next-step i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .submit-container {
                padding: 1rem;
            }
            
            .submission-steps {
                flex-direction: column;
                gap: 2rem;
            }
            
            .submission-steps::before {
                display: none;
            }
            
            .step {
                display: flex;
                align-items: center;
                gap: 1rem;
                text-align: left;
            }
            
            .step-icon {
                margin: 0;
                flex-shrink: 0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .category-options, .urgency-options {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Student Navigation -->
    <?php include '../../includes/layout/student-nav.php'; ?>

    <!-- Main Content -->
    <div class="submit-container">
        <!-- Header -->
        <div class="page-header">
            <h1>Submit a Complaint</h1>
            <p>Report issues anonymously or with your identity protected</p>
        </div>

        <!-- Submission Steps -->
        <div class="submission-steps">
            <div class="step active">
                <div class="step-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="step-label">Complaint Details</div>
            </div>
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-category"></i>
                </div>
                <div class="step-label">Category & Urgency</div>
            </div>
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-paperclip"></i>
                </div>
                <div class="step-label">Attachments</div>
            </div>
            <div class="step">
                <div class="step-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="step-label">Review & Submit</div>
            </div>
        </div>

        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Complaint Submitted Successfully!</h2>
                <p>Your complaint has been received and is under review.</p>
                
                <div class="success-code">
                    <?php 
                    // Extract complaint code from success message
                    preg_match('/[A-Z]{3}-\d{6}/', $success, $matches);
                    echo $matches[0] ?? 'COMPLAINT-CODE';
                    ?>
                </div>
                
                <p>Save this code to track your complaint status.</p>
                
                <div class="flex gap-3 justify-center mt-4">
                    <a href="dashboard.php" class="btn btn-light btn-secondary">
                        <i class="fas fa-home"></i> Return to Dashboard
                    </a>
                    <a href="my-complaints.php" class="btn btn-secondary btn-outline-light" >
                        <i class="fas fa-list"></i> View My Complaints
                    </a>
                    <a href="submit.php" class="btn btn-secondary btn-outline-light">
                        <i class="fas fa-plus"></i> Submit Another
                    </a>
                </div>
            </div>

            <!-- What's Next -->
            <div class="whats-next">
                <h3>What happens next?</h3>
                <p>Here's what to expect after submitting your complaint:</p>
                
                <div class="next-steps">
                    <div class="next-step">
                        <i class="fas fa-clock"></i>
                        <h4>Review Process</h4>
                        <p class="text-sm">Your complaint will be reviewed by the category administrator within 24-48 hours.</p>
                    </div>
                    
                    <div class="next-step">
                        <i class="fas fa-bell"></i>
                        <h4>Status Updates</h4>
                        <p class="text-sm">You'll receive notifications when your complaint status changes.</p>
                    </div>
                    
                    <div class="next-step">
                        <i class="fas fa-comments"></i>
                        <h4>Follow-up</h4>
                        <p class="text-sm">Administrators may contact you for additional information if needed.</p>
                    </div>
                    
                    <div class="next-step">
                        <i class="fas fa-chart-line"></i>
                        <h4>Track Progress</h4>
                        <p class="text-sm">Monitor your complaint status in the "My Complaints" section.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Complaint Form -->
            <form method="POST" enctype="multipart/form-data" class="complaint-form" id="complaintForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <?php if ($error): ?>
                    <div class="alert alert-error mb-4">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Section 1: Basic Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Complaint Information
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Complaint Title <span class="required">*</span>
                        </label>
                        <input type="text" name="title" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                               placeholder="Briefly describe the issue" 
                               required minlength="10" maxlength="200">
                        <div class="form-help">Be specific and concise. 10-200 characters.</div>
                        <div class="char-counter" id="titleCounter">0/200</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Detailed Description <span class="required">*</span>
                        </label>
                        <div id="descriptionEditor" style="height: 200px;"></div>
                        <textarea name="description" id="descriptionText" style="display: none;" 
                                  required minlength="50"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="form-help">
                            Provide complete details about the issue. Include dates, locations, and any relevant information.
                            <span id="descriptionCounter" class="char-counter">0 characters</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Location (Optional)
                        </label>
                        <input type="text" name="location" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                               placeholder="e.g., Main Campus, Block A, Room 101">
                        <div class="form-help">Where did this issue occur?</div>
                    </div>
                </div>

                <!-- Section 2: Category & Urgency -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tags"></i>
                        Category & Urgency
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Category <span class="required">*</span>
                        </label>
                        <div class="category-options">
                            <?php if (empty($categories)): ?>
                                <div class="text-center py-4 text-gray-500">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <p>No categories available. Please contact administrator.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <div class="category-option">
                                        <input type="radio" name="category_id" value="<?php echo $category['id']; ?>" 
                                               id="category_<?php echo $category['id']; ?>" 
                                               required <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'checked' : ''; ?>>
                                        <label for="category_<?php echo $category['id']; ?>" class="category-card">
                                            <div class="category-icon" style="background: <?php echo $category['color']; ?>;">
                                                <?php 
                                                // Default icons based on category name
                                                $icon = 'fas fa-question-circle';
                                                $cat_name = strtolower($category['name']);
                                                if (strpos($cat_name, 'hostel') !== false) $icon = 'fas fa-bed';
                                                elseif (strpos($cat_name, 'campus') !== false) $icon = 'fas fa-university';
                                                elseif (strpos($cat_name, 'department') !== false) $icon = 'fas fa-graduation-cap';
                                                elseif (strpos($cat_name, 'security') !== false) $icon = 'fas fa-shield-alt';
                                                elseif (strpos($cat_name, 'library') !== false) $icon = 'fas fa-book';
                                                elseif (strpos($cat_name, 'transport') !== false) $icon = 'fas fa-bus';
                                                elseif (strpos($cat_name, 'health') !== false) $icon = 'fas fa-heartbeat';
                                                ?>
                                                <i class="<?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                            <div class="category-desc"><?php echo htmlspecialchars($category['description']); ?></div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Urgency Level <span class="required">*</span>
                        </label>
                        <div class="urgency-options">
                            <div class="urgency-option urgency-critical">
                                <input type="radio" name="urgency" value="critical" 
                                       id="urgency_critical" required <?php echo ($_POST['urgency'] ?? '') == 'critical' ? 'checked' : ''; ?>>
                                <label for="urgency_critical" class="urgency-card">
                                    <div class="urgency-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="urgency-name">Critical</div>
                                    <div class="urgency-desc">Needs immediate attention</div>
                                </label>
                            </div>
                            
                            <div class="urgency-option urgency-high">
                                <input type="radio" name="urgency" value="high" 
                                       id="urgency_high" <?php echo ($_POST['urgency'] ?? '') == 'high' ? 'checked' : ''; ?>>
                                <label for="urgency_high" class="urgency-card">
                                    <div class="urgency-icon">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                    <div class="urgency-name">High</div>
                                    <div class="urgency-desc">Address within 24 hours</div>
                                </label>
                            </div>
                            
                            <div class="urgency-option urgency-medium">
                                <input type="radio" name="urgency" value="medium" 
                                       id="urgency_medium" <?php echo ($_POST['urgency'] ?? 'medium') == 'medium' ? 'checked' : ''; ?>>
                                <label for="urgency_medium" class="urgency-card">
                                    <div class="urgency-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="urgency-name">Medium</div>
                                    <div class="urgency-desc">Address within 3 days</div>
                                </label>
                            </div>
                            
                            <div class="urgency-option urgency-low">
                                <input type="radio" name="urgency" value="low" 
                                       id="urgency_low" <?php echo ($_POST['urgency'] ?? '') == 'low' ? 'checked' : ''; ?>>
                                <label for="urgency_low" class="urgency-card">
                                    <div class="urgency-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="urgency-name">Low</div>
                                    <div class="urgency-desc">Address when possible</div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Attachments -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-paperclip"></i>
                        Attachments (Optional)
                    </h3>
                    
                    <div class="form-group">
                        <div class="file-upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <strong>Click to upload</strong> or drag and drop
                            </div>
                            <p class="text-sm text-gray-500 mb-2">Maximum 5 files, 10MB each</p>
                            <p class="text-xs text-gray-500">Supported: JPG, PNG, PDF, DOC, DOCX</p>
                            <input type="file" name="attachments[]" id="fileInput" multiple 
                                   style="display: none;" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </div>
                        
                        <div class="file-list" id="fileList"></div>
                    </div>
                </div>

                <!-- Section 4: Privacy Settings -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-secret"></i>
                        Privacy Settings
                    </h3>
                    
                    <div class="form-group">
                        <!-- <div class="checkbox-group">
                            <input type="checkbox" name="anonymous" id="anonymous" 
                                   class="checkbox-input" <?php echo isset($_POST['anonymous']) ? 'checked' : ''; ?>>
                            <label for="anonymous" class="checkbox-label">
                                Submit anonymously
                            </label>
                        </div> -->
                        <div class="form-help">
                            Your identity will be hidden from administrators. Only the system will know you submitted this complaint.
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <div>
                        <button type="submit" name="submit_another" value="1" class="btn btn-outline">
                            <i class="fas fa-plus"></i> Submit & Add Another
                        </button>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="button" class="btn btn-outline" onclick="previewComplaint()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="submit" name="submit" value="1" class="btn btn-gradient">
                            <i class="fas fa-paper-plane"></i> Submit Complaint
                        </button>
                    </div>
                </div>
            </form>

            <!-- Preview Modal -->
            <div class="modal" id="previewModal">
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Complaint Preview</h3>
                        <button type="button" class="modal-close" onclick="closePreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body" id="previewContent">
                        <!-- Preview will be loaded here -->
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rich Text Editor -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <script>
        // Initialize Quill Editor
        const quill = new Quill('#descriptionEditor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            },
            placeholder: 'Describe the issue in detail...'
        });
        
        // Sync Quill content with textarea
        quill.on('text-change', () => {
            const content = quill.root.innerHTML;
            document.getElementById('descriptionText').value = content;
            
            // Update character counter
            const text = quill.getText().trim();
            const counter = document.getElementById('descriptionCounter');
            counter.textContent = text.length + ' characters';
            
            if (text.length < 50) {
                counter.className = 'char-counter error';
            } else if (text.length < 100) {
                counter.className = 'char-counter warning';
            } else {
                counter.className = 'char-counter';
            }
        });
        
        // Initialize with existing content
        const existingContent = document.getElementById('descriptionText').value;
        if (existingContent) {
            quill.root.innerHTML = existingContent;
                // Trigger text-change to update counter
            // quill.emit('text-change');
            
        }
        
        // Title character counter
        const titleInput = document.querySelector('input[name="title"]');
        const titleCounter = document.getElementById('titleCounter');
        
        titleInput.addEventListener('input', function() {
            const length = this.value.length;
            titleCounter.textContent = length + '/200';
            
            if (length < 10) {
                titleCounter.className = 'char-counter error';
            } else if (length < 20) {
                titleCounter.className = 'char-counter warning';
            } else {
                titleCounter.className = 'char-counter';
            }
        });
        
        // Initialize title counter
        if (titleInput.value) {
            titleInput.dispatchEvent(new Event('input'));
        }
        
        // File Upload Handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const files = [];
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(fileListObj) {
            const newFiles = Array.from(fileListObj);
            const totalFiles = files.length + newFiles.length;
            
            if (totalFiles > 5) {
                showNotification('Maximum 5 files allowed', 'error');
                return;
            }
            
            newFiles.forEach(file => {
                if (file.size > 10 * 1024 * 1024) { // 10MB
                    showNotification(`File ${file.name} exceeds 10MB limit`, 'error');
                    return;
                }
                
                const validTypes = ['image/jpeg', 'image/png', 'application/pdf', 
                                   'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!validTypes.includes(file.type)) {
                    showNotification(`File ${file.name} has unsupported format`, 'error');
                    return;
                }
                
                files.push(file);
                renderFileItem(file);
            });
            
            // Update file input
            const dataTransfer = new DataTransfer();
            files.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
        
        function renderFileItem(file) {
            const fileId = Date.now() + Math.random();
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.dataset.id = fileId;
            
            const fileSize = formatFileSize(file.size);
            const fileIcon = getFileIcon(file.type);
            
            fileItem.innerHTML = `
                <div class="file-info">
                    <i class="fas ${fileIcon} file-icon"></i>
                    <div>
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${fileSize}</div>
                    </div>
                </div>
                <button type="button" class="file-remove" onclick="removeFile('${fileId}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            fileList.appendChild(fileItem);
        }
        
        function removeFile(fileId) {
            const fileItem = document.querySelector(`.file-item[data-id="${fileId}"]`);
            if (fileItem) {
                const fileName = fileItem.querySelector('.file-name').textContent;
                const fileIndex = files.findIndex(f => f.name === fileName);
                
                if (fileIndex > -1) {
                    files.splice(fileIndex, 1);
                    
                    // Update file input
                    const dataTransfer = new DataTransfer();
                    files.forEach(file => dataTransfer.items.add(file));
                    fileInput.files = dataTransfer.files;
                }
                
                fileItem.remove();
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function getFileIcon(mimeType) {
            if (mimeType.startsWith('image/')) return 'fa-image';
            if (mimeType === 'application/pdf') return 'fa-file-pdf';
            if (mimeType.includes('word') || mimeType.includes('document')) return 'fa-file-word';
            return 'fa-file';
        }
        
        // Form Validation
        const form = document.getElementById('complaintForm');
        form.addEventListener('submit', function(e) {
            // Validate description
            const description = quill.getText().trim();
            if (description.length < 50) {
                e.preventDefault();
                showNotification('Description must be at least 50 characters', 'error');
                return;
            }
            
            // Validate category
            const categorySelected = document.querySelector('input[name="category_id"]:checked');
            if (!categorySelected) {
                e.preventDefault();
                showNotification('Please select a category', 'error');
                return;
            }
            
            // Validate urgency
            const urgencySelected = document.querySelector('input[name="urgency"]:checked');
            if (!urgencySelected) {
                e.preventDefault();
                showNotification('Please select urgency level', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                submitBtn.disabled = true;
            }
        });
        
        // Preview Complaint
        function previewComplaint() {
            const formData = new FormData(form);
            const previewContent = document.getElementById('previewContent');
            
            // Basic validation
            if (!formData.get('title') || !quill.getText().trim()) {
                showNotification('Please fill in title and description first', 'error');
                return;
            }
            
            // Build preview HTML
            const categoryId = formData.get('category_id');
            const categoryName = categoryId ? document.querySelector(`label[for="category_${categoryId}"] .category-name`).textContent : 'Not selected';
            
            const urgency = formData.get('urgency');
            const urgencyName = urgency ? document.querySelector(`label[for="urgency_${urgency}"] .urgency-name`).textContent : 'Not selected';
            
            const previewHTML = `
                <div class="preview-container">
                    <h4 class="preview-title">${formData.get('title') || 'No title'}</h4>
                    
                    <div class="preview-section">
                        <h5><i class="fas fa-align-left"></i> Description</h5>
                        <div class="preview-description">${quill.root.innerHTML || 'No description provided'}</div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="preview-section">
                            <h5><i class="fas fa-tag"></i> Category</h5>
                            <p>${categoryName}</p>
                        </div>
                        <div class="preview-section">
                            <h5><i class="fas fa-exclamation-circle"></i> Urgency</h5>
                            <p>${urgencyName}</p>
                        </div>
                    </div>
                    
                    ${formData.get('location') ? `
                    <div class="preview-section">
                        <h5><i class="fas fa-map-marker-alt"></i> Location</h5>
                        <p>${formData.get('location')}</p>
                    </div>
                    ` : ''}
                    
                    ${files.length > 0 ? `
                    <div class="preview-section">
                        <h5><i class="fas fa-paperclip"></i> Attachments (${files.length})</h5>
                        <ul class="list-disc pl-5">
                            ${files.map(file => `<li>${file.name} (${formatFileSize(file.size)})</li>`).join('')}
                        </ul>
                    </div>
                    ` : ''}
                    
                    <div class="preview-section">
                        <h5><i class="fas fa-user-secret"></i> Privacy</h5>
                        <p>${formData.get('anonymous') ? 'Anonymous Submission' : 'Identity Visible to Admins'}</p>
                    </div>
                    
                    <div class="preview-footer mt-6 pt-4 border-t">
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-info-circle"></i>
                            This is a preview of how your complaint will appear to administrators.
                        </p>
                    </div>
                </div>
            `;
            
            previewContent.innerHTML = previewHTML;
            document.getElementById('previewModal').classList.add('active');
        }
        
        function closePreview() {
            document.getElementById('previewModal').classList.remove('active');
        }
        
        // Notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `toast toast-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>
