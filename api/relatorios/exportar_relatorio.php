<?php
/**
 * API para exportar relatórios em diferentes formatos
 * api/relatorios/exportar_relatorio.php
 * 
 * Formatos suportados: CSV, Excel, PDF
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die('Não autorizado');
}

// Verificar permissão
if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
    http_response_code(403);
    die('Sem permissão para exportar relatórios');
}

// Pegar parâmetros
$tipo = $_POST['tipo'] ?? '';
$formato = $_POST['formato'] ?? 'csv';
$data = json_decode($_POST['data'] ?? '[]', true);
$filtros = json_decode($_POST['filtros'] ?? '{}', true);

if (empty($data)) {
    die('Nenhum dado para exportar');
}

// Definir nome do arquivo
$nomeArquivo = 'relatorio_' . $tipo . '_' . date('Y-m-d_H-i-s');

// Executar exportação baseada no formato
switch ($formato) {
    case 'csv':
        exportarCSV($data, $nomeArquivo, $tipo);
        break;
    case 'excel':
        exportarExcel($data, $nomeArquivo, $tipo, $filtros);
        break;
    case 'pdf':
        exportarPDF($data, $nomeArquivo, $tipo, $filtros);
        break;
    default:
        die('Formato não suportado');
}

/**
 * Exportar para CSV
 */
function exportarCSV($data, $nomeArquivo, $tipo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    if (empty($data)) {
        fclose($output);
        return;
    }
    
    // Headers baseados no tipo
    $headers = getHeaders($tipo);
    fputcsv($output, array_values($headers), ';');
    
    // Dados
    foreach ($data as $row) {
        $linha = [];
        foreach ($headers as $key => $label) {
            $valor = $row[$key] ?? '';
            $linha[] = formatarValorExport($valor, $key);
        }
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
}

/**
 * Exportar para Excel (formato HTML compatível)
 */
function exportarExcel($data, $nomeArquivo, $tipo, $filtros) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$nomeArquivo.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Início do HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #0056d2; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            .header { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
            .filters { font-size: 12px; color: #666; margin-bottom: 20px; }
            .footer { margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            ASSEGO - Relatório de <?php echo ucfirst(str_replace('_', ' ', $tipo)); ?>
        </div>
        
        <div class="filters">
            <?php if (!empty($filtros)): ?>
                <strong>Filtros aplicados:</strong><br>
                <?php
                if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                    echo "Período: " . date('d/m/Y', strtotime($filtros['data_inicio'])) . 
                         " até " . date('d/m/Y', strtotime($filtros['data_fim'])) . "<br>";
                }
                if (!empty($filtros['corporacao'])) {
                    echo "Corporação: " . $filtros['corporacao'] . "<br>";
                }
                if (!empty($filtros['patente'])) {
                    echo "Patente: " . $filtros['patente'] . "<br>";
                }
                if (!empty($filtros['lotacao'])) {
                    echo "Lotação: " . $filtros['lotacao'] . "<br>";
                }
                ?>
            <?php endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php
                    $headers = getHeaders($tipo);
                    foreach ($headers as $label) {
                        echo "<th>$label</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $contador = 1;
                foreach ($data as $row) {
                    echo "<tr>";
                    echo "<td>$contador</td>";
                    foreach (array_keys($headers) as $key) {
                        $valor = formatarValorExport($row[$key] ?? '', $key);
                        echo "<td>$valor</td>";
                    }
                    echo "</tr>";
                    $contador++;
                }
                ?>
            </tbody>
        </table>
        
        <div class="footer">
            Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?><br>
            Total de registros: <?php echo count($data); ?><br>
            Sistema ASSEGO - Associação dos Servidores Efetivos da Segurança Pública do Estado de Goiás
        </div>
    </body>
    </html>
    <?php
}

/**
 * Exportar para PDF (formato HTML para impressão)
 */
function exportarPDF($data, $nomeArquivo, $tipo, $filtros) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $nomeArquivo; ?></title>
        <style>
            @page { 
                size: A4 landscape; 
                margin: 1cm;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                color: #333;
            }
            
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #0056d2;
                padding-bottom: 10px;
            }
            
            .logo {
                font-size: 24px;
                font-weight: bold;
                color: #0056d2;
                margin-bottom: 5px;
            }
            
            .titulo {
                font-size: 18px;
                margin-bottom: 10px;
            }
            
            .filters {
                background-color: #f5f5f5;
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 5px;
                font-size: 10px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            th {
                background-color: #0056d2;
                color: white;
                padding: 8px;
                text-align: left;
                font-size: 10px;
                border: 1px solid #333;
            }
            
            td {
                padding: 6px;
                border: 1px solid #ddd;
                font-size: 10px;
            }
            
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .footer {
                position: fixed;
                bottom: 0;
                width: 100%;
                text-align: center;
                font-size: 9px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            .page-break {
                page-break-after: always;
            }
            
            @media print {
                body {
                    print-color-adjust: exact;
                    -webkit-print-color-adjust: exact;
                }
                
                .no-print {
                    display: none;
                }
            }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="header">
            <div class="logo">ASSEGO</div>
            <div class="titulo">Relatório de <?php echo ucfirst(str_replace('_', ' ', $tipo)); ?></div>
            <div style="font-size: 10px; color: #666;">
                Associação dos Servidores Efetivos da Segurança Pública do Estado de Goiás
            </div>
        </div>
        
        <?php if (!empty($filtros)): ?>
        <div class="filters">
            <strong>Filtros Aplicados:</strong>
            <?php
            $filtrosTexto = [];
            if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                $filtrosTexto[] = "Período: " . date('d/m/Y', strtotime($filtros['data_inicio'])) . 
                                 " até " . date('d/m/Y', strtotime($filtros['data_fim']));
            }
            if (!empty($filtros['corporacao'])) {
                $filtrosTexto[] = "Corporação: " . $filtros['corporacao'];
            }
            if (!empty($filtros['patente'])) {
                $filtrosTexto[] = "Patente: " . $filtros['patente'];
            }
            if (!empty($filtros['lotacao'])) {
                $filtrosTexto[] = "Lotação: " . $filtros['lotacao'];
            }
            echo implode(' | ', $filtrosTexto);
            ?>
        </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th width="30">#</th>
                    <?php
                    $headers = getHeaders($tipo);
                    foreach ($headers as $label) {
                        echo "<th>$label</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $contador = 1;
                $linhasPorPagina = 25;
                
                foreach ($data as $index => $row) {
                    // Adiciona quebra de página a cada X linhas
                    if ($index > 0 && $index % $linhasPorPagina == 0) {
                        echo '</tbody></table><div class="page-break"></div><table><thead><tr>';
                        echo '<th width="30">#</th>';
                        foreach ($headers as $label) {
                            echo "<th>$label</th>";
                        }
                        echo '</tr></thead><tbody>';
                    }
                    
                    echo "<tr>";
                    echo "<td>$contador</td>";
                    foreach (array_keys($headers) as $key) {
                        $valor = formatarValorExport($row[$key] ?? '', $key);
                        echo "<td>$valor</td>";
                    }
                    echo "</tr>";
                    $contador++;
                }
                ?>
            </tbody>
        </table>
        
        <div class="footer">
            <div>Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?> | Total de registros: <?php echo count($data); ?></div>
            <div>ASSEGO - Sistema de Gestão | www.assego.org.br</div>
        </div>
        
        <div class="no-print" style="position: fixed; top: 10px; right: 10px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #0056d2; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Imprimir / Salvar PDF
            </button>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Obter headers baseado no tipo de relatório
 */
function getHeaders($tipo) {
    $headers = [
        'desfiliacoes' => [
            'nome' => 'Nome',
            'rg' => 'RG',
            'cpf' => 'CPF',
            'telefone' => 'Telefone',
            'email' => 'E-mail',
            'patente' => 'Patente',
            'corporacao' => 'Corporação',
            'lotacao' => 'Lotação',
            'data_desfiliacao' => 'Data Desfiliação'
        ],
        'indicacoes' => [
            'indicador' => 'Nome do Indicador',
            'patente' => 'Patente',
            'corporacao' => 'Corporação',
            'total_indicacoes' => 'Total Indicações',
            'indicacoes_periodo' => 'Indicações no Período',
            'ultima_indicacao' => 'Última Indicação'
        ],
        'aniversariantes' => [
            'nome' => 'Nome',
            'data_nascimento' => 'Data Nascimento',
            'idade' => 'Idade',
            'dia_aniversario' => 'Dia',
            'mes_aniversario' => 'Mês',
            'patente' => 'Patente',
            'corporacao' => 'Corporação',
            'lotacao' => 'Lotação',
            'telefone' => 'Telefone',
            'email' => 'E-mail'
        ],
        'novos_cadastros' => [
            'nome' => 'Nome',
            'rg' => 'RG',
            'cpf' => 'CPF',
            'telefone' => 'Telefone',
            'email' => 'E-mail',
            'patente' => 'Patente',
            'corporacao' => 'Corporação',
            'lotacao' => 'Lotação',
            'data_aprovacao' => 'Data Cadastro',
            'indicacao' => 'Indicado por',
            'tipo_cadastro' => 'Tipo Cadastro'
        ],
        'estatisticas' => [
            'metrica' => 'Métrica',
            'valor' => 'Valor',
            'percentual' => 'Percentual'
        ]
    ];
    
    return $headers[$tipo] ?? [];
}

/**
 * Formatar valor para exportação
 */
function formatarValorExport($valor, $campo) {
    if (empty($valor) || $valor === null) {
        return '';
    }
    
    // Formatar datas
    if (strpos($campo, 'data_') === 0 || $campo === 'ultima_indicacao') {
        if ($valor && $valor !== '0000-00-00' && $valor !== '0000-00-00 00:00:00') {
            return date('d/m/Y', strtotime($valor));
        }
        return '';
    }
    
    // Formatar CPF
    if ($campo === 'cpf') {
        $cpf = preg_replace('/\D/', '', $valor);
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . 
                   substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
    }
    
    // Formatar telefone
    if ($campo === 'telefone') {
        $tel = preg_replace('/\D/', '', $valor);
        if (strlen($tel) === 11) {
            return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 5) . '-' . substr($tel, 7);
        } elseif (strlen($tel) === 10) {
            return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 4) . '-' . substr($tel, 6);
        }
    }
    
    // Formatar percentual
    if ($campo === 'percentual' && is_numeric($valor)) {
        return number_format($valor, 2, ',', '.') . '%';
    }
    
    return $valor;
}

// Registrar log de exportação
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $stmt = $db->prepare("
        INSERT INTO Historico_Relatorios 
        (nome_relatorio, parametros, gerado_por, formato, contagem_registros, data_geracao)
        VALUES (:nome, :parametros, :funcionario, :formato, :contagem, NOW())
    ");
    
    $stmt->execute([
        ':nome' => 'Exportação - ' . ucfirst($tipo),
        ':parametros' => json_encode($filtros),
        ':funcionario' => $_SESSION['funcionario_id'] ?? null,
        ':formato' => $formato,
        ':contagem' => count($data)
    ]);
} catch (Exception $e) {
    // Ignora erro de log
}
?>