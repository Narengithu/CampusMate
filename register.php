
<?php
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 8) {
        $message = "Enter a valid name and email. Password must be at least 8 characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hash]);

            $message = "Registration successful! You can now log in.";
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $message = "This email is already registered.";
            } else {
                error_log($e->getMessage());
                $message = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CampusMate</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav>
        <h2>CampusMate</h2>
        <div><a href="index.php">Home</a></div>
    </nav>

    <main class="auth-layout">
        <section class="auth-art">
            <p style="font-weight:800;color:#1769ff;letter-spacing:1.5px;">JOIN THE COMMUNITY</p>
            <h2>Make campus life more connected.</h2>
            <p>Create your account to report items, reunite belongings with their owners, and share resources with fellow students.</p>
            <div class="card" style="margin-top:18px;padding:18px;">
                <strong style="color:#102a56;">One campus. Many ways to help.</strong>
                <p style="margin-top:5px;">Be part of a more helpful student community.</p>
            </div>
        </section>
        <section class="auth-panel">
            <h1>Create account</h1>
            <p style="color:#64748b;margin-bottom:22px;">It only takes a moment to get started.</p>
            <?php if ($message !== ""): ?><p class="message" role="status"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
            <form method="POST">
                <label for="name">Full name</label>
                <input id="name" type="text" name="name" placeholder="Your full name" maxlength="100" autocomplete="name" required>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" placeholder="you@example.com" maxlength="150" autocomplete="email" required>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required>
                <button class="btn" type="submit">Create account</button>
            </form>
            <p style="margin-top:22px;color:#64748b;">Already have an account? <a href="login.php">Sign in</a></p>
        </section>
    </main>
</body>
</html>
