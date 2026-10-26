<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $action = $_POST["action"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please refresh the page.";
    } else {
        try {
            // List a resource.
            if ($action === "list") {
                $name = trim($_POST["name"] ?? "");
                $description = trim($_POST["description"] ?? "");
                $category = trim($_POST["category"] ?? "");
                $location = trim($_POST["location"] ?? "");

                if ($name === "" || $category === "") {
                    $message = "Enter a resource name and category.";
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO items
                         (user_id, name, description, category, location, listing_type, status)
                         VALUES (?, ?, ?, ?, ?, 'borrow', 'available')"
                    );
                    $stmt->execute([
                        $userId, $name, $description, $category, $location
                    ]);

                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                    header("Location: resources.php?listed=1");
                    exit;
                }

            // Request to borrow a resource.
            } elseif ($action === "request") {
                $itemId = filter_input(INPUT_POST, "item_id", FILTER_VALIDATE_INT);

                if (!$itemId) {
                    $message = "Invalid resource selected.";
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "SELECT id, user_id, status
                         FROM items
                         WHERE id = ? AND listing_type = 'borrow'
                         FOR UPDATE"
                    );
                    $stmt->execute([$itemId]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$item || (int)$item["user_id"] === $userId) {
                        $pdo->rollBack();
                        $message = "You cannot request this resource.";
                    } elseif ($item["status"] !== "available") {
                        $pdo->rollBack();
                        $message = "This resource is no longer available.";
                    } else {
                        $check = $pdo->prepare(
                            "SELECT id FROM borrow_requests
                             WHERE item_id = ? AND borrower_id = ?
                               AND status IN ('pending', 'approved', 'borrowed')
                             LIMIT 1"
                        );
                        $check->execute([$itemId, $userId]);

                        if ($check->fetch()) {
                            $pdo->rollBack();
                            $message = "You already have an active request for this resource.";
                        } else {
                            $note = trim($_POST["request_note"] ?? "");
                            $insert = $pdo->prepare(
                                "INSERT INTO borrow_requests
                                 (item_id, borrower_id, request_note, status)
                                 VALUES (?, ?, ?, 'pending')"
                            );
                            $insert->execute([$itemId, $userId, $note]);

                            $pdo->commit();
                            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                            header("Location: resources.php?requested=1");
                            exit;
                        }
                    }
                }

            // Owner approves or rejects a request.
            } elseif ($action === "manage") {
                $requestId = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);
                $decision = $_POST["decision"] ?? "";

                if (!$requestId || !in_array($decision, ["approve", "reject"], true)) {
                    $message = "Invalid borrow request.";
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "SELECT br.id, br.item_id, br.status AS request_status,
                                i.user_id AS owner_id, i.status AS item_status,
                                i.listing_type
                         FROM borrow_requests br
                         JOIN items i ON br.item_id = i.id
                         WHERE br.id = ?
                         FOR UPDATE"
                    );
                    $stmt->execute([$requestId]);
                    $request = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$request || (int)$request["owner_id"] !== $userId) {
                        $pdo->rollBack();
                        $message = "You are not allowed to manage this request.";
                    } elseif (
                        $request["listing_type"] !== "borrow" ||
                        $request["request_status"] !== "pending" ||
                        $request["item_status"] !== "available"
                    ) {
                        $pdo->rollBack();
                        $message = "This request is no longer available for review.";
                    } elseif ($decision === "approve") {
                        $updateRequest = $pdo->prepare(
                            "UPDATE borrow_requests SET status = 'approved'
                             WHERE id = ? AND status = 'pending'"
                        );
                        $updateRequest->execute([$requestId]);

                        $updateItem = $pdo->prepare(
                            "UPDATE items SET status = 'requested'
                             WHERE id = ? AND status = 'available'"
                        );
                        $updateItem->execute([(int)$request["item_id"]]);

                        if (
                            $updateRequest->rowCount() !== 1 ||
                            $updateItem->rowCount() !== 1
                        ) {
                            throw new RuntimeException("Could not approve the borrow request.");
                        }

                        // Reject other pending requests for this resource.
                        $rejectOthers = $pdo->prepare(
                            "UPDATE borrow_requests SET status = 'rejected'
                             WHERE item_id = ? AND id <> ? AND status = 'pending'"
                        );
                        $rejectOthers->execute([
                            (int)$request["item_id"], $requestId
                        ]);

                        $pdo->commit();
                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                        header("Location: resources.php?managed=approved");
                        exit;
                    } else {
                        $updateRequest = $pdo->prepare(
                            "UPDATE borrow_requests SET status = 'rejected'
                             WHERE id = ? AND status = 'pending'"
                        );
                        $updateRequest->execute([$requestId]);

                        if ($updateRequest->rowCount() !== 1) {
                            throw new RuntimeException("Could not reject the borrow request.");
                        }

                        $pdo->commit();
                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                        header("Location: resources.php?managed=rejected");
                        exit;
                    }
                }

            // Owner marks an approved resource as returned.
            } elseif ($action === "return") {
                $requestId = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);

                if (!$requestId) {
                    $message = "Invalid borrow request.";
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "SELECT br.id, br.item_id, br.status AS request_status,
                                i.user_id AS owner_id, i.status AS item_status,
                                i.listing_type
                         FROM borrow_requests br
                         JOIN items i ON br.item_id = i.id
                         WHERE br.id = ?
                         FOR UPDATE"
                    );
                    $stmt->execute([$requestId]);
                    $request = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$request || (int)$request["owner_id"] !== $userId) {
                        $pdo->rollBack();
                        $message = "You are not allowed to return this resource.";
                    } elseif (
                        $request["listing_type"] !== "borrow" ||
                        $request["request_status"] !== "approved" ||
                        $request["item_status"] !== "requested"
                    ) {
                        $pdo->rollBack();
                        $message = "This request cannot be marked as returned.";
                    } else {
                        $updateRequest = $pdo->prepare(
                            "UPDATE borrow_requests SET status = 'returned'
                             WHERE id = ? AND status = 'approved'"
                        );
                        $updateRequest->execute([$requestId]);

                        $updateItem = $pdo->prepare(
                            "UPDATE items SET status = 'available'
                             WHERE id = ? AND listing_type = 'borrow'
                               AND status = 'requested'"
                        );
                        $updateItem->execute([(int)$request["item_id"]]);

                        if (
                            $updateRequest->rowCount() !== 1 ||
                            $updateItem->rowCount() !== 1
                        ) {
                            throw new RuntimeException("Could not mark the resource as returned.");
                        }

                        $pdo->commit();
                        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                        header("Location: resources.php?managed=returned");
                        exit;
                    }
                }
            } else {
                $message = "Invalid action.";
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $message = "Something went wrong. Please try again.";
        }
    }
}

// Success messages.
if (isset($_GET["listed"])) {
    $message = "Resource listed successfully.";
} elseif (isset($_GET["requested"])) {
    $message = "Borrow request submitted successfully.";
} elseif (isset($_GET["managed"])) {
    if ($_GET["managed"] === "approved") {
        $message = "Borrow request approved successfully.";
    } elseif ($_GET["managed"] === "rejected") {
        $message = "Borrow request rejected successfully.";
    } elseif ($_GET["managed"] === "returned") {
        $message = "Resource marked as returned successfully.";
    }
}

// Available resources shared by other students.
$stmt = $pdo->prepare(
    "SELECT items.id, items.name, items.description,
            items.category, items.location, items.image_path,
            users.name AS owner_name
     FROM items
     JOIN users ON items.user_id = users.id
     WHERE items.listing_type = 'borrow'
       AND items.status = 'available'
       AND items.user_id <> ?
     ORDER BY items.created_at DESC"
);
$stmt->execute([$userId]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requests received for resources owned by the logged-in student.
$stmt = $pdo->prepare(
    "SELECT br.id AS request_id, br.request_note, br.status AS request_status,
            br.requested_at, i.name AS item_name, i.id AS item_id,
            u.name AS borrower_name, u.email AS borrower_email
     FROM borrow_requests br
     JOIN items i ON br.item_id = i.id
     JOIN users u ON br.borrower_id = u.id
     WHERE i.user_id = ? AND i.listing_type = 'borrow'
     ORDER BY br.requested_at DESC"
);
$stmt->execute([$userId]);
$receivedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requests made by the logged-in student.
$stmt = $pdo->prepare(
    "SELECT br.id AS request_id, br.request_note,
            br.status AS request_status, br.requested_at,
            i.name AS item_name, u.name AS owner_name
     FROM borrow_requests br
     JOIN items i ON br.item_id = i.id
     JOIN users u ON i.user_id = u.id
     WHERE br.borrower_id = ?
     ORDER BY br.requested_at DESC"
);
$stmt->execute([$userId]);
$myRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Exchange | CampusMate</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .resource-content {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
        }
        .resource-form, .resource-card, .request-card {
            background: #fff;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
            overflow-wrap: anywhere;
        }
        .resource-form input, .resource-form textarea, .resource-form select,
        .resource-card textarea {
            width: 100%;
            padding: 11px;
            margin: 8px 0 14px;
            border: 1px solid #ccd3dd;
            border-radius: 7px;
            box-sizing: border-box;
            font: inherit;
        }
        .resource-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .resource-photo {
            width: 100%;
            max-height: 220px;
            object-fit: contain;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .notice {
            padding: 12px;
            margin-bottom: 20px;
            background: #eaf3ff;
            border-radius: 8px;
        }
        .request-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .reject-button { background: #b42318; }
        .status {
            font-weight: bold;
            text-transform: capitalize;
        }
        .section-gap { margin-top: 38px; }
    </style>
</head>
<body>
<nav>
    <h2>CampusMate</h2>
    <div>
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="lost_found.php">Lost &amp; Found</a>
    </div>
</nav>

<section class="hero">
    <h1>Resource Exchange</h1>
    <p>Share resources with fellow students or request to borrow them.</p>
    <a class="btn" href="dashboard.php">Back to Dashboard</a>
</section>

<main class="resource-content">
    <?php if ($message !== ""): ?>
        <p class="notice" role="status"><?php echo e($message); ?></p>
    <?php endif; ?>

    <section>
        <h2>Share a Resource</h2>
        <form method="POST" class="resource-form">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION["csrf_token"]); ?>">
            <input type="hidden" name="action" value="list">

            <label for="name">Resource name *</label>
            <input id="name" name="name" maxlength="150"
                   placeholder="e.g. Engineering Mathematics textbook" required>

            <label for="category">Category *</label>
            <select id="category" name="category" required>
                <option value="">Choose category</option>
                <option>Books</option>
                <option>Electronics</option>
                <option>Lab Equipment</option>
                <option>Stationery</option>
                <option>Sports</option>
                <option>Other</option>
            </select>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"
                      placeholder="Describe the resource"></textarea>

            <label for="location">Pickup location</label>
            <input id="location" name="location" maxlength="150"
                   placeholder="e.g. Library">

            <button class="btn" type="submit">List Resource</button>
        </form>
    </section>

    <section class="section-gap">
        <h2>Available Resources</h2>
        <?php if (!$resources): ?>
            <p>No resources are currently available from other students.</p>
        <?php else: ?>
            <div class="resource-grid">
                <?php foreach ($resources as $resource): ?>
                    <article class="resource-card">
                        <?php
                        $photo = basename((string)($resource["image_path"] ?? ""));
                        $photoFile = __DIR__ . "/uploads/" . $photo;
                        ?>
                        <?php if ($photo !== "" && is_file($photoFile)): ?>
                            <img class="resource-photo"
                                 src="uploads/<?php echo rawurlencode($photo); ?>"
                                 alt="Resource photo">
                        <?php endif; ?>

                        <h3><?php echo e($resource["name"]); ?></h3>
                        <p><strong>Category:</strong> <?php echo e($resource["category"]); ?></p>
                        <p><strong>Description:</strong>
                            <?php echo e($resource["description"] ?: "Not provided"); ?></p>
                        <p><strong>Pickup location:</strong>
                            <?php echo e($resource["location"] ?: "Not specified"); ?></p>
                        <p><strong>Shared by:</strong> <?php echo e($resource["owner_name"]); ?></p>

                        <form method="POST">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo e($_SESSION["csrf_token"]); ?>">
                            <input type="hidden" name="action" value="request">
                            <input type="hidden" name="item_id"
                                   value="<?php echo (int)$resource["id"]; ?>">

                            <label for="note-<?php echo (int)$resource["id"]; ?>">
                                Note to owner (optional)
                            </label>
                            <textarea id="note-<?php echo (int)$resource["id"]; ?>"
                                      name="request_note" rows="2" maxlength="1000"
                                      placeholder="When do you need it?"></textarea>
                            <button class="btn" type="submit">Request to Borrow</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section-gap">
        <h2>Borrow Requests Received</h2>
        <p>Review requests from students who want to borrow your resources.</p>

        <?php if (!$receivedRequests): ?>
            <p>You have not received any borrow requests yet.</p>
        <?php else: ?>
            <?php foreach ($receivedRequests as $request): ?>
                <article class="request-card">
                    <h3><?php echo e($request["item_name"]); ?></h3>
                    <p><strong>Requested by:</strong> <?php echo e($request["borrower_name"]); ?></p>
                    <p><strong>Contact email:</strong> <?php echo e($request["borrower_email"]); ?></p>
                    <p><strong>Note:</strong>
                        <?php echo nl2br(e($request["request_note"] ?: "No note provided")); ?></p>
                    <p class="status"><strong>Status:</strong>
                        <?php echo e($request["request_status"]); ?></p>
                    <small>Requested: <?php echo e($request["requested_at"]); ?></small>

                    <?php if ($request["request_status"] === "pending"): ?>
                        <form method="POST" class="request-actions">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo e($_SESSION["csrf_token"]); ?>">
                            <input type="hidden" name="action" value="manage">
                            <input type="hidden" name="request_id"
                                   value="<?php echo (int)$request["request_id"]; ?>">
                            <button class="btn" type="submit" name="decision" value="approve">
                                Approve Request
                            </button>
                            <button class="btn reject-button" type="submit"
                                    name="decision" value="reject">
                                Reject Request
                            </button>
                        </form>
                    <?php elseif ($request["request_status"] === "approved"): ?>
                        <form method="POST" class="request-actions"
                              onsubmit="return confirm('Confirm that the resource has actually been returned?');">
                            <input type="hidden" name="csrf_token"
                                   value="<?php echo e($_SESSION["csrf_token"]); ?>">
                            <input type="hidden" name="action" value="return">
                            <input type="hidden" name="request_id"
                                   value="<?php echo (int)$request["request_id"]; ?>">
                            <button class="btn" type="submit">Mark as Returned</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="section-gap">
        <h2>My Borrow Requests</h2>
        <?php if (!$myRequests): ?>
            <p>You have not requested to borrow any resources yet.</p>
        <?php else: ?>
            <?php foreach ($myRequests as $request): ?>
                <article class="request-card">
                    <h3><?php echo e($request["item_name"]); ?></h3>
                    <p><strong>Resource owner:</strong> <?php echo e($request["owner_name"]); ?></p>
                    <p><strong>My note:</strong>
                        <?php echo e($request["request_note"] ?: "No note provided"); ?></p>
                    <p class="status"><strong>Status:</strong>
                        <?php echo e($request["request_status"]); ?></p>
                    <small>Requested: <?php echo e($request["requested_at"]); ?></small>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
