// scratch/try_passwords.js
const mysql2 = require('./node_modules/mysql2/promise');

const PASSWORDS = [
  '',
  'root',
  '1234',
  'jhcis',
  'jhcis2023',
  'jhcis2024',
  'jhcis2025',
  'jhcis2026',
  'password',
  'admin',
  'mysql',
  'Prevention2026',
  '03756', // pcucode
  'tansum',
];

async function tryPassword(pw) {
  let conn;
  try {
    conn = await mysql2.createConnection({
      host: 'localhost',
      port: 3333,
      user: 'root',
      password: pw,
      connectTimeout: 2000,
    });
    console.log(`✅ สำเร็จ! รหัสผ่าน: "${pw}"`);
    await conn.end();
    return true;
  } catch (e) {
    if (e.code === 'ER_ACCESS_DENIED_ERROR') {
      process.stdout.write('.');
    } else {
      console.log(`\n⚠️ Error: ${e.message}`);
    }
    return false;
  } finally {
    if (conn) try { await conn.end(); } catch (_) {}
  }
}

async function run() {
  console.log('🔍 ทดสอบ passwords...');
  for (const pw of PASSWORDS) {
    const ok = await tryPassword(pw);
    if (ok) break;
  }
  console.log('\nเสร็จสิ้นการทดสอบ');
}
run();
