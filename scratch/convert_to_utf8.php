<?php
$file = __DIR__ . '/../admin/import_hdc.php';
$content = file_get_contents($file);
$encoding = mb_detect_encoding($content, ['UTF-8', 'TIS-620', 'SJIS', 'EUC-JP', 'ASCII', 'ISO-8859-11']);
echo "Detected encoding: " . $encoding . "\n";

if ($encoding !== 'UTF-8') {
    // If it's TIS-620 or ISO-8859-11 (Thai ANSI)
    $converted = mb_convert_encoding($content, 'UTF-8', 'TIS-620');
    // Save backup
    file_put_contents($file . '.bak', $content);
    // Write UTF-8
    file_put_contents($file, $converted);
    echo "Converted file to UTF-8 and saved backup to import_hdc.php.bak\n";
} else {
    echo "File is already UTF-8 or could not be converted\n";
}
