// scratch/setup_db.js
// สร้างฐานข้อมูล tansum_ncd, ตาราง, และ seed ข้อมูลทดสอบ
const mysql2 = require('./node_modules/mysql2/promise');

const ROOT_CONFIG = {
  host: 'localhost',
  port: 3333,
  user: 'root',
  password: '',
  multipleStatements: true,
};

async function run() {
  let conn;
  try {
    conn = await mysql2.createConnection(ROOT_CONFIG);
    console.log('✅ เชื่อมต่อ MySQL สำเร็จ (port 3333)');

    // สร้างฐานข้อมูลและ user
    await conn.query(`CREATE DATABASE IF NOT EXISTS tansum_ncd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`);
    console.log('✅ สร้างฐานข้อมูล tansum_ncd');

    try {
      await conn.query(`CREATE USER 'tansum_ncd'@'localhost' IDENTIFIED BY 'Prevention2026';`);
    } catch (err) {
      if (err.code !== 'ER_CANNOT_USER') {
        throw err;
      }
    }
    await conn.query(`GRANT ALL PRIVILEGES ON tansum_ncd.* TO 'tansum_ncd'@'localhost';`);
    await conn.query(`FLUSH PRIVILEGES;`);
    console.log('✅ สร้าง/ตั้งค่า user tansum_ncd สำเร็จ');

    await conn.query(`USE tansum_ncd;`);

    // ===== สร้างตาราง =====
    await conn.query(`
      CREATE TABLE IF NOT EXISTS vhv_users (
        vhv_id VARCHAR(20) NOT NULL PRIMARY KEY,
        vhv_name VARCHAR(255) NOT NULL,
        vhv_moo INT NOT NULL DEFAULT 1,
        vhid_code VARCHAR(20),
        hoscode VARCHAR(10),
        password_hash VARCHAR(255) NOT NULL,
        is_leader TINYINT(1) NOT NULL DEFAULT 0,
        line_user_id VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS target_population (
        cid VARCHAR(13) NOT NULL PRIMARY KEY,
        hid VARCHAR(15),
        pid INT,
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        sex VARCHAR(2),
        birth DATE,
        house_no VARCHAR(50),
        moo INT DEFAULT 1,
        sub_district_code VARCHAR(10),
        vhid_code VARCHAR(20),
        hoscode VARCHAR(10),
        latitude DECIMAL(10, 7),
        longitude DECIMAL(10, 7),
        health_status_origin VARCHAR(20) DEFAULT 'NORMAL',
        need_screen_dm TINYINT(1) DEFAULT 0,
        need_screen_ht TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS task_assignments (
        assignment_id INT AUTO_INCREMENT PRIMARY KEY,
        target_cid VARCHAR(13) NOT NULL,
        vhv_id VARCHAR(20) NOT NULL,
        budget_year INT DEFAULT 2026,
        assignment_status ENUM('pending','completed','skipped') DEFAULT 'pending',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (target_cid) REFERENCES target_population(cid) ON DELETE CASCADE,
        FOREIGN KEY (vhv_id) REFERENCES vhv_users(vhv_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS screening_results (
        screening_id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        sys_bp1 INT,
        dia_bp1 INT,
        sys_bp2 INT,
        dia_bp2 INT,
        dtx_value INT,
        dtx_type ENUM('fpg','rpg') DEFAULT 'fpg',
        weight DECIMAL(5,1),
        height DECIMAL(5,1),
        waist DECIMAL(5,1),
        bmi DECIMAL(5,2),
        diet_risk VARCHAR(10) DEFAULT 'green',
        exercise_risk VARCHAR(10) DEFAULT 'green',
        stress_risk VARCHAR(10) DEFAULT 'green',
        smoking_risk VARCHAR(10) DEFAULT 'green',
        alcohol_risk VARCHAR(10) DEFAULT 'green',
        cv_risk_score DECIMAL(5,2) DEFAULT 0,
        screening_lat DECIMAL(10,7),
        screening_lng DECIMAL(10,7),
        skipped_reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES task_assignments(assignment_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS vhv_rewards (
        reward_id INT AUTO_INCREMENT PRIMARY KEY,
        vhv_id VARCHAR(20) NOT NULL,
        screening_id INT NOT NULL,
        points_earned INT DEFAULT 1,
        cash_earned DECIMAL(8,2) DEFAULT 0.00,
        approval_status ENUM('approved','waiting','rejected') DEFAULT 'approved',
        approved_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vhv_id) REFERENCES vhv_users(vhv_id) ON DELETE CASCADE,
        FOREIGN KEY (screening_id) REFERENCES screening_results(screening_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS staging_hdc_dm (
        staging_id INT AUTO_INCREMENT PRIMARY KEY,
        hoscode VARCHAR(10),
        hosname VARCHAR(255),
        pid INT,
        cid VARCHAR(13),
        name VARCHAR(255),
        lname VARCHAR(255),
        sex VARCHAR(2),
        birth DATE,
        hid VARCHAR(15),
        addr VARCHAR(255),
        check_vhid VARCHAR(20),
        typearea VARCHAR(5),
        risk VARCHAR(5),
        result VARCHAR(255),
        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS staging_hdc_ht (
        staging_id INT AUTO_INCREMENT PRIMARY KEY,
        hoscode VARCHAR(10),
        hosname VARCHAR(255),
        pid INT,
        cid VARCHAR(13),
        name VARCHAR(255),
        lname VARCHAR(255),
        sex VARCHAR(2),
        birth DATE,
        hid VARCHAR(15),
        addr VARCHAR(255),
        check_vhid VARCHAR(20),
        typearea VARCHAR(5),
        sbp INT,
        dbp INT,
        risk VARCHAR(5),
        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS line_house_mappings (
        line_user_id VARCHAR(100) NOT NULL,
        hid VARCHAR(15) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (line_user_id, hid)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    await conn.query(`
      CREATE TABLE IF NOT EXISTS assignment_history_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT,
        action VARCHAR(50),
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);

    console.log('✅ สร้างตารางทั้งหมดสำเร็จ');

    // ===== TRUNCATE ข้อมูลเก่า =====
    await conn.query(`SET FOREIGN_KEY_CHECKS = 0;`);
    await conn.query(`TRUNCATE TABLE vhv_rewards;`);
    await conn.query(`TRUNCATE TABLE screening_results;`);
    await conn.query(`TRUNCATE TABLE task_assignments;`);
    await conn.query(`TRUNCATE TABLE target_population;`);
    await conn.query(`TRUNCATE TABLE vhv_users;`);
    await conn.query(`TRUNCATE TABLE staging_hdc_dm;`);
    await conn.query(`TRUNCATE TABLE staging_hdc_ht;`);
    await conn.query(`TRUNCATE TABLE assignment_history_log;`);
    await conn.query(`SET FOREIGN_KEY_CHECKS = 1;`);
    console.log('🗑️ ล้างข้อมูลเก่าเรียบร้อย');

    // ===== SEED VHV Users =====
    // Password hash สำหรับ '1234' (bcrypt) - ใช้ค่า pre-computed
    const passHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // bcrypt '1234'
    
    await conn.query(`
      INSERT INTO vhv_users (vhv_id, vhv_name, vhv_moo, vhid_code, hoscode, password_hash, is_leader) VALUES
      ('1001', 'นางใจดี รักสงบ (ประธาน)', 1, '34180101', '10688', ?, 1),
      ('1002', 'นางสมศรี มีสุข (อสม.)', 1, '34180101', '10688', ?, 0),
      ('1003', 'นายสมชาย แข็งแรง (อสม.)', 2, '34180102', '10688', ?, 0)
    `, [passHash, passHash, passHash]);
    console.log('✅ เพิ่ม VHV users 3 คน (รหัสผ่าน: 1234)');

    // ===== SEED Target Population =====
    await conn.query(`
      INSERT INTO target_population 
      (cid, hid, pid, first_name, last_name, sex, birth, house_no, moo, sub_district_code, vhid_code, hoscode, latitude, longitude, health_status_origin, need_screen_dm, need_screen_ht)
      VALUES
      ('1234567890112', '112', 12, 'นางแดง', 'งามยิ่ง', '2', '1972-08-23', '45', 1, '341801', '34180101', '10688', 15.4300, 104.9800, 'BOTH', 1, 1),
      ('1234567890113', '113', 13, 'น.ส.เขียว', 'สดใส', '2', '1985-11-05', '78/1', 1, '341801', '34180101', '10688', 15.4320, 104.9820, 'BOTH', 1, 1),
      ('1234567890114', '201', 14, 'นายขาว', 'บริสุทธิ์', '1', '1958-02-17', '3', 2, '341801', '34180102', '10688', 15.4400, 104.9900, 'HT_ONLY', 0, 1)
    `);
    console.log('✅ เพิ่มประชากรเป้าหมาย 3 ราย');

    // ===== SEED Task Assignments =====
    await conn.query(`
      INSERT INTO task_assignments (target_cid, vhv_id, budget_year, assignment_status) VALUES
      ('1234567890112', '1002', 2026, 'pending'),
      ('1234567890113', '1002', 2026, 'pending'),
      ('1234567890114', '1003', 2026, 'pending')
    `);
    console.log('✅ เพิ่มใบงาน 3 ใบงาน');

    console.log('\n🎉 Seed ข้อมูลสำเร็จทั้งหมด!');
    console.log('');
    console.log('📋 สรุปบัญชีทดสอบ:');
    console.log('  ประธาน (หมู่ 1): รหัส 1001 / รหัสผ่าน 1234');
    console.log('  อสม. (หมู่ 1):   รหัส 1002 / รหัสผ่าน 1234  → มี 2 ใบงาน (นางแดง, น.ส.เขียว)');
    console.log('  อสม. (หมู่ 2):   รหัส 1003 / รหัสผ่าน 1234  → มี 1 ใบงาน (นายขาว)');
    console.log('');
    console.log('🗺️ พิกัดทดสอบ GPS:');
    console.log('  นางแดง งามยิ่ง (หมู่ 1): 15.4300, 104.9800');
    console.log('  น.ส.เขียว สดใส (หมู่ 1): 15.4320, 104.9820');
    console.log('  นายขาว บริสุทธิ์ (หมู่ 2): 15.4400, 104.9900');

  } catch (err) {
    console.error('❌ เกิดข้อผิดพลาด:', err.message);
    if (err.code) console.error('   Error Code:', err.code);
    process.exit(1);
  } finally {
    if (conn) await conn.end();
  }
}

run();
