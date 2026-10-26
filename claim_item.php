
<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$itemId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $itemId = filter_input(INPUT_POST, "item_id", FILTER_VALIDATE_INT);
    $token = $_POST["csrf_token"] ?? "";
    $details = trim($_POST["details"] ?? "");

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please refresh the page.";
    } elseif (!$itemId || $details === "") {
        $message = "Please provide your claim details.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, name
             FROM items
             WHERE id = ? AND listing_type IN ('lost', 'found')
               AND status = 'open'"
        );
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $message = "This item is unavailable for claims.";
        } elseif ((int)$item["user_id"] === (int)$_SESSION["user_id"]) {
            $message = "You cannot claim an item you reported yourself.";
        } else {
            $check = $pdo->prepare(
                "SELECT id FROM claims
                 WHERE item_id = ? AND claimant_id = ?
                   AND status = 'pending'"
            );
            $check->execute([$itemId, $_SESSION["user_id"]]);

            if ($check->fetch()) {
                $message = "You already have a pending claim for this item.";
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO claims
                     (item_id, claimant_id, details)
                     VALUES (?, ?, ?)"
                );
                $insert->execute([
                    $itemId,
                    $_SESSION["user_id"],
                    $details
                ]);

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                header("Location: lost_found.php?claim_sent=1");
                exit;
            }
        }
    }
}

$item = null;

if ($itemId && $itemId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, name, description, category, location, user_id
         FROM items
         WHERE id = ? AND listing_type IN ('lost', 'found')
           AND status = 'open'"
    );
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && (int)$item["user_id"] === (int)$_SESSION["user_id"]) {
        $item = null;
        $message = "You cannot claim an item you reported yourself.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Item | CampusMate</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav>
        <h2>CampusMate</h2>
        <div>
            <a href="index.php">Home</a>
            <a href="lost_found.php">Lost &amp; Found</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
    </nav>

    <section class="hero">
        <h1>Claim an Item</h1>

        <?php if ($message !== ""): ?>
            <p role="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>

        <?php if ($item): ?>
            <h2><?php echo htmlspecialchars($item["name"], ENT_QUOTES, "UTF-8"); ?></h2>
            <p>Explain why you believe this item belongs to you.</p>

            <form method="POST" class="item-form">
                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                <input type="hidden" name="item_id"
                    value="<?php echo (int)$item["id"]; ?>">

                <label for="details">Claim Details</label>
                <textarea id="details" name="details" rows="5"
                    maxlength="2000"
                    placeholder="Describe identifying details that can help verify your claim."
                    required></textarea>

                <button class="btn" type="submit">Submit Claim</button>
            </form>
        <?php elseif ($message === ""): ?>
            <p>Item not found or it is no longer available for claims.</p>
        <?php endif; ?>
    </section>
</body>
</html>
