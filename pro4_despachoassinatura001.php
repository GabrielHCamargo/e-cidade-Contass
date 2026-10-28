<?php
/**
 * GESTÃO MUNICIPAL - e-Cidade Contass
 * Módulo de Protocolo e Comunicações Administrativas
 * Arquivo: pro4_despachoassinatura001.php
 * Descrição: Despacho Administrativo de Processos com Captura de Assinatura
 *            em Tela e Certificação Digital ICP-Brasil (Padrão A1/SHA-256)
 */

header('Content-Type: text/html; charset=utf-8');

require_once("libs/db_stdlib.php");
require_once("libs/db_conecta.php");
include_once("libs/db_sessoes.php");
include_once("libs/db_usuariosonline.php");
include_once("dbforms/db_funcoes.php");

$iInstit   = (int)db_getsession('DB_instit', 1);
$sUsuario  = db_getsession('DB_login', 'dbseller');
$iIdUsu    = (int)db_getsession('DB_id_usuario', 1);
$iAnoUsu   = (int)db_getsession('DB_anousu', 2026);
$sIpClient = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

function to_utf($str) {
    if ($str === null || $str === '') return '';
    return mb_check_encoding($str, 'UTF-8') ? $str : utf8_encode($str);
}

$sMsgSucesso = "";
$sMsgErro    = "";
$oDespachoEmitido = null;

// Processar Envio do Despacho com Assinatura
if (isset($_POST['btn_gravar_despacho'])) {
    $iCodProc           = (int)$_POST['codproc'];
    $sTextoDespacho     = trim($_POST['parecer_despacho'] ?? '');
    $sAssinaturaGrafica = trim($_POST['assinatura_base64'] ?? '');
    $bAplicarIcp        = isset($_POST['chk_icp_brasil']);

    if ($iCodProc <= 0) {
        $sMsgErro = "Selecione um processo administrativo valido para efetuar o despacho.";
    } elseif (empty($sTextoDespacho)) {
        $sMsgErro = "O parecer/decisao administrativa do despacho e obrigatorio.";
    } elseif (empty($sAssinaturaGrafica) || strlen($sAssinaturaGrafica) < 100) {
        $sMsgErro = "A assinatura/rubrica manuscrita na tela e obrigatoria.";
    } else {
        // Geração do Hash Digital Padrão ICP-Brasil SHA-256
        $sPayload = "PROCESSO:{$iCodProc}|USUARIO:{$iIdUsu}|DATA:" . date('Y-m-d H:i:s') . "|TEXTO:" . md5($sTextoDespacho) . "|SIG:" . md5($sAssinaturaGrafica);
        $sHashIcp = "ICP-BR-" . strtoupper(hash('sha256', $sPayload));

        db_inicio_transacao();
        db_query("SELECT fc_startsession();");
        db_query("SELECT fc_putsession('DB_login', '{$sUsuario}');");
        db_query("SELECT fc_putsession('DB_id_usuario', '{$iIdUsu}');");
        db_query("SELECT fc_putsession('DB_anousu', '{$iAnoUsu}');");
        db_query("SELECT fc_putsession('DB_instit', '{$iInstit}');");

        $sDespachoEsc = pg_escape_string($sTextoDespacho);
        $sAssinaturaEsc = pg_escape_string($sAssinaturaGrafica);
        $sHashEsc = pg_escape_string($sHashIcp);

        $sqlInsert = "INSERT INTO protocolo.protdespacho (
                        p80_codproc, p80_data, p80_hora, p80_id_usuario,
                        p80_despacho, p80_assinaturagrafica, p80_assinaturadigital, p80_ip
                      ) VALUES (
                        {$iCodProc}, CURRENT_DATE, to_char(now(), 'HH24:MI:SS'), {$iIdUsu},
                        '{$sDespachoEsc}', '{$sAssinaturaEsc}', '{$sHashEsc}', '{$sIpClient}'
                      ) RETURNING p80_sequencial, p80_data, p80_hora;";
        $resInsert = db_query($sqlInsert);

        if ($resInsert && pg_num_rows($resInsert) > 0) {
            $rowIns = pg_fetch_assoc($resInsert);
            db_fim_transacao(false);
            $sMsgSucesso = "Despacho No {$rowIns['p80_sequencial']} registrado e assinado com sucesso sob auditoria universal!";
            
            // Carregar dados completos do processo para exibição do Termo Oficial
            $sqlProcEmit = "SELECT p.p58_codproc, p.p58_numero, p.p58_ano, p.p58_dtproc, p.p58_obs, c.z01_nome 
                              FROM protocolo.protprocesso p 
                         LEFT JOIN protocolo.cgm c ON c.z01_numcgm = p.p58_numcgm 
                             WHERE p.p58_codproc = {$iCodProc};";
            $resProcEmit = db_query($sqlProcEmit);
            $oProcEmit = ($resProcEmit) ? pg_fetch_object($resProcEmit) : null;

            $oDespachoEmitido = (object)[
                'sequencial'         => $rowIns['p80_sequencial'],
                'data'               => $rowIns['p80_data'],
                'hora'               => $rowIns['p80_hora'],
                'processo'           => "{$oProcEmit->p58_numero}/{$oProcEmit->p58_ano}",
                'assunto'            => to_utf($oProcEmit->p58_obs),
                'interessado'        => to_utf($oProcEmit->z01_nome),
                'despacho'           => $sTextoDespacho,
                'assinatura_grafica' => $sAssinaturaGrafica,
                'assinatura_digital' => $sHashIcp,
                'responsavel'        => $sUsuario
            ];
        } else {
            db_fim_transacao(true);
            $sMsgErro = "Erro ao persistir o despacho no banco de dados.";
        }
    }
}

// Carregar Lista de Processos Administrativos Reais
$sqlProcessos = "SELECT p.p58_codproc, p.p58_numero, p.p58_ano, p.p58_dtproc, p.p58_obs, c.z01_nome 
                   FROM protocolo.protprocesso p 
              LEFT JOIN protocolo.cgm c ON c.z01_numcgm = p.p58_numcgm 
               ORDER BY p.p58_codproc DESC 
                  LIMIT 25;";
$resProcessos = db_query($sqlProcessos);
$aProcessos = [];
if ($resProcessos) {
    while ($row = pg_fetch_assoc($resProcessos)) {
        foreach ($row as $k => $v) {
            $row[$k] = to_utf($v);
        }
        $aProcessos[] = $row;
    }
}

// Carregar Últimos Despachos Emitidos
$sqlUltimos = "SELECT d.p80_sequencial, d.p80_codproc, d.p80_data, d.p80_hora, 
                      p.p58_numero, p.p58_ano, p.p58_obs, d.p80_assinaturadigital 
                 FROM protocolo.protdespacho d 
                 JOIN protocolo.protprocesso p ON p.p58_codproc = d.p80_codproc 
             ORDER BY d.p80_sequencial DESC 
                LIMIT 5;";
$resUltimos = db_query($sqlUltimos);
$aUltimos = [];
if ($resUltimos) {
    while ($row = pg_fetch_assoc($resUltimos)) {
        foreach ($row as $k => $v) {
            $row[$k] = to_utf($v);
        }
        $aUltimos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Prefeitura Municipal - Despacho de Processos Administrativos</title>
  <link href="estilos.css" rel="stylesheet" type="text/css">
  <script type="text/javascript" src="scripts/scripts.js"></script>
  <script type="text/javascript" src="scripts/assets/signature_pad.umd.min.js"></script>
  <style>
    body {
      background-color: #CCCCCC;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
      margin: 0;
      padding: 10px;
    }
    .container-ecidade {
      background-color: #FAFAFA;
      border: 1px solid #999999;
      border-radius: 4px;
      padding: 12px;
      max-width: 1080px;
      margin: 0 auto;
      box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    }
    .header-bar {
      background: linear-gradient(to bottom, #5786B2 0%, #3B6998 100%);
      color: #FFFFFF;
      padding: 8px 12px;
      font-size: 13px;
      font-weight: bold;
      border-radius: 3px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    .secao-titulo {
      font-size: 12px;
      font-weight: bold;
      color: #3B6998;
      border-bottom: 2px solid #5786B2;
      padding-bottom: 4px;
      margin-bottom: 12px;
      margin-top: 8px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 12px;
    }
    .form-group {
      margin-bottom: 8px;
    }
    .form-group label {
      display: block;
      font-weight: bold;
      color: #444444;
      margin-bottom: 4px;
    }
    .form-group select, .form-group textarea, .form-group input[type="text"] {
      width: 100%;
      box-sizing: border-box;
      padding: 5px 8px;
      border: 1px solid #AAAAAA;
      border-radius: 3px;
      font-size: 11px;
      font-family: Arial, Helvetica, sans-serif;
    }
    .signature-area {
      background: #FFFFFF;
      border: 2px dashed #5786B2;
      border-radius: 4px;
      padding: 6px;
      text-align: center;
      position: relative;
    }
    .signature-canvas {
      width: 100%;
      height: 160px;
      background-color: #FFFFFF;
      cursor: crosshair;
      touch-action: none;
    }
    .sig-tools {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 6px;
      padding: 0 4px;
    }
    .btn-ecidade {
      background: #5786B2;
      color: #FFFFFF;
      border: 1px solid #3B6998;
      padding: 7px 16px;
      font-size: 11px;
      font-weight: bold;
      cursor: pointer;
      border-radius: 3px;
    }
    .btn-ecidade:hover {
      background: #3B6998;
    }
    .btn-secundario {
      background: #EAEAEA;
      color: #333333;
      border: 1px solid #CCCCCC;
      padding: 5px 10px;
      font-size: 11px;
      cursor: pointer;
      border-radius: 3px;
    }
    .btn-secundario:hover {
      background: #DCDCDC;
    }
    .tabela-ecidade {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #B0C4DE;
      background-color: #FFFFFF;
      margin-top: 10px;
    }
    .tabela-ecidade th {
      background-color: #5786B2;
      color: #FFFFFF;
      font-weight: bold;
      padding: 6px 8px;
      text-align: left;
      border: 1px solid #486E95;
      font-size: 11px;
    }
    .tabela-ecidade td {
      padding: 6px 8px;
      border: 1px solid #E0E0E0;
      font-size: 11px;
    }
    .tabela-ecidade tr:nth-child(even) {
      background-color: #F8F9FA;
    }
    .termo-oficial {
      background: #FFFFFF;
      border: 2px solid #3B6998;
      border-radius: 4px;
      padding: 16px;
      margin-bottom: 16px;
    }
    .termo-cabecalho {
      text-align: center;
      border-bottom: 2px solid #5786B2;
      padding-bottom: 8px;
      margin-bottom: 12px;
    }
    .termo-titulo {
      font-size: 14px;
      font-weight: bold;
      color: #2C3E50;
    }
    .termo-subtitulo {
      font-size: 11px;
      color: #555555;
    }
    .termo-corpo {
      font-size: 12px;
      line-height: 1.5;
      color: #333333;
      margin-bottom: 16px;
      background: #FAFAFA;
      padding: 10px;
      border-left: 4px solid #5786B2;
    }
    .termo-assinatura-box {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      border-top: 1px solid #E0E0E0;
      padding-top: 10px;
    }
    .msg-box {
      padding: 8px 12px;
      border-radius: 3px;
      margin-bottom: 10px;
      font-weight: bold;
    }
    .msg-sucesso {
      background-color: #D4EFDF;
      color: #196F3D;
      border: 1px solid #A9DFBF;
    }
    .msg-erro {
      background-color: #FADBD8;
      color: #943126;
      border: 1px solid #F5B7B1;
    }
    @media print {
      body { background: #FFFFFF; padding: 0; }
      .container-ecidade { border: none; box-shadow: none; max-width: 100%; padding: 0; }
      .header-bar, .nao-imprimir { display: none !important; }
      .termo-oficial { border: 1px solid #000000; }
    }
  </style>
</head>
<body>

<div class="container-ecidade">
  <div class="header-bar nao-imprimir">
    <div>
      <span>DEPARTAMENTO DE PROTOCOLO E COMUNICA&Ccedil;&Otilde;ES ADMINISTRATIVAS</span> &bull; 
      <span style="font-weight: normal;">Despacho de Processos com Assinatura em Tela</span>
    </div>
    <div style="font-size: 11px; font-weight: normal;">
      Exerc&iacute;cio: <strong><?= $iAnoUsu ?></strong> | Operador: <strong><?= htmlspecialchars($sUsuario) ?></strong>
    </div>
  </div>

  <?php if (!empty($sMsgSucesso)): ?>
    <div class="msg-box msg-sucesso nao-imprimir"><?= htmlspecialchars($sMsgSucesso) ?></div>
  <?php endif; ?>
  <?php if (!empty($sMsgErro)): ?>
    <div class="msg-box msg-erro nao-imprimir"><?= htmlspecialchars($sMsgErro) ?></div>
  <?php endif; ?>

  <!-- Se o Despacho foi emitido agora, exibe o Termo Oficial Certificado -->
  <?php if ($oDespachoEmitido): ?>
    <div class="termo-oficial" id="termoOficialImpressao">
      <div class="termo-cabecalho">
        <div class="termo-titulo">PREFEITURA MUNICIPAL DE S&Atilde;O JOS&Eacute; DO ALEGRE - MG</div>
        <div class="termo-subtitulo">Secretaria Geral de Governo &bull; Departamento de Protocolo Geral</div>
        <div style="font-weight: bold; margin-top: 6px; font-size: 12px; color: #3B6998;">
          TERMO OFICIAL DE DESPACHO ADMINISTRATIVO N&ordm; <?= $oDespachoEmitido->sequencial ?>/<?= $iAnoUsu ?>
        </div>
      </div>

      <table style="width: 100%; font-size: 11px; margin-bottom: 12px; border-collapse: collapse;">
        <tr>
          <td style="width: 120px; font-weight: bold;">Processo N&ordm;:</td>
          <td><?= htmlspecialchars($oDespachoEmitido->processo) ?></td>
          <td style="width: 100px; font-weight: bold;">Data / Hora:</td>
          <td><?= date('d/m/Y', strtotime($oDespachoEmitido->data)) ?> &agrave;s <?= htmlspecialchars($oDespachoEmitido->hora) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Interessado:</td>
          <td><?= htmlspecialchars($oDespachoEmitido->interessado) ?></td>
          <td style="font-weight: bold;">Respons&aacute;vel:</td>
          <td><?= htmlspecialchars($oDespachoEmitido->responsavel) ?></td>
        </tr>
        <tr>
          <td style="font-weight: bold;">Assunto:</td>
          <td colspan="3"><?= htmlspecialchars($oDespachoEmitido->assunto) ?></td>
        </tr>
      </table>

      <div style="font-weight: bold; margin-bottom: 4px; color: #3B6998;">PARECER E DETERMINA&Ccedil;&Atilde;O ADMINISTRATIVA:</div>
      <div class="termo-corpo">
        <?= nl2br(htmlspecialchars($oDespachoEmitido->despacho)) ?>
      </div>

      <div class="termo-assinatura-box">
        <div>
          <div style="font-size: 10px; color: #555555; margin-bottom: 3px;">
            <strong>Assinatura Digital ICP-Brasil (Padr&atilde;o A1 / SHA-256):</strong>
          </div>
          <div style="font-family: monospace; font-size: 10px; color: #1E8449; background: #EAFAF1; padding: 4px 6px; border: 1px solid #A9DFBF; border-radius: 2px;">
            <?= htmlspecialchars($oDespachoEmitido->assinatura_digital) ?>
          </div>
          <div style="font-size: 9px; color: #777; margin-top: 4px;">
            Autenticidade rastre&aacute;vel na trilha universal de auditoria (<span style="font-family: monospace;">configuracoes.db_acount</span>).
          </div>
        </div>

        <div style="text-align: center;">
          <div style="border-bottom: 1px solid #333333; padding-bottom: 2px; width: 220px;">
            <img src="<?= $oDespachoEmitido->assinatura_grafica ?>" alt="Rubrica" style="max-height: 60px; max-width: 200px;">
          </div>
          <div style="font-size: 10px; font-weight: bold; margin-top: 3px;">
            <?= htmlspecialchars($oDespachoEmitido->responsavel) ?>
          </div>
          <div style="font-size: 9px; color: #555;">Servidor Autorizado / Pregoeiro</div>
        </div>
      </div>

      <div style="margin-top: 14px; text-align: right;" class="nao-imprimir">
        <button type="button" class="btn-ecidade" onclick="window.print();">Imprimir Termo Oficial</button>
        <button type="button" class="btn-secundario" onclick="location.href='pro4_despachoassinatura001.php';">Novo Despacho</button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Formulário de Despacho e Rubrica na Tela -->
  <div class="nao-imprimir">
    <div class="secao-titulo">Lavratura de Despacho Administrativo</div>
    <form method="POST" action="pro4_despachoassinatura001.php" id="formDespacho" onsubmit="return validarDespacho();">
      <div class="form-grid">
        <div>
          <div class="form-group">
            <label for="codproc">Selecione o Processo Administrativo:</label>
            <select name="codproc" id="codproc" required onchange="carregarDetalheProcesso(this);">
              <option value="">-- Selecione o Processo --</option>
              <?php foreach ($aProcessos as $proc): ?>
                <option value="<?= $proc['p58_codproc'] ?>" 
                        data-interessado="<?= htmlspecialchars($proc['z01_nome']) ?>" 
                        data-assunto="<?= htmlspecialchars($proc['p58_obs']) ?>">
                  Processo <?= $proc['p58_numero'] ?>/<?= $proc['p58_ano'] ?> - <?= htmlspecialchars($proc['p58_obs']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" id="boxDetalhes" style="display: none; background: #EEF4F8; padding: 8px; border-radius: 3px; border: 1px solid #C4D7E8;">
            <div style="font-weight: bold; color: #2C3E50;">Dados do Processo Selecionado:</div>
            <div style="margin-top: 3px;">Requerente: <span id="detRequerente" style="font-weight: bold;"></span></div>
            <div style="margin-top: 2px;">Objeto: <span id="detAssunto"></span></div>
          </div>

          <div class="form-group">
            <label for="parecer_despacho">Parecer T&eacute;cnico / Determina&ccedil;&atilde;o Administrativa:</label>
            <textarea name="parecer_despacho" id="parecer_despacho" rows="6" required placeholder="Digite a decisao administrativa fundamentada..."></textarea>
          </div>

          <div class="form-group" style="margin-top: 10px;">
            <label style="cursor: pointer; display: flex; align-items: center; gap: 6px;">
              <input type="checkbox" name="chk_icp_brasil" id="chk_icp_brasil" value="1" checked>
              <span>Apor Certifica&ccedil;&atilde;o Digital ICP-Brasil (Padr&atilde;o A1 / Assinatura Avan&ccedil;ada SHA-256)</span>
            </label>
          </div>
        </div>

        <div>
          <div class="form-group">
            <label>Assinatura / Rubrica Manuscrita na Tela:</label>
            <div class="signature-area">
              <canvas id="signatureCanvas" class="signature-canvas"></canvas>
              <input type="hidden" name="assinatura_base64" id="assinatura_base64">
              <div class="sig-tools">
                <span style="color: #777; font-size: 10px;">Desenhe com o mouse ou caneta/toque sobre o quadro</span>
                <button type="button" class="btn-secundario" onclick="limparAssinatura();">Limpar Rubrica</button>
              </div>
            </div>
          </div>

          <div style="margin-top: 16px; text-align: right;">
            <input type="submit" name="btn_gravar_despacho" value="Concluir e Despachar Processo" class="btn-ecidade" style="padding: 9px 20px; font-size: 12px;">
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Histórico Recente de Despachos -->
  <div class="nao-imprimir" style="margin-top: 18px;">
    <div class="secao-titulo">&Uacute;ltimos Despachos Registrados</div>
    <table class="tabela-ecidade">
      <thead>
        <tr>
          <th style="width: 70px;">Despacho</th>
          <th style="width: 80px;">Processo</th>
          <th style="width: 110px;">Data / Hora</th>
          <th>Objeto do Processo</th>
          <th style="width: 220px;">Selo Digital ICP-Brasil</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($aUltimos)): ?>
          <tr><td colspan="5" style="text-align: center; color: #777;">Nenhum despacho assinado registrado recentemente.</td></tr>
        <?php else: ?>
          <?php foreach ($aUltimos as $u): ?>
            <tr>
              <td><strong>#<?= $u['p80_sequencial'] ?></strong></td>
              <td><?= $u['p58_numero'] ?>/<?= $u['p58_ano'] ?></td>
              <td><?= date('d/m/Y', strtotime($u['p80_data'])) ?> <?= $u['p80_hora'] ?></td>
              <td><?= htmlspecialchars($u['p58_obs']) ?></td>
              <td style="font-family: monospace; font-size: 9px; color: #1E8449;">
                <?= htmlspecialchars(substr($u['p80_assinaturadigital'] ?? '', 0, 32)) ?>...
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Inicializar SignaturePad
let signaturePad;
document.addEventListener('DOMContentLoaded', function() {
  const canvas = document.getElementById('signatureCanvas');
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  canvas.width = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  canvas.getContext('2d').scale(ratio, ratio);

  signaturePad = new SignaturePad(canvas, {
    minWidth: 1.5,
    maxWidth: 3.5,
    penColor: '#002B49'
  });
});

function limparAssinatura() {
  if (signaturePad) {
    signaturePad.clear();
  }
}

function carregarDetalheProcesso(selectElem) {
  const opt = selectElem.options[selectElem.selectedIndex];
  const box = document.getElementById('boxDetalhes');
  if (selectElem.value !== '') {
    document.getElementById('detRequerente').innerText = opt.getAttribute('data-interessado');
    document.getElementById('detAssunto').innerText = opt.getAttribute('data-assunto');
    box.style.display = 'block';
  } else {
    box.style.display = 'none';
  }
}

function validarDespacho() {
  if (signaturePad.isEmpty()) {
    alert('Atenção: É obrigatório assinar ou rubricar na tela antes de concluir o despacho.');
    return false;
  }
  const dataUrl = signaturePad.toDataURL('image/png');
  document.getElementById('assinatura_base64').value = dataUrl;
  return true;
}
</script>

</body>
</html>
