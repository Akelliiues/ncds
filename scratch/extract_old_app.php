<?php
$output = shell_exec('git show 4d4f649309fc92e65149a132d2229cee4585789d:assets/js/app.js');

// Convert from UTF-16LE if needed
if (strpos($output, "\xFF\xFE") === 0 || strpos($output, "\xFE\xFF") === 0 || preg_match('/^\0[^\0]/', $output) || preg_match('/^[^\0]\0/', $output)) {
    // Attempt standard iconv or custom conversion
    $utf8 = @iconv('UTF-16LE', 'UTF-8', $output);
    if (!$utf8) {
        // Fallback custom conversion
        $utf8 = '';
        $len = strlen($output);
        for ($i = 0; $i < $len; $i += 2) {
            $utf8 .= $output[$i];
        }
    }
    file_put_contents(__DIR__ . '/old_app_utf8.js', $utf8);
    echo "Saved " . strlen($utf8) . " bytes to scratch/old_app_utf8.js\n";
} else {
    file_put_contents(__DIR__ . '/old_app_utf8.js', $output);
    echo "Saved " . strlen($output) . " bytes directly to scratch/old_app_utf8.js\n";
}
