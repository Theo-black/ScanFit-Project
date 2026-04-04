<?php
// contact.php

// Load shared helper functions (if needed for layout, config, etc.)
require_once 'functions.php';

// Track form submission outcome for user feedback
$success = false;
$successMessage = "Thank you for your message! We'll get back to you soon.";
$error = null;
$contactRecipient = 'scanfitsupport@gmail.com';

// Handle contact form submission when the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('contact.php');

    // Trim and collect all input fields from the form
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation: ensure no required field is left empty
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields';
    // Validate email format using PHP's filter_var
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessageHtml = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $mailSubject = 'ScanFit Contact Form: ' . $subject;
        $htmlBody = "
            <h2>New Contact Form Message</h2>
            <p><strong>Name:</strong> {$safeName}</p>
            <p><strong>Email:</strong> {$safeEmail}</p>
            <p><strong>Subject:</strong> {$safeSubject}</p>
            <p><strong>Message:</strong><br>{$safeMessageHtml}</p>
        ";
        $textBody = "New Contact Form Message\n"
            . "Name: {$name}\n"
            . "Email: {$email}\n"
            . "Subject: {$subject}\n\n"
            . "Message:\n{$message}\n";

        if (!saveContactMessage($name, $email, $subject, $message)) {
            $error = 'We could not save your message right now. Please try again.';
        } elseif (sendAppEmail($contactRecipient, $mailSubject, $htmlBody, $textBody)) {
            $success = true;
            $_POST = [];
        } else {
            $success = true;
            $successMessage = 'Your message was saved and sent to the admin inbox. Email delivery is temporarily unavailable.';
            $_POST = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and base typography */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:#f8f9fa;color:#333
        }
        .hero{
            /* Top hero banner section with gradient background */
            height:40vh;
            background:linear-gradient(rgba(102,126,234,.9),rgba(118,75,162,.9));
            display:flex;align-items:center;justify-content:center;
            text-align:center;color:#fff
        }
        .hero h1{
            /* Hero heading styling */
            font-size:clamp(2.5rem,6vw,4rem);font-weight:900;
            text-shadow:0 4px 30px rgba(0,0,0,.4)
        }
        .container{max-width:1200px;margin:0 auto;padding:4rem 2rem}
        .contact-grid{
            /* Two-column layout for contact info and form */
            display:grid;grid-template-columns:1fr 1fr;
            gap:3rem;margin-top:2rem
        }
        .contact-info{
            /* Card containing static contact information */
            background:#fff;border-radius:20px;padding:3rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .contact-info h2{
            /* Title for contact information section */
            font-size:2rem;color:#2c3e50;margin-bottom:2rem;
            font-weight:800
        }
        .info-item{
            /* Row for each contact detail (address, phone, etc.) */
            display:flex;align-items:start;gap:1.5rem;
            margin-bottom:2rem;padding-bottom:2rem;
            border-bottom:1px solid #e1e4e8
        }
        .info-item:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
        .info-icon{
            /* Icon styling with gradient text effect */
            font-size:2rem;background:linear-gradient(135deg,#667eea,#764ba2);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent
        }
        .info-content h3{
            /* Heading for each info line (Address, Phone, etc.) */
            font-size:1.2rem;color:#2c3e50;margin-bottom:.5rem;
            font-weight:700
        }
        .info-content p{color:#666;line-height:1.8}
        .contact-form{
            /* Card containing the contact form fields */
            background:#fff;border-radius:20px;padding:3rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .contact-form h2{
            /* Title for the contact form section */
            font-size:2rem;color:#2c3e50;margin-bottom:2rem;
            font-weight:800
        }
        .form-group{margin-bottom:1.5rem}
        .form-group label{
            /* Label styling above each input/textarea */
            display:block;font-weight:600;margin-bottom:.5rem;
            color:#2c3e50
        }
        .form-group input,
        .form-group textarea{
            /* Shared styling for text inputs and textarea */
            width:100%;padding:1rem;border:2px solid #e1e4e8;
            border-radius:12px;font-size:1rem;outline:none;
            transition:border-color .3s;font-family:inherit
        }
        .form-group input:focus,
        .form-group textarea:focus{border-color:#667eea}
        .form-group textarea{resize:vertical;min-height:150px}
        .submit-btn{
            /* Submit button styling for sending the message */
            width:100%;padding:1.2rem;border:none;border-radius:15px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;font-size:1.1rem;font-weight:700;cursor:pointer;
            transition:transform .2s
        }
        .submit-btn:hover{transform:translateY(-2px)}
        .success-msg{
            /* Banner shown when form submission is successful */
            background:#28a745;color:#fff;padding:1rem;
            border-radius:12px;margin-bottom:1.5rem;text-align:center
        }
        .error-msg{
            /* Banner shown when validation fails */
            background:#ff4444;color:#fff;padding:1rem;
            border-radius:12px;margin-bottom:1.5rem;text-align:center
        }
        .map-section{
            /* Section containing location/map placeholder */
            margin-top:3rem;background:#fff;border-radius:20px;
            padding:2rem;box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .map-section h2{
            /* Heading for the map section */
            font-size:1.8rem;color:#2c3e50;margin-bottom:1.5rem;
            font-weight:800
        }
        .map-placeholder{
            /* Visual placeholder where an embedded map could go */
            width:100%;height:400px;background:#f0f2f5;
            border-radius:15px;display:flex;align-items:center;
            justify-content:center;color:#666;font-size:1.2rem
        }
        @media(max-width:968px){
            /* Responsive layout: stack columns vertically on smaller screens */
            .contact-grid{grid-template-columns:1fr}
            .container{padding:3rem 1.5rem}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="hero">
    <!-- Simple hero area introducing the contact page -->
    <h1>Contact Us</h1>
</section>

<div class="container">
    <div class="contact-grid">
        <div class="contact-info">
            <!-- Static company contact details (address, phone, email, hours) -->
            <h2>Get in Touch</h2>

            <div class="info-item">
                <div class="info-icon">📍</div>
                <div class="info-content">
                    <h3>Address</h3>
                    <p>24 Main Street<br>May Pen, Clarendon<br>Jamaica</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">📞</div>
                <div class="info-content">
                    <h3>Phone</h3>
                    <p>+1 (876) 123-4567<br>Mon-Fri: 9:00 AM - 6:00 PM EST</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">✉️</div>
                <div class="info-content">
                    <h3>Email</h3>
                    <p>scanfitsupport@gmail.com</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">⏰</div>
                <div class="info-content">
                    <h3>Business Hours</h3>
                    <p>Monday - Friday: 9:00 AM - 6:00 PM<br>
                       Saturday: 10:00 AM - 4:00 PM<br>
                       Sunday: Closed</p>
                </div>
            </div>
        </div>

        <div class="contact-form">
            <!-- Interactive form where visitors can send a message -->
            <h2>Send Us a Message</h2>

            <?php if ($success): ?>
                <!-- Success feedback shown after a valid submission -->
                <div class="success-msg"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <!-- Error feedback shown when validation fails -->
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrfInput(); ?>
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="name" placeholder="John Doe" required
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="your@email.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="How can we help you?" required
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" placeholder="Tell us more about your inquiry..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="submit-btn">Send Message</button>
            </form>
        </div>
    </div>

    <div class="map-section">
        <!--  map embed  -->
        <h2>Find Us</h2>
        <div class="map-container" style="margin-top:2rem;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.15);">
    <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3434.501872007238!2d-77.24365702482164!3d17.96719008302224!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8edb9f07fcfc3899%3A0x7da2ad697a012d26!2s24%20Main%20St%2C%20May%20Pen!5e1!3m2!1sen!2sjm!4v1766193721825!5m2!1sen!2sjm" 
        width="1200" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
    </iframe>    
</div>

    </div>
</div>
</body>
</html>
