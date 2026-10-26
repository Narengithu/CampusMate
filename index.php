
<?php
require_once "config/database.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusMate | Smart Campus</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <nav>
        <h2>CampusMate</h2>
        <div>
            <a href="index.php">Home</a>
            <a href="lost_found.php">Lost & Found</a>
            <a href="resources.php">Resources</a>
            <a href="login.php">Login</a>
        </div>
    </nav>

    <section class="hero">
        <h1>Your Campus. Your Community.</h1>
        <p>
            Find lost belongings, report found items, and share
            useful resources with fellow students in one place.
        </p>

        <a class="btn" href="lost_found.php">Explore Lost & Found</a>
        <a class="btn" href="resources.php">Browse Resources</a>
    </section>

    <section class="features">
        <div class="card">
            <h3>Lost & Found</h3>
            <p>Report found belongings and help students reconnect with their items.</p>
        </div>

        <div class="card">
            <h3>Resource Exchange</h3>
            <p>Share or borrow books, calculators and other useful campus resources.</p>
        </div>

        <div class="card">
            <h3>Track Your Activity</h3>
            <p>Keep track of your item reports, claims and borrowing requests.</p>
        </div>
    </section>

    <footer>
        <p>CampusMate &copy; 2026 | Smart Campus Resource Exchange</p>
    </footer>

</body>
</html>
