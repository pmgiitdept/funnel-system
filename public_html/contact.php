<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Get and clean form data
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $subject_line = htmlspecialchars(trim($_POST['subject']));
    $message = trim($_POST['message']);

    $mail = new PHPMailer(true);

    try {
        // 2. Gmail SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // ⚠️ REPLACE THESE WITH YOUR INFO ⚠️
        $mail->Username   = 'bermasjonathan2@gmail.com'; 
        $mail->Password   = 'zbad cyhw yzoi jwai'; 
        // ⚠️ REPLACE THESE WITH YOUR INFO ⚠️

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // 3. Email recipients
        $mail->setFrom($mail->Username, $name);
        $mail->addAddress('bermasjonathan2@gmail.com'); // Test email goes to you
        $mail->addReplyTo($email, $name);

        // 4. Email content
        $mail->isHTML(true);
        $mail->Subject = "New Website Inquiry: $subject_line";
        $mail->Body    = "
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Subject:</strong> $subject_line</p>
            <hr>
            <p><strong>Message:</strong><br>$message</p>
        ";

        // 5. Send email
        $mail->send();
        
        // Success - redirect back to index
        echo "<script>
            alert('✅ Message sent successfully!');
            window.location.href = 'index.html';
        </script>";

    } catch (Exception $e) {
        // Error
        echo "<script>
            alert('❌ Error: Message could not be sent. {$mail->ErrorInfo}');
            window.location.href = 'index.html';
        </script>";
    }
}
?>