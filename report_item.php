
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $message = "Invalid request. Please refresh and try again.";
    } else {
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $type = $_POST["listing_type"] ?? "";

        $imagePath = null;
        $uploadError = "";

        if (
            $name === "" ||
            !in_array($type, ["lost", "found"], true)
        ) {
            $message = "Please enter an item name and select a valid report type.";
        } else {
            $file = $_FILES["item_image"] ?? null;

            if ($file && $file["error"] !== UPLOAD_ERR_NO_FILE) {
                if ($file["error"] !== UPLOAD_ERR_OK) {
                    $uploadError = "Image upload failed. Please try again.";
                } elseif ($file["size"] > 5 * 1024 * 1024) {
                    $uploadError = "Image must be 5 MB or smaller.";
                } elseif (!is_uploaded_file($file["tmp_name"])) {
                    $uploadError = "Invalid uploaded file.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file["tmp_name"]);

                    $allowedTypes = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp"
                    ];

                    if (!isset($allowedTypes[$mime])) {
                        $uploadError =
                            "Only JPG, PNG, and WebP images are allowed.";
                    } else {
                        $uploadDirectory = __DIR__ . "/uploads/";

                        if (!is_dir($uploadDirectory)) {
                            $uploadError = "Uploads folder was not found.";
                        } else {
                            $fileName = bin2hex(random_bytes(16))
                                . "." . $allowedTypes[$mime];

                            $destination = $uploadDirectory . $fileName;

                            if (move_uploaded_file(
                                $file["tmp_name"],
                                $destination
                            )) {
                                $imagePath = $fileName;
                            } else {
                                $uploadError =
                                    "Could not save the image. Check folder permissions.";
                            }
                        }
                    }
                }
            }

            if ($uploadError !== "") {
                $message = $uploadError;
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO items
                        (user_id, name, description, category, location,
                         listing_type, image_path)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $_SESSION["user_id"],
                        $name,
                        $description,
                        $category,
                        $location,
                        $type,
                        $imagePath
                    ]);

                    $_SESSION["csrf_token"] =
                        bin2hex(random_bytes(32));

                    header("Location: lost_found.php?reported=1");
                    exit;
                } catch (PDOException $e) {
                    if ($imagePath !== null) {
                        $savedFile = __DIR__ . "/uploads/" . $imagePath;

                        if (is_file($savedFile)) {
                            unlink($savedFile);
                        }
                    }

                    error_log($e->getMessage());
                    $message =
                        "Could not save the report. Please try again.";
                }
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
    <title>Report an Item | CampusMate</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav>
        <h2>CampusMate</h2>
        <div>
            <a href="index.php">Home</a>
            <a href="lost_found.php">Lost & Found</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
    </nav>

    <section class="hero">
        <h1>Report an Item</h1>
        <p>Help your campus community find lost belongings.</p>

        <?php if ($message !== ""): ?>
            <p role="alert">
                <?php echo htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>
            </p>
        <?php endif; ?>

        <form method="POST" class="item-form"
              enctype="multipart/form-data">

            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars(
                    $_SESSION["csrf_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>">

            <label for="name">Item Name</label>
            <input type="text" id="name" name="name"
                maxlength="150"
                placeholder="e.g. Blue water bottle" required>

            <label for="listing_type">Report Type</label>
            <select id="listing_type" name="listing_type" required>
                <option value="">Select report type</option>
                <option value="lost">I lost an item</option>
                <option value="found">I found an item</option>
            </select>

            <label for="category">Category</label>
            <input type="text" id="category" name="category"
                maxlength="60"
                placeholder="e.g. Electronics, Books">

            <label for="location">Location</label>
            <input type="text" id="location" name="location"
                maxlength="150"
                placeholder="e.g. Library, Block A">

            <label for="description">Description</label>
            <textarea id="description" name="description"
                rows="4"
                placeholder="Describe the item and relevant details"></textarea>

            <label for="item_image">Upload Item Photo (Optional)</label>
            <input type="file" id="item_image" name="item_image"
                accept="image/jpeg,image/png,image/webp">

            <small>Accepted formats: JPG, PNG, WebP. Maximum size: 5 MB.</small>

            <button class="btn" type="submit">Submit Report</button>
        </form>
    </section>
</body>
</html>
