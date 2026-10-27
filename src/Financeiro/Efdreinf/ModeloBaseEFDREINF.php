<?php

namespace ECidade\Financeiro\Efdreinf;

use funcao;
use stdClass;

/**
 * Classe base para EFDREINF
 *
 * @package  EFDREINF
 * @author   Dayvison Nunes
 */
abstract class ModeloBaseEFDREINF
{

       //constants
    const SSL_DEFAULT = 0; //default
    const SSL_TLSV1 = 1; //TLSv1
    const SSL_SSLV2 = 2; //SSLv2
    const SSL_SSLV3 = 3; //SSLv3
    const SSL_TLSV1_0 = 4; //TLSv1.0
    const SSL_TLSV1_1 = 5; //TLSv1.1
    const SSL_TLSV1_2 = 6; //TLSv1.2
    /**
     * Dados
     * @var \stdClass
     */
    protected $dados;

    protected $dadosCGM;

    protected $cgc;

    protected $url;

    protected $envs;
   
    /**
     *
     * @param \stdClass $dados
     */
    public function __construct($dados,$dadosCGM,$cgc)
    {
        $this->dados    = $dados;
        $this->dadosCGM = $dadosCGM;
        $this->cgc      = $cgc;
        // Redirecionamento autônomo para o microserviço Sidecar local (PHP 8.2)
        $this->url     = "http://ecidade_sidecar_gov/sped-efdreinf/";
        
    }

     /**
     * Retorna dados no formato necessario para envio
     * @return array stdClass
     */
    abstract public function montarDadosReinfR4099();

}
