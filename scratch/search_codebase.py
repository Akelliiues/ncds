import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
keywords = ['line_house_mappings', 'line_user_id']

for root, dirs, files in os.walk(base_dir):
    if 'node_modules' in root or '.git' in root or 'scratch' in root:
        continue
    for file in files:
        if file.endswith('.php') or file.endswith('.js') or file.endswith('.sql'):
            path = os.path.join(root, file)
            try:
                with open(path, 'r', encoding='utf-8') as f:
                    content = f.read()
            except Exception:
                try:
                    with open(path, 'r', encoding='tis-620') as f:
                        content = f.read()
                except Exception:
                    continue
            
            for kw in keywords:
                if kw in content:
                    print(f"Found '{kw}' in {os.path.relpath(path, base_dir)}")
                    # Find occurrences
                    lines = content.split('\n')
                    for i, line in enumerate(lines):
                        if kw in line:
                            print(f"  Line {i+1}: {line.strip()}")
