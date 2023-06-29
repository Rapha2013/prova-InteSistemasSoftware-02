<?php

namespace App\Http\Controllers;

use App\Models\Folha;
use App\Models\Funcionario;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class FolhaController extends Controller
{

    private $folha;
    private $funcionario;

    public function __construct(Folha $folha, Funcionario $funcionario)
    {
        $this->folha = $folha;
        $this->funcionario = $funcionario;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        try {

            $funcionario = $this->funcionario->where('cpf', $request->funcionario['cpf'])->first();

            if (!$funcionario) {
                $funcionario = $this->funcionario->create([
                    'nome' => $request->funcionario['nome'],
                    'cpf' => $request->funcionario['cpf'],
                    'created_at' => date('Y-m-d H:m:s')
                ]);
            }

            $folhaPagamento = $this->folha->create([
                'mes' => $request->mes,
                'ano' => $request->ano,
                'horas' => $request->horas,
                'valor' => $request->valor,
                'id_funcionario' => $funcionario->id,
                'created_at' => date('Y-m-d H:m:s')
            ]);

            return response()->json($folhaPagamento->with('funcionario')->orderBy('id', 'desc')->get(), 201);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function calculaFolha()
    {

        $funcionarios = $this->funcionario->all();

        $folhaFuncionario = [];

        foreach ($funcionarios as $funcionario) {

            $folhas = $this->folha->where('id_funcionario', $funcionario->id)->with('funcionario')->whereNull('dt_processado')->get();

            if (count($folhas) == 0) {
                continue;
            }

            $mes = 0;
            $ano = 0;
            $horas = 0;
            $valor = 0;
            $bruto = 0;
            $irrf = 0;
            $inss = 0.00;

            foreach ($folhas as $folha) {

                $horas += $folha->horas;
                $mes = $folha->mes;
                $ano = $folha->ano;
                $valor = $folha->valor;

                $this->folha->where('id', $folha->id)->update([
                    'dt_processado' => date('Y-m-d H:m:s')
                ]);
            }

            $bruto = intval($horas) * doubleval($valor);

            if ($bruto >= 1903.99 && $bruto <= 2826.65) {
                $irrf = ($bruto * 7.5) / 100;
            } else if ($bruto >= 2826.66 && $bruto <= 3751.05) {
                $irrf = ($bruto * 15.0) / 100;
            } else if ($bruto >= 3751.06 && $bruto <= 4664.68) {
                $irrf = ($bruto * 22.5) / 100;
            } else if ($bruto >= 4664.68) {
                $irrf = ($bruto * 27.5) / 100;
            }


            if ($bruto <= 1693.72) {
                $inss = ($bruto * 8.0) / 100;
            } else if ($bruto >= 1693.73 && $bruto <= 2822.90) {
                $inss = ($bruto * 9.0) / 100;
            } else if ($bruto >= 2822.91 && $bruto <= 5645.80) {
                $inss = ($bruto * 11.5) / 100;
            } else if ($bruto >= 5645.81) {
                $inss = 621.03;
            }

            $folhaFuncionario[] = (object) [
                "folha" => (object) [
                    "mes" => $mes,
                    "ano" => $ano,
                    "horas" => $horas,
                    "valor" => $valor,
                    "bruto" => $bruto,
                    'irrf' => $irrf,
                    'inss' => $inss,
                    'fgts' => ($bruto * 8.0) / 100,
                    'liquido' => $bruto - $irrf - $inss,
                    'funcionario' => (object) [
                        "nome" => $funcionario->nome,
                        "cpf"  => $funcionario->cpf
                    ]
                ]
            ];
        }

        $retorno = $this->rabbitMq($folhaFuncionario);

        return response()->json($retorno, 201);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function rabbitMq($folhaFuncionario)
    {

        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('folha', false, false, false, false);

        $msg = new AMQPMessage(json_encode($folhaFuncionario));
        $channel->basic_publish($msg, '', 'folha');

        echo "Folha enviada para a mensageria!";

        $channel->close();
        $connection->close();
    }
}
