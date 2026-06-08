<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <title>Login Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="css/styles.css">
</head>
<body>
    <nav>
        <img src="images/logo.png" alt="Company Logo" class="logo">
    </nav>
    <div class="container">
        <div class="login-card">
            <h2>Login to Your Account</h2>
            <form id="loginForm">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" value="agnicarrental@gmail.com" placeholder="Enter your email" required>
                </div>
                <div class="form-group password-wrapper">
                    <label for="password">Password</label>
                    <input type="password" id="password" placeholder="Enter your password" required>
                    <i class="fas fa-eye toggle-icon" id="toggleIcon"></i>
                </div>
                <p id="error-message" class="error-msg"></p>
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </div>
    <script src="javascripts/script.js"></script>
</body>
</html>