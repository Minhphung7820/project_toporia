<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to Our Platform!</h1>
    </div>
    <div class="content">
        <h2>Hello, <?= htmlspecialchars($name) ?>!</h2>
        <p>Thank you for registering with us. We're excited to have you on board!</p>
        <p>Your account has been successfully created. You can now access all features of our platform.</p>
        <p>
            <a href="<?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?>/dashboard" class="button">
                Go to Dashboard
            </a>
        </p>
        <p>If you have any questions, feel free to reach out to our support team.</p>
        <p>Best regards,<br>The Team</p>
    </div>
    <div class="footer">
        <p>This email was sent to you because you registered an account.</p>
        <p>&copy; <?= date('Y') ?> Our Platform. All rights reserved.</p>
    </div>
</body>
</html>
