<?php
// Mail Configuration using PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Ensure ROOT_PATH is defined (this file can be loaded early via Composer autoload files)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Load Composer autoloader if needed
if (!class_exists(PHPMailer::class)) {
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

if (!function_exists('loadDotEnvIfPresent')) {
    function loadDotEnvIfPresent($path) {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        if (!is_readable($path)) return;

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            if ($key === '' || getenv($key) !== false) continue;

            // strip surrounding quotes
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            // set env for this process
            $_ENV[$key] = $val;
            @putenv($key . '=' . $val);
        }
    }
}

// Load .env values into getenv() for SMTP_* keys (if present)
loadDotEnvIfPresent(ROOT_PATH . '/.env');

if (!class_exists('Mailer')) {
class Mailer {
    private PHPMailer $mail;
    private array $config;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->config = $this->getConfig();
        $this->configure();
    }

    private function envOrNull(string $key): ?string {
        $v = getenv($key);
        if ($v === false) return null;
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
    private function getConfig(): array {
        // NOTE: Per project requirement, ignore SMTP configuration stored in the database/settings.
        // Only use environment variables (.env) and constant fallbacks.
        $host = $this->envOrNull('SMTP_HOST')
            ?? (defined('SMTP_HOST') ? SMTP_HOST : null)
            ?? 'smtp.gmail.com';

        $port = (int)($this->envOrNull('SMTP_PORT')
            ?? (defined('SMTP_PORT') ? SMTP_PORT : 587));
        if ($port <= 0) $port = 587;

        $username = $this->envOrNull('SMTP_USERNAME')
            ?? (defined('SMTP_USERNAME') ? (string)SMTP_USERNAME : "boysboysdev@gmail.com")
            ?? null;

        $password = $this->envOrNull('SMTP_PASSWORD')
            ?? (defined('SMTP_PASSWORD') ? (string)SMTP_PASSWORD : 'ravx sort hiao kdkt')
            ?? null;


        // Gmail app passwords are often copied with spaces (e.g. 'xxxx xxxx xxxx xxxx').
        // Normalize for Gmail/Google SMTP to prevent auth failures.
        if (!empty($password)) {
            $isGmailHost = str_contains(strtolower((string)$host), 'gmail') || str_contains(strtolower((string)$host), 'google');
            $isGmailUser = str_ends_with(strtolower((string)($username ?? '')), '@gmail.com');
            if (($isGmailHost || $isGmailUser) && preg_match('/\s/', (string)$password)) {
                $password = preg_replace('/\s+/', '', (string)$password);
            }
        }

        $smtp_secure = strtolower((string)($this->envOrNull('SMTP_SECURE')
            ?? $this->envOrNull('SMTP_ENCRYPTION')
            ?? (defined('SMTP_SECURE') ? (string)SMTP_SECURE : null)
            ?? 'tls'));

        // Normalize secure value for PHPMailer
        if ($smtp_secure === 'starttls') $smtp_secure = 'tls';
        if (!in_array($smtp_secure, ['tls', 'ssl', ''], true)) $smtp_secure = 'tls';

        $from_email = $this->envOrNull('EMAIL_FROM')
            ?? $this->envOrNull('SMTP_FROM')
            ?? (defined('EMAIL_FROM') ? (string)EMAIL_FROM : null)
            ?? $username
            ?? '';

        $from_name = $this->envOrNull('EMAIL_FROM_NAME')
            ?? $this->envOrNull('SMTP_FROM_NAME')
            ?? (defined('EMAIL_FROM_NAME') ? (string)EMAIL_FROM_NAME : null)
            ?? (defined('APP_NAME') ? APP_NAME : 'HTU Complaint System');


        // Gmail/Google SMTP may reject a From address that doesn't match the authenticated account.
        // Prefer the authenticated username for From.
        if (!empty($username) && !empty($host)) {
            $hostLower = strtolower((string)$host);
            if (str_contains($hostLower, 'gmail') || str_contains($hostLower, 'google')) {
                $from_email = (string)$from_email;
                $usernameEmail = (string)$username;
                if ($from_email !== '' && strcasecmp($from_email, $usernameEmail) !== 0) {
                    $from_email = $usernameEmail;
                }
            }
        }

        // Use configured SMTP_FROM/EMAIL_FROM as Reply-To when it differs from the SMTP-authenticated From.
        $reply_to_email = $this->envOrNull('SMTP_FROM')
            ?? $this->envOrNull('EMAIL_FROM')
            ?? $from_email;
        $reply_to_name = $this->envOrNull('SMTP_FROM_NAME')
            ?? $this->envOrNull('EMAIL_FROM_NAME')
            ?? $from_name;

        $debug = (int)($this->envOrNull('SMTP_DEBUG') ?? 0);
        $timeout = (int)($this->envOrNull('SMTP_TIMEOUT') ?? 20);

        return [
            'host' => 'smtp.gmail.com',
            'port' => 456,
            'username' =>  'boysboysdev@gmail.com',
            'password' =>  'ravx sort hiao kdkt',
            'from_email' => 'boysboysdev@gmail.com',
            'from_name' => 'HTU complaint System',
            'reply_to_email' => 'boysboysdev@gmail.com',
            'reply_to_name' => 'HTU Complaint System',
            'smtp_secure' => PHPMailer::ENCRYPTION_SMTPS,
            'debug' => SMTP::DEBUG_OFF,
            'timeout' => 30,
        ];
    }

    private function configure(): void {
        $this->mail->isSMTP();
        $this->mail->Host = $this->config['host'];
        $this->mail->Port = (int)$this->config['port'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->config['username'];
        $this->mail->Password = $this->config['password'];

        // Use PHPMailer constants when possible
        if ($this->config['smtp_secure'] === 'ssl') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($this->config['smtp_secure'] === 'tls') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mail->SMTPSecure = '';
            $this->mail->SMTPAutoTLS = false;
        }

        $this->mail->Timeout = max(5, (int)$this->config['timeout']);
        $this->mail->SMTPKeepAlive = false;

        // Never echo SMTP debug to the browser; log it instead.
        $this->mail->SMTPDebug = max(0, (int)$this->config['debug']);
        $logFile = ROOT_PATH . '/logs/smtp.log';
        $this->mail->Debugoutput = function ($str, $level) use ($logFile) {
            $line = date('Y-m-d H:i:s') . ' SMTP[' . $level . ']: ' . trim((string)$str) . "\n";
            @file_put_contents($logFile, $line, FILE_APPEND);
            error_log(trim($line));
        };

        // Set From/Reply-To
        if ($this->config['from_email'] !== '') {
            $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $replyEmail = (string)($this->config['reply_to_email'] ?? $this->config['from_email']);
            $replyName = (string)($this->config['reply_to_name'] ?? $this->config['from_name']);
            if ($replyEmail !== '') {
                $this->mail->addReplyTo($replyEmail, $replyName);
            }

        }
    }

    public function sendVerificationEmail($to, $name, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);

            $this->mail->isHTML(true);
            $this->mail->Subject = 'Verify Your Account - HTU Complaint System';

            $verificationLink = (defined('APP_URL') ? APP_URL : '') . '/pages/auth/verify.php?token=' . $token;

            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>HTU Complaint System</h1>
                    </div>
                    <div class='content'>
                        <h2>Welcome, {$name}!</h2>
                        <p>Thank you for registering. Please verify your email address to activate your account.</p>
                        <p>Your verification code: <strong>{$token}</strong></p>
                        <p>Or click the button below:</p>
                        <a href='{$verificationLink}' class='btn'>Verify Account</a>
                        <p>This link will expire in 24 hours.</p>
                        <p>If you didn't create an account, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $this->mail->Body = $message;
            $this->mail->AltBody = "Verify your account by visiting: {$verificationLink}\n\nVerification Code: {$token}";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error (verification): ' . $this->mail->ErrorInfo . ' | ' . $e->getMessage());
            return false;
        }
    }

    public function sendEmailNotification($to, $name, $complaint_code, $title, $category, $urgency, $complaint_id, $complaintLink) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $name);

            $this->mail->isHTML(true);
            $this->mail->Subject = 'New Complaint Submitted - HTU Complaint System';

            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>HTU Complaint System</h1>
                    </div>
                    <div class='content'>
                        <h2>New Complaint Submitted</h2>
                        <p>Dear {$name},</p>
                        <p>A new complaint has been submitted:</p>
                        <ul>
                            <li><strong>Complaint Code:</strong> {$complaint_code}</li>
                            <li><strong>Title:</strong> {$title}</li>
                            <li><strong>Category:</strong> {$category}</li>
                            <li><strong>Urgency:</strong> {$urgency}</li>
                        </ul>
                        <p>Please review the complaint details by clicking the button below:</p>
                        <a href='{$complaintLink}' class='btn'>View Complaint Details</a>
                        <p>This link will expire in 24 hours.</p>
                        <p>If you didn't create an account, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $this->mail->Body = $message;
            $this->mail->AltBody = "View complaint details by visiting: {$complaintLink}\n\nComplaint Code: {$complaint_code}\nTitle: {$title}\nCategory: {$category}\nUrgency: {$urgency}";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error (complaint notification): ' . $this->mail->ErrorInfo . ' | ' . $e->getMessage());
            return false;
        }
    }

    public function sendNotification($to, $subject, $message, $isHTML = true, $allowFallback = true) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->isHTML($isHTML);
            $this->mail->Body = $message;

            if (!$isHTML) {
                $this->mail->AltBody = $message;
            }

            return $this->mail->send();
        } catch (Exception $e) {
            $info = $this->mail->ErrorInfo ?: $e->getMessage();
            error_log('SMTP Error: ' . $info);


            // Also write to logs/smtp.log for easier debugging
            try {
                $logFile = ROOT_PATH . '/logs/smtp.log';
                $line = date('Y-m-d H:i:s') . ' SMTP_ERROR: ' . trim((string)$info) . "\n";
                @file_put_contents($logFile, $line, FILE_APPEND);
            } catch (Throwable $t) { /* ignore */ }

            if ($allowFallback) {
                error_log('Fallback to mail()');
                return $this->sendViaPhpMail($to, $subject, $message, $isHTML);
            }

            return false;
        }
    }

    private function sendViaPhpMail($to, $subject, $message, $isHTML = true) {
        try {
            $headers = "MIME-Version: 1.0\r\n";
            if ($isHTML) {
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            }
            if ($this->config['from_email'] !== '') {
                $headers .= "From: {$this->config['from_name']} <{$this->config['from_email']}>\r\n";
                $headers .= "Reply-To: {$this->config['from_email']}\r\n";
            }

            $result = mail($to, $subject, $message, $headers);
            error_log("PHP mail() result for {$to}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log('PHP mail() Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getLastErrorInfo(): string {
        return (string)($this->mail->ErrorInfo ?? '');
    }
}
}

// Create global mailer instance
if (!function_exists('getMailer')) {
    function getMailer() {
        static $mailer = null;
        if ($mailer === null) {
            $mailer = new Mailer();
        }
        return $mailer;
    }
}

?>
