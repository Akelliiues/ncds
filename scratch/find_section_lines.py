import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(base_dir, 'scratch/admin_manual_utf8.html')

if os.path.exists(path):
    with open(path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
        
    targets = [
        'id="admin-db-maintenance"',
        'id="admin-dpac-mg"',
        'id="admin-vhv-approval"',
        'id="admin-targets"'
    ]
    
    for t in targets:
        found = False
        for idx, line in enumerate(lines):
            if t in line:
                print(f"Target '{t}' starts around line {idx + 1}")
                found = True
                # Print next few lines
                for j in range(max(0, idx - 2), min(len(lines), idx + 8)):
                    print(f"  {j+1}: {lines[j].strip()}")
                break
        if not found:
            print(f"Target '{t}' not found")
