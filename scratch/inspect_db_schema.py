import subprocess
import json

php_code = """<?php
require_once 'config/db.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "=== Table: $t ===\\n";
    $cols = $pdo->query("DESCRIBE `$t`")->fetchAll();
    foreach ($cols as $c) {
        echo "  " . $c['Field'] . " | " . $c['Type'] . "\\n";
    }
}
"""

with open('scratch/temp_inspect.php', 'w') as f:
    f.write(php_code)

try:
    res = subprocess.run(['scratch/php/php.exe', 'scratch/temp_inspect.php'], capture_output=True, text=True, encoding='utf-8')
    print(res.stdout)
except Exception as e:
    print("Error:", e)
