<?php
/**
 * API para exportação de dados de auditoria em CSV - VERSÃO CORRIGIDA
 * /api/auditoria/exportar.php
 * 
 * CORREÇÃO CRÍTICA: Usar Auth class em vez de busca manual por sessão
 * PROBLEMA: $_SESSION['nome'] não existe, deveria usar Auth::getUser()
 */

// IMPORTANTE: Não mostrar erros na saída para não quebrar o CSV
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Auditoria.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }

    // ===========================================
    // CORREÇÃO PRINCIPAL: USAR CLASSE AUTH
    // ===========================================
    
    session_start();
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $usuarioLogado = $auth->getUser();
    $funcionarioId = $usuarioLogado['id'];
    $funcionarioNome = $usuarioLogado['nome'];
    $departamentoId = $usuarioLogado['departamento_id'];
    $cargoUsuario = $usuarioLogado['cargo'];
    
    // DEBUG DETALHADO
    error_log("=== EXPORTAÇÃO AUDITORIA CORRIGIDA ===");
    error_log("Funcionário ID: " . $funcionarioId);
    error_log("Funcionário Nome: " . $funcionarioNome);
    error_log("Departamento ID: " . $departamentoId);
    error_log("Cargo: " . $cargoUsuario);
    
    // Criar instância da auditoria
    $auditoria = new Auditoria();
    
    // ===========================================
    // LÓGICA DE PERMISSÕES CORRIGIDA
    // ===========================================
    
    $isPresidencia = ($departamentoId == 1) || in_array($cargoUsuario, ['Presidente', 'Vice-Presidente']);
    $isDiretor = in_array($cargoUsuario, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador']);
    
    // Preparar filtros
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
    
    // ===========================================
    // CORREÇÃO: FILTRO DEPARTAMENTAL BASEADO EM PERMISSÕES
    // ===========================================
    
    if (!$isPresidencia && $isDiretor && $departamentoId) {
        // Diretores veem apenas seu departamento
        $filtros['departamento_usuario'] = $departamentoId;
        error_log("FILTRO DEPARTAMENTAL APLICADO: Departamento " . $departamentoId);
    } elseif (!$isPresidencia && !$isDiretor) {
        // Funcionários normais veem apenas próprios registros
        $filtros['funcionario_id'] = $funcionarioId;
        error_log("FILTRO FUNCIONÁRIO APLICADO: Funcionário " . $funcionarioId);
    } else {
        error_log("SEM FILTROS: Usuário da presidência ou com acesso total");
    }
    
    // Verificar se foi passado filtro departamental externo
    if (!empty($_GET['departamento_usuario'])) {
        $deptFiltro = (int)$_GET['departamento_usuario'];
        
        // Validação de segurança
        if (!$isPresidencia && $deptFiltro !== $departamentoId) {
            error_log("ERRO SEGURANÇA: Usuário dept $departamentoId tentou exportar dept $deptFiltro");
            throw new Exception('Acesso negado: você não pode exportar dados de outros departamentos');
        }
        
        $filtros['departamento_usuario'] = $deptFiltro;
        error_log("FILTRO DEPARTAMENTAL EXTERNO: " . $deptFiltro);
    }
    
    // Remover limite para exportar todos os dados (com limite de segurança)
    $filtros['limit'] = 10000; // Limite de segurança
    
    // Buscar registros
    $registros = $auditoria->buscarHistorico($filtros);
    
    error_log("REGISTROS ENCONTRADOS PARA EXPORTAÇÃO: " . count($registros));
    
    // ===========================================
    // PREPARAR EXPORT CSV
    // ===========================================
    
    $sufixo = '';
    if (!$isPresidencia && $isDiretor) {
        $sufixo = '_dept_' . $departamentoId;
    } elseif (!$isPresidencia && !$isDiretor) {
        $sufixo = '_user_' . $funcionarioId;
    } else {
        $sufixo = '_completo';
    }
    
    $filename = 'auditoria' . $sufixo . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Preparar cabeçalhos para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Abrir saída como arquivo
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8 (para Excel reconhecer acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // ===========================================
    // CABEÇALHOS DO CSV
    // ===========================================
    
    $headers = [
        'ID',
        'Data/Hora',
        'Funcionário',
        'Cargo Funcionário',
        'Departamento',
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
    
    // ===========================================
    // DADOS
    // ===========================================
    
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
            '', // Cargo - buscar se necessário
            '', // Departamento - buscar se necessário
            $registro['acao'],
            $registro['tabela'],
            $registro['registro_id'] ?? '',
            $registro['associado_nome'] ?? '',
            '', // CPF - buscar se necessário
            $registro['ip_origem'] ?? '',
            $registro['browser_info'] ?? '',
            $registro['sessao_id'] ?? '',
            !empty($registro['alteracoes']) ? 'Sim' : 'Não',
            $resumoAlteracoes
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    // ===========================================
    // ESTATÍSTICAS NO FINAL
    // ===========================================
    
    fputcsv($output, [], ';'); // Linha vazia
    fputcsv($output, ['=== ESTATÍSTICAS DE EXPORTAÇÃO ==='], ';');
    fputcsv($output, ['Total de registros exportados:', count($registros)], ';');
    fputcsv($output, ['Data da exportação:', date('d/m/Y H:i:s')], ';');
    fputcsv($output, ['Exportado por:', $funcionarioNome], ';');
    fputcsv($output, ['ID do funcionário:', $funcionarioId], ';');
    fputcsv($output, ['Departamento:', $departamentoId], ';');
    fputcsv($output, ['Cargo:', $cargoUsuario], ';');
    
    // Escopo da exportação
    if ($isPresidencia) {
        fputcsv($output, ['Escopo:', 'Sistema Completo (Presidência)'], ';');
    } elseif ($isDiretor) {
        fputcsv($output, ['Escopo:', "Departamento $departamentoId"], ';');
    } else {
        fputcsv($output, ['Escopo:', "Registros próprios (ID: $funcionarioId)"], ';');
    }
    
    // Filtros aplicados
    if (!empty($filtros)) {
        fputcsv($output, [], ';'); // Linha vazia
        fputcsv($output, ['=== FILTROS APLICADOS ==='], ';');
        foreach ($filtros as $filtro => $valor) {
            if ($filtro !== 'limit' && !empty($valor)) {
                fputcsv($output, [$filtro . ':', $valor], ';');
            }
        }
    }
    
    fclose($output);
    
    // ===========================================
    // REGISTRAR A EXPORTAÇÃO NA AUDITORIA
    // ===========================================
    
    try {
        $auditoria->registrar([
            'tabela' => 'Auditoria',
            'acao' => 'EXPORTAR',
            'funcionario_id' => $funcionarioId, // ← AGORA USA O ID CORRETO
            'detalhes' => [
                'tipo_exportacao' => 'CSV',
                'total_registros' => count($registros),
                'filtros_aplicados' => $filtros,
                'arquivo' => $filename,
                'funcionario_exportador' => $funcionarioNome,
                'escopo' => $isPresidencia ? 'COMPLETO' : ($isDiretor ? "DEPT_$departamentoId" : "USER_$funcionarioId")
            ]
        ]);
        
        error_log("✅ Exportação registrada na auditoria corretamente");
        
    } catch (Exception $e) {
        // Não interromper a exportação se não conseguir registrar
        error_log("❌ Erro ao registrar exportação na auditoria: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("❌ ERRO CRÍTICO na exportação: " . $e->getMessage());
    
    // Se chegou até aqui, não pode mais usar JSON, então retorna texto simples
    header('Content-Type: text/plain');
    echo "Erro ao exportar dados: " . $e->getMessage();
}
?>