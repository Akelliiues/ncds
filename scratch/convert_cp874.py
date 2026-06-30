import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(base_dir, 'manual.php')

try:
    with open(path, 'r', encoding='cp874', errors='replace') as f:
        content = f.read()
    output_path = os.path.join(base_dir, 'scratch/admin_manual_utf8.html')
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Successfully converted manual.php to UTF-8 using cp874 and saved to scratch/admin_manual_utf8.html")
except Exception as e:
    print(f"Error: {e}")
