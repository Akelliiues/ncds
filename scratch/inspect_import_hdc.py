import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
files_to_check = [
    'admin/import_hdc.php',
    'admin/process_etl.php',
    'admin/unit_house_manager.php'
]

for file in files_to_check:
    path = os.path.join(base_dir, file)
    if os.path.exists(path):
        print(f"=== Inspecting {file} ===")
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
