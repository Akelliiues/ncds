import os

files = [
    'assignment.php', 'dpac_manager.php', 'hdc_import.php', 'hdc_list.php', 
    'import_hdc.php', 'print_qr.php', 'process_etl.php', 'seed_db.php', 'vhv_approval.php'
]

# We use UTF-8 and ignore/replace any byte decode issues to preserve Thai characters
target_sub = """            <a href="vhv_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'vhv_approval.php' ? 'active' : '' ?>" data-tooltip="จัดการผู้ใช้ อสม.">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </a>"""

profile_sub = """
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>"""

for filename in files:
    filepath = os.path.join('admin', filename)
    if not os.path.exists(filepath):
        print(f"Skipped (does not exist): {filepath}")
        continue
    
    # Read as binary to be absolutely safe with encoding
    with open(filepath, 'rb') as f:
        content = f.read()
    
    # Normalize CRLF and search/replace in binary format to avoid encoding bugs
    target_bytes = target_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('cp874')
    profile_bytes = profile_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('cp874')
    
    # Let's search inside the binary content
    if b'profile.php' not in content and target_bytes in content:
        updated = content.replace(target_bytes, target_bytes + profile_bytes)
        with open(filepath, 'wb') as f:
            f.write(updated)
        print(f"Successfully updated: {filename}")
    else:
        # Check TIS-620/cp874 representation if cp874 encode above didn't match (fallback to utf-8 encode)
        target_bytes_utf = target_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')
        profile_bytes_utf = profile_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')
        if b'profile.php' not in content and target_bytes_utf in content:
            updated = content.replace(target_bytes_utf, target_bytes_utf + profile_bytes_utf)
            with open(filepath, 'wb') as f:
                f.write(updated)
            print(f"Successfully updated (UTF-8): {filename}")
        else:
            print(f"Skipped (target not found or profile already exists): {filename}")
