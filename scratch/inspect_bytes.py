import sys

path = r"d:\_Site\ssotansum\ncd\admin\import_hdc.php"
with open(path, 'rb') as f:
    data = f.read(200)
print("Hex representation of first 200 bytes:")
print(data.hex())
print("Byte values:")
print(list(data))
