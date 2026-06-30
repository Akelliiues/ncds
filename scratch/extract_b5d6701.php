<?php
$output = shell_exec('git show b5d6701:assets/js/app.js');

echo "Length: " . strlen($output) . "\n";
echo "First 20 bytes hex: " . bin2hex(substr($output, 0, 20)) . "\n";

// Check if null bytes exist
$null_at_even = 0;
$null_at_odd = 0;
$len = strlen($output);
for ($i = 0; $i < min($len, 1000); $i++) {
    if (ord($output[$i]) === 0) {
        if ($i % 2 === 0) $null_at_even++;
        else $null_at_odd++;
    }
}

$utf8 = '';
if ($null_at_even > 100) {
    echo "Detected UTF-16BE\n";
    $utf8 = @iconv('UTF-16BE', 'UTF-8', $output);
} elseif ($null_at_odd > 100) {
    echo "Detected UTF-16LE\n";
    $utf8 = @iconv('UTF-16LE', 'UTF-8', $output);
} else {
    echo "Detected UTF-8 / ASCII\n";
    $utf8 = $output;
}

if ($utf8) {
    file_put_contents(__DIR__ . '/old_app_b5d6701.js', $utf8);
    echo "Saved UTF-8 file to scratch/old_app_b5d6701.js, length " . strlen($utf8) . " bytes\n";
} else {
    echo "Failed to convert\n";
}
