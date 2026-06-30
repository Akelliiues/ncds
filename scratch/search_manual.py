import os
import re

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(base_dir, 'manual.php')

if os.path.exists(path):
    print("=== Searching manual.php ===")
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception:
        with open(path, 'r', encoding='tis-620') as f:
            content = f.read()
            
    # Find all ids or h3/h4 headings
    headings = re.findall(r'<h[34][^>]*>(.*?)</h[34]>', content)
    print("Headings:")
    for h in headings[:50]:
        print(f"  {h.strip()}")
        
    print("\nSections (ids):")
    ids = re.findall(r'id=["\'](.*?)["\']', content)
    for id_val in ids[:50]:
        print(f"  {id_val}")
