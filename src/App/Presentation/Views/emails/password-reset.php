<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
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
            background-color: #FF5722;
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
            background-color: #FF5722;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .token {
            background-color: #e0e0e0;
            padding: 10px;
            border-radius: 3px;
            font-family: monospace;
            margin: 15px 0;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 20px 0;
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
        <h1>Password Reset Request</h1>
    </div>
    <div class="content">
        <h2>Reset Your Password</h2>
        <p>You are receiving this email because we received a password reset request for your account.</p>
        <p>Click the button below to reset your password:</p>
        <p>
            <a href="<?= htmlspecialchars($resetUrl) ?>" class="button">
                Reset Password
            </a>
        </p>
        <p>Or copy and paste this link into your browser:</p>
        <div class="token">
            <?= htmlspecialchars($resetUrl) ?>
        </div>
        <div class="warning">
            <strong>Security Note:</strong> This password reset link will expire in 60 minutes. If you did not request a password reset, please ignore this email. Your password will remain unchanged.
        </div>
        <p>Best regards,<br>The Team</p>
    </div>
    <div class="footer">
        <p>If you're having trouble clicking the button, copy and paste the URL above into your web browser.</p>
        <p>&copy; <?= date('Y') ?> Our Platform. All rights reserved.</p>
    </div>
</body>
</html>
