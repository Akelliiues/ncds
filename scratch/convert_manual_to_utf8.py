import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(base_dir, 'manual.php')

try:
    with open(path, 'r', encoding='tis-620') as f:
        content = f.read()
except Exception as e:
    print(f"Error reading TIS-620: {e}")
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()

# Let's write the entire file or just the admin sections to a UTF-8 file
output_path = os.path.join(base_dir, 'scratch/admin_manual_utf8.html')
with open(output_path, 'w', encoding='utf-8') as f:
    f.write(content)

print(f"Successfully converted manual.php to UTF-8 and saved to scratch/admin_manual_utf8.html")
