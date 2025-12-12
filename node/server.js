let mysql = require('mysql2');
const express = require('express');
const app = express();
const cors = require('cors');
app.use(cors());

let con = mysql.createConnection({
  host: "db",
  user: "root",
  password: "123",
  database: "incredidose",
  port: 3306
});

app.get('/getpatients', (req, res) => {
    const id = req.query.id;
    con.query(
        'SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE p.doctorid = ? GROUP BY u.userid',
        [id],                      
        (err, results) => {
            if (err) return res.status(500).json({ error: err });
            res.json(results);
        }
    );
});

app.listen(3000, () => console.log('Node API running on port 3000'));