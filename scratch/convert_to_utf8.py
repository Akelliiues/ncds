import os

path = r"d:\_Site\ssotansum\ncd\admin\import_hdc.php"
try:
    with open(path, 'rb') as f:
        content = f.read()
    # Try decoding as tis-620 or windows-874
    try:
        decoded = content.decode('tis-620')
        print("Successfully decoded as TIS-620")
    except Exception as e1:
        decoded = content.decode('cp874')
        print("Successfully decoded as CP874")
    
    # Save backup
    with open(path + '.bak', 'wb') as f:
        f.write(content)
    # Write as UTF-8
    with open(path, 'w', encoding='utf-8', newline='') as f:
        f.write(decoded)
    print("Converted to UTF-8 and backup saved.")
except Exception as e:
    print("Error:", e)
