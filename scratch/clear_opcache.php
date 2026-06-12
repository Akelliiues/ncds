<?php
// scratch/clear_opcache.php
header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "OPCache has been successfully cleared!\n";
    } else {
        echo "Failed to clear OPCache.\n";
    }
} else {
    echo "OPCache extension is not loaded or opcache_reset() is disabled.\n";
}
