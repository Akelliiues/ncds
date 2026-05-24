import os

files = [
    'index.php', 'assignment.php', 'dpac_manager.php', 'hdc_import.php', 'hdc_list.php', 
    'import_hdc.php', 'print_qr.php', 'process_etl.php', 'profile.php', 'seed_db.php', 'vhv_approval.php'
]

profile_sub = """            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-tooltip="ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>"""

reports_sub = """
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" data-tooltip="รายงานและการพิมพ์">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </a>"""

for filename in files:
    filepath = os.path.join('admin', filename)
    if not os.path.exists(filepath):
        continue
    
    with open(filepath, 'rb') as f:
        content = f.read()
        
    # We search using binary to avoid encoding mismatch issues
    profile_bytes = profile_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')
    reports_bytes = reports_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('utf-8')
    
    if b'reports.php' not in content and profile_bytes in content:
        updated = content.replace(profile_bytes, profile_bytes + reports_bytes)
        with open(filepath, 'wb') as f:
            f.write(updated)
        print(f"Successfully added reports to: {filename}")
    else:
        # Check CP874 version
        profile_bytes_cp = profile_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('cp874')
        reports_bytes_cp = reports_sub.replace('\r\n', '\n').replace('\n', '\r\n').encode('cp874')
        if b'reports.php' not in content and profile_bytes_cp in content:
            updated = content.replace(profile_bytes_cp, profile_bytes_cp + reports_bytes_cp)
            with open(filepath, 'wb') as f:
                f.write(updated)
            print(f"Successfully added reports (CP874) to: {filename}")
        else:
            print(f"Skipped reports for: {filename}")
