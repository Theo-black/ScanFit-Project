
<?php 
//about.php

// Include core functions and shared utilities for the Scanfit application
require_once 'functions.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Reset default margin/padding and ensure consistent box sizing */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            /* Base typography and text color for the page */
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            color:#333;line-height:1.8
        }
        .hero{
            /* Hero section with background image and gradient overlay */
            height:50vh;
            background:linear-gradient(rgba(102,126,234,.9),rgba(118,75,162,.9)),
                       url('https://images.pexels.com/photos/3184292/pexels-photo-3184292.jpeg?auto=compress&cs=tinysrgb&w=1920') center/cover;
            display:flex;align-items:center;justify-content:center;
            text-align:center;color:#fff
        }
        .hero h1{
            /* Responsive hero heading styling */
            font-size:clamp(2.5rem,6vw,4rem);font-weight:900;
            text-shadow:0 4px 30px rgba(0,0,0,.4)
        }
        .container{
            /* Central content container with max width */
            max-width:1200px;margin:0 auto;padding:5rem 2rem
        }
        .content-section{
            /* Vertical spacing between content sections */
            margin-bottom:4rem
        }
        .content-section h2{
            /* Section heading styling */
            font-size:2.5rem;color:#2c3e50;margin-bottom:1.5rem;
            font-weight:800
        }
        .content-section p{
            /* Paragraph styling for main content */
            font-size:1.2rem;color:#555;margin-bottom:1.5rem;
            line-height:2
        }
        .features-grid{
            /* Responsive grid layout for feature cards */
            display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
            gap:2.5rem;margin-top:3rem
        }
        .feature-box{
            /* Individual feature card styling */
            background:#fff;padding:2.5rem;border-radius:20px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            text-align:center;transition:all .3s
        }
        .feature-box:hover{
            /* Hover effect for feature cards */
            transform:translateY(-10px);
            box-shadow:0 20px 50px rgba(0,0,0,.12)
        }
        .feature-icon{
            /* Icon styling with gradient text effect */
            font-size:3.5rem;margin-bottom:1.5rem;
            background:linear-gradient(135deg,#667eea,#764ba2);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent
        }
        .feature-box h3{
            /* Feature title styling */
            font-size:1.6rem;margin-bottom:1rem;color:#2c3e50
        }
        .feature-box p{
            /* Feature description text styling */
            color:#666;line-height:1.8
        }
        .stats-section{
            /* Highlighted statistics section styling */
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            padding:5rem 2rem;color:#fff;text-align:center;
            margin:4rem 0
        }
        .stats-grid{
            /* Grid layout for stats boxes */
            display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
            gap:3rem;max-width:1200px;margin:0 auto;margin-top:3rem
        }
        .stat-box h3{
            /* Numeric statistic styling */
            font-size:3.5rem;font-weight:900;margin-bottom:.5rem
        }
        .stat-box p{
            /* Statistic label text styling */
            font-size:1.2rem;opacity:.95
        }
        .mission-section{
            /* Mission statement section styling */
            background:#f8f9fa;padding:5rem 2rem;text-align:center
        }
        .mission-content{
            /* Center mission content with max width */
            max-width:900px;margin:0 auto
        }
        .mission-content h2{
            /* Mission heading styling */
            font-size:2.5rem;color:#2c3e50;margin-bottom:2rem;
            font-weight:800
        }
        .mission-content p{
            /* Mission paragraph styling */
            font-size:1.3rem;color:#555;line-height:2
        }
        @media(max-width:768px){
            /* Responsive padding adjustments for smaller screens */
            .container{padding:3rem 1.5rem}
            .stats-section{padding:3rem 1.5rem}
        }
    </style>
</head>
<body>
<?php 
// Inserts the shared navigation bar 
include 'navbar.php'; 
?>


<section class="hero">
    <!--  page title -->
    <h1>About Scanfit</h1>
</section>


<div class="container">
    <div class="content-section">
        <!-- Introductory section -->
        <h2>Revolutionizing Online Fashion Shopping</h2>
        <p>
            Scanfit is a cutting-edge e-commerce platform that combines technology with fashion to provide
            a seamless shopping experience. We understand that finding the perfect fit online can be challenging,
            which is why we developed our innovative BMI-powered size recommendation system.
        </p>
        <p>
            Our mission is to eliminate the guesswork from online clothing purchases. By analyzing your body
            measurements and BMI, we provide personalized size recommendations that help you find clothes
            that fit perfectly the first time, every time.
        </p>
    </div>


    <div class="content-section">
       
        <h2>What Makes Us Different</h2>
        <div class="features-grid">
            <div class="feature-box">
                <!--  sizing recommendations -->
                <div class="feature-icon">📏</div>
                <h3>Smart Sizing</h3>
                <p>Our BMI calculator analyzes your measurements to recommend the perfect size for your body type</p>
            </div>


            <div class="feature-box">
               
                <div class="feature-icon">✨</div>
                <h3>Quality First</h3>
                <p>We carefully curate every product to ensure premium quality and lasting durability</p>
            </div>


            <div class="feature-box">
                
                <div class="feature-icon">🎯</div>
                <h3>Perfect Fit</h3>
                <p>Our personalized recommendations reduce returns and ensure satisfaction with every purchase</p>
            </div>


            <div class="feature-box">
                
                <div class="feature-icon">🚀</div>
                <h3>Fast Shipping</h3>
                <p>Quick processing and reliable delivery get your orders to you when you need them</p>
            </div>


            <div class="feature-box">
               
                <div class="feature-icon">🔒</div>
                <h3>Secure Shopping</h3>
                <p>Advanced encryption and secure payment processing protect your information</p>
            </div>


            <div class="feature-box">
               
                <div class="feature-icon">💝</div>
                <h3>Customer Care</h3>
                <p>Dedicated support team ready to help with any questions or concerns</p>
            </div>
        </div>
    </div>
</div>


<section class="stats-section">
    
    <h2 style="font-size:2.5rem;margin-bottom:1rem">Scanfit by the Numbers</h2>
    <p style="font-size:1.2rem;opacity:.95">Making a difference in online fashion retail</p>


    <div class="stats-grid">
        <div class="stat-box">
           
            <h3>10K+</h3>
            <p>Happy Customers</p>
        </div>


        <div class="stat-box">
            
            <h3>5K+</h3>
            <p>Products Available</p>
        </div>


        <div class="stat-box">
            
            <h3>98%</h3>
            <p>Satisfaction Rate</p>
        </div>


        <div class="stat-box">
            
            <h3>24/7</h3>
            <p>Customer Support</p>
        </div>
    </div>
</section>


<section class="mission-section">

    <!-- Mission statement -->
    <div class="mission-content">
        <h2>Our Mission</h2>
        <p>
            To make online fashion shopping effortless and enjoyable by providing accurate size recommendations,
            premium quality products, and exceptional customer service. We believe everyone deserves to look and
            feel their best, and we're committed to making that possible through technology and innovation.
        </p>
    </div>
</section>


<div class="container">
    <div class="content-section" style="text-align:center">

        <!-- section leading users to the BMI calculator -->
        <h2>Ready to Find Your Perfect Fit?</h2>
        <p style="margin-bottom:2rem">
            Join thousands of satisfied customers who trust Scanfit for their fashion needs
        </p>
        <a href="bmi_calculator.php" style="display:inline-block;padding:1.2rem 2.5rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;text-decoration:none;border-radius:50px;font-weight:700;font-size:1.1rem;transition:transform .3s">
            Get Started
        </a>
    </div>
</div>
</body>
</html>
