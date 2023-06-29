const sqlite3 = require('sqlite3').verbose();
const db = new sqlite3.Database('./database.sqlite');

const amqp = require('amqplib/callback_api');

const insertTable = (params) => {
    const insertQuery = `
  INSERT INTO folhaCalculada (mes, ano, horas, valor, bruto, irrf, inss, fgts, liquido, funcionario_nome, funcionario_cpf)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
`;

    // Executa o comando SQL para inserir os dados
    db.run(insertQuery, [params.mes, params.ano, params.horas, params.valor, params.bruto, params.irrf, params.inss, params.fgts, params.liquido, params.funcionario['nome'], params.funcionario['cpf']], function (err) {
        if (err) {
            console.error('Erro ao inserir os dados:', err.message);
        } else {
            console.log(`Dados inseridos com sucesso. ID da linha inserida: ${this.lastID}`);
        }
    });
}

amqp.connect('amqp://localhost', function (error0, connection) {
    if (error0) {
        throw error0;
    }
    connection.createChannel(function (error1, channel) {
        if (error1) {
            throw error1;
        }

        var queue = 'folha';

        channel.assertQueue(queue, {
            durable: false
        });

        channel.consume(queue, function (msg) {

            var retorno = JSON.parse(msg.content.toString());

            if (retorno) {
                retorno.forEach(folha => {
                    insertTable(folha.folha)
                });
            }

        }, {
            noAck: true
        });
    });
});


