import os
import re

root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Files and directories to exclude from modification
exclude_dirs = {'.git', '.vscode', 'node_modules', 'sessions'}
exclude_files = {'session.php'}

modified_count = 0

for dirpath, dirnames, filenames in os.walk(root_dir):
    # Modify dirnames in place to skip excluded directories
    dirnames[:] = [d for d in dirnames if d not in exclude_dirs]
    
    for filename in filenames:
        if not filename.endswith('.php') or filename in exclude_files:
            continue
            
        file_path = os.path.join(dirpath, filename)
        
        # Read the file content
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
            
        # Check if session_start() is in the file
        if re.search(r'session_start\s*\(\s*\)\s*;', content):
            # Compute relative depth to root_dir
            rel_path = os.path.relpath(dirpath, root_dir)
            if rel_path == '.':
                depth = 0
            else:
                depth = len(rel_path.replace('\\', '/').split('/'))
                
            prefix = '../' * depth
            replacement = f"require_once __DIR__ . '/{prefix}config/session.php';"
            
            # Perform the replacement
            new_content = re.sub(r'session_start\s*\(\s*\)\s*;', replacement, content)
            
            # Write back
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
                
            print(f"Updated: {os.path.relpath(file_path, root_dir)} -> {replacement}")
            modified_count += 1

print(f"\nDone! Modified {modified_count} PHP files.")
