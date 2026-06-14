<?php
require_once '../../config/constants.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/auth/session.inc.php';

//New code 

// require_once ROOT_PATH . '/includes/utilities/helpers.php';
// require_once ROOT_PATH . '/includes/auth/session.inc.php';

// if (isLoggedIn()) {
//     redirectBasedOnRole();
// }

// $error = '';
// $success = '';

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once ROOT_PATH . '/includes/auth/register.inc.php';
// }







// Redirect if already logged in
if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../includes/auth/register.inc.php';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
            content: '';
            position: absolute;
            width: 300%;
            height: 300%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(245, 101, 101, 0.3) 0%, transparent 50%);
            animation: gradient-shift 15s ease infinite;
        }

        .register-card {
            width: 100%;
            max-width: 500px;
            z-index: 1;
            animation: scaleIn 0.5s ease-out;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-secondary);
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: var(--border-color);
            z-index: 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
            position: relative;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .step.completed .step-number {
            background: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .step-label {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 500;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-step.active {
            display: block;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 2;
        }

        .input-with-icon .form-control {
            padding-left: 3rem;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            gap: 1rem;
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            height: 4px;
            background: var(--bg-tertiary);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .strength-text {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .requirements {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .requirement i {
            font-size: 0.875rem;
        }

        .requirement.met {
            color: var(--success-color);
        }

        .login-link {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
        }

        .login-link a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
        }

        .toggle-btn {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(30deg);
        }

        @media (max-width: 640px) {
            .register-card {
                padding: 1.5rem;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
            
            .steps::before {
                left: 30px;
                right: 30px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Theme Toggle -->
        <div class="theme-toggle">
            <button class="toggle-btn" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>

        <!-- Register Card -->
        <div class="glass-card register-card">
            <!-- Logo -->
            <div class="logo">
                <h1><i class="fas fa-comment-dots"></i> HTU Complaints</h1>
                <p>Create your anonymous account</p>
            </div>

            <!-- Steps Indicator -->
            <div class="steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <span class="step-label">Account</span>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <span class="step-label">Personal</span>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <span class="step-label">Verify</span>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error animate-slide-in-down">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success animate-slide-in-down">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Step 1: Account Information -->
                <div class="form-step active" id="step1">
                    <div class="form-group">
                        <label class="form-label">Index Number</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input 
                                type="text" 
                                name="index_number" 
                                class="form-control glass-input" 
                                placeholder="0323080303"
                                required
                                pattern="[0-9]{10}"
                                title="Enter a valid 10-digit index number"
                                autofocus
                            >
                        </div>
                        <small class="text-muted">Your 10-digit HTU index number</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                name="email" 
                                class="form-control glass-input" 
                                placeholder="0323080303@htu.edu.gh"
                                required
                                readonly
                                id="emailField"
                            >
                        </div>
                        <small class="text-muted">Auto-generated from your index number</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                name="password" 
                                class="form-control glass-input" 
                                placeholder="Create a strong password"
                                required
                                minlength="6"
                                id="passwordField"
                            >
                            <button 
                                type="button" 
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                onclick="togglePassword('passwordField')"
                            >
                                <i class="fas fa-eye" id="passwordIcon"></i>
                            </button>
                        </div>
                        
                        <!-- Password Strength -->
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Password strength</div>
                        </div>

                        <!-- Password Requirements -->
                        <div class="requirements">
                            <div class="requirement" id="reqLength">
                                <i class="fas fa-circle" id="reqLengthIcon"></i>
                                <span>At least 6 characters</span>
                            </div>
                            <div class="requirement" id="reqUpper">
                                <i class="fas fa-circle" id="reqUpperIcon"></i>
                                <span>One uppercase letter</span>
                            </div>
                            <div class="requirement" id="reqNumber">
                                <i class="fas fa-circle" id="reqNumberIcon"></i>
                                <span>One number</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="form-control glass-input" 
                                placeholder="Confirm your password"
                                required
                                id="confirmPasswordField"
                            >
                            <button 
                                type="button" 
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                onclick="togglePassword('confirmPasswordField')"
                            >
                                <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                            </button>
                        </div>
                        <div class="text-red-500 text-sm mt-1" id="passwordMatch"></div>
                    </div>

                    <div class="form-actions">
                        <div></div>
                        <button type="button" class="btn btn-gradient" onclick="nextStep()">
                            Continue <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="form-step" id="step2">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input 
                                type="text" 
                                name="full_name" 
                                class="form-control glass-input" 
                                placeholder="Enter your full name"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <div class="input-with-icon">
                            <i class="fas fa-phone"></i>
                            <input 
                                type="tel" 
                                name="phone" 
                                class="form-control glass-input" 
                                placeholder="0241234567"
                                pattern="[0-9]{10}"
                                title="Enter a valid 10-digit Ghanaian phone number"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-control glass-select" required>
                            <option value="">Select your department</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Electrical Engineering">Electrical Engineering</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                            <option value="Accounting">Accounting</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-control glass-select" required>
                            <option value="">Select your level</option>
                            <option value="100">Level 100</option>
                            <option value="200">Level 200</option>
                            <option value="300">Level 300</option>
                            <option value="400">Level 400</option>
                            <option value="Graduate">Graduate</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="prevStep()">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-user-plus mr-2"></i> Create Account
                        </button>
                    </div>
                </div>
            </form>

            <!-- Login Link -->
            <div class="login-link">
                <p>
                    Already have an account? 
                    <a href="<?php echo APP_URL; ?>/pages/auth/login.php">Sign in here</a>
                </p>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 3;

        function updateSteps() {
            document.querySelectorAll('.step').forEach((step, index) => {
                const stepNum = parseInt(step.dataset.step);
                if (stepNum < currentStep) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (stepNum === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });

            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active');
            });
            document.getElementById(`step${currentStep}`).classList.add('active');
        }

        function nextStep() {
            if (validateStep(currentStep)) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateSteps();
                }
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateSteps();
            }
        }

        function validateStep(step) {
            if (step === 1) {
                const indexNumber = document.querySelector('input[name="index_number"]');
                const password = document.getElementById('passwordField');
                const confirmPassword = document.getElementById('confirmPasswordField');
                
                // Validate index number
                if (!indexNumber.value.trim() || !/^\d{10}$/.test(indexNumber.value)) {
                    showError(indexNumber, 'Please enter a valid 10-digit index number');
                    return false;
                }
                
                // Validate password
                if (!password.value.trim() || password.value.length < 6) {
                    showError(password, 'Password must be at least 6 characters');
                    return false;
                }
                
                // Validate password match
                if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, 'Passwords do not match');
                    return false;
                }
                
                // Auto-generate email
                const emailField = document.getElementById('emailField');
                emailField.value = indexNumber.value + '@htu.edu.gh';
            }
            
            return true;
        }

        function showError(input, message) {
            // Remove any existing error
            const existingError = input.parentElement.querySelector('.error-text');
            if (existingError) existingError.remove();
            
            // Add error message
            const error = document.createElement('div');
            error.className = 'error-text text-red-500 text-sm mt-1';
            error.textContent = message;
            input.parentElement.appendChild(error);
            
            // Add error class to input
            input.classList.add('border-red-500');
            
            // Scroll to error
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Remove error on input
            input.addEventListener('input', function() {
                error.remove();
                input.classList.remove('border-red-500');
            }, { once: true });
        }

        // Password strength checker
        document.getElementById('passwordField').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            // Requirements
            const hasLength = password.length >= 6;
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            
            // Update requirement indicators
            updateRequirement('reqLength', hasLength);
            updateRequirement('reqUpper', hasUpper);
            updateRequirement('reqNumber', hasNumber);
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength++;
            if (hasUpper) strength++;
            if (hasNumber) strength++;
            
            // Update strength bar and text
            const strengthPercent = (strength / 3) * 100;
            strengthFill.style.width = strengthPercent + '%';
            
            // Set colors and text
            if (strength === 0) {
                strengthFill.style.background = '#f56565';
                strengthText.textContent = 'Very Weak';
                strengthText.style.color = '#f56565';
            } else if (strength === 1) {
                strengthFill.style.background = '#ed8936';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#ed8936';
            } else if (strength === 2) {
                strengthFill.style.background = '#ecc94b';
                strengthText.textContent = 'Fair';
                strengthText.style.color = '#ecc94b';
            } else {
                strengthFill.style.background = '#48bb78';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#48bb78';
            }
            
            // Check password match
            const confirmPassword = document.getElementById('confirmPasswordField');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (confirmPassword.value) {
                if (password === confirmPassword.value) {
                    passwordMatch.textContent = '✓ Passwords match';
                    passwordMatch.style.color = '#48bb78';
                } else {
                    passwordMatch.textContent = '✗ Passwords do not match';
                    passwordMatch.style.color = '#f56565';
                }
            }
        });

        // Confirm password checker
        document.getElementById('confirmPasswordField').addEventListener('input', function(e) {
            const password = document.getElementById('passwordField').value;
            const confirmPassword = e.target.value;
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (confirmPassword) {
                if (password === confirmPassword) {
                    passwordMatch.textContent = '✓ Passwords match';
                    passwordMatch.style.color = '#48bb78';
                } else {
                    passwordMatch.textContent = '✗ Passwords do not match';
                    passwordMatch.style.color = '#f56565';
                }
            } else {
                passwordMatch.textContent = '';
            }
        });

        function updateRequirement(id, met) {
            const req = document.getElementById(id);
            const icon = document.getElementById(id + 'Icon');
            
            if (met) {
                req.classList.add('met');
                icon.className = 'fas fa-check-circle';
                icon.style.color = '#48bb78';
            } else {
                req.classList.remove('met');
                icon.className = 'fas fa-circle';
                icon.style.color = '#a0aec0';
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId === 'passwordField' ? 'passwordIcon' : 'confirmPasswordIcon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Auto-format index number to email
        document.querySelector('input[name="index_number"]').addEventListener('input', function(e) {
            const emailField = document.getElementById('emailField');
            if (/^\d{10}$/.test(e.target.value)) {
                emailField.value = e.target.value+'@htu.edu.gh';
            } else {
                emailField.value = '';
            }
        });
    </script>
</body>
</html>