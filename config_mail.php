<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendMail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';          // ✅ Company mail server
        $mail->SMTPAuth = true;
        $mail->Username = 'helpdesk@kdc.go.ke';   // ✅ Company email
        $mail->Password = 'Is@36977041';             // ✅ Company password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Sender
        $mail->setFrom('helpdesk@kdc.go.ke', 'KDC Helpdesk');

        // Recipient
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Show error during testing
        echo "Mailer Error: " . $mail->ErrorInfo;
        return false;


    }
}
?>
