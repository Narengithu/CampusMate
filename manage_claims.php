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
$userId = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $claimId = filter_input(INPUT_POST, "claim_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please refresh the page.";
    } elseif (!$claimId || !in_array($action, ["approve", "reject"], true)) {
        $message = "Invalid claim request.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT claims.id, claims.item_id, claims.status,
                        items.user_id AS reporter_id, items.listing_type,
                        items.status AS item_status
                 FROM claims
                 JOIN items ON claims.item_id = items.id
                 WHERE claims.id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$claimId]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$claim ||
                (int) $claim["reporter_id"] !== $userId
            ) {
                $message = "You are not allowed to manage this claim.";
                $pdo->rollBack();
            } elseif (
                $claim["status"] !== "pending" ||
                $claim["listing_type"] !== "found" ||
                $claim["item_status"] !== "open"
            ) {
                $message = "This claim is no longer available for review.";
                $pdo->rollBack();
            } else {
                if ($action === "approve") {
                    $updateClaim = $pdo->prepare(
                        "UPDATE claims SET status = 'approved'
                         WHERE id = ? AND status = 'pending'"
                    );
                    $updateClaim->execute([$claimId]);

                    $updateItem = $pdo->prepare(
                        "UPDATE items SET status = 'claimed'
                         WHERE id = ? AND status = 'open'"
                    );
                    $updateItem->execute([(int) $claim["item_id"]]);

                    if (
                        $updateClaim->rowCount() !== 1 ||
                        $updateItem->rowCount() !== 1
                    ) {
                        throw new RuntimeException("The claim could not be approved.");
                    }

                    // Reject other pending claims for the same item.
                    $rejectOthers = $pdo->prepare(
                        "UPDATE claims SET status = 'rejected'
                         WHERE item_id = ? AND id <> ?
                           AND status = 'pending'"
                    );
                    $rejectOthers->execute([
                        (int) $claim["item_id"],
                        $claimId
                    ]);

                    $message = "Claim approved successfully.";
                } else {
                    $updateClaim = $pdo->prepare(
                        "UPDATE claims SET status = 'rejected'
                         WHERE id = ? AND status = 'pending'"
                    );
                    $updateClaim->execute([$claimId]);

                    if ($updateClaim->rowCount() !== 1) {
                        throw new RuntimeException("The claim could not be rejected.");
                    }

                    $message = "Claim rejected successfully.";
                }

                $pdo->commit();
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $message = "Something went wrong. Please try again.";
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT claims.id AS claim_id, claims.details,
            claims.status AS claim_status, claims.created_at,
            items.name AS item_name, items.location,
            users.name AS claimant_name, users.email AS claimant_email
     FROM claims
     JOIN items ON claims.item_id = items.id
     JOIN users ON claims.claimant_id = users.id
     WHERE items.user_id = ?
       AND items.listing_type = 'found'
     ORDER BY claims.created_at DESC"
);
$stmt->execute([$userId]);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims | CampusMate</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .claims-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .claim-card {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            overflow-wrap: anywhere;
        }

        .claim-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .reject-button {
            background: #b42318;
        }

        .claim-status {
            font-weight: bold;
        }

        .message {
            margin: 15px 0;
        }
    </style>
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
        <h1>Manage Claim Requests</h1>
        <p>Review requests for items you reported as found.</p>
    </section>

    <main class="claims-container">
        <?php if ($message !== ""): ?>
            <p class="message" role="status">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </p>
        <?php endif; ?>

        <?php if (!$claims): ?>
            <p>You have no claim requests yet.</p>
        <?php endif; ?>

        <?php foreach ($claims as $claim): ?>
            <article class="claim-card">
                <h2>
                    <?php echo htmlspecialchars($claim["item_name"], ENT_QUOTES, "UTF-8"); ?>
                </h2>

                <p>
                    <strong>Claimant:</strong>
                    <?php echo htmlspecialchars($claim["claimant_name"], ENT_QUOTES, "UTF-8"); ?>
                </p>

                <p>
                    <strong>Contact email:</strong>
                    <?php echo htmlspecialchars($claim["claimant_email"], ENT_QUOTES, "UTF-8"); ?>
                </p>

                <p>
                    <strong>Location:</strong>
                    <?php echo htmlspecialchars($claim["location"] ?? "Not specified", ENT_QUOTES, "UTF-8"); ?>
                </p>

                <p><strong>Claim explanation:</strong></p>
                <p>
                    <?php echo nl2br(htmlspecialchars($claim["details"], ENT_QUOTES, "UTF-8")); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <span class="claim-status">
                        <?php echo htmlspecialchars(ucfirst($claim["claim_status"]), ENT_QUOTES, "UTF-8"); ?>
                    </span>
                </p>

                <small>
                    Submitted:
                    <?php echo htmlspecialchars($claim["created_at"], ENT_QUOTES, "UTF-8"); ?>
                </small>

                <?php if ($claim["claim_status"] === "pending"): ?>
                    <form method="POST" class="claim-actions">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                        <input type="hidden" name="claim_id"
                            value="<?php echo (int) $claim["claim_id"]; ?>">

                        <button class="btn" type="submit" name="action" value="approve">
                            Approve Claim
                        </button>

                        <button class="btn reject-button" type="submit" name="action" value="reject">
                            Reject Claim
                        </button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>