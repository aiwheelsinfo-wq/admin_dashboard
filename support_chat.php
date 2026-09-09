<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Connect to Database
if (file_exists(__DIR__ . '/db_connect.php')) {
    require_once __DIR__ . '/db_connect.php';
} else {
    require_once __DIR__ . '/../2025/db_connect.php';
}

date_default_timezone_set('Asia/Kolkata');

// Auto ensure support_messages table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'support_messages'");
if ($checkTable && $checkTable->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_type ENUM('vendor', 'customer') DEFAULT 'vendor',
        user_phone VARCHAR(20) DEFAULT NULL,
        user_name VARCHAR(100) DEFAULT NULL,
        vendor_phone VARCHAR(20) NOT NULL,
        sender_type ENUM('vendor', 'customer', 'admin') NOT NULL,
        sender_name VARCHAR(100) DEFAULT NULL,
        message TEXT NOT NULL,
        attachment_url VARCHAR(500) DEFAULT NULL,
        attachment_type VARCHAR(50) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vendor_phone (vendor_phone),
        INDEX idx_user_type_phone (user_type, user_phone),
        INDEX idx_created_at (created_at),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/uploads/support';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
    @chmod($uploadDir, 0777);
}

// Parse request data (JSON or POST)
$rawBody = file_get_contents('php://input');
$jsonData = json_decode($rawBody, true) ?? [];

$action = $_GET['action'] ?? $_POST['action'] ?? $jsonData['action'] ?? '';

switch ($action) {
    // ==========================================
    // 1. SEND MESSAGE (Vendor, Customer, or Admin)
    // ==========================================
    case 'send_message':
        $user_type = trim($_POST['user_type'] ?? $jsonData['user_type'] ?? 'vendor');
        $phone = trim($_POST['user_phone'] ?? $jsonData['user_phone'] ?? $_POST['vendor_phone'] ?? $jsonData['vendor_phone'] ?? '');
        $sender_type = trim($_POST['sender_type'] ?? $jsonData['sender_type'] ?? $user_type);
        $sender_name = trim($_POST['sender_name'] ?? $jsonData['sender_name'] ?? '');
        $message = trim($_POST['message'] ?? $jsonData['message'] ?? '');
        $attachment_url = null;
        $attachment_type = null;

        if (empty($phone)) {
            echo json_encode(["status" => "error", "message" => "Phone number is required."]);
            exit;
        }

        // Handle multipart file attachment upload
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $file = $_FILES['attachment'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (in_array($ext, $allowed)) {
                $filename = 'proof_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $attachment_url = "https://agnicarrental.com/admin2025/uploads/support/" . $filename;
                    $attachment_type = ($ext === 'pdf') ? 'pdf' : 'image';
                }
            }
        }
        // Handle base64 image upload
        elseif (!empty($jsonData['attachment_base64'])) {
            $base64 = $jsonData['attachment_base64'];
            $ext = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
                $ext = strtolower($type[1]);
                if ($ext === 'jpeg') $ext = 'jpg';
            }
            $base64 = base64_decode(str_replace(' ', '+', $base64));
            if ($base64) {
                $filename = 'proof_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;
                if (file_put_contents($targetPath, $base64)) {
                    $attachment_url = "https://agnicarrental.com/admin2025/uploads/support/" . $filename;
                    $attachment_type = 'image';
                }
            }
        }

        if (empty($message) && empty($attachment_url)) {
            echo json_encode(["status" => "error", "message" => "Cannot send an empty message."]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO support_messages (user_type, user_phone, user_name, vendor_phone, sender_type, sender_name, message, attachment_url, attachment_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
        if (!$stmt) {
            echo json_encode(["status" => "error", "message" => "DB prepare error: " . $conn->error]);
            exit;
        }

        $stmt->bind_param("sssssssss", $user_type, $phone, $sender_name, $phone, $sender_type, $sender_name, $message, $attachment_url, $attachment_type);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();

            echo json_encode([
                "status" => "success",
                "message" => "Message sent successfully",
                "data" => [
                    "id" => $newId,
                    "user_type" => $user_type,
                    "user_phone" => $phone,
                    "user_name" => $sender_name,
                    "sender_type" => $sender_type,
                    "sender_name" => $sender_name,
                    "message" => $message,
                    "attachment_url" => $attachment_url,
                    "attachment_type" => $attachment_type,
                    "is_read" => 0,
                    "created_at" => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Execute error: " . $stmt->error]);
        }
        exit;

    // ==========================================
    // 2. GET MESSAGES (Conversation thread)
    // ==========================================
    case 'get_messages':
        $user_type = trim($_GET['user_type'] ?? $_POST['user_type'] ?? $jsonData['user_type'] ?? 'vendor');
        $phone = trim($_GET['user_phone'] ?? $_POST['user_phone'] ?? $_GET['vendor_phone'] ?? $_POST['vendor_phone'] ?? $jsonData['vendor_phone'] ?? '');
        $reader = trim($_GET['reader'] ?? $_POST['reader'] ?? $jsonData['reader'] ?? $user_type);

        if (empty($phone)) {
            echo json_encode(["status" => "error", "message" => "Phone number is required."]);
            exit;
        }

        // Mark incoming messages as read based on who is reading
        if ($reader === 'admin') {
            $conn->query("UPDATE support_messages SET is_read = 1 WHERE (user_phone = '$phone' OR vendor_phone = '$phone') AND user_type = '$user_type' AND sender_type != 'admin' AND is_read = 0");
        } else {
            $conn->query("UPDATE support_messages SET is_read = 1 WHERE (user_phone = '$phone' OR vendor_phone = '$phone') AND user_type = '$user_type' AND sender_type = 'admin' AND is_read = 0");
        }

        $userInfo = [
            "phone" => $phone,
            "name" => ($user_type === 'customer') ? "Customer $phone" : "Transport Partner",
            "agency_name" => "",
            "status" => "active",
            "block_reason" => "",
            "blocked_at" => null
        ];

        if ($user_type === 'vendor') {
            $vQuery = $conn->prepare("SELECT full_name, agency_name, status, block_reason, blocked_at FROM drivers WHERE phone_number = ? LIMIT 1");
            if ($vQuery) {
                $vQuery->bind_param("s", $phone);
                $vQuery->execute();
                $vRes = $vQuery->get_result();
                if ($row = $vRes->fetch_assoc()) {
                    $userInfo["name"] = $row["full_name"] ?? $userInfo["name"];
                    $userInfo["agency_name"] = $row["agency_name"] ?? "";
                    $userInfo["status"] = $row["status"] ?? "active";
                    $userInfo["block_reason"] = $row["block_reason"] ?? "";
                    $userInfo["blocked_at"] = $row["blocked_at"] ?? null;
                }
                $vQuery->close();
            }
        } else {
            // For customer, try finding profile in users table
            $bQuery = $conn->prepare("SELECT name, email, city FROM users WHERE phone_number = ? LIMIT 1");
            if ($bQuery) {
                $bQuery->bind_param("s", $phone);
                $bQuery->execute();
                $bRes = $bQuery->get_result();
                if ($bRow = $bRes->fetch_assoc()) {
                    if (!empty($bRow["name"])) $userInfo["name"] = $bRow["name"];
                    $userInfo["agency_name"] = $bRow["city"] ? "City: " . $bRow["city"] : "";
                }
                $bQuery->close();
            }
        }

        // Fetch conversation messages
        $stmt = $conn->prepare("SELECT id, user_type, user_phone, user_name, vendor_phone, sender_type, sender_name, message, attachment_url, attachment_type, is_read, created_at FROM support_messages WHERE (user_phone = ? OR vendor_phone = ?) AND user_type = ? ORDER BY created_at ASC");
        $stmt->bind_param("sss", $phone, $phone, $user_type);
        $stmt->execute();
        $res = $stmt->get_result();

        $messages = [];
        while ($row = $res->fetch_assoc()) {
            $messages[] = [
                "id" => (int)$row["id"],
                "user_type" => $row["user_type"],
                "user_phone" => $row["user_phone"] ?: $row["vendor_phone"],
                "sender_type" => $row["sender_type"],
                "sender_name" => $row["sender_name"],
                "message" => $row["message"],
                "attachment_url" => $row["attachment_url"],
                "attachment_type" => $row["attachment_type"],
                "is_read" => (int)$row["is_read"],
                "created_at" => $row["created_at"]
            ];
        }
        $stmt->close();

        echo json_encode([
            "status" => "success",
            "vendor" => $userInfo,
            "user" => $userInfo,
            "messages" => $messages
        ]);
        exit;

    // ==========================================
    // 3. GET THREADS (Filtered by user_type)
    // ==========================================
    case 'get_threads':
        $user_type = trim($_GET['user_type'] ?? $_POST['user_type'] ?? $jsonData['user_type'] ?? 'vendor');
        $safeType = ($user_type === 'customer') ? 'customer' : 'vendor';

        // 1. Get distinct users for this user_type
        $distinctUsersSql = "
            SELECT DISTINCT COALESCE(NULLIF(user_phone, ''), vendor_phone) AS phone 
            FROM support_messages 
            WHERE user_type = '$safeType' AND COALESCE(NULLIF(user_phone, ''), vendor_phone) IS NOT NULL AND COALESCE(NULLIF(user_phone, ''), vendor_phone) != ''
        ";
        $uRes = $conn->query($distinctUsersSql);
        $phones = [];
        if ($uRes) {
            while ($uRow = $uRes->fetch_assoc()) {
                $phones[] = $uRow['phone'];
            }
        }

        $threads = [];
        $totalUnread = 0;

        foreach ($phones as $phone) {
            $escapedPhone = $conn->real_escape_string($phone);

            // Latest message for this user
            $latestSql = "
                SELECT message, sender_type, sender_name, user_name, created_at, attachment_url 
                FROM support_messages 
                WHERE (user_phone = '$escapedPhone' OR vendor_phone = '$escapedPhone') AND user_type = '$safeType' 
                ORDER BY id DESC LIMIT 1
            ";
            $lRes = $conn->query($latestSql);
            $latest = $lRes ? $lRes->fetch_assoc() : null;

            // Unread count
            $targetSender = ($safeType === 'customer') ? 'customer' : 'vendor';
            $unreadSql = "
                SELECT COUNT(*) AS cnt 
                FROM support_messages 
                WHERE (user_phone = '$escapedPhone' OR vendor_phone = '$escapedPhone') 
                  AND user_type = '$safeType' 
                  AND sender_type = '$targetSender' 
                  AND is_read = 0
            ";
            $unRes = $conn->query($unreadSql);
            $unreadCount = ($unRes && $unRow = $unRes->fetch_assoc()) ? (int)$unRow['cnt'] : 0;
            $totalUnread += $unreadCount;

            // Metadata resolution
            $name = $latest['user_name'] ?? ($safeType === 'customer' ? "Passenger $phone" : "Partner $phone");
            $agency = "";
            $status = "active";
            $blockReason = "";
            $blockedAt = null;

            if ($safeType === 'vendor') {
                $dRes = $conn->query("SELECT full_name, agency_name, status, block_reason, blocked_at FROM drivers WHERE phone_number = '$escapedPhone' LIMIT 1");
                if ($dRes && $dRow = $dRes->fetch_assoc()) {
                    if (!empty($dRow['full_name'])) $name = $dRow['full_name'];
                    $agency = $dRow['agency_name'] ?? '';
                    $status = $dRow['status'] ?? 'active';
                    $blockReason = $dRow['block_reason'] ?? '';
                    $blockedAt = $dRow['blocked_at'] ?? null;
                }
            } else {
                $bRes = $conn->query("SELECT name, city FROM users WHERE phone_number = '$escapedPhone' LIMIT 1");
                if ($bRes && $bRow = $bRes->fetch_assoc()) {
                    if (!empty($bRow['name'])) $name = $bRow['name'];
                    if (!empty($bRow['city'])) {
                        $agency = "City: " . $bRow['city'];
                    }
                }
            }

            $threads[] = [
                "vendor_phone" => $phone,
                "user_phone" => $phone,
                "vendor_name" => $name,
                "user_name" => $name,
                "agency_name" => $agency ?: ($safeType === 'customer' ? 'Online Booking Passenger' : ''),
                "vendor_status" => $status,
                "block_reason" => $blockReason,
                "blocked_at" => $blockedAt,
                "last_message" => $latest['message'] ?? '',
                "last_sender_type" => $latest['sender_type'] ?? $safeType,
                "last_created_at" => $latest['created_at'] ?? '',
                "has_attachment" => !empty($latest['attachment_url']),
                "unread_count" => $unreadCount
            ];
        }

        // Sort threads by latest message descending
        usort($threads, function($a, $b) {
            return strcmp($b['last_created_at'], $a['last_created_at']);
        });

        echo json_encode([
            "status" => "success",
            "user_type" => $safeType,
            "total_threads" => count($threads),
            "total_unread" => $totalUnread,
            "threads" => $threads
        ]);
        exit;

    // ==========================================
    // 4. MARK AS READ
    // ==========================================
    case 'mark_read':
        $phone = trim($_POST['user_phone'] ?? $_POST['vendor_phone'] ?? $jsonData['user_phone'] ?? $jsonData['vendor_phone'] ?? '');
        $reader = trim($_POST['reader'] ?? $jsonData['reader'] ?? 'admin');
        $user_type = trim($_POST['user_type'] ?? $jsonData['user_type'] ?? 'vendor');

        if (!empty($phone)) {
            $sender = ($reader === 'admin') ? $user_type : 'admin';
            $conn->query("UPDATE support_messages SET is_read = 1 WHERE (user_phone = '$phone' OR vendor_phone = '$phone') AND user_type = '$user_type' AND sender_type = '$sender'");
        }

        echo json_encode(["status" => "success", "message" => "Marked as read"]);
        exit;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
        exit;
}
?>
