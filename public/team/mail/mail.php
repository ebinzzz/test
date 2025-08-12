<?php


include_once("class.phpmailer.php");
include_once("class.smtp.php");
/**
 * Sends a welcome email to a new team member using PHPMailer.
 *
 * @param string $recipient_email The email address of the new team member.
 * @param string $role The role of the new team member.
 * @param string $full_name The full name of the new team member.
 * @return bool True on success, false on failure.
 */
function sendWelcomeEmail($recipient_email, $role, $full_name,$username,$plain_password) {
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->IsSMTP();
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = "tls";
        $mail->Host       = "smtp.gmail.com";
        $mail->Port       = 587;
        $mail->Username   = "zorqent@gmail.com";
        $mail->Password   = "uvimcgnfwxdresfv";

        // This allows insecure connections for development, but should be handled carefully in production.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];
        
        //Recipients
        $mail->setFrom("zorqent@gmail.com", "Zorqent Team");
        $mail->addAddress($recipient_email);
        $mail->addReplyTo("support@zorqent.com", "Zorqent Support");
        
        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome Aboard!';
        
        // The HTML email body as a string with dynamic content
 

// Updated HTML body with inline styles
$html_body = '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team Update - Zorqent</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; background-color: #fafafa; color: #374151; line-height: 1.5;">

<div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 6px; border: 1px solid #e5e7eb; overflow: hidden;">

<div style="background: #cececeff; padding: 24px 20px; border-bottom: 1px solid #e5e7eb;">
<div style="color: #1f2937; font-size: 20px; font-weight: 600; margin-bottom: 4px;">Zorqent</div>
<div style="color: #6b7280; font-size: 13px;">Technology Solutions Team</div>
</div>

<img src="https://t3.ftcdn.net/jpg/01/27/38/98/360_F_127389862_pMUoWAQMoKsq6QOrF8kq8S9KaXOCjlHP.jpg" style="width: 100%; height: 180px; object-fit: cover; display: block;" alt="Welcome Image">

<div style="padding: 24px 20px;">

<div style="font-size: 15px; margin-bottom: 16px;">
Hi '.htmlspecialchars($full_name).',
</div>

<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 16px; margin: 16px 0; border-left: 3px solid #4b5563;">
<div style="color: #1f2937; font-size: 16px; font-weight: 600; margin-bottom: 4px;">Welcome Aboard!</div>
<div style="font-size: 12px; color: #6b7280;">We are excited to have you join our team.</div>
</div>

<div style="font-size: 14px; color: #4b5563; margin: 16px 0;">
We\'re thrilled to officially welcome you to the Zorqent team. Your skills as a <b>' . htmlspecialchars($role) . '</b> will be a great asset, and we\'re looking forward to your contributions. We believe your fresh perspective will help us achieve great things.
</div>

<div style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 16px; border-radius: 4px; margin: 16px 0;">
<h4 style="font-size: 14px; color: #1f2937; margin: 0 0 6px 0;">Your Login Credentials</h4>
<p style="font-size: 12px; color: #6b7280; margin: 0;">Please use the following credentials to access your company accounts and services.</p>
<ul style="list-style-type: none; padding: 0; margin: 12px 0 0 0;">
<li style="font-size: 12px; color: #4b5563; margin-bottom: 4px;"><strong>Username:</strong> '.htmlspecialchars($username).'</li>
<li style="font-size: 12px; color: #4b5563;"><strong>Password:</strong> '.htmlspecialchars($plain_password).'</li>
</ul>
</div>

<div style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 16px; border-radius: 4px; margin: 16px 0; text-align: center;">
<h4 style="font-size: 14px; color: #1f2937; margin: 0 0 6px 0;">Get Started with Your Onboarding</h4>
<p style="font-size: 12px; color: #6b7280; margin: 0 0 12px 0;">Your journey begins with our onboarding portal. Please click the button below to complete your initial setup.</p>
<a href="http://www.zadmin.free.nf/login.php" style="display: inline-block; background: #1f2937; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; font-size: 12px; font-weight: 500;">Go to Onboarding Portal</a>
</div>

<div style="font-size: 14px; color: #4b5563; margin: 16px 0;">
Thank you for choosing Zorqent. We\'re excited to see you grow with us.
</div>

<div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
<div style="font-weight: 500; color: #1f2937; font-size: 14px; margin-bottom: 2px;">The Zorqent Management Team</div>
<div style="color: #6b7280; font-size: 12px;">management@zorqent.com</div>
</div>

</div>

<div style="background: #f8fafc; color: #6b7280; padding: 16px 20px; text-align: center; border-top: 1px solid #e5e7eb;">
<div style="margin-bottom: 8px;">
<a href="#" style="color: #4b5563; text-decoration: none; margin: 0 8px; font-size: 11px;">Intranet</a>
<a href="#" style="color: #4b5563; text-decoration: none; margin: 0 8px; font-size: 11px;">HR Policies</a>
<a href="#" style="color: #4b5563; text-decoration: none; margin: 0 8px; font-size: 11px;">Calendar</a>
</div>
<div style="font-size: 10px; color: #9ca3af;">
&copy; 2025 Zorqent. All rights reserved.
</div>
</div>

</div>
</body>
</html>
';

        $mail->Body = $html_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}