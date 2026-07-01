<?php
require_once __DIR__ . '/razorpay_x_config.php';

class RazorpayX {
    
    /**
     * Executes a curl request to RazorpayX API.
     */
    private static function callAPI($endpoint, $payload) {
        $ch = curl_init("https://api.razorpay.com/v1" . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, RAZORPAYX_KEY_ID . ":" . RAZORPAYX_KEY_SECRET);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $http_code,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * Ensures the vendor is registered as a Contact in RazorpayX.
     */
    public static function getOrCreateContact(mysqli $conn, $vendor_phone) {
        // Fetch vendor info from DB (users table)
        $stmt = $conn->prepare("SELECT name, email, razorpay_contact_id FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $vendor_phone);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$res) {
            throw new Exception("Vendor user account not found in database.");
        }
        
        if (!empty($res['razorpay_contact_id'])) {
            return $res['razorpay_contact_id'];
        }
        
        // If not registered on Razorpay, create a Contact
        $payload = [
            "name" => !empty($res['name']) ? $res['name'] : "Vendor_" . $vendor_phone,
            "email" => !empty($res['email']) ? $res['email'] : "vendor_" . $vendor_phone . "@example.com",
            "contact" => $vendor_phone,
            "type" => "vendor",
            "reference_id" => "vendor_" . $vendor_phone
        ];
        
        $resAPI = self::callAPI("/contacts", $payload);
        if ($resAPI['code'] === 200 || $resAPI['code'] === 201) {
            $contact_id = $resAPI['data']['id'];
            
            // Save to DB
            $stmt = $conn->prepare("UPDATE users SET razorpay_contact_id = ? WHERE phone_number = ?");
            $stmt->bind_param("ss", $contact_id, $vendor_phone);
            $stmt->execute();
            $stmt->close();
            
            return $contact_id;
        } else {
            $desc = $resAPI['data']['error']['description'] ?? "Failed to create RazorpayX contact.";
            throw new Exception("RazorpayX Contact Error: " . $desc);
        }
    }
    
    /**
     * Ensures the vendor has a Fund Account linked in RazorpayX.
     */
    public static function getOrCreateFundAccount(mysqli $conn, $vendor_phone, $contact_id) {
        // Fetch bank details from DB (users table)
        $stmt = $conn->prepare("SELECT bank_account_no, bank_ifsc, bank_holder_name, upi_id, razorpay_fund_account_id FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $vendor_phone);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$res) {
            throw new Exception("Vendor user account not found in database.");
        }
        
        if (!empty($res['razorpay_fund_account_id'])) {
            return $res['razorpay_fund_account_id'];
        }
        
        $payload = [
            "contact_id" => $contact_id
        ];
        
        // Check if bank account details are set (Priority 1)
        if (!empty($res['bank_account_no']) && !empty($res['bank_ifsc'])) {
            $payload["account_type"] = "bank_account";
            $payload["bank_account"] = [
                "name" => !empty($res['bank_holder_name']) ? $res['bank_holder_name'] : ("Vendor_" . $vendor_phone),
                "ifsc" => trim($res['bank_ifsc']),
                "account_number" => trim($res['bank_account_no'])
            ];
        } 
        // Check if UPI ID is set (Priority 2)
        elseif (!empty($res['upi_id'])) {
            $payload["account_type"] = "vpa";
            $payload["vpa"] = [
                "address" => trim($res['upi_id'])
            ];
        } else {
            throw new Exception("Vendor has no registered bank account or UPI ID. Please register bank/UPI details first.");
        }
        
        $resAPI = self::callAPI("/fund_accounts", $payload);
        if ($resAPI['code'] === 200 || $resAPI['code'] === 201) {
            $fund_id = $resAPI['data']['id'];
            
            // Save to DB
            $stmt = $conn->prepare("UPDATE users SET razorpay_fund_account_id = ? WHERE phone_number = ?");
            $stmt->bind_param("ss", $fund_id, $vendor_phone);
            $stmt->execute();
            $stmt->close();
            
            return $fund_id;
        } else {
            $desc = $resAPI['data']['error']['description'] ?? "Failed to link RazorpayX fund account.";
            throw new Exception("RazorpayX Fund Account Error: " . $desc);
        }
    }
    
    /**
     * Executes the payout.
     */
    public static function createPayout($fund_account_id, $amount, $booking_id) {
        $amount_in_paise = round($amount * 100);
        $payload = [
            "account_number" => RAZORPAYX_ACCOUNT_NUMBER,
            "fund_account_id" => $fund_account_id,
            "amount" => $amount_in_paise,
            "currency" => "INR",
            "mode" => "IMPS",
            "purpose" => "merchant-settlement",
            "queue_if_low_balance" => true,
            "reference_id" => "booking_" . $booking_id . "_settlement",
            "narration" => "Rentox Settlement"
        ];
        
        // Use payout idempotency to prevent double-charging on network retry
        $ch = curl_init("https://api.razorpay.com/v1/payouts");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Payout-Idempotency: pay_ref_' . $booking_id
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, RAZORPAYX_KEY_ID . ":" . RAZORPAYX_KEY_SECRET);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $res_data = json_decode($response, true);
        
        if ($http_code === 200 || $http_code === 201) {
            return [
                'success' => true,
                'utr' => !empty($res_data['utr']) ? $res_data['utr'] : "TXN_" . $res_data['id'],
                'payout_id' => $res_data['id'],
                'status' => $res_data['status']
            ];
        } else {
            $desc = $res_data['error']['description'] ?? "Failed to execute payout.";
            return [
                'success' => false,
                'message' => $desc
            ];
        }
    }
}
?>
