<!DOCTYPE html>
<html>
<head>
    <title>Email Verified</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .success { color: #28a745; font-size: 24px; margin-bottom: 20px; }
        .error { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
        .info { color: #17a2b8; font-size: 24px; margin-bottom: 20px; }
        .btn { background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 20px; }
        .btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        @if(session('success'))
            <div class="success">✓</div>
            <h1>Email Verified Successfully!</h1>
            <p>{{ session('success') }}</p>
        @elseif(session('info'))
            <div class="info">ℹ️</div>
            <h1>Email Already Verified</h1>
            <p>{{ session('info') }}</p>
        @elseif(session('error'))
            <div class="error">✗</div>
            <h1>Verification Failed</h1>
            <p>{{ session('error') }}</p>
        @else
            <div class="success">✓</div>
            <h1>Email Verified Successfully!</h1>
            <p>Your email address has been verified. You can now log in to your account.</p>
        @endif

        <a href="{{ url('/') }}" class="btn">Go to Home Page</a>
    </div>
</body>
</html>
