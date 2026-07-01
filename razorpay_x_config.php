<?php
// RazorpayX Configuration Settings

define('RAZORPAYX_USE_LIVE', false); // Set to true to switch to live mode in 2 days

// Test Credentials
define('RAZORPAYX_TEST_KEY_ID', 'rzp_test_GIqSfPJk12gAgz');
define('RAZORPAYX_TEST_KEY_SECRET', 'k16L3yB8eFwKxGz4mJ9pQ8r'); // Placeholder (User should fill this with their real test secret)
define('RAZORPAYX_TEST_ACCOUNT_NUMBER', '7878780080316316'); // Placeholder test virtual account

// Live Credentials
define('RAZORPAYX_LIVE_KEY_ID', 'rzp_live_q9eMvidQ7LrwVQ');
define('RAZORPAYX_LIVE_KEY_SECRET', 'YOUR_LIVE_KEY_SECRET');
define('RAZORPAYX_LIVE_ACCOUNT_NUMBER', 'YOUR_LIVE_ACCOUNT_NUMBER');

// Active credentials selector
define('RAZORPAYX_KEY_ID', RAZORPAYX_USE_LIVE ? RAZORPAYX_LIVE_KEY_ID : RAZORPAYX_TEST_KEY_ID);
define('RAZORPAYX_KEY_SECRET', RAZORPAYX_USE_LIVE ? RAZORPAYX_LIVE_KEY_SECRET : RAZORPAYX_TEST_KEY_SECRET);
define('RAZORPAYX_ACCOUNT_NUMBER', RAZORPAYX_USE_LIVE ? RAZORPAYX_LIVE_ACCOUNT_NUMBER : RAZORPAYX_TEST_ACCOUNT_NUMBER);
?>
