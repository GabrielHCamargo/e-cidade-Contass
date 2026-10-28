<?php
/**
 * GESTÃO MUNICIPAL - e-Cidade Contass
 * Módulo de Protocolo e Comunicações Administrativas
 * Arquivo: pro3_graficos001.php
 * Descrição: Painel Analítico e Gráficos Estatísticos de Tramitação de Processos
 *            Volume por Departamento, Tempo Médio de Permanência e Status
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

// Filtro opcional por ano
$iAnoFiltro = isset($_GET['ano']) ? (int)$_GET['ano'] : $iAnoUsu;

// 1. Consulta Volume e Tempo Médio por Departamento
$sqlDeptos = "SELECT d.coddepto, d.descrdepto, 
                     COUNT(p.p58_codproc) AS total_processos,
                     COALESCE(ROUND(AVG(CURRENT_DATE - p.p58_dtproc), 1), 0) AS media_dias
                FROM configuracoes.db_depart d
           LEFT JOIN protocolo.protprocesso p ON p.p58_coddepto = d.coddepto 
                 AND (p.p58_ano = {$iAnoFiltro} OR {$iAnoFiltro} = 0)
            GROUP BY d.coddepto, d.descrdepto
              HAVING COUNT(p.p58_codproc) > 0
            ORDER BY total_processos DESC, d.descrdepto;";
$resDeptos = db_query($sqlDeptos);

$aNomesDeptos = [];
$aQtdDeptos   = [];
$aMediaDias   = [];
$aTabelaDeptos = [];

$iTotalProcessos = 0;
$fSomaDias = 0;

if ($resDeptos) {
    while ($row = pg_fetch_assoc($resDeptos)) {
        $sNomeDepto     = to_utf($row['descrdepto']);
        $aNomesDeptos[] = $sNomeDepto;
        $aQtdDeptos[]   = (int)$row['total_processos'];
        $aMediaDias[]   = (float)$row['media_dias'];
        $row['descrdepto'] = $sNomeDepto;
        $aTabelaDeptos[]   = $row;
        $iTotalProcessos += (int)$row['total_processos'];
        $fSomaDias += ((float)$row['media_dias'] * (int)$row['total_processos']);
    }
}
$fMediaGlobalDias = ($iTotalProcessos > 0) ? round($fSomaDias / $iTotalProcessos, 1) : 0;

// 2. Consulta Status dos Processos (Com Despacho vs Em Tramitação)
$sqlStatus = "SELECT 
                CASE 
                    WHEN EXISTS (SELECT 1 FROM protocolo.protdespacho d WHERE d.p80_codproc = p.p58_codproc) 
                        THEN 'Despachado / Concluído'
                    WHEN (CURRENT_DATE - p.p58_dtproc) > 15 
                        THEN 'Em Tramitação (Atenção)'
                    ELSE 'Em Análise / Regular'
                END AS status_tramite,
                COUNT(p.p58_codproc) AS total
              FROM protocolo.protprocesso p
             WHERE (p.p58_ano = {$iAnoFiltro} OR {$iAnoFiltro} = 0)
          GROUP BY 1
          ORDER BY total DESC;";
$resStatus = db_query($sqlStatus);

$aLabelsStatus = [];
$aQtdStatus    = [];
if ($resStatus) {
    while ($row = pg_fetch_assoc($resStatus)) {
        $aLabelsStatus[] = to_utf($row['status_tramite']);
        $aQtdStatus[]    = (int)$row['total'];
    }
}
if (empty($aLabelsStatus)) {
    $aLabelsStatus = [to_utf('Em Tramitação'), 'Despachado'];
    $aQtdStatus    = [0, 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Prefeitura Municipal - Gr&aacute;ficos Gerenciais de Processos</title>
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
      color: #3B6998;
    }
    .grid-graficos {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      margin-bottom: 14px;
    }
    .grafico-card {
      background: #FFFFFF;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
      padding: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .grafico-card-full {
      grid-column: 1 / -1;
    }
    .card-titulo {
      font-size: 12px;
      font-weight: bold;
      color: #3B6998;
      border-bottom: 1px solid #E0E0E0;
      padding-bottom: 6px;
      margin-bottom: 10px;
    }
    .canvas-container {
      position: relative;
      height: 240px;
      width: 100%;
    }
    .filter-bar {
      margin-bottom: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #FFFFFF;
      padding: 8px 12px;
      border: 1px solid #D0D0D0;
      border-radius: 4px;
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
    @media print {
      body { background: #FFFFFF; padding: 0; }
      .container-ecidade { border: none; box-shadow: none; max-width: 100%; padding: 0; }
      .header-bar, .filter-bar, .nao-imprimir { display: none !important; }
      .canvas-container { height: 200px; }
    }
  </style>
</head>
<body>

<div class="container-ecidade">
  <div class="header-bar nao-imprimir">
    <div>
      <span>DEPARTAMENTO DE PROTOCOLO GERAL</span> &bull; 
      <span style="font-weight: normal;">Estat&iacute;sticas e Gr&aacute;ficos de Tramita&ccedil;&atilde;o de Processos</span>
    </div>
    <div style="font-size: 11px; font-weight: normal;">
      Exerc&iacute;cio: <strong><?= $iAnoUsu ?></strong> | Operador: <strong><?= htmlspecialchars(to_utf($sUsuario)) ?></strong>
    </div>
  </div>

  <!-- Barra de Filtros -->
  <div class="filter-bar nao-imprimir">
    <form method="GET" action="pro3_graficos001.php" style="display: flex; gap: 10px; align-items: center;">
      <label for="ano" style="font-weight: bold; color: #444;">Exerc&iacute;cio de Refer&ecirc;ncia:</label>
      <select name="ano" id="ano" style="padding: 4px 8px; font-size: 11px;">
        <option value="2026" <?= ($iAnoFiltro == 2026) ? 'selected' : '' ?>>2026 (Atual)</option>
        <option value="2025" <?= ($iAnoFiltro == 2025) ? 'selected' : '' ?>>2025</option>
        <option value="0" <?= ($iAnoFiltro == 0) ? 'selected' : '' ?>>Todos os Exerc&iacute;cios</option>
      </select>
      <button type="submit" class="btn-ecidade">Atualizar Indicadores</button>
    </form>
    <div>
      <button type="button" class="btn-secundario" onclick="window.print();">Imprimir Relat&oacute;rio</button>
    </div>
  </div>

  <!-- Cards de Resumo Executivo -->
  <div class="kpi-row">
    <div class="kpi-card">
      <div class="kpi-title">Total de Processos</div>
      <div class="kpi-value"><?= $iTotalProcessos ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-title">Departamentos com Demanda</div>
      <div class="kpi-value" style="color: #27AE60;"><?= count($aTabelaDeptos) ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-title">Tempo M&eacute;dio de Perman&ecirc;ncia</div>
      <div class="kpi-value" style="color: #E67E22;"><?= $fMediaGlobalDias ?> <span style="font-size: 13px;">dias</span></div>
    </div>
  </div>

  <!-- Painel dos 3 Gráficos Oficiais em Chart.js -->
  <div class="grid-graficos">
    <!-- Gráfico 1: Volume de Processos por Setor -->
    <div class="grafico-card">
      <div class="card-titulo">1. Volume de Processos por Departamento de Tramita&ccedil;&atilde;o</div>
      <div class="canvas-container">
        <canvas id="chartVolumeDeptos"></canvas>
      </div>
    </div>

    <!-- Gráfico 2: Tempo Médio de Permanência por Setor -->
    <div class="grafico-card">
      <div class="card-titulo">2. Tempo M&eacute;dio de Perman&ecirc;ncia por Setor (Dias &Uacute;teis/Corridos)</div>
      <div class="canvas-container">
        <canvas id="chartTempoMedio"></canvas>
      </div>
    </div>

    <!-- Gráfico 3: Distribuição por Status de Tramitação -->
    <div class="grafico-card grafico-card-full">
      <div class="card-titulo">3. Distribui&ccedil;&atilde;o Geral por Status de Tramita&ccedil;&atilde;o dos Processos</div>
      <div style="display: flex; gap: 20px; align-items: center;">
        <div style="flex: 0 0 280px; height: 210px;">
          <canvas id="chartStatusProcessos"></canvas>
        </div>
        <div style="flex: 1;">
          <div style="font-weight: bold; color: #444; margin-bottom: 6px;">Quadro Anal&iacute;tico por Departamento:</div>
          <table class="tabela-ecidade">
            <thead>
              <tr>
                <th>Departamento</th>
                <th style="width: 100px; text-align: right;">Processos</th>
                <th style="width: 130px; text-align: right;">Perman&ecirc;ncia M&eacute;dia</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aTabelaDeptos as $td): ?>
                <tr>
                  <td><?= htmlspecialchars($td['descrdepto']) ?></td>
                  <td style="text-align: right; font-weight: bold;"><?= $td['total_processos'] ?></td>
                  <td style="text-align: right; color: #555;"><?= $td['media_dias'] ?> dias</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Dados fornecidos diretamente pelo PostgreSQL
const nomesDeptos = <?= json_encode($aNomesDeptos, JSON_UNESCAPED_UNICODE) ?>;
const qtdDeptos   = <?= json_encode($aQtdDeptos) ?>;
const mediaDias   = <?= json_encode($aMediaDias) ?>;
const labelsStatus = <?= json_encode($aLabelsStatus, JSON_UNESCAPED_UNICODE) ?>;
const qtdStatus    = <?= json_encode($aQtdStatus) ?>;

// 1. Gráfico de Volume por Setor
new Chart(document.getElementById('chartVolumeDeptos').getContext('2d'), {
  type: 'bar',
  data: {
    labels: nomesDeptos,
    datasets: [{
      label: 'Volume de Processos',
      data: qtdDeptos,
      backgroundColor: '#5786B2',
      borderColor: '#3B6998',
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 } },
      x: { ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 25 } }
    }
  }
});

// 2. Gráfico de Tempo Médio de Permanência
new Chart(document.getElementById('chartTempoMedio').getContext('2d'), {
  type: 'bar',
  data: {
    labels: nomesDeptos,
    datasets: [{
      label: 'Dias de Permanência',
      data: mediaDias,
      backgroundColor: '#E67E22',
      borderColor: '#D35400',
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true, title: { display: true, text: 'Dias' } },
      x: { ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 25 } }
    }
  }
});

// 3. Gráfico de Status dos Processos
new Chart(document.getElementById('chartStatusProcessos').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: labelsStatus,
    datasets: [{
      data: qtdStatus,
      backgroundColor: ['#2ECC71', '#F39C12', '#3498DB', '#E74C3C'],
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
</script>

</body>
</html>