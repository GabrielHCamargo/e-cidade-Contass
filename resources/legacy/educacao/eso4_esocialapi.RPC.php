<?php
require_once('libs/db_stdlib.php');
require_once('libs/db_utils.php');
require_once('libs/db_app.utils.php');
require_once('libs/db_conecta.php');
require_once('libs/db_sessoes.php');
require_once('dbforms/db_funcoes.php');
require_once('libs/JSON.php');

use \ECidade\V3\Extension\Registry;
use \ECidade\Core\Config as AppConfig;
use ECidade\RecursosHumanos\ESocial\DadosESocial;
use ECidade\RecursosHumanos\ESocial\Model\Formulario\Tipo;
use ECidade\RecursosHumanos\ESocial\Integracao\ESocial;
use ECidade\RecursosHumanos\ESocial\Integracao\Recurso;
use ECidade\RecursosHumanos\ESocial\Integracao\FormatterFactory;
use ECidade\RecursosHumanos\ESocial\Agendamento\Evento;

Registry::set('app.config', new AppConfig());

\ECidade\V3\Extension\Registry::get('app.config')->merge([
    'charset' => 'UTF-8',
    'php.display_errors' => true,
    'php.error_reporting' => E_ALL & ~E_DEPRECATED & ~E_STRICT,
    'app.api' => [
        'centraldeajuda' => 'http://centraldeajuda.dbseller.com.br/help/api/index.php/',
        'esocial' => [
            'url' => 'http://ecidade_sidecar_gov/sped-esocial/',
            'login' => '',
            'password' => ''
        ]
    ],
    'app.proxy' => [],
    'app.request.session' => '*.php',
    'app.request.session.readOnlyOn' => '{skins/*,*.js,*.css}',
    'app.request.asset.cacheable.extension' => ['js', 'css', 'jpg', 'jpeg', 'png', 'bmp', 'ttf', 'gif'],
    'app.error.log' => true,
    'app.error.log.mask' => "{type} - {message} in {file} on line {line}\n{trace}",
    'app.error.log.mask.trace' => "#{index} {file}:{line} - {class}{type}{function}({args})\n",
    'app.events' => ['app.error' => '\ECidade\V3\Error\EventHandler'],
    'app.log.verbosity' => \ECidade\V3\Extension\Logger::ERROR,
    'db.client_encoding' => 'LATIN1',
]);

function formatarDadosPreenchimento($dadosDoPreenchimento, $arquivo)
{
    if (current($dadosDoPreenchimento) instanceof \ECidade\RecursosHumanos\ESocial\Model\Formulario\DadosPreenchimento) {
        $formatter = FormatterFactory::get($arquivo);
        $dadosDoPreenchimento = $formatter->formatar($dadosDoPreenchimento);
    }
    return $dadosDoPreenchimento;
}

function adicionarEventoFila($dadosDoPreenchimento, $arquivo, $iCgm, $oParam)
{
    foreach (array_chunk($dadosDoPreenchimento, 50) as $aTabela) {
        $eventoFila = new Evento(
            $arquivo,
            $iCgm,
            $iCgm,
            $aTabela,
            $oParam->tpAmb,
            "{$oParam->iAnoValidade}-{$oParam->iMesValidade}",
            $oParam->modo,
            $oParam->dtalteracao ?? null,
            $oParam->indapuracao,
            $oParam->tppgto,
            $oParam->tpevento,
            $oParam->transDCTFWeb
        );
        $eventoFila->adicionarFila();
    }
}

function adicionarEventoTabela($dadosDoPreenchimento, $arquivo, $iCgm, $oParam)
{
    foreach (array_chunk($dadosDoPreenchimento, 1) as $aTabela) {
        $eventoFila = new Evento(
            $arquivo,
            $iCgm,
            $iCgm,
            $aTabela,
            $oParam->tpAmb,
            "{$oParam->iAnoValidade}-{$oParam->iMesValidade}",
            $oParam->modo,
            $oParam->dtalteracao ?? null,
            $oParam->indapuracao,
            $oParam->tppgto,
            $oParam->tpevento,
            $oParam->transDCTFWeb,
            $oParam->evtpgtos,
            null,
            $oParam->dtpgto,
        );
        $eventoFila->adicionarFila();
    }
}

function executarFilaEsocial()
{
    ob_start();
    system("php -q filaEsocial.php");
    ob_end_clean();
}

function getTiposEventos()
{
    return [
        Tipo::CADASTRAMENTO_INICIAL,
        Tipo::AFASTAMENTO_TEMPORARIO,
        Tipo::REMUNERACAO_TRABALHADOR,
        Tipo::REMUNERACAO_SERVIDOR,
        Tipo::PAGAMENTOS_RENDIMENTOS,
        Tipo::CADASTRO_BENEFICIO,
        Tipo::DESLIGAMENTO,
        Tipo::CD_BENEF_IN,
        Tipo::BENEFICIOS_ENTESPUBLICOS,
        Tipo::ALTERACAO_CONTRATO,
        Tipo::EXCLUSAO_EVENTOS,
        Tipo::REABERTURA_EVENTOS,
        Tipo::FECHAMENTO_EVENTOS
    ];
}

function getEmpregadores()
{
    $campos = ' distinct z01_numcgm as cgm, z01_cgccpf as documento, nomeinst as nome, codigo as instituicao,
                (select count(*) as certificado from esocialcertificado where rh214_cgm = z01_numcgm) as certificado';
    $oDaoDbConfig = db_utils::getDao("db_config");
    $sql = $oDaoDbConfig->sql_query(null, $campos, 'z01_numcgm', 'codigo = ' . db_getsession("DB_instit"));

    $rs = db_query($sql);

    if (!$rs) {
        throw new DBException("Ocorreu um erro ao consultar os CGM vinculados as lotações.\nContate o suporte.");
    }

    if (pg_num_rows($rs) == 0) {
        throw new Exception("Não existem empregadores cadastrados na base.");
    }

    return db_utils::fieldsMemory($rs, 0);
}

function processarEmpregador($oParam)
{
    if (!file_exists($oParam->sPath)) {
        throw new Exception("Houve um erro ao realizar upload do arquivo. Tente novamente.");
    }

    db_inicio_transacao();
    $oDaoEsocialcertificado = db_utils::getDao("esocialcertificado");
    $oDaoEsocialcertificado->rh214_cgm = $oParam->empregador;
    $oDaoEsocialcertificado->rh214_senha = base64_encode($oParam->senha);
    $oDaoEsocialcertificado->rh214_certificado = base64_encode(file_get_contents($oParam->sPath));
    $oDaoEsocialcertificado->rh214_instit = db_getsession("DB_instit");
    $oDaoEsocialcertificado->save();
    if ($oDaoEsocialcertificado->erro_status == 0) {
        throw new \Exception("Erro ao enviar certificado. " . $oDaoEsocialcertificado->erro_msg);
    }

    db_fim_transacao(false);
    unlink($oParam->sPath);

    return "Certificado enviado com sucesso.";
}

function excluirEventos($oParam)
{
    $dadosESocial = new DadosESocial();

    db_inicio_transacao();
    $iCgm = $oParam->empregador;

    foreach ($oParam->eventosParaExcluir as $evento) {
        $dadosESocial->setReponsavelPeloPreenchimento($iCgm);

        $eventoFila = new Evento(
            "S3000",
            $iCgm,
            $iCgm,
            null,
            $oParam->tpAmb,
            "{$oParam->iAnoValidade}-{$oParam->iMesValidade}",
            $oParam->modo,
            empty($oParam->dtalteracao) ? null : $oParam->dtalteracao,
            $oParam->indapuracao,
            $oParam->tppgto,
            $oParam->tpevento,
            $oParam->transDCTFWeb,
            $oParam->evtpgtos,
            $evento
        );

        $eventoFila->adicionarEventoExclusao();
    }

    db_fim_transacao(false);

    ob_start();
    $response = system("php -q filaEsocial.php");
    ob_end_clean();

    return "Dados agendados para envio.";
}

function transmitirRubricas($oParam)
{
    $dadosESocial = new DadosESocial();
    db_inicio_transacao();

    // Rubricas a serem enviadas
    $seqRubricas = $oParam->rubricas;
    $explode_seq = explode(',', $seqRubricas);
    $aRubricas = array();
    foreach ($explode_seq as $rub) {
        $aRubricas[] = "'" . $rub . "'";
    }
    $stringRubricas = implode(",", $aRubricas);

    $iCgm = $oParam->empregador;
    $dadosESocial->setReponsavelPeloPreenchimento($iCgm);
    $dadosDoPreenchimento = $dadosESocial->getPorTipo(Tipo::RUBRICA, $stringRubricas);
    foreach (array_chunk($dadosDoPreenchimento, 1) as $aTabela) {
        $eventoFila = new Evento(
            current($oParam->arquivos),
            $iCgm,
            $iCgm,
            $aTabela,
            $oParam->tpAmb,
            "{$oParam->iAnoValidade}-{$oParam->iMesValidade}",
            $oParam->modo,
            $oParam->dtalteracao,
            "{$oParam->iAnoValidade}-{$oParam->iMesValidade}",
        );
        $eventoFila->adicionarFila();
    }

    db_fim_transacao(false);

    ob_start();
    $response = system("php -q filaEsocial.php");
    ob_end_clean();

    return "Dados das Rubricas agendados para envio.";
}

$oJson = new services_json();
$oParam = JSON::create()->parse(str_replace('\\', "", $_POST["json"]));
$oRetorno = new stdClass();
$oRetorno->iStatus = 1;
$oRetorno->sMessage = '';

try {
    @db_query('SELECT fc_startsession();');

    switch ($oParam->exec) {

        case "getEmpregadores":
            $oRetorno->empregador = getEmpregadores();
            break;

        case "empregador":
            $oRetorno->sMessage = processarEmpregador($oParam);
            break;

        case "transmitir":
            $dadosESocial = new DadosESocial();
            db_inicio_transacao();
            $iCgm = $oParam->empregador;

            foreach ($oParam->arquivos as $arquivo) {
                $tipoFormulario = Tipo::getTipoFormulario($arquivo);
                $dadosESocial->setReponsavelPeloPreenchimento($iCgm);

                $dadosDoPreenchimento = $dadosESocial->getPorTipo(
                    $tipoFormulario,
                    $oParam->matricula ?? null,
                    $oParam->cgm ?? null,
                    $oParam->tpevento ?? null,
                    "{$oParam->iAnoValidade}-{$oParam->iMesValidade}",
                    $oParam->dtpgto ?? null
                );

                if (!in_array($tipoFormulario, getTiposEventos())) {
                    $dadosDoPreenchimento = formatarDadosPreenchimento($dadosDoPreenchimento, $arquivo);
                    adicionarEventoFila($dadosDoPreenchimento, $arquivo, $iCgm, $oParam);
                } else {
                    adicionarEventoTabela($dadosDoPreenchimento, $arquivo, $iCgm, $oParam);
                }
            }

            db_fim_transacao(false);
            executarFilaEsocial();

            $oRetorno->sMessage = "Dados agendados para envio.";
            break;

        case "excluir":
            $oRetorno->sMessage = excluirEventos($oParam);
            break;

        case "consultar":
            $clesocialenvio = db_utils::getDao("esocialenvio");
            $oRetorno->lUpdate = $clesocialenvio->checkQueue();
            break;

        case "transmitirrubricas":
            $oRetorno->sMessage = transmitirRubricas($oParam);
            break;

        case "apagarErros":
            $clesocialenvio = db_utils::getDao("esocialenvio");
            $oRetorno->lUpdate = $clesocialenvio->deleteErros();
            break;
    }
} catch (Exception $eErro) {
    if (db_utils::inTransaction()) {
        db_fim_transacao(true);
    }
    $oRetorno->iStatus  = 2;
    $oRetorno->sMessage = $eErro->getMessage();
}

$oRetorno->erro = $oRetorno->iStatus == 2;
echo JSON::create()->stringify($oRetorno);
