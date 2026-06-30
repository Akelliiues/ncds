<?php
$files = [
    __DIR__ . '/../test_screen.html',
    __DIR__ . '/../test_screen2.html',
    __DIR__ . '/../vhv/screening_form.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    echo "=== Reading $file ===\n";
    $content = file_get_contents($file);
    // Convert from UTF-16LE if needed
    if (strpos($content, "\xFF\xFE") === 0) {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
    }
    
    // Find all occurrences of VhvNumPad and their surrounding text (50 chars before and after)
    $offset = 0;
    while (($pos = stripos($content, 'VhvNumPad', $offset)) !== false) {
        $start = max(0, $pos - 100);
        $len = min(strlen($content) - $start, 250);
        $snippet = substr($content, $start, $len);
        echo "Found at position $pos:\n[...]" . str_replace("\r\n", " ", $snippet) . "[...]\n\n";
        $offset = $pos + 9;
    }
}
