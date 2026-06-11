import sys

path = r"d:\_Site\ssotansum\ncd\admin\import_hdc.php"
with open(path, 'rb') as f:
    content = f.read()

print("File size:", len(content), "bytes")

# Check for null bytes
null_count = content.count(b'\x00')
print("Null bytes count:", null_count)

# Try to decode line by line to locate errors
lines = content.split(b'\n')
for i, line in enumerate(lines):
    try:
        line.decode('utf-8')
    except UnicodeDecodeError as e:
        print(f"Error at line {i+1}: {e}")
        print("  Raw bytes:", line[max(0, e.start-10):e.end+10])
        # Try decoding the line as windows-874
        try:
            tis_decoded = line.decode('cp874')
            print("  Decoded as CP874:", tis_decoded)
        except Exception:
            pass
