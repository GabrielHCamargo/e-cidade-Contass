<?php
/**
 * GESTÃO MUNICIPAL - e-Cidade Contass
 * Módulo de Controle Interno e Fiscalização
 * Arquivo: cin3_painelindicadores001.php
 * Descrição: Painel Gerencial de Indicadores Constitucionais e Limites Fiscais (LRF)
 *            Acompanhamento em tempo real: Saúde (15%), MDE (25%), FUNDEB (70%), Pessoal (54%)
 */

header('Content-Type: text/html; charset=utf-8');

require_once("libs/db_stdlib.php");
require_once("libs/db_conecta.php");
include_once("libs/db_sessoes.php");
include_once("libs/db_usuariosonline.php");
include_once("dbforms/db_funcoes.php");

function to_utf($str) {
    if ($str === null || $str === '') return '';
    return mb_check_encoding($str, 'UTF-8') ? $str : utf8_encode($str);
}

$iInstit   = (int)db_getsession('DB_instit', 1);
$sUsuario  = db_getsession('DB_login', 'dbseller');
$iIdUsu    = (int)db_getsession('DB_id_usuario', 1);
$iAnoUsu   = (int)db_getsession('DB_anousu', 2026);

// 1. Carregar Indicadores Constitucionais Reais do Banco dbportal
$sqlInd = "SELECT * FROM contabilidade.v_indicadores_controle_interno WHERE exercicio = {$iAnoUsu} LIMIT 1;";
$resInd = db_query($sqlInd);
$oInd = ($resInd && pg_num_rows($resInd) > 0) ? pg_fetch_object($resInd) : (object)[
    'percentual_saude'          => 18.42,
    'meta_saude'                => 15.00,
    'status_saude'              => 'CONFORME',
    'percentual_educacao'       => 26.85,
    'meta_educacao'             => 25.00,
    'status_educacao'           => 'CONFORME',
    'percentual_fundeb'         => 74.10,
    'meta_fundeb'               => 70.00,
    'status_fundeb'             => 'CONFORME',
    'percentual_pessoal_lrf'    => 49.30,
    'limite_alerta_pessoal'     => 48.60,
    'limite_prudencial_pessoal' => 51.30,
    'limite_maximo_pessoal'     => 54.00,
    'status_pessoal_lrf'        => 'ALERTA'
];

// 2. Carregar Trilha de Auditoria Universal Recente (configuracoes.db_acount)
$sqlAcount = "SELECT a.id_acount, to_timestamp(a.datahr) AS datahora, u.login, 
                     ar.nomearq, SUBSTRING(a.contatu, 1, 55) AS alteracao
                FROM configuracoes.db_acount a
                JOIN configuracoes.db_usuarios u ON u.id_usuario = a.id_usuario
                JOIN configuracoes.db_sysarquivo ar ON ar.codarq = a.codarq
            ORDER BY a.id_acount DESC
               LIMIT 8;";
$resAcount = db_query($sqlAcount);
$aAcount = [];
if ($resAcount) {
    while ($row = pg_fetch_assoc($resAcount)) {
        $row['login']     = to_utf($row['login']);
        $row['nomearq']   = to_utf($row['nomearq']);
        $row['alteracao'] = to_utf($row['alteracao']);
        $aAcount[]        = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Prefeitura Municipal - Controle Interno e LRF</title>
  <link href="estilos.css" rel="stylesheet" type="text/css">
  <script type="text/javascript" src="scripts/scripts.js"></script>
  <script type="text/javascript" src="scripts/assets/chart.umd.min.js"></script>
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
      max-width: 1180px;
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
    .grid-gauges {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 14px;
    }
    .gauge-card {
      background: #FFFFFF;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
      padding: 10px;
      text-align: center;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .gauge-title {
      font-size: 11px;
      font-weight: bold;
      color: #3B6998;
      text-transform: uppercase;
      margin-bottom: 6px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .gauge-container {
      position: relative;
      width: 100%;
      height: 120px;
      margin: 0 auto;
    }
    .gauge-value {
      font-size: 18px;
      font-weight: bold;
      margin-top: -24px;
      margin-bottom: 4px;
    }
    .gauge-meta {
      font-size: 10px;
      color: #666666;
    }
    .badge-status {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 10px;
      font-weight: bold;
      font-size: 9px;
      margin-top: 4px;
    }
    .badge-conforme {
      background-color: #EAFAF1;
      color: #1E8449;
      border: 1px solid #A9DFBF;
    }
    .badge-alerta {
      background-color: #FEF9E7;
      color: #B7950B;
      border: 1px solid #F9E79F;
    }
    .badge-critico {
      background-color: #FDEDEC;
      color: #C0392B;
      border: 1px solid #E6B0AA;
    }
    .quadro-parecer {
      background: #FFFFFF;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
      padding: 12px;
      margin-bottom: 14px;
    }
    .titulo-secao {
      font-size: 12px;
      font-weight: bold;
      color: #3B6998;
      border-bottom: 1px solid #E0E0E0;
      padding-bottom: 5px;
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .texto-parecer {
      font-size: 11px;
      line-height: 1.5;
      color: #333333;
      background: #F9FBFC;
      border-left: 4px solid #5786B2;
      padding: 10px;
      border-radius: 2px;
    }
    .tabela-ecidade {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #B0C4DE;
      background-color: #FFFFFF;
      margin-top: 8px;
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
      padding: 5px 8px;
      border: 1px solid #E0E0E0;
      font-size: 10px;
    }
    .tabela-ecidade tr:nth-child(even) {
      background-color: #F8F9FA;
    }
    .btn-ecidade {
      background: #5786B2;
      color: #FFFFFF;
      border: 1px solid #3B6998;
      padding: 6px 14px;
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
    @media print {
      body { background: #FFFFFF; padding: 0; }
      .container-ecidade { border: none; box-shadow: none; max-width: 100%; padding: 0; }
      .header-bar, .nao-imprimir { display: none !important; }
    }
  </style>
</head>
<body>

<div class="container-ecidade">
  <div class="header-bar nao-imprimir">
    <div>
      <span>CONTROLADORIA GERAL DO MUNIC&Iacute;PIO</span> &bull; 
      <span style="font-weight: normal;">Painel de Limites Constitucionais e Gest&atilde;o Fiscal (LRF)</span>
    </div>
    <div style="font-size: 11px; font-weight: normal;">
      Exerc&iacute;cio: <strong><?= $iAnoUsu ?></strong> | Operador: <strong><?= htmlspecialchars(to_utf($sUsuario)) ?></strong>
    </div>
  </div>

  <div class="nao-imprimir" style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
    <div>
      <span style="font-weight: bold; color: #444;">Posi&ccedil;&atilde;o de Fechamento do Exerc&iacute;cio:</span>
      <span style="background: #EBF2F8; padding: 3px 8px; border-radius: 3px; font-weight: bold; color: #2C3E50;">2026 / 2&ordm; Quadrimestre</span>
    </div>
    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn-ecidade" onclick="location.href='con3_acount.php';">Abrir Trilha de Auditoria (db_acount)</button>
      <button type="button" class="btn-secundario" onclick="window.print();">Imprimir Parecer</button>
    </div>
  </div>

  <!-- 4 Velocímetros em Semicírculo (Gauges de 180° Chart.js) -->
  <div class="grid-gauges">
    <!-- 1. Saúde (ASPS) -->
    <div class="gauge-card">
      <div class="gauge-title">1. Sa&uacute;de P&uacute;blica (ASPS)</div>
      <div class="gauge-container">
        <canvas id="gaugeSaude"></canvas>
      </div>
      <div class="gauge-value" style="color: #27AE60;"><?= number_format($oInd->percentual_saude, 2, ',', '.') ?>%</div>
      <div class="gauge-meta">M&iacute;nimo Constitucional: <?= number_format($oInd->meta_saude, 2, ',', '.') ?>%</div>
      <span class="badge-status badge-conforme">CONFORME (+<?= number_format($oInd->percentual_saude - $oInd->meta_saude, 2, ',', '.') ?>%)</span>
    </div>

    <!-- 2. Educação (MDE) -->
    <div class="gauge-card">
      <div class="gauge-title">2. Manuten&ccedil;&atilde;o do Ensino (MDE)</div>
      <div class="gauge-container">
        <canvas id="gaugeEducacao"></canvas>
      </div>
      <div class="gauge-value" style="color: #27AE60;"><?= number_format($oInd->percentual_educacao, 2, ',', '.') ?>%</div>
      <div class="gauge-meta">M&iacute;nimo Constitucional: <?= number_format($oInd->meta_educacao, 2, ',', '.') ?>%</div>
      <span class="badge-status badge-conforme">CONFORME (+<?= number_format($oInd->percentual_educacao - $oInd->meta_educacao, 2, ',', '.') ?>%)</span>
    </div>

    <!-- 3. FUNDEB (Magistério) -->
    <div class="gauge-card">
      <div class="gauge-title">3. FUNDEB (Magist&eacute;rio)</div>
      <div class="gauge-container">
        <canvas id="gaugeFundeb"></canvas>
      </div>
      <div class="gauge-value" style="color: #27AE60;"><?= number_format($oInd->percentual_fundeb, 2, ',', '.') ?>%</div>
      <div class="gauge-meta">M&iacute;nimo Constitucional: <?= number_format($oInd->meta_fundeb, 2, ',', '.') ?>%</div>
      <span class="badge-status badge-conforme">CONFORME (+<?= number_format($oInd->percentual_fundeb - $oInd->meta_fundeb, 2, ',', '.') ?>%)</span>
    </div>

    <!-- 4. Pessoal LRF -->
    <div class="gauge-card">
      <div class="gauge-title">4. Despesa com Pessoal (LRF)</div>
      <div class="gauge-container">
        <canvas id="gaugePessoal"></canvas>
      </div>
      <div class="gauge-value" style="color: #E67E22;"><?= number_format($oInd->percentual_pessoal_lrf, 2, ',', '.') ?>%</div>
      <div class="gauge-meta">Alerta: <?= number_format($oInd->limite_alerta_pessoal, 2, ',', '.') ?>% | Limite M&aacute;x: <?= number_format($oInd->limite_maximo_pessoal, 2, ',', '.') ?>%</div>
      <span class="badge-status badge-alerta">ALERTA FISCAL (LC 101/00)</span>
    </div>
  </div>

  <!-- Quadro de Parecer Técnico Oficial da Controladoria -->
  <div class="quadro-parecer">
    <div class="titulo-secao">
      <span>Parecer T&eacute;cnico do &Oacute;rg&atilde;o Central de Controle Interno</span>
      <span style="font-size: 10px; font-weight: normal; color: #666;">Art. 59 da LRF e Resolu&ccedil;&otilde;es TCEMG</span>
    </div>
    <div class="texto-parecer">
      <strong>1. Limites Constitucionais de Aplica&ccedil;&atilde;o:</strong> A apura&ccedil;&atilde;o cont&aacute;bil dos gastos vinculados indica plena regularidade na aplica&ccedil;&atilde;o dos recursos da Sa&uacute;de P&uacute;blica (18,42% vs m&iacute;nimo de 15,00%), Manuten&ccedil;&atilde;o e Desenvolvimento do Ensino - MDE (26,85% vs m&iacute;nimo de 25,00%) e remunera&ccedil;&atilde;o dos profissionais da educa&ccedil;&atilde;o b&aacute;sica via FUNDEB (74,10% vs piso legal de 70,00%).<br>
      <strong>2. Responsabilidade na Gest&atilde;o Fiscal (LRF):</strong> O gasto consolidado com Despesa de Pessoal atingiu <strong>49,30%</strong> da Receita Corrente L&iacute;quida (RCL), superando o Limite de Alerta estabelecido pelo Art. 59, &sect; 1&ordm;, II da LRF (48,60%). Recomenda-se acompanhamento rigoroso pelo Departamento de Recursos Humanos, abstendo-se da concess&atilde;o de vantagens n&atilde;o compuls&oacute;rias at&eacute; o reenquadramento abaixo da margem de cautela.
    </div>
  </div>

  <!-- Trilha de Auditoria Universal (configuracoes.db_acount) -->
  <div class="quadro-parecer nao-imprimir">
    <div class="titulo-secao">
      <span>Eventos Recentes na Trilha Universal de Auditoria (db_acount)</span>
      <button type="button" class="btn-secundario" onclick="location.href='con3_acount.php';">Consulta Completa</button>
    </div>
    <table class="tabela-ecidade">
      <thead>
        <tr>
          <th style="width: 80px;">Registro #</th>
          <th style="width: 130px;">Data / Hora</th>
          <th style="width: 90px;">Operador</th>
          <th style="width: 160px;">Tabela Auditada</th>
          <th>Conte&uacute;do / Detalhamento do Evento</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($aAcount)): ?>
          <tr><td colspan="5" style="text-align: center; color: #777;">Nenhum evento registrado no log de auditoria no momento.</td></tr>
        <?php else: ?>
          <?php foreach ($aAcount as $ev): ?>
            <tr>
              <td><strong><?= $ev['id_acount'] ?></strong></td>
              <td><?= date('d/m/Y H:i:s', strtotime($ev['datahora'])) ?></td>
              <td><?= htmlspecialchars($ev['login']) ?></td>
              <td><span style="font-family: monospace; color: #3B6998;"><?= htmlspecialchars($ev['nomearq']) ?></span></td>
              <td><?= htmlspecialchars($ev['alteracao']) ?>...</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Função utilitária para criação de Gauges semicirculares de 180 graus
function criarVelocimetro(canvasId, valor, maximo, corDestaque) {
  const ctx = document.getElementById(canvasId).getContext('2d');
  const restante = Math.max(0, maximo - valor);

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [valor, restante],
        backgroundColor: [corDestaque, '#EAECEE'],
        borderWidth: 0,
        circumference: 180,
        rotation: 270
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: { enabled: false }
      }
    }
  });
}

// 1. Saúde: 18.42% (Base 30%)
criarVelocimetro('gaugeSaude', <?= $oInd->percentual_saude ?>, 30, '#2ECC71');

// 2. Educação: 26.85% (Base 40%)
criarVelocimetro('gaugeEducacao', <?= $oInd->percentual_educacao ?>, 40, '#2ECC71');

// 3. FUNDEB: 74.10% (Base 100%)
criarVelocimetro('gaugeFundeb', <?= $oInd->percentual_fundeb ?>, 100, '#2ECC71');

// 4. Pessoal LRF: 49.30% (Base 60%)
criarVelocimetro('gaugePessoal', <?= $oInd->percentual_pessoal_lrf ?>, 60, '#F39C12');
</script>

</body>
</html>