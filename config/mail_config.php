<?php
// Mail Configuration using PHPMailer
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\SMTP;
// use PHPMailer\PHPMailer\Exception;

// require_once ROOT_PATH . '/vendor/autoload.php';
// if (!class_exists('Mailer')) {
// class Mailer {
//     private $mail;
//     private $config;

//     public function __construct() {
//         $this->mail = new PHPMailer(true);
//         $this->config = $this->getConfig();
//         $this->configure();
//     }

//     private function getConfig() {
//         return [
//             'host' => 'smtp.gmail.com',
//             'port' => 465,
//             'username' => 'boysboysdev@gmail.com', // Change this
//             'password' => 'ravx sort hiao kdkt',    // Change this (Gmail App Password)
//             'from_email' => 'boysboysdev@gmail.com',
//             'from_name' => 'HTU Complaint System',
//             'smtp_secure' => PHPMailer::ENCRYPTION_SMTPS,
//             'debug' => 2 // 0 = off, 1 = client messages, 2 = client and server messages
//         ];
//     }

//     private function configure() {
//         // Server settings
//         $this->mail->isSMTP();
//         $this->mail->Host       = $this->config['host'];
//         $this->mail->SMTPAuth   = true;
//         $this->mail->Username   = $this->config['username'];
//         $this->mail->Password   = $this->config['password'];
//         $this->mail->SMTPSecure = $this->config['smtp_secure'];
//         $this->mail->Port       = $this->config['port'];
//         $this->mail->SMTPDebug  = $this->config['debug'];

//         // Recipients
//         $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
//         $this->mail->addReplyTo($this->config['from_email'], $this->config['from_name']);
//     }

//     public function sendVerificationEmail($to, $name, $token) {
//         try {
//             $this->mail->clearAddresses();
//             $this->mail->addAddress($to, $name);

//             // Content
//             $this->mail->isHTML(true);
//             $this->mail->Subject = 'Verify Your Account - HTU Complaint System';
            
//             $verificationLink = APP_URL . '/pages/auth/verify.php?token=' . $token;
            
//             $message = "
//             <html>
//             <head>
//                 <style>
//                     body { font-family: Arial, sans-serif; line-height: 1.6; }
//                     .container { max-width: 600px; margin: 0 auto; padding: 20px; }
//                     .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
//                     .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
//                     .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
//                     .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
//                 </style>
//             </head>
//             <body>
//                 <div class='container'>
//                     <div class='header'>
//                         <h1>HTU Complaint System</h1>
//                     </div>
//                     <div class='content'>
//                         <h2>Welcome, $name!</h2>
//                         <p>Thank you for registering. Please verify your email address to activate your account.</p>
//                         <p>Your verification code: <strong>$token</strong></p>
//                         <p>Or click the button below:</p>
//                         <a href='$verificationLink' class='btn'>Verify Account</a>
//                         <p>This link will expire in 24 hours.</p>
//                         <p>If you didn't create an account, please ignore this email.</p>
//                     </div>
//                     <div class='footer'>
//                         <p>This is an automated message, please do not reply.</p>
//                     </div>
//                 </div>
//             </body>
//             </html>
//             ";

//             $this->mail->Body = $message;
//             $this->mail->AltBody = "Verify your account by visiting: $verificationLink\n\nVerification Code: $token";

//             $this->mail->send();
//             return true;
//         } catch (Exception $e) {
//             error_log("Mailer Error: " . $this->mail->ErrorInfo);
//             return false;
//         }
//     }
// // General notification method

// public function sendEmailNotification($to, $name, $complaint_code, $title,$category, $urgency,$complaint_id,$complaint_url) {
//         try {
//             $this->mail->clearAddresses();
//             $this->mail->addAddress($to, $name);
        
//             // Content
//             $this->mail->isHTML(true);
//             $this->mail->Subject = 'New Complaint Submitted - HTU Complaint System';
            
//             $complaintLink = $complaint_url . '?view=' . $complaint_id;
            
//             $message = "
//             <html>
//             <head>
//                 <style>
//                     body { font-family: Arial, sans-serif; line-height: 1.6; }
//                     .container { max-width: 600px; margin: 0 auto; padding: 20px; }
//                     .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
//                     .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
//                     .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
//                     .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
//                 </style>
//             </head>
//             <body>
//                 <div class='container'>
//                     <div class='header'>
//                         <h1>HTU Complaint System</h1>
//                     </div>
//                     <div class='content'>
//                         <h2>New Complaint Submitted</h2>
//                         <p>Dear $name,</p>
//                         <p>A new complaint has been submitted:</p>
//                         <ul>
//                             <li><strong>Complaint Code:</strong> $complaint_code</li>
//                             <li><strong>Title:</strong> $title</li>
//                             <li><strong>Category:</strong> $category</li>
//                             <li><strong>Urgency:</strong> $urgency</li>
//                         </ul>
//                         <p>Please review the complaint details by clicking the button below:</p>
//                         <a href='$complaintLink' class='btn'>View Complaint Details</a>
//                         <p>This link will expire in 24 hours.</p>
//                         <p>If you didn't create an account, please ignore this email.</p>
//                     </div>
//                     <div class='footer'>
//                         <p>This is an automated message, please do not reply.</p>
//                     </div>
//                 </div>
//             </body>
//             </html>
//             ";

//             $this->mail->Body = $message;
//             $this->mail->AltBody = "View complaint details by visiting: $complaintLink\n\nComplaint Code: $complaint_code\nTitle: $title\nCategory: $category\nUrgency: $urgency";

//             $this->mail->send();
//             return true;
//         } catch (Exception $e) {
//             error_log("Mailer Error: " . $this->mail->ErrorInfo);
//             return false;
//         }
//     }


//     public function sendNotification($to, $subject, $message, $isHTML = true) {
//         try {
//             // Try SMTP first
//             $this->mail->clearAddresses();
//             $this->mail->addAddress($to);
//             $this->mail->Subject = $subject;
//             $this->mail->isHTML($isHTML);
//             $this->mail->Body = $message;

//             if (!$isHTML) {
//                 $this->mail->AltBody = $message;
//             }

//             return $this->mail->send();
//         } catch (Exception $e) {
//             // SMTP failed - fallback to PHP mail()
//             error_log("SMTP Error: " . $e->getMessage() . " | Fallback to mail()");
//             return $this->sendViaPhpMail($to, $subject, $message, $isHTML);
//         }
//     }

//     private function sendViaPhpMail($to, $subject, $message, $isHTML = true) {
//         try {
//             $headers = "MIME-Version: 1.0" . "\r\n";
//             if ($isHTML) {
//                 $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
//             }
//             $headers .= "From: " . $this->config['from_email'] . " <" . $this->config['from_email'] . ">" . "\r\n";
//             $headers .= "Reply-To: " . $this->config['from_email'] . "\r\n";
            
//             $result = mail($to, $subject, $message, $headers);
//             error_log("PHP mail() result for $to: " . ($result ? "SUCCESS" : "FAILED"));
//             return $result;
//         } catch (Exception $e) {
//             error_log("PHP mail() Error: " . $e->getMessage());
//             return false;
//         }
//     }
// }
// }
// // Create global mailer instance
// if (!function_exists('getMailer')) {
// function getMailer() {
//     static $mailer = null;
//     if ($mailer === null) {
//         $mailer = new Mailer();
//     }
//     return $mailer;
// }
// }

// Mail Configuration using PHPMailer
require_once __DIR__ . '/constants.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once ROOT_PATH . '/vendor/autoload.php';

if (!class_exists('Mailer')) {
    class Mailer {
        private array $config;

        public function __construct() {
            $this->config = $this->getConfig();
        }

        private function getConfig(): array {
            return [
                'host'        => 'smtp.gmail.com',
                'port'        => 465,
                'username'    => 'boysboysdev@gmail.com',
                'password'    => 'ravx sort hiao kdkt',
                'from_email'  => 'boysboysdev@gmail.com',
                'from_name'   => 'HTU Complaint System',
                'smtp_secure' => PHPMailer::ENCRYPTION_SMTPS,
                'debug'       => SMTP::DEBUG_OFF, // change to SMTP::DEBUG_SERVER when testing
                'timeout'     => 30,
            ];
        }

        /**
         * Create and configure a fresh PHPMailer instance every time.
         */
        private function createMailer(): PHPMailer {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port       = $this->config['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = $this->config['timeout'];

            // Debug
            $mail->SMTPDebug  = $this->config['debug'];
            $mail->Debugoutput = 'html';

            // Sender
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);

            // Helpful for localhost testing if SSL cert chain causes issues.
            // Remove these in production if your environment works normally.
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            return $mail;
        }

        private function logError(string $context, Exception $e = null, ?PHPMailer $mail = null): void {
            $message = $context;

            if ($mail) {
                $message .= " | PHPMailer ErrorInfo: " . $mail->ErrorInfo;
            }

            if ($e) {
                $message .= " | Exception: " . $e->getMessage();
            }

            error_log($message);
        }

        public function sendVerificationEmail(string $to, string $name, string $token): bool {
            $mail = $this->createMailer();

            try {
                $safeName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

                $verificationLink = APP_URL . '/pages/auth/verify.php?token=' . urlencode($token);

                $mail->addAddress($to, $name);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Account - HTU Complaint System';

                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .btn { display: inline-block; background: #667eea; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>HTU Complaint System</h1>
                        </div>
                        <div class='content'>
                            <h2>Welcome, {$safeName}!</h2>
                            <p>Thank you for registering. Please verify your email address to activate your account.</p>
                            <p>Your verification code: <strong>{$safeToken}</strong></p>
                            <p>Or click the button below:</p>
                            <a href='{$verificationLink}' class='btn'>Verify Account</a>
                            <p>This link will expire in 24 hours.</p>
                            <p>If you did not create an account, please ignore this email.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mail->Body    = $message;
                $mail->AltBody = "Welcome, {$name}!\n\nVerify your account by visiting:\n{$verificationLink}\n\nVerification Code: {$token}";

                $mail->send();
                return true;
            } catch (Exception $e) {
                $this->logError('sendVerificationEmail failed', $e, $mail);
                return false;
            }
        }

        public function sendEmailNotification(
            string $to,
            string $name,
            string $complaint_code,
            string $title,
            string $category,
            string $urgency,
            string $complaint_id,
            string $complaint_url
        ): bool {
            $mail = $this->createMailer();

            try {
                $safeName          = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                $safeComplaintCode = htmlspecialchars($complaint_code, ENT_QUOTES, 'UTF-8');
                $safeTitle         = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $safeCategory      = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
                $safeUrgency       = htmlspecialchars($urgency, ENT_QUOTES, 'UTF-8');

                $complaintLink = rtrim($complaint_url, '/') . '?view=' . urlencode($complaint_id);

                $mail->addAddress($to, $name);
                $mail->isHTML(true);
                $mail->Subject = 'New Complaint Submitted - HTU Complaint System';

                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .btn { display: inline-block; background: #667eea; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
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
                            <p>Dear {$safeName},</p>
                            <p>A new complaint has been submitted:</p>
                            <ul>
                                <li><strong>Complaint Code:</strong> {$safeComplaintCode}</li>
                                <li><strong>Title:</strong> {$safeTitle}</li>
                                <li><strong>Category:</strong> {$safeCategory}</li>
                                <li><strong>Urgency:</strong> {$safeUrgency}</li>
                            </ul>
                            <p>Please review the complaint details by clicking the button below:</p>
                            <a href='{$complaintLink}' class='btn'>View Complaint Details</a>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mail->Body    = $message;
                $mail->AltBody = "Dear {$name},\n\nA new complaint has been submitted.\nComplaint Code: {$complaint_code}\nTitle: {$title}\nCategory: {$category}\nUrgency: {$urgency}\n\nView details here:\n{$complaintLink}";

                $mail->send();
                return true;
            } catch (Exception $e) {
                $this->logError('sendEmailNotification failed', $e, $mail);
                return false;
            }
        }

        public function sendNotification(string $to, string $subject, string $message, bool $isHTML = true): bool {
            $mail = $this->createMailer();

            try {
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->isHTML($isHTML);
                $mail->Body = $message;

                if ($isHTML) {
                    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
                } else {
                    $mail->AltBody = $message;
                }

                $mail->send();
                return true;
            } catch (Exception $e) {
                $this->logError('sendNotification failed', $e, $mail);
                return false;
            }
        }
    }
}

// Create global mailer instance
if (!function_exists('getMailer')) {
    function getMailer(): Mailer {
        static $mailer = null;

        if ($mailer === null) {
            $mailer = new Mailer();
        }

        return $mailer;
    }
}
?>