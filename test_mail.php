<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'Dobiero@kdc.go.ke';   // Your Outlook email
    $mail->Password = 'Is@36977041';         // Your Outlook password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('Dobiero@kdc.go.ke', 'KDC Helpdesk');
    $mail->addAddress('dalmasnyabutoobiero@gmail.com'); // Replace with your own email to test
    $mail->Subject = 'PHPMailer Test';
    $mail->Body    = 'This is a test email sent via Outlook SMTP and PHPMailer.';

    $mail->send();
    echo "Message sent successfully!";
} catch (Exception $e) {
    echo "Message could not be sent. Error: {$mail->ErrorInfo}";
}
