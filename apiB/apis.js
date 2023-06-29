const sqlite3 = require('sqlite3').verbose();
const db = new sqlite3.Database('./database.sqlite');

const express = require('express')
const app = express()


app.get('/folha/listar', function (req, res) {
    db.all("SELECT * FROM folhaCalculada", function (err, tables) {
        res.send(tables)
    });
  })

  app.get('/folha/total', function (req, res) {
    db.each("SELECT SUM(liquido) AS total FROM folhaCalculada", function (err, tables) {
        res.send(tables)
    });
  })


  app.get('/folha/media', function (req, res) {
    
    db.all("SELECT * FROM folhaCalculada", function (err, retorno) {

        var total = 0;

        retorno.forEach(folha => {
            total += folha.liquido
        });

        res.send({
            qtd_folhas: retorno.length,
            total: 'R$: ' + total,
            media: 'R$: ' + (total / retorno.length).toFixed(2)
        })
    });
  })
  
app.listen(3000)

