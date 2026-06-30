import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(base_dir, 'admin/import_hdc.php')

if os.path.exists(path):
    print("=== Inspecting import_hdc.php ===")
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception:
        with open(path, 'r', encoding='tis-620') as f:
            content = f.read()
            
    lines = content.split('\n')
    for i, line in enumerate(lines):
        if 'hid' in line.lower():
            print(f"  Line {i+1}: {line.strip()}")
