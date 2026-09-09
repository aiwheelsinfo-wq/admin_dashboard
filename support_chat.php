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
        vendor_phone VARCHAR(20) NOT NULL,
        sender_type ENUM('vendor', 'admin') NOT NULL,
        sender_name VARCHAR(100) DEFAULT NULL,
        message TEXT NOT NULL,
        attachment_url VARCHAR(500) DEFAULT NULL,
        attachment_type VARCHAR(50) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vendor_phone (vendor_phone),
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
    // 1. SEND MESSAGE (Vendor or Admin)
    // ==========================================
    case 'send_message':
        $vendor_phone = trim($_POST['vendor_phone'] ?? $jsonData['vendor_phone'] ?? '');
        $sender_type = trim($_POST['sender_type'] ?? $jsonData['sender_type'] ?? 'vendor');
        $sender_name = trim($_POST['sender_name'] ?? $jsonData['sender_name'] ?? '');
        $message = trim($_POST['message'] ?? $jsonData['message'] ?? '');
        $attachment_url = null;
        $attachment_type = null;

        if (empty($vendor_phone)) {
            echo json_encode(["status" => "error", "message" => "vendor_phone is required."]);
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

        $stmt = $conn->prepare("INSERT INTO support_messages (vendor_phone, sender_type, sender_name, message, attachment_url, attachment_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
        if (!$stmt) {
            echo json_encode(["status" => "error", "message" => "DB prepare error: " . $conn->error]);
            exit;
        }

        $stmt->bind_param("ssssss", $vendor_phone, $sender_type, $sender_name, $message, $attachment_url, $attachment_type);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();

            echo json_encode([
                "status" => "success",
                "message" => "Message sent successfully",
                "data" => [
                    "id" => $newId,
                    "vendor_phone" => $vendor_phone,
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
        $vendor_phone = trim($_GET['vendor_phone'] ?? $_POST['vendor_phone'] ?? $jsonData['vendor_phone'] ?? '');
        $reader = trim($_GET['reader'] ?? $_POST['reader'] ?? $jsonData['reader'] ?? 'vendor'); // 'vendor' or 'admin'

        if (empty($vendor_phone)) {
            echo json_encode(["status" => "error", "message" => "vendor_phone is required."]);
            exit;
        }

        // Mark incoming messages as read based on who is viewing
        if ($reader === 'admin') {
            $conn->query("UPDATE support_messages SET is_read = 1 WHERE vendor_phone = '$vendor_phone' AND sender_type = 'vendor' AND is_read = 0");
        } else {
            $conn->query("UPDATE support_messages SET is_read = 1 WHERE vendor_phone = '$vendor_phone' AND sender_type = 'admin' AND is_read = 0");
        }

        // Fetch vendor profile details (status, agency, name, block reason)
        $vendorInfo = [
            "phone" => $vendor_phone,
            "name" => "Transport Partner",
            "agency_name" => "",
            "status" => "active",
            "block_reason" => "",
            "blocked_at" => null
        ];

        $vQuery = $conn->prepare("SELECT full_name, agency_name, status, block_reason, blocked_at FROM drivers WHERE phone_number = ? LIMIT 1");
        if ($vQuery) {
            $vQuery->bind_param("s", $vendor_phone);
            $vQuery->execute();
            $vRes = $vQuery->get_result();
            if ($row = $vRes->fetch_assoc()) {
                $vendorInfo["name"] = $row["full_name"] ?? $vendorInfo["name"];
                $vendorInfo["agency_name"] = $row["agency_name"] ?? "";
                $vendorInfo["status"] = $row["status"] ?? "active";
                $vendorInfo["block_reason"] = $row["block_reason"] ?? "";
                $vendorInfo["blocked_at"] = $row["blocked_at"] ?? null;
            }
            $vQuery->close();
        }

        // Fetch conversation messages
        $stmt = $conn->prepare("SELECT id, vendor_phone, sender_type, sender_name, message, attachment_url, attachment_type, is_read, created_at FROM support_messages WHERE vendor_phone = ? ORDER BY created_at ASC");
        $stmt->bind_param("s", $vendor_phone);
        $stmt->execute();
        $res = $stmt->get_result();

        $messages = [];
        while ($row = $res->fetch_assoc()) {
            $messages[] = [
                "id" => (int)$row["id"],
                "vendor_phone" => $row["vendor_phone"],
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
            "vendor" => $vendorInfo,
            "messages" => $messages
        ]);
        exit;

    // ==========================================
    // 3. GET THREADS (For Admin Helpdesk Sidebar)
    // ==========================================
    case 'get_threads':
        $sql = "
            SELECT 
                sm.vendor_phone,
                d.full_name AS vendor_name,
                d.agency_name,
                COALESCE(d.status, v.status, 'active') AS vendor_status,
                COALESCE(d.block_reason, v.block_reason, '') AS block_reason,
                COALESCE(d.blocked_at, v.blocked_at) AS blocked_at,
                latest.last_message,
                latest.last_sender_type,
                latest.last_created_at,
                latest.has_attachment,
                COALESCE(unread.unread_count, 0) AS unread_count
            FROM (
                SELECT DISTINCT vendor_phone FROM support_messages
            ) sm
            LEFT JOIN drivers d ON sm.vendor_phone = d.phone_number
            LEFT JOIN vendors v ON sm.vendor_phone = v.phone_number
            LEFT JOIN (
                SELECT 
                    m1.vendor_phone,
                    m1.message AS last_message,
                    m1.sender_type AS last_sender_type,
                    m1.created_at AS last_created_at,
                    IF(m1.attachment_url IS NOT NULL AND m1.attachment_url != '', 1, 0) AS has_attachment
                FROM support_messages m1
                INNER JOIN (
                    SELECT vendor_phone, MAX(id) AS max_id
                    FROM support_messages
                    GROUP BY vendor_phone
                ) m2 ON m1.id = m2.max_id
            ) latest ON sm.vendor_phone = latest.vendor_phone
            LEFT JOIN (
                SELECT vendor_phone, COUNT(*) AS unread_count
                FROM support_messages
                WHERE sender_type = 'vendor' AND is_read = 0
                GROUP BY vendor_phone
            ) unread ON sm.vendor_phone = unread.vendor_phone
            ORDER BY latest.last_created_at DESC
        ";

        $res = $conn->query($sql);
        $threads = [];
        $totalUnread = 0;

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $unread = (int)($row['unread_count'] ?? 0);
                $totalUnread += $unread;
                $threads[] = [
                    "vendor_phone" => $row["vendor_phone"],
                    "vendor_name" => !empty($row["vendor_name"]) ? $row["vendor_name"] : "Partner " . $row["vendor_phone"],
                    "agency_name" => $row["agency_name"] ?? "",
                    "vendor_status" => $row["vendor_status"] ?? "active",
                    "block_reason" => $row["block_reason"] ?? "",
                    "blocked_at" => $row["blocked_at"] ?? null,
                    "last_message" => $row["last_message"] ?? "",
                    "last_sender_type" => $row["last_sender_type"] ?? "vendor",
                    "last_created_at" => $row["last_created_at"] ?? "",
                    "has_attachment" => (bool)($row["has_attachment"] ?? false),
                    "unread_count" => $unread
                ];
            }
        }

        echo json_encode([
            "status" => "success",
            "total_threads" => count($threads),
            "total_unread" => $totalUnread,
            "threads" => $threads
        ]);
        exit;

    // ==========================================
    // 4. MARK AS READ
    // ==========================================
    case 'mark_read':
        $vendor_phone = trim($_POST['vendor_phone'] ?? $jsonData['vendor_phone'] ?? '');
        $reader = trim($_POST['reader'] ?? $jsonData['reader'] ?? 'admin');

        if (!empty($vendor_phone)) {
            $sender = ($reader === 'admin') ? 'vendor' : 'admin';
            $conn->query("UPDATE support_messages SET is_read = 1 WHERE vendor_phone = '$vendor_phone' AND sender_type = '$sender'");
        }

        echo json_encode(["status" => "success", "message" => "Marked as read"]);
        exit;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
        exit;
}
?>
