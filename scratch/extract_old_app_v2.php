<?php
$output = shell_exec('git show 4d4f649309fc92e65149a132d2229cee4585789d:assets/js/app.js');

echo "Length: " . strlen($output) . "\n";
echo "First 20 bytes hex: " . bin2hex(substr($output, 0, 20)) . "\n";

// Let's detect the encoding
// If it starts with ff fe, it is UTF-16LE
// If it starts with fe ff, it is UTF-16BE
// If there are many null bytes at odd/even positions, we can detect it.

$utf8 = '';
if (substr($output, 0, 2) === "\xff\xfe") {
    echo "Detected UTF-16LE BOM\n";
    $utf8 = iconv('UTF-16LE', 'UTF-8', substr($output, 2));
} elseif (substr($output, 0, 2) === "\xfe\xff") {
    echo "Detected UTF-16BE BOM\n";
    $utf8 = iconv('UTF-16BE', 'UTF-8', substr($output, 2));
} else {
    // Check if it's UTF-16 without BOM
    $null_at_even = 0;
    $null_at_odd = 0;
    $len = strlen($output);
    for ($i = 0; $i < min($len, 1000); $i++) {
        if (ord($output[$i]) === 0) {
            if ($i % 2 === 0) $null_at_even++;
            else $null_at_odd++;
        }
    }
    
    if ($null_at_even > 100) {
        echo "Detected UTF-16BE without BOM\n";
        $utf8 = iconv('UTF-16BE', 'UTF-8', $output);
    } elseif ($null_at_odd > 100) {
        echo "Detected UTF-16LE without BOM\n";
        $utf8 = iconv('UTF-16LE', 'UTF-8', $output);
    } else {
        echo "Detected UTF-8 / ASCII\n";
        $utf8 = $output;
    }
}

file_put_contents(__DIR__ . '/old_app_utf8_v2.js', $utf8);
echo "Saved UTF-8 file of length " . strlen($utf8) . " bytes.\n";
