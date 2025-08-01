<?php
/**
 * API para exportação de dados de auditoria em CSV
 * /api/auditoria/exportar.php
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auditoria.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }

    // Criar instância da auditoria
    $auditoria = new Auditoria();
    
    // Preparar filtros (mesma lógica da API de registros)
    $filtros = [];
    
    if (!empty($_GET['tabela'])) {
        $filtros['tabela'] = $_GET['tabela'];
    }
    
    if (!empty($_GET['acao'])) {
        $filtros['acao'] = $_GET['acao'];
    }
    
    if (!empty($_GET['funcionario_id'])) {
        $filtros['funcionario_id'] = (int)$_GET['funcionario_id'];
    }
    
    if (!empty($_GET['associado_id'])) {
        $filtros['associado_id'] = (int)$_GET['associado_id'];
    }
    
    if (!empty($_GET['data_inicio'])) {
        $filtros['data_inicio'] = $_GET['data_inicio'];
    }
    
    if (!empty($_GET['data_fim'])) {
        $filtros['data_fim'] = $_GET['data_fim'];
    }
    
    // Remover limite para exportar todos os dados
    $filtros['limit'] = 10000; // Limite de segurança
    
    // Buscar registros
    $registros = $auditoria->buscarHistorico($filtros);
    
    // Preparar cabeçalhos para download
    $filename = 'auditoria_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Abrir saída como arquivo
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8 (para Excel reconhecer acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos do CSV
    $headers = [
        'ID',
        'Data/Hora',
        'Funcionário',
        'Cargo',
        'Ação',
        'Tabela',
        'Registro ID',
        'Associado',
        'CPF Associado',
        'IP Origem',
        'Navegador',
        'Sessão ID',
        'Tem Alterações',
        'Resumo Alterações'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Dados
    foreach ($registros as $registro) {
        // Processar alterações para resumo
        $resumoAlteracoes = '';
        if (!empty($registro['alteracoes'])) {
            try {
                $alteracoesDecodificadas = json_decode($registro['alteracoes'], true);
                if (is_array($alteracoesDecodificadas)) {
                    if (isset($alteracoesDecodificadas[0]) && isset($alteracoesDecodificadas[0]['campo'])) {
                        // Formato de detalhes de alteração
                        $campos = array_column($alteracoesDecodificadas, 'campo');
                        $resumoAlteracoes = 'Campos alterados: ' . implode(', ', $campos);
                    } else {
                        // Formato genérico
                        $resumoAlteracoes = 'Dados: ' . json_encode($alteracoesDecodificadas);
                    }
                }
            } catch (Exception $e) {
                $resumoAlteracoes = 'Dados não legíveis';
            }
        }
        
        $linha = [
            $registro['id'],
            date('d/m/Y H:i:s', strtotime($registro['data_hora'])),
            $registro['funcionario_nome'] ?? 'Sistema',
            '', // Cargo - seria necessário buscar
            $registro['acao'],
            $registro['tabela'],
            $registro['registro_id'] ?? '',
            $registro['associado_nome'] ?? '',
            $registro['associado_cpf'] ?? '',
            $registro['ip_origem'] ?? '',
            $registro['browser_info'] ?? '',
            $registro['sessao_id'] ?? '',
            !empty($registro['alteracoes']) ? 'Sim' : 'Não',
            $resumoAlteracoes
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    // Adicionar estatísticas no final
    fputcsv($output, [], ';'); // Linha vazia
    fputcsv($output, ['=== ESTATÍSTICAS ==='], ';');
    fputcsv($output, ['Total de registros exportados:', count($registros)], ';');
    fputcsv($output, ['Data da exportação:', date('d/m/Y H:i:s')], ';');
    
    // Filtros aplicados
    if (!empty($filtros)) {
        fputcsv($output, ['Filtros aplicados:'], ';');
        foreach ($filtros as $filtro => $valor) {
            if ($filtro !== 'limit' && !empty($valor)) {
                fputcsv($output, [$filtro . ':', $valor], ';');
            }
        }
    }
    
    fclose($output);
    
    // Registrar a exportação na auditoria
    try {
        $auditoria->registrar([
            'tabela' => 'Auditoria',
            'acao' => 'EXPORTAR',
            'detalhes' => [
                'tipo_exportacao' => 'CSV',
                'total_registros' => count($registros),
                'filtros_aplicados' => $filtros,
                'arquivo' => $filename
            ]
        ]);
    } catch (Exception $e) {
        // Não interromper a exportação se não conseguir registrar
        error_log("Erro ao registrar exportação na auditoria: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Erro na API de exportação de auditoria: " . $e->getMessage());
    
    // Se chegou até aqui, não pode mais usar JSON, então retorna texto simples
    header('Content-Type: text/plain');
    echo "Erro ao exportar dados: " . $e->getMessage();
}
?>