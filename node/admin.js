const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');
const session = require('express-session');

const app = express();

app.use(cors({ origin: true, credentials: true }));
app.use(express.json());

app.use(session({
  name: process.env.SESSION_NAME || 'sid',
  secret: process.env.SESSION_SECRET || 'wordington_top_ten_hackers',
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    maxAge: 1800000, // 30 minutes
    secure: false, 
    sameSite: 'lax'
  }
}));

const db = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'incredidose',
  port: 3306,
  waitForConnections: true,
  connectionLimit: 10
});


app.post('/auth/login', async (req, res) => {
  const { email, password } = req.body || {};

  if (!email || !password) {
    return res.status(400).json({
      success: false,
      error: 'email and password required'
    });
  }

  try {
    const [rows] = await db.query(
      'SELECT userid, firstname, lastname, email, role, password FROM user WHERE email = ?',
      [email]
    );

    if (!rows.length || rows[0].password !== password) {
      return res.status(401).json({
        success: false,
        error: 'invalid email or password'
      });
    }

    const user = rows[0];

    req.session.userid = user.userid;
    req.session.firstname = user.firstname;
    req.session.lastname = user.lastname;
    req.session.email = user.email;
    req.session.role = user.role;

    res.json({
      success: true,
      userid: user.userid,
      firstname: user.firstname,
      lastname: user.lastname,
      email: user.email,
      role: user.role
    });

  } catch (err) {
    console.error(err);
    res.status(500).json({
      success: false,
      error: 'database error'
    });
  }
});

app.post('/auth/logout', (req, res) => {
  req.session.destroy(err => {
    if (err) {
      return res.status(500).json({
        success: false,
        error: 'logout failed'
      });
    }
    res.clearCookie(process.env.SESSION_NAME || 'sid');
    res.json({ success: true });
  });
});

app.get('/auth/session', (req, res) => {
  if (!req.session?.userid) {
    return res.status(401).json({
      success: false,
      error: 'not authenticated'
    });
  }

  res.json({
    success: true,
    userid: req.session.userid,
    firstname: req.session.firstname,
    lastname: req.session.lastname,
    email: req.session.email,
    role: req.session.role
  });
});

function requireAdmin(req, res, next) {
  if (!req.session?.userid) {
    return res.status(401).json({
      success: false,
      error: 'not authenticated'
    });
  }

  if (req.session.role !== 'admn') {
    return res.status(403).json({
      success: false,
      error: 'admin access only'
    });
  }

  next();
}


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

async function practitionerExists(userId, type) {
  const [rows] = await db.query(
    `SELECT u.userid FROM user u JOIN practitioner p ON u.userid = p.userid WHERE p.type = ? AND u.userid = ?`,
    [type, userId]
  );
  return rows.length > 0;
}

// view doctors
app.get('/admin/doctors', requireAdmin, async (req, res) => {
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

// view pharmacists
app.get('/admin/pharmacists', requireAdmin, async (req, res) => {
  try {
    const [pharmacists] = await db.query(`
      SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum,
             u.birthdate, u.gender, p.licensenum, p.specialization, p.affiliation
      FROM user u
      JOIN practitioner p ON u.userid = p.userid
      WHERE p.type = 'pharmacist'
      ORDER BY u.lastname, u.firstname
    `);

    res.json({ success: true, pharmacists });
  } catch (err) {
    console.error(err);
    res.status(500).json({ success: false, error: 'Database error' });
  }
});

// create doctor
app.post('/admin/doctors', requireAdmin, async (req, res) => {
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

  
  if (!(await practitionerExists(id, type))) {
    return res.status(404).json({ success:false, error:`${type} not found` });
  }

  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();

    if (req.body.email && await checkEmailExists(req.body.email, id)) {
      throw { status: 400, msg: 'Email already in use by another user' };
    }
    if (req.body.licensenum && await checkLicenseExists(req.body.licensenum, id)) {
      throw { status: 400, msg: 'License number already in use' };
    }

    
    const userFields = ['firstname','lastname','email','contactnum','birthdate','gender','password'];
    const userUpdate = buildUpdate(userFields, req.body);

    if (userUpdate.cols.length) {
      await conn.query(
        `UPDATE user SET ${userUpdate.cols.join(', ')} WHERE userid = ?`,
        [...userUpdate.values, id]
      );
    }

    const practitionerFields = ['licensenum','specialization','affiliation'];
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
    console.error(err);
    res.status(err.status || 500).json({ success: false, error: err.msg || 'Database error' });
  } finally {
    conn.release();
  }
}

app.put('/admin/doctors/:doctorid', requireAdmin, (req, res) => updatePractitioner(req, res, 'doctor'));
app.put('/admin/pharmacists/:pharmacistid', requireAdmin, (req, res) => updatePractitioner(req, res, 'pharmacist'));


const PORT = 3001;
app.listen(PORT, () => console.log(`Admin API running on port ${PORT}`));