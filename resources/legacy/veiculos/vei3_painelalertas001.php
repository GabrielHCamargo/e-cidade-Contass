<?php
/**
 * GESTÃO MUNICIPAL - e-Cidade Contass
 * Módulo de Veículos e Transportes
 * Arquivo: vei3_painelalertas001.php
 * Descrição: Painel de Monitoramento e Alertas Preventivos da Frota Municipal
 *            Acompanhamento semafórico (CNH, Troca de Óleo, Pneus e Revisões)
 */

header('Content-Type: text/html; charset=utf-8');

require_once("libs/db_stdlib.php");
require_once("libs/db_conecta.php");
include_once("libs/db_sessoes.php");
include_once("libs/db_usuariosonline.php");
include_once("dbforms/db_funcoes.php");

$iInstit  = (int)db_getsession('DB_instit', 1);
$sUsuario = db_getsession('DB_login', 'dbseller');
$iIdUsu   = (int)db_getsession('DB_id_usuario', 1);
$iAnoUsu  = (int)db_getsession('DB_anousu', 2026);

function to_utf($str) {
    if ($str === null || $str === '') return '';
    return mb_check_encoding($str, 'UTF-8') ? $str : utf8_encode($str);
}

$sMsgSucesso = "";
$sMsgErro    = "";

// Tratamento de atualização de parâmetros de alerta
if (isset($_POST['btn_salvar_param'])) {
    $iDiasCnh       = (int)$_POST['dias_cnh'];
    $iKmTrocaOleo   = (int)$_POST['km_oleo'];
    $iKmPneus       = (int)$_POST['km_pneus'];
    $iKmRevisao     = (int)$_POST['km_revisao'];

    if ($iDiasCnh > 0 && $iKmTrocaOleo > 0) {
        db_inicio_transacao();
        db_query("SELECT fc_startsession();");
        db_query("SELECT fc_putsession('DB_login', '{$sUsuario}');");
        db_query("SELECT fc_putsession('DB_id_usuario', '{$iIdUsu}');");
        db_query("SELECT fc_putsession('DB_anousu', '{$iAnoUsu}');");
        db_query("SELECT fc_putsession('DB_instit', '{$iInstit}');");

        $sqlUp = "UPDATE veiculos.veicalertaparam 
                     SET ve90_diascnh     = {$iDiasCnh},
                         ve90_kmtrocaoleo = {$iKmTrocaOleo},
                         ve90_kmpneus     = {$iKmPneus},
                         ve90_kmrevisao   = {$iKmRevisao}
                   WHERE ve90_instit      = {$iInstit};";
        $resUp = db_query($sqlUp);
        if ($resUp) {
            db_fim_transacao(false);
            $sMsgSucesso = "Parâmetros operacionais da frota atualizados com sucesso e auditados!";
        } else {
            db_fim_transacao(true);
            $sMsgErro = "Erro ao gravar parâmetros no banco de dados.";
        }
    }
}

// Carregar Parâmetros Vigentes
$resParam = db_query("SELECT * FROM veiculos.veicalertaparam WHERE ve90_instit = {$iInstit} LIMIT 1;");
$oParam = ($resParam && pg_num_rows($resParam) > 0) ? pg_fetch_object($resParam) : (object)[
    've90_diascnh'     => 30,
    've90_kmtrocaoleo' => 10000,
    've90_kmpneus'     => 40000,
    've90_kmrevisao'   => 10000
];

// Carregar Alertas Reais da View do PostgreSQL
$sqlAlertas = "SELECT tipo, categoria, identificador, documento, 
                      data_referencia, dias_restantes, km_atual, km_limite, diferenca_km, 
                      status, mensagem 
                 FROM veiculos.v_alertas_frota 
             ORDER BY CASE status WHEN 'VERMELHO' THEN 1 WHEN 'AMARELO' THEN 2 ELSE 3 END, 
                      tipo, identificador;";
$resAlertas = db_query($sqlAlertas);
$aAlertas = [];
$iTotalCritico = 0;
$iTotalAtencao = 0;
$iTotalRegular = 0;

if ($resAlertas) {
    while ($row = pg_fetch_assoc($resAlertas)) {
        foreach ($row as $k => $v) {
            $row[$k] = to_utf($v);
        }
        $aAlertas[] = $row;
        if ($row['status'] == 'VERMELHO') $iTotalCritico++;
        elseif ($row['status'] == 'AMARELO') $iTotalAtencao++;
        else $iTotalRegular++;
    }
}
$iTotalGeral = count($aAlertas);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Prefeitura Municipal - Monitoramento da Frota</title>
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
    .kpi-row {
      display: flex;
      gap: 12px;
      margin-bottom: 14px;
    }
    .kpi-card {
      flex: 1;
      background: #FFFFFF;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
      padding: 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .kpi-card:hover {
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
      border-color: #5786B2;
    }
    .kpi-title {
      font-size: 11px;
      font-weight: bold;
      color: #555555;
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .kpi-value {
      font-size: 22px;
      font-weight: bold;
    }
    .val-total { color: #2C3E50; }
    .val-critico { color: #C0392B; }
    .val-atencao { color: #D35400; }
    .val-regular { color: #27AE60; }

    .chart-panel-row {
      display: flex;
      gap: 14px;
      margin-bottom: 14px;
      align-items: stretch;
    }
    .chart-box {
      flex: 0 0 320px;
      background: #FFFFFF;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
      padding: 10px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .param-box {
      flex: 1;
      background: #FFFFFF;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
      padding: 12px;
    }
    .param-title {
      font-weight: bold;
      color: #3B6998;
      font-size: 12px;
      border-bottom: 1px solid #E0E0E0;
      padding-bottom: 6px;
      margin-bottom: 10px;
    }
    .param-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .param-group label {
      display: block;
      font-weight: bold;
      color: #444444;
      margin-bottom: 3px;
    }
    .param-group input {
      width: 90%;
      padding: 4px 6px;
      border: 1px solid #AAAAAA;
      border-radius: 2px;
      font-size: 11px;
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
    .filter-bar {
      margin-bottom: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .tabela-ecidade {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #B0C4DE;
      background-color: #FFFFFF;
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
    .tabela-ecidade tr:hover {
      background-color: #EBF2F8;
    }
    .badge-status {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 12px;
      font-weight: bold;
      font-size: 10px;
      text-align: center;
      min-width: 75px;
    }
    .badge-vermelho {
      background-color: #FDEDEC;
      color: #C0392B;
      border: 1px solid #E6B0AA;
    }
    .badge-amarelo {
      background-color: #FEF9E7;
      color: #B7950B;
      border: 1px solid #F9E79F;
    }
    .badge-verde {
      background-color: #EAFAF1;
      color: #1E8449;
      border: 1px solid #A9DFBF;
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
  </style>
</head>
<body>

<div class="container-ecidade">
  <div class="header-bar">
    <div>
      <span>DEPARTAMENTO DE GEST&Atilde;O DA FROTA MUNICIPAL</span> &bull; 
      <span style="font-weight: normal;">Painel de Alertas e Manuten&ccedil;&atilde;o Preventiva</span>
    </div>
    <div style="font-size: 11px; font-weight: normal;">
      Exerc&iacute;cio: <strong><?= $iAnoUsu ?></strong> | Operador: <strong><?= htmlspecialchars($sUsuario) ?></strong>
    </div>
  </div>

  <?php if (!empty($sMsgSucesso)): ?>
    <div class="msg-box msg-sucesso"><?= htmlspecialchars($sMsgSucesso) ?></div>
  <?php endif; ?>
  <?php if (!empty($sMsgErro)): ?>
    <div class="msg-box msg-erro"><?= htmlspecialchars($sMsgErro) ?></div>
  <?php endif; ?>

  <!-- Resumo Operacional KPI -->
  <div class="kpi-row">
    <div class="kpi-card" onclick="filtrarStatus('TODOS')">
      <div class="kpi-title">Total Monitorado</div>
      <div class="kpi-value val-total"><?= $iTotalGeral ?></div>
    </div>
    <div class="kpi-card" onclick="filtrarStatus('VERMELHO')">
      <div class="kpi-title">Cr&iacute;ticos / Vencidos</div>
      <div class="kpi-value val-critico"><?= $iTotalCritico ?></div>
    </div>
    <div class="kpi-card" onclick="filtrarStatus('AMARELO')">
      <div class="kpi-title">Aten&ccedil;&atilde;o / Pr&oacute;ximos</div>
      <div class="kpi-value val-atencao"><?= $iTotalAtencao ?></div>
    </div>
    <div class="kpi-card" onclick="filtrarStatus('VERDE')">
      <div class="kpi-title">Regulares / Conformes</div>
      <div class="kpi-value val-regular"><?= $iTotalRegular ?></div>
    </div>
  </div>

  <!-- Gráfico Semafórico + Ajuste de Parâmetros -->
  <div class="chart-panel-row">
    <div class="chart-box">
      <div style="font-weight: bold; margin-bottom: 8px; color: #444;">Distribui&ccedil;&atilde;o de Alertas</div>
      <div style="width: 200px; height: 180px;">
        <canvas id="graficoStatus"></canvas>
      </div>
    </div>

    <div class="param-box">
      <div class="param-title">Toler&acirc;ncias e Par&acirc;metros Preventivos da Frota</div>
      <form method="POST" action="vei3_painelalertas001.php">
        <div class="param-grid">
          <div class="param-group">
            <label for="dias_cnh">Anteced&ecirc;ncia Alerta CNH (Dias):</label>
            <input type="number" id="dias_cnh" name="dias_cnh" value="<?= (int)$oParam->ve90_diascnh ?>" required min="5" max="180">
          </div>
          <div class="param-group">
            <label for="km_oleo">Ciclo Troca de &Oacute;leo (Km):</label>
            <input type="number" id="km_oleo" name="km_oleo" value="<?= (int)$oParam->ve90_kmtrocaoleo ?>" required min="1000" step="500">
          </div>
          <div class="param-group">
            <label for="km_pneus">Ciclo Troca de Pneus (Km):</label>
            <input type="number" id="km_pneus" name="km_pneus" value="<?= (int)$oParam->ve90_kmpneus ?>" required min="5000" step="1000">
          </div>
          <div class="param-group">
            <label for="km_revisao">Ciclo Revis&atilde;o Geral (Km):</label>
            <input type="number" id="km_revisao" name="km_revisao" value="<?= (int)$oParam->ve90_kmrevisao ?>" required min="5000" step="1000">
          </div>
        </div>
        <div style="margin-top: 14px; text-align: right;">
          <input type="submit" name="btn_salvar_param" value="Gravar Par&acirc;metros e Auditar" class="btn-ecidade">
        </div>
      </form>
    </div>
  </div>

  <!-- Barra de Filtros Rápidos -->
  <div class="filter-bar">
    <div>
      <span style="font-weight: bold; color: #444;">Filtrar Exibi&ccedil;&atilde;o:</span>
      <button type="button" class="btn-secundario" onclick="filtrarStatus('TODOS')">Todos (<?= $iTotalGeral ?>)</button>
      <button type="button" class="btn-secundario" style="color: #C0392B; font-weight: bold;" onclick="filtrarStatus('VERMELHO')">Cr&iacute;ticos (<?= $iTotalCritico ?>)</button>
      <button type="button" class="btn-secundario" style="color: #B7950B; font-weight: bold;" onclick="filtrarStatus('AMARELO')">Aten&ccedil;&atilde;o (<?= $iTotalAtencao ?>)</button>
      <button type="button" class="btn-secundario" style="color: #1E8449; font-weight: bold;" onclick="filtrarStatus('VERDE')">Regulares (<?= $iTotalRegular ?>)</button>
    </div>
    <div>
      <button type="button" class="btn-secundario" onclick="window.print();">Imprimir Relat&oacute;rio</button>
      <button type="button" class="btn-secundario" onclick="location.reload();">Atualizar Dados</button>
    </div>
  </div>

  <!-- Tabela de Alertas em Tempo Real -->
  <table class="tabela-ecidade" id="tabelaAlertas">
    <thead>
      <tr>
        <th style="width: 70px; text-align: center;">Sem&aacute;foro</th>
        <th style="width: 70px;">Tipo</th>
        <th style="width: 220px;">Identifica&ccedil;&atilde;o do Recurso</th>
        <th style="width: 140px;">Documento / Placa</th>
        <th>Diagn&oacute;stico Operacional</th>
        <th style="width: 110px; text-align: right;">Medi&ccedil;&atilde;o / Ref.</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($aAlertas)): ?>
        <tr>
          <td colspan="6" style="text-align: center; padding: 15px; color: #777;">
            Nenhum alerta de manuten&ccedil;&atilde;o ou condutor registrado no momento.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($aAlertas as $alerta): 
          $sClasseBadge = 'badge-verde';
          $sLabelStatus = 'REGULAR';
          if ($alerta['status'] == 'VERMELHO') {
              $sClasseBadge = 'badge-vermelho';
              $sLabelStatus = 'CR&Iacute;TICO';
          } elseif ($alerta['status'] == 'AMARELO') {
              $sClasseBadge = 'badge-amarelo';
              $sLabelStatus = 'ATEN&Ccedil;&Atilde;O';
          }
        ?>
          <tr data-status="<?= $alerta['status'] ?>">
            <td style="text-align: center;">
              <span class="badge-status <?= $sClasseBadge ?>"><?= $sLabelStatus ?></span>
            </td>
            <td><strong><?= htmlspecialchars($alerta['tipo']) ?></strong></td>
            <td><?= htmlspecialchars($alerta['identificador']) ?></td>
            <td><?= htmlspecialchars($alerta['documento']) ?></td>
            <td><?= htmlspecialchars($alerta['mensagem']) ?></td>
            <td style="text-align: right;">
              <?php if ($alerta['tipo'] == 'CNH'): ?>
                <?= date('d/m/Y', strtotime($alerta['data_referencia'])) ?>
              <?php else: ?>
                <?= number_format($alerta['km_atual'], 0, ',', '.') ?> km
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// Renderização do gráfico Doughnut oficial
const ctx = document.getElementById('graficoStatus').getContext('2d');
const chartStatus = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Crítico', 'Atenção', 'Regular'],
    datasets: [{
      data: [<?= $iTotalCritico ?>, <?= $iTotalAtencao ?>, <?= $iTotalRegular ?>],
      backgroundColor: ['#E74C3C', '#F39C12', '#2ECC71'],
      borderWidth: 1,
      borderColor: '#FFFFFF'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { font: { size: 10 } }
      }
    }
  }
});

// Filtro Dinâmico em Linha
function filtrarStatus(statusAlvo) {
  const linhas = document.querySelectorAll('#tabelaAlertas tbody tr');
  linhas.forEach(linha => {
    const statusLinha = linha.getAttribute('data-status');
    if (!statusLinha) return;
    if (statusAlvo === 'TODOS' || statusLinha === statusAlvo) {
      linha.style.display = '';
    } else {
      linha.style.display = 'none';
    }
  });
}
</script>

</body>
</html>
