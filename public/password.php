<?php
// Generate hash for "password123"
$password = "password123";

// Using PASSWORD_DEFAULT (currently bcrypt)
$hash1 = password_hash($password, PASSWORD_DEFAULT);
$hash2 = password_hash($password, PASSWORD_DEFAULT);
$hash3 = password_hash($password, PASSWORD_DEFAULT);

// Using PASSWORD_BCRYPT explicitly
$hash_bcrypt = password_hash($password, PASSWORD_BCRYPT);

// Using PASSWORD_ARGON2I (if available)
if (defined('PASSWORD_ARGON2I')) {
    $hash_argon2i = password_hash($password, PASSWORD_ARGON2I);
}

// Using PASSWORD_ARGON2ID (if available)
if (defined('PASSWORD_ARGON2ID')) {
    $hash_argon2id = password_hash($password, PASSWORD_ARGON2ID);
}

echo "Password: " . $password . "\n\n";

echo "PASSWORD_DEFAULT (bcrypt) hashes:\n";
echo "Hash 1: " . $hash1 . "\n";
echo "Hash 2: " . $hash2 . "\n";
echo "Hash 3: " . $hash3 . "\n\n";

echo "PASSWORD_BCRYPT hash:\n";
echo $hash_bcrypt . "\n\n";

if (isset($hash_argon2i)) {
    echo "PASSWORD_ARGON2I hash:\n";
    echo $hash_argon2i . "\n\n";
}

if (isset($hash_argon2id)) {
    echo "PASSWORD_ARGON2ID hash:\n";
    echo $hash_argon2id . "\n\n";
}

// Verify the hashes work
echo "Verification tests:\n";
echo "Hash 1 verification: " . (password_verify($password, $hash1) ? "✓ PASS" : "✗ FAIL") . "\n";
echo "Hash 2 verification: " . (password_verify($password, $hash2) ? "✓ PASS" : "✗ FAIL") . "\n";
echo "Hash 3 verification: " . (password_verify($password, $hash3) ? "✓ PASS" : "✗ FAIL") . "\n";

// Test with wrong password
echo "Wrong password test: " . (password_verify("wrongpassword", $hash1) ? "✗ FAIL" : "✓ PASS") . "\n\n";

// Show hash information
echo "Hash information for Hash 1:\n";
print_r(password_get_info($hash1));

echo "\nExample SQL UPDATE statement:\n";
echo "UPDATE users SET password = '" . $hash1 . "' WHERE username = 'your_username';\n\n";

// For manual database insertion, here's a ready-to-use hash:
echo "Ready-to-use hash for database (copy this):\n";
echo $hash1 . "\n\n";

// Cost analysis (for performance tuning)
echo "Cost analysis:\n";
$start_time = microtime(true);
$test_hash = password_hash($password, PASSWORD_DEFAULT);
$end_time = microtime(true);
$duration = ($end_time - $start_time) * 1000; // Convert to milliseconds

echo "Hash generation time: " . round($duration, 2) . " ms\n";
echo "Recommended: 250-500ms for good security vs performance balance\n";

// Custom cost example (if you want to adjust security level)
echo "\nCustom cost examples:\n";
$options_low = ['cost' => 10];  // Faster, less secure
$options_high = ['cost' => 12]; // Slower, more secure

$hash_low_cost = password_hash($password, PASSWORD_BCRYPT, $options_low);
$hash_high_cost = password_hash($password, PASSWORD_BCRYPT, $options_high);

echo "Low cost (10):  " . $hash_low_cost . "\n";
echo "High cost (12): " . $hash_high_cost . "\n";

?>

<!-- If you want to run this in a web browser, uncomment the HTML below -->
<!--
<!DOCTYPE html>
<html>
<head>
    <title>Password Hash Generator</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1000px; }
        .hash { background: #e8f4fd; padding: 10px; border-radius: 4px; word-break: break-all; margin: 5px 0; }
        .success { color: green; }
        .error { color: red; }
        h3 { color: #333; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Hash Generator Results</h2>
        <p><strong>Password:</strong> <?php echo htmlspecialchars($password); ?></p>
        
        <h3>Generated Hashes (each is unique due to salt):</h3>
        <div class="hash"><?php echo htmlspecialchars($hash1); ?></div>
        <div class="hash"><?php echo htmlspecialchars($hash2); ?></div>
        <div class="hash"><?php echo htmlspecialchars($hash3); ?></div>
        
        <h3>Verification Results:</h3>
        <p class="success">✓ All hashes verify correctly with original password</p>
        <p class="success">✓ Wrong password correctly rejected</p>
        
        <h3>For Database Use:</h3>
        <div class="hash"><?php echo htmlspecialchars($hash1); ?></div>
        <p><em>Copy the hash above to insert into your database</em></p>
    </div>
</body>
</html>
-->