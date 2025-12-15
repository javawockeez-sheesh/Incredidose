const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const db = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'incredidose',
  port: 3306,
  waitForConnections: true,
  connectionLimit: 10
});

async function checkEmailExists(email, excludeUserId = null) {
  const sql = excludeUserId
    ? 'SELECT userid FROM user WHERE email = ? AND userid != ?'
    : 'SELECT userid FROM user WHERE email = ?';
  const params = excludeUserId ? [email, excludeUserId] : [email];
  const [rows] = await db.query(sql, params);
  return rows.length > 0;
}

async function checkLicenseExists(licensenum, excludeUserId = null) {
  const sql = excludeUserId
    ? 'SELECT userid FROM practitioner WHERE licensenum = ? AND userid != ?'
    : 'SELECT userid FROM practitioner WHERE licensenum = ?';
  const params = excludeUserId ? [licensenum, excludeUserId] : [licensenum];
  const [rows] = await db.query(sql, params);
  return rows.length > 0;
}

function buildUpdate(fields, data) {
  const cols = [];
  const values = [];
  for (const key of fields) {
    if (data[key] !== undefined) {
      cols.push(`${key} = ?`);
      values.push(data[key]);
    }
  }
  return { cols, values };
}

async function ensurePractitionerExists(userId, type) {
  const [rows] = await db.query(
    `SELECT u.userid FROM user u JOIN practitioner p ON u.userid = p.userid WHERE p.type = ? AND u.userid = ?`,
    [type, userId]
  );
  return rows.length > 0;
}

// view doctors
app.get('/admin/doctors', async (req, res) => {
  try {
    const [doctors] = await db.query(`
      SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum,
             u.birthdate, u.gender, p.licensenum, p.specialization, p.affiliation
      FROM user u
      JOIN practitioner p ON u.userid = p.userid
      WHERE p.type = 'doctor'
      ORDER BY u.lastname, u.firstname
    `);

    res.json({ success: true, doctors });
  } catch (err) {
    res.status(500).json({ success: false, error: 'Database error' });
  }
});

// create doctor
app.post('/admin/doctors', async (req, res) => {
  const required = ['firstname','lastname','email','contactnum','birthdate','gender','licensenum','specialization','password'];
  for (const f of required) {
    if (!req.body[f]) return res.status(400).json({ success:false, error:`Missing required field: ${f}` });
  }

  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();

    if (await checkEmailExists(req.body.email)) {
      throw { status:400, msg:'Email already exists' };
    }

    if (await checkLicenseExists(req.body.licensenum)) {
      throw { status:400, msg:'License number already exists' };
    }

    const [userResult] = await conn.query(
      `INSERT INTO user (firstname, lastname, email, contactnum, birthdate, gender, password, role)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'pcr')`,
      [req.body.firstname, req.body.lastname, req.body.email, req.body.contactnum,
       req.body.birthdate, req.body.gender, req.body.password]
    );

    await conn.query(
      `INSERT INTO practitioner (userid, type, licensenum, specialization, affiliation)
       VALUES (?, 'doctor', ?, ?, ?)`,
      [userResult.insertId, req.body.licensenum, req.body.specialization, req.body.affiliation || '']
    );

    await conn.commit();

    res.status(201).json({
      success: true,
      message: 'Doctor created successfully',
      doctor: {
        userid: userResult.insertId,
        firstname: req.body.firstname,
        lastname: req.body.lastname,
        email: req.body.email,
        licensenum: req.body.licensenum,
        specialization: req.body.specialization
      }
    });
  } catch (err) {
    await conn.rollback();
    res.status(err.status || 500).json({ success:false, error: err.msg || 'Database error' });
  } finally {
    conn.release();
  }
});

// update practitioner
async function updatePractitioner(req, res, type) { 
  const id = req.params[`${type}id`];
  if (!(await ensurePractitionerExists(id, type))) {
    return res.status(404).json({ success:false, error:`${type} not found` });
  }

  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();

    if (req.body.email && await checkEmailExists(req.body.email, id)) {
      throw { status:400, msg:'Email already in use by another user' };
    }

    if (req.body.licensenum && await checkLicenseExists(req.body.licensenum, id)) {
      throw { status:400, msg:'License number already in use' };
    }

    const userUpdate = buildUpdate(
      ['firstname','lastname','email','contactnum','birthdate','gender','password'],
      req.body
    );

    if (userUpdate.cols.length) {
      await conn.query(
        `UPDATE user SET ${userUpdate.cols.join(', ')} WHERE userid = ?`,
        [...userUpdate.values, id]
      );
    }

    const practitionerFields = type === 'doctor'
      ? ['licensenum','specialization','affiliation']
      : ['licensenum','pharmacy_name','pharmacy_address'];

    const practitionerUpdate = buildUpdate(practitionerFields, req.body);

    if (practitionerUpdate.cols.length) {
      await conn.query(
        `UPDATE practitioner SET ${practitionerUpdate.cols.join(', ')} WHERE userid = ? AND type = ?`,
        [...practitionerUpdate.values, id, type]
      );
    }

    await conn.commit();

    res.json({
      success: true,
      message: `${type.charAt(0).toUpperCase() + type.slice(1)} updated successfully`,
      updatedFields: {
        user: userUpdate.cols.map(c => c.split(' = ')[0]),
        practitioner: practitionerUpdate.cols.map(c => c.split(' = ')[0])
      }
    });
  } catch (err) {
    await conn.rollback();
    res.status(err.status || 500).json({ success:false, error: err.msg || 'Database error' });
  } finally {
    conn.release();
  }
}

app.put('/admin/doctors/:doctorid', (req, res) => updatePractitioner(req, res, 'doctor'));
app.put('/admin/pharmacists/:pharmacistid', (req, res) => updatePractitioner(req, res, 'pharmacist'));

// view pharmacists
app.get('/admin/pharmacists', async (req, res) => {
  try {
    const [pharmacists] = await db.query(`
      SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum,
             u.birthdate, u.gender, p.licensenum, p.pharmacy_name, p.pharmacy_address
      FROM user u
      JOIN practitioner p ON u.userid = p.userid
      WHERE p.type = 'pharmacist'
      ORDER BY u.lastname, u.firstname
    `);

    res.json({ success: true, pharmacists });
  } catch (err) {
    res.status(500).json({ success:false, error:'Database error' });
  }
});

const PORT = 3001;
app.listen(PORT, () => console.log(`Admin API running on port ${PORT}`));