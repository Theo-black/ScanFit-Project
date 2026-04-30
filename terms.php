<?php
require_once 'functions.php';
$version = getTermsAgreementVersion();
$continueUrl = 'register.php?terms_read=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>License Agreement and Terms & Conditions - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Tahoma,sans-serif;background:#f8fafc;color:#1f2937;line-height:1.65}
        .wrap{max-width:980px;margin:0 auto;padding:2rem}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:2rem;box-shadow:0 12px 30px rgba(15,23,42,.08)}
        h1{font-size:2rem;margin-bottom:.4rem;color:#111827}
        h2{font-size:1.15rem;margin-top:1.6rem;margin-bottom:.55rem;color:#111827}
        p,li{color:#374151}
        ul{padding-left:1.35rem}
        .meta{color:#6b7280;margin-bottom:1.4rem}
        a{color:#4f46e5;font-weight:700;text-decoration:none}
        a:hover{text-decoration:underline}
        .actions{
            position:sticky;bottom:0;margin-top:2rem;padding-top:1rem;
            background:linear-gradient(180deg,rgba(255,255,255,.72),#fff 38%);
            display:flex;justify-content:flex-end;gap:.75rem;flex-wrap:wrap
        }
        .continue-btn{
            display:inline-block;border:none;border-radius:10px;padding:.9rem 1.2rem;
            background:#4f46e5;color:#fff!important;font-weight:800;text-decoration:none;
            box-shadow:0 10px 22px rgba(79,70,229,.22)
        }
        .continue-btn:hover{text-decoration:none;background:#4338ca}
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<main class="wrap">
    <article class="card">
        <h1>ScanFit License Agreement and Terms & Conditions</h1>
        <p class="meta">Version <?php echo htmlspecialchars($version); ?>. Effective April 30, 2026.</p>

        <p>
            By creating a ScanFit account, using the ScanFit website, placing an order, using the size guide,
            or using any ScanFit account feature, you agree to this License Agreement and Terms & Conditions.
            If you do not agree, do not create an account or use the service.
        </p>

        <h2>1. Account Eligibility</h2>
        <p>
            You must provide accurate registration information and keep your login credentials secure. You are
            responsible for activity that happens through your account unless you promptly report unauthorized use.
        </p>

        <h2>2. Limited License To Use ScanFit</h2>
        <p>
            ScanFit grants you a limited, personal, non-transferable, revocable license to access and use the
            website for shopping, order management, body-size guidance, wishlist, reviews, and related account
            features. You may not copy, resell, reverse engineer, scrape, attack, or misuse the website, scanner,
            code, content, product data, or account systems.
        </p>

        <h2>3. Orders, Prices, Shipping, Returns, and Refunds</h2>
        <p>
            Product availability, pricing, shipping charges, taxes, coupon discounts, and order totals may change.
            Orders may be cancelled or refused if payment fails, inventory is unavailable, fraud is suspected, or
            incorrect information is provided. Returns and refunds must be requested through the order tools and are
            subject to review, product condition, payment status, and applicable store policy.
        </p>

        <h2>4. Size Guide and Body Scanner</h2>
        <p>
            ScanFit size recommendations, BMI tools, body measurements, and scanner results are informational
            shopping aids only. They are not medical advice and do not guarantee product fit. You are responsible
            for reviewing product details before purchase.
        </p>

        <h2>5. User Content</h2>
        <p>
            If you submit reviews, messages, profile data, measurements, or other content, you confirm that the
            content is lawful and accurate to the best of your knowledge. You grant ScanFit permission to use that
            content as needed to operate the store, provide customer support, display reviews, prevent fraud, and
            maintain account records.
        </p>

        <h2>6. Privacy and Data Use</h2>
        <p>
            ScanFit collects and stores account details, order history, addresses, cart activity, wishlist items,
            reviews, measurement data you choose to save, payment status metadata, and agreement acceptance records.
            Payment card details are handled by the configured payment provider and are not intentionally stored by
            ScanFit. You should not upload or submit sensitive information that is not needed for shopping.
        </p>

        <h2>7. Prohibited Conduct</h2>
        <ul>
            <li>Do not use another person's account or payment details without permission.</li>
            <li>Do not submit false, abusive, illegal, or misleading information.</li>
            <li>Do not attempt to bypass checkout, payment, inventory, security, or admin controls.</li>
            <li>Do not interfere with the website, local scanner, database, email, or payment integrations.</li>
        </ul>

        <h2>8. Service Availability</h2>
        <p>
            ScanFit may be unavailable during maintenance, outages, local server issues, third-party service failures,
            or other technical problems. Features may be changed, suspended, or discontinued.
        </p>

        <h2>9. Disclaimers and Limitation of Liability</h2>
        <p>
            ScanFit is provided on an "as is" and "as available" basis. To the fullest extent allowed by law, ScanFit
            disclaims implied warranties and is not liable for indirect, incidental, special, consequential, or punitive
            damages arising from account use, purchases, scanner recommendations, delays, errors, or third-party services.
        </p>

        <h2>10. Agreement Updates</h2>
        <p>
            ScanFit may update these terms. New account creation requires agreement to the current version. Continued
            use after changes means you accept the updated terms.
        </p>

        <h2>11. Contact</h2>
        <p>
            Questions about these terms can be sent through the <a href="contact.php">contact page</a>.
        </p>

        <div class="actions">
            <a class="continue-btn" href="<?php echo htmlspecialchars($continueUrl); ?>">Continue to Create Account</a>
        </div>
    </article>
</main>
</body>
</html>
