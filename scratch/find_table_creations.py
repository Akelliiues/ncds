import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
create_tables = []

def search_file(filepath):
    # Try UTF-8 first, then CP874 / TIS-620
    content = ""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except UnicodeDecodeError:
        try:
            with open(filepath, 'r', encoding='cp874') as f:
                content = f.read()
        except Exception as e:
            return
            
    if 'CREATE TABLE' in content.upper():
        print(f"Found CREATE TABLE in {filepath}")
        lines = content.split('\n')
        for idx, line in enumerate(lines):
            if 'CREATE TABLE' in line.upper():
                print(f"  Line {idx+1}: {line.strip()}")

for root, dirs, files in os.walk(base_dir):
    if 'node_modules' in root or '.git' in root or 'scratch' in root:
        continue
    for file in files:
        if file.endswith('.php'):
            search_file(os.path.join(root, file))
