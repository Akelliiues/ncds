path = r"d:\_Site\ssotansum\ncd\admin\import_hdc.php"
with open(path, 'rb') as f:
    content = f.read()

# Replace the corrupted sequence
corrupted = b'?>\xb8\x8a\xe0\xb8\xb7\xe0\xb9\x88\xe0\xb8\xad'
repaired = b'?>\xe0\xb8\x8a\xe0\xb8\xb7\xe0\xb9\x88\xe0\xb8\xad'

if corrupted in content:
    new_content = content.replace(corrupted, repaired)
    # Save backup
    with open(path + '.bak2', 'wb') as f:
        f.write(content)
    # Write repaired file
    with open(path, 'wb') as f:
        f.write(new_content)
    print("Repaired corrupted UTF-8 byte sequence!")
else:
    print("Corrupted sequence not found. Let's do a more robust fix.")
    # Decode with replace and write back
    decoded = content.decode('utf-8', errors='replace')
    with open(path, 'w', encoding='utf-8', newline='') as f:
        f.write(decoded)
    print("Rewritten file with UTF-8 replacement characters.")
