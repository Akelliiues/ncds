import os

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(base_dir, 'manual.php')

try:
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
except Exception:
    with open(path, 'r', encoding='tis-620') as f:
        content = f.read()

# Find <section id="admin-db-maintenance">...</section>
# Let's search using index
start_idx = content.find('id="admin-db-maintenance"')
if start_idx != -1:
    # Go back to find the opening <section
    start_sec = content.rfind('<section', 0, start_idx)
    # Find the closing </section>
    end_sec = content.find('</section>', start_idx)
    if start_sec != -1 and end_sec != -1:
        print(content[start_sec:end_sec + len('</section>')])
    else:
        print("Could not find section bounds")
else:
    print("Could not find section ID")
