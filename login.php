
<?php
session_start();
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare(
        "SELECT id, name, email, password_hash, role
         FROM users WHERE email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password_hash"])) {
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        header("Location: dashboard.php");
        exit;
    }

    $message = "Invalid email or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CampusMate</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav>
        <h2>CampusMate</h2>
        <div><a href="index.php">Home</a></div>
    </nav>

    <main class="auth-layout">
        <section class="auth-art">
            <p style="font-weight:800;color:#1769ff;letter-spacing:1.5px;">WELCOME TO CAMPUSMATE</p>
            <h2>Your campus community, all in one place.</h2>
            <p>Reconnect lost belongings, share useful resources, and make campus life easier together.</p>
            <div class="card" style="margin-top:18px;padding:18px;">
                <strong style="color:#102a56;">A little help goes a long way.</strong>
                <p style="margin-top:5px;">Sign in to manage your reports, claims, and borrowing requests.</p>
            </div>
        </section>
        <section class="auth-panel">
            <h1>Welcome back</h1>
            <p style="color:#64748b;margin-bottom:22px;">Sign in to continue to your CampusMate account.</p>
            <?php if ($message !== ""): ?><p class="message" role="alert"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
            <form method="POST">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                <button class="btn" type="submit">Sign in</button>
            </form>
            <p style="margin-top:22px;color:#64748b;">New to CampusMate? <a href="register.php">Create an account</a></p>
        </section>
    </main>
</body>
</html>
