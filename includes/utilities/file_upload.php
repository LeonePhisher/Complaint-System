<?php
// File Upload Utilities

/**
 * Sanitize file name
 */
function sanitizeFileName($filename) {
    // Remove special characters and spaces
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    // Remove multiple dots
    $filename = preg_replace('/\.+/', '.', $filename);
    // Remove leading/trailing dots
    $filename = trim($filename, '.');
    // Limit length
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = substr($name, 0, 50);
    
    return $name . ($ext ? '.' . $ext : '');
}

/**
 * Validate uploaded file
 */
function validateUploadedFile($tmp_name, $filename, $file_size, $file_type) {
    // Check file size
    if ($file_size > MAX_FILE_SIZE) {
        error_log("File size exceeds maximum: {$filename}");
        return false;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        error_log("File type not allowed: {$extension}");
        return false;
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    ];
    
    if (isset($allowed_mimes[$extension]) && !in_array($mime_type, $allowed_mimes[$extension])) {
        error_log("MIME type mismatch for {$filename}: got {$mime_type}");
        return false;
    }
    
    return true;
}

/**
 * Upload complaint file
 */
function uploadComplaintFile($tmp_name, $filename, $student_id) {
    try {
        // Create directory if it doesn't exist
        $upload_dir = COMPLAINT_UPLOADS . $student_id . '/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $unique_filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $file_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $file_path)) {
            return [
                'success' => true,
                'filename' => $unique_filename,
                'path' => $file_path
            ];
        } else {
            error_log("Failed to move uploaded file: {$tmp_name}");
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error uploading file'];
    }
}

/**
 * Delete complaint file
 */
function deleteComplaintFile($filename, $student_id) {
    try {
        $file_path = COMPLAINT_UPLOADS . $student_id . '/' . $filename;
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("File delete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get safe file download path
 */
function getSafeFilePath($filename, $student_id) {
    $file_path = COMPLAINT_UPLOADS . $student_id . '/' . basename($filename);
    
    // Security check: ensure file is within allowed directory
    $real_path = realpath($file_path);
    $upload_dir = realpath(COMPLAINT_UPLOADS . $student_id . '/');
    
    if ($real_path && $upload_dir && strpos($real_path, $upload_dir) === 0) {
        return $real_path;
    }
    
    return false;
}
?>
