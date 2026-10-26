
<?php
session_start();
require_once "config/database.php";

$stmt = $pdo->query(
    "SELECT items.id, items.user_id, items.name, items.description,
            items.category, items.location, items.listing_type,
            items.status, items.created_at, items.image_path,
            users.name AS reporter
     FROM items
     JOIN users ON items.user_id = users.id
     WHERE items.listing_type IN ('lost', 'found')
     ORDER BY items.created_at DESC"
);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isLoggedIn = isset($_SESSION["user_id"]);

function escapeText($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lost & Found | CampusMate</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            background: #f5f8ff;
        }

        .lost-hero {
            text-align: center;
            padding: 55px 20px 45px;
            background:
                radial-gradient(circle at 10% 10%, #dceaff, transparent 40%),
                linear-gradient(135deg, #eaf2ff, #f8fbff);
            border-bottom: 1px solid #e1eafa;
        }

        .lost-hero h1 {
            margin: 0 0 12px;
            color: #102a56;
            font-size: clamp(30px, 4vw, 44px);
            font-weight: 800;
            letter-spacing: -1px;
        }

        .lost-hero p {
            margin: 0 auto 22px;
            color: #5d708d;
            font-size: 16px;
        }

        .lost-hero .success-message {
            display: block;
            width: fit-content;
            max-width: 100%;
            margin: 12px auto;
            padding: 10px 16px;
            border: 1px solid #b8efd5;
            border-radius: 10px;
            background: #e8fff3;
            color: #08754d;
        }

        .lost-items {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            width: min(1200px, calc(100% - 40px));
            margin: 0 auto;
            padding: 36px 0 55px;
            align-items: stretch;
        }

        .lost-item-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 20px;
            overflow-wrap: anywhere;
            background: #fff;
            border: 1px solid #e1e9f5;
            border-radius: 18px;
            box-shadow: 0 8px 26px rgba(24, 55, 100, .07);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .lost-item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 32px rgba(24, 55, 100, .12);
        }

        .lost-item-card h2 {
            margin: 14px 0 10px;
            color: #102a56;
            font-size: 20px;
            line-height: 1.4;
        }

        .item-photo {
            display: block;
            width: 100%;
            height: 210px;
            margin: 0 0 12px;
            padding: 8px;
            object-fit: contain;
            border: 1px solid #edf1f7;
            border-radius: 12px;
            background: #f7faff;
        }

        .item-details {
            display: grid;
            gap: 7px;
        }

        .item-details p {
            margin: 0;
            color: #526783;
            font-size: 14px;
            line-height: 1.55;
        }

        .item-details strong {
            color: #263f62;
        }

        .item-type {
            display: inline-flex;
            align-self: flex-start;
            width: fit-content;
            padding: 5px 11px;
            border-radius: 20px;
            background: #eaf2ff;
            color: #1558c8;
            font-size: 12px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .item-type.lost {
            background: #fff0e5;
            color: #a54b0a;
        }

        .item-status {
            display: inline-flex;
            align-self: flex-start;
            width: fit-content;
            margin-top: 12px;
            padding: 5px 11px;
            border-radius: 20px;
            background: #e8f1ff;
            color: #1459c7;
            font-size: 12px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .item-status.open {
            background: #d9f8e9;
            color: #08754d;
        }

        .item-status.claimed {
            background: #e8eafc;
            color: #4546a4;
        }

        .claim-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            align-self: flex-start;
            margin: 15px 0 5px;
            padding: 11px 17px;
            border-radius: 10px;
            background: linear-gradient(135deg, #2678ff, #1558e8);
            color: white;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 5px 13px rgba(23, 105, 255, .18);
        }

        .claim-button:hover {
            background: #1055d8;
            color: white;
        }

        .item-date {
            margin-top: auto;
            padding-top: 16px;
            color: #8491a5;
            font-size: 11px;
        }

        .empty-items {
            grid-column: 1 / -1;
            padding: 45px 20px;
            text-align: center;
            background: #fff;
            border: 1px solid #e1e9f5;
            border-radius: 18px;
            color: #64748b;
        }

        @media (max-width: 950px) {
            .lost-items {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .lost-hero {
                padding: 40px 18px;
            }

            .lost-items {
                width: calc(100% - 28px);
                grid-template-columns: 1fr;
                gap: 17px;
                padding: 22px 0 35px;
            }

            .lost-item-card {
                padding: 17px;
            }

            .item-photo {
                height: 200px;
            }
        }
    </style>
</head>

<body>

<nav>
    <h2>CampusMate</h2>

    <div>
        <a href="index.php">Home</a>
        <a href="lost_found.php">Lost & Found</a>
        <a href="resources.php">Resources</a>

        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </div>
</nav>

<section class="lost-hero">
    <h1>Campus Lost & Found</h1>
    <p>Lost something or found an item? Help reconnect belongings with their owners.</p>

    <?php if (isset($_GET["reported"])): ?>
        <p class="success-message">
            Your item report was saved successfully!
        </p>
    <?php endif; ?>

    <?php if (isset($_GET["claim_sent"])): ?>
        <p class="success-message">
            Your claim request was submitted successfully!
        </p>
    <?php endif; ?>

    <a class="btn" href="report_item.php">+ Report an Item</a>
</section>

<section class="lost-items">

    <?php if (count($items) === 0): ?>
        <div class="empty-items">
            <h2>No items reported yet</h2>
            <p>Be the first to report a lost or found item on campus.</p>
            <a class="btn" href="report_item.php">Report an Item</a>
        </div>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>

        <?php
        $isOwnItem = $isLoggedIn &&
            (int)$_SESSION["user_id"] === (int)$item["user_id"];

        $isClaimable = $item["status"] === "open" &&
            $item["listing_type"] === "found";
        ?>

        <article class="lost-item-card">

            <span class="item-type <?php echo escapeText($item["listing_type"]); ?>">
                <?php echo escapeText(ucfirst($item["listing_type"])); ?> Item
            </span>

            <?php if (!empty($item["image_path"])): ?>
                <img
                    class="item-photo"
                    src="uploads/<?php echo rawurlencode(basename($item["image_path"])); ?>"
                    alt="Photo of <?php echo escapeText($item["name"]); ?>"
                    loading="lazy"
                >
            <?php endif; ?>

            <h2><?php echo escapeText($item["name"]); ?></h2>

            <div class="item-details">
                <p>
                    <strong>Category:</strong>
                    <?php echo escapeText($item["category"] ?: "Not specified"); ?>
                </p>

                <p>
                    <strong>Location:</strong>
                    <?php echo escapeText($item["location"] ?: "Not specified"); ?>
                </p>

                <p>
                    <strong>Description:</strong>
                    <?php echo nl2br(escapeText($item["description"] ?: "No description provided.")); ?>
                </p>

                <p>
                    <strong>Reported by:</strong>
                    <?php echo escapeText($item["reporter"]); ?>
                </p>
            </div>

            <span class="item-status <?php echo escapeText(strtolower($item["status"])); ?>">
                Status: <?php echo escapeText(ucfirst($item["status"])); ?>
            </span>

            <?php if ($isClaimable && !$isOwnItem): ?>

                <?php if ($isLoggedIn): ?>
                    <a
                        class="claim-button"
                        href="claim_item.php?id=<?php echo (int)$item["id"]; ?>"
                    >
                        Claim Item
                    </a>
                <?php else: ?>
                    <a class="claim-button" href="login.php">
                        Login to Claim
                    </a>
                <?php endif; ?>

            <?php endif; ?>

            <small class="item-date">
                Reported on:
                <?php echo escapeText($item["created_at"]); ?>
            </small>

        </article>

    <?php endforeach; ?>

</section>

<footer>
    CampusMate &copy; <?php echo date("Y"); ?> |
    Smart Campus Resource Exchange
</footer>

</body>
</html>
