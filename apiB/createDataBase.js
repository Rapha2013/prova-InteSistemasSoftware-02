const sqlite3 = require('sqlite3').verbose();
const db = new sqlite3.Database('./database.sqlite');

const createTableQuery = `
  CREATE TABLE IF NOT EXISTS folhaCalculada (
    id INTEGER PRIMARY KEY,
    mes INTEGER,
    ano INTEGER,
    horas DOUBLE,
    valor DOUBLE,
    bruto DOUBLE,
    irrf DOUBLE,
    inss DOUBLE,
    fgts DOUBLE,
    liquido DOUBLE,
    funcionario_nome TEXT,
    funcionario_cpf INTEGER
  )
`;

// Executa o comando SQL para criar a tabela
db.run(createTableQuery, (err) => {
  if (err) {
    console.error('Erro ao criar tabela:', err.message);
  } else {
    console.log('Tabela criada com sucesso!');
  }

  // Fecha a conexão com o banco de dados
  db.close();
});