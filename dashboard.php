
<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* My reported items */
$stmt = $pdo->prepare(
    "SELECT id, name, listing_type, status, location, image_path, created_at
     FROM items
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$userId]);
$myItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* My submitted claims */
$stmt = $pdo->prepare(
    "SELECT claims.id, claims.details, claims.status,
            claims.created_at, items.name AS item_name, items.location
     FROM claims
     JOIN items ON claims.item_id = items.id
     WHERE claims.claimant_id = ?
     ORDER BY claims.created_at DESC"
);
$stmt->execute([$userId]);
$myClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Claims submitted for my found items */
$stmt = $pdo->prepare(
    "SELECT claims.id AS claim_id,
            claims.details,
            claims.status AS claim_status,
            claims.created_at,
            items.name AS item_name,
            users.name AS claimant_name
     FROM claims
     JOIN items ON claims.item_id = items.id
     JOIN users ON claims.claimant_id = users.id
     WHERE items.user_id = ?
       AND items.listing_type = 'found'
     ORDER BY claims.created_at DESC"
);
$stmt->execute([$userId]);
$receivedClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalItems = count($myItems);
$totalClaims = count($myClaims);
$pendingClaims = 0;

foreach ($receivedClaims as $claim) {
    if (strtolower($claim["claim_status"]) === "pending") {
        $pendingClaims++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CampusMate | Dashboard</title>
<link rel="stylesheet" href="assets/css/style.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f7fd;
    color: #172b4d;
    font-family: Arial, sans-serif;
    font-size: 15px;
}

/* Navigation */
nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    padding: 18px 5%;
    background: #fff;
    border-bottom: 1px solid #e1e9f5;
}

nav h2 {
    margin: 0;
    color: #15376b;
    font-size: 24px;
}

nav div {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 22px;
}

nav a {
    color: #405574;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}

nav a:hover {
    color: #1769ff;
}

/* Welcome banner */
.hero {
    width: 100%;
    padding: 42px 5%;
    background: linear-gradient(120deg, #dceaff, #f7faff);
    border-bottom: 1px solid #dfe8f7;
}

.hero-inner {
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
}

.hero h1 {
    margin: 0 0 12px;
    color: #102a56;
    font-size: clamp(30px, 3vw, 40px);
    line-height: 1.3;
    letter-spacing: -0.5px;
}

.hero p {
    margin: 0;
    color: #5d708b;
    font-size: 16px;
    line-height: 1.7;
}

.dashboard-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 18px;
    border: none;
    border-radius: 10px;
    background: #1769ff;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: background .2s, transform .2s;
}

.btn:hover {
    background: #1055d6;
    transform: translateY(-2px);
}

/* Main dashboard uses more screen width */
.dashboard-content {
    width: 92%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 34px 0 60px;
}

/* Summary cards */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    margin-bottom: 44px;
}

.summary-card {
    position: relative;
    padding: 26px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e1e9f5;
    border-radius: 17px;
    box-shadow: 0 7px 22px rgba(24, 55, 100, .05);
}

.summary-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #1769ff;
}

.summary-card:nth-child(2)::before {
    background: #7c6af0;
}

.summary-card:nth-child(3)::before {
    background: #f0a23b;
}

.summary-label {
    margin: 0 0 14px;
    color: #63748e;
    font-size: 15px;
    font-weight: 600;
}

.summary-number {
    margin: 0;
    color: #15376b;
    font-size: 40px;
    font-weight: 800;
}

.summary-note {
    margin: 10px 0 0;
    color: #8491a5;
    font-size: 13px;
    line-height: 1.6;
}

/* Item and claim sections */
.dashboard-section {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 22px;
    margin-bottom: 38px;
}

.dashboard-section > h2 {
    grid-column: 1 / -1;
    margin: 0;
    padding-bottom: 16px;
    color: #15376b;
    font-size: 25px;
    border-bottom: 1px solid #dfe8f5;
}

/* Claim sections use two columns on desktop, reducing unused space */
.dashboard-content > .dashboard-section:nth-of-type(3),
.dashboard-content > .dashboard-section:nth-of-type(4) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.dashboard-content > .dashboard-section:nth-of-type(3) > h2,
.dashboard-content > .dashboard-section:nth-of-type(4) > h2 {
    grid-column: 1 / -1;
}

/* Cards */
.dashboard-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 23px;
    overflow-wrap: anywhere;
    background: #fff;
    border: 1px solid #e1e9f5;
    border-radius: 17px;
    box-shadow: 0 7px 22px rgba(24, 55, 100, .05);
    transition: transform .2s, box-shadow .2s;
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 13px 28px rgba(24, 55, 100, .1);
}

.dashboard-card h3 {
    margin: 0 0 15px;
    color: #15376b;
    font-size: 20px;
    line-height: 1.45;
}

.dashboard-card p {
    margin: 8px 0;
    color: #526783;
    font-size: 14px;
    line-height: 1.75;
}

.dashboard-card strong {
    color: #263f62;
}

/* Item photos */
.photo-area {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 220px;
    margin-bottom: 15px;
    overflow: hidden;
    background: #f6f9ff;
    border: 1px solid #eaf0f8;
    border-radius: 12px;
}

.item-photo {
    display: block;
    width: 100%;
    height: 100%;
    padding: 10px;
    object-fit: contain;
}

.photo-placeholder {
    color: #8a9ab2;
    font-size: 14px;
}

/* Status labels */
.status {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 12px !important;
}

.status-badge {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    background: #eaf1ff;
    color: #245ac0;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}

.status-pending {
    background: #fff2cf;
    color: #946200;
}

.status-approved,
.status-claimed,
.status-returned,
.status-open,
.status-available {
    background: #dff8eb;
    color: #08754d;
}

.status-rejected {
    background: #ffebea;
    color: #b42318;
}

/* Dates and empty sections */
.item-date {
    display: block;
    margin-top: auto;
    padding-top: 20px;
    color: #8491a5;
    font-size: 12px;
    line-height: 1.7;
}

.empty-message {
    grid-column: 1 / -1;
    margin: 0;
    padding: 30px;
    background: #fff;
    border: 1px dashed #cbd8eb;
    border-radius: 14px;
    color: #64748b;
    font-size: 15px;
    line-height: 1.8;
}

.empty-message a {
    color: #1769ff;
    font-weight: 700;
    text-decoration: none;
}

.empty-message a:hover {
    text-decoration: underline;
}

/* Footer */
footer {
    padding: 25px 16px;
    text-align: center;
    background: #fff;
    border-top: 1px solid #e3eaf5;
    color: #71819a;
    font-size: 13px;
}

/* Laptop and tablet */
@media (max-width: 1000px) {
    .dashboard-content {
        width: 94%;
    }

    .dashboard-section {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .photo-area {
        height: 200px;
    }
}

/* Mobile */
@media (max-width: 600px) {
    nav {
        padding: 16px 18px;
    }

    nav div {
        gap: 12px;
    }

    nav a {
        font-size: 12px;
    }

    .hero {
        padding: 32px 20px;
    }

    .hero h1 {
        font-size: 29px;
    }

    .hero p {
        font-size: 14px;
    }

    .dashboard-links {
        flex-direction: column;
    }

    .dashboard-links .btn {
        width: 100%;
    }

    .dashboard-content {
        width: calc(100% - 28px);
        padding-top: 24px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
        gap: 14px;
        margin-bottom: 35px;
    }

    .summary-card {
        padding: 22px;
    }

    .summary-number {
        font-size: 34px;
    }

    .dashboard-section {
        grid-template-columns: 1fr;
        gap: 15px;
        margin-bottom: 35px;
    }

    .dashboard-section > h2 {
        font-size: 22px;
    }

    .dashboard-card {
        padding: 19px;
    }

    .dashboard-card h3 {
        font-size: 19px;
    }

    .photo-area {
        height: 210px;
    }
}
</style>
</head>

<body>

<nav>
    <h2>CampusMate</h2>

    <div>
        <a href="index.php">Home</a>
        <a href="lost_found.php">Lost &amp; Found</a>
        <a href="resources.php">Resource Exchange</a>
        <a href="manage_claims.php">Manage Claims</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<section class="hero">
    <div class="hero-inner">
        <h1>Welcome, <?php echo e($_SESSION["name"] ?? "Student"); ?>!</h1>
        <p>Your campus activity, all in one place.</p>

        <div class="dashboard-links">
            <a class="btn" href="report_item.php">+ Report an Item</a>
            <a class="btn" href="lost_found.php">Explore Lost &amp; Found</a>
            <a class="btn" href="resources.php">Browse Resources</a>
            <a class="btn" href="manage_claims.php">Manage Claims</a>
        </div>
    </div>
</section>

<main class="dashboard-content">

    <!-- Summary cards -->
    <section class="summary-grid">
        <article class="summary-card">
            <p class="summary-label">My Reported Items</p>
            <p class="summary-number"><?php echo $totalItems; ?></p>
            <p class="summary-note">Items you've reported</p>
        </article>

        <article class="summary-card">
            <p class="summary-label">My Claim Requests</p>
            <p class="summary-number"><?php echo $totalClaims; ?></p>
            <p class="summary-note">Claims you've submitted</p>
        </article>

        <article class="summary-card">
            <p class="summary-label">Pending Claims for My Items</p>
            <p class="summary-number"><?php echo $pendingClaims; ?></p>
            <p class="summary-note">Awaiting your review</p>
        </article>
    </section>

    <!-- My reported items -->
    <section class="dashboard-section">
        <h2>My Reported Items</h2>

        <?php if (empty($myItems)): ?>
            <p class="empty-message">
                You haven't reported any items yet.
                <a href="report_item.php">Report your first item.</a>
            </p>
        <?php endif; ?>

        <?php foreach ($myItems as $item): ?>
            <article class="dashboard-card">
                <h3><?php echo e($item["name"]); ?></h3>

                <div class="photo-area">
                    <?php if (!empty($item["image_path"])): ?>
                        <img
                            class="item-photo"
                            src="uploads/<?php echo rawurlencode(basename($item["image_path"])); ?>"
                            alt="Photo of <?php echo e($item["name"]); ?>"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <span class="photo-placeholder">No photo available</span>
                    <?php endif; ?>
                </div>

                <p>
                    <strong>Type:</strong>
                    <?php echo e(ucfirst($item["listing_type"])); ?>
                </p>

                <p>
                    <strong>Location:</strong>
                    <?php echo e($item["location"] ?: "Not specified"); ?>
                </p>

                <p class="status">
                    <strong>Status:</strong>
                    <span class="status-badge status-<?php echo e(strtolower($item["status"])); ?>">
                        <?php echo e(ucfirst($item["status"])); ?>
                    </span>
                </p>

                <small class="item-date">
                    Reported: <?php echo e($item["created_at"]); ?>
                </small>
            </article>
        <?php endforeach; ?>
    </section>

    <!-- My claim requests -->
    <section class="dashboard-section">
        <h2>My Claim Requests</h2>

        <?php if (empty($myClaims)): ?>
            <p class="empty-message">
                You haven't submitted any claim requests yet.
                Browse <a href="lost_found.php">Lost &amp; Found</a> to find items.
            </p>
        <?php endif; ?>

        <?php foreach ($myClaims as $claim): ?>
            <article class="dashboard-card">
                <h3><?php echo e($claim["item_name"]); ?></h3>

                <p>
                    <strong>Location:</strong>
                    <?php echo e($claim["location"] ?: "Not specified"); ?>
                </p>

                <p><strong>My explanation:</strong></p>
                <p><?php echo nl2br(e($claim["details"])); ?></p>

                <p class="status">
                    <strong>Status:</strong>
                    <span class="status-badge status-<?php echo e(strtolower($claim["status"])); ?>">
                        <?php echo e(ucfirst($claim["status"])); ?>
                    </span>
                </p>

                <small class="item-date">
                    Submitted: <?php echo e($claim["created_at"]); ?>
                </small>
            </article>
        <?php endforeach; ?>
    </section>

    <!-- Claims for my found items -->
    <section class="dashboard-section">
        <h2>Claim Requests for My Items</h2>

        <?php if (empty($receivedClaims)): ?>
            <p class="empty-message">
                No one has submitted a claim for your found items yet.
            </p>
        <?php endif; ?>

        <?php foreach ($receivedClaims as $claim): ?>
            <article class="dashboard-card">
                <h3><?php echo e($claim["item_name"]); ?></h3>

                <p>
                    <strong>Claimant:</strong>
                    <?php echo e($claim["claimant_name"]); ?>
                </p>

                <p><strong>Claim explanation:</strong></p>
                <p><?php echo nl2br(e($claim["details"])); ?></p>

                <p class="status">
                    <strong>Status:</strong>
                    <span class="status-badge status-<?php echo e(strtolower($claim["claim_status"])); ?>">
                        <?php echo e(ucfirst($claim["claim_status"])); ?>
                    </span>
                </p>

                <small class="item-date">
                    Submitted: <?php echo e($claim["created_at"]); ?>
                </small>

                <?php if (strtolower($claim["claim_status"]) === "pending"): ?>
                    <p>
                        <a class="btn" href="manage_claims.php">Review Claim</a>
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>

</main>

<footer>
    CampusMate &copy; <?php echo date("Y"); ?> |
    Smart Campus Resource Exchange
</footer>

</body>
</html>
