<?php
/**
 * API para buscar dados completos do associado
 * api/associados/buscar_dados_completos.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        throw new Exception('Não autorizado');
    }
    
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID do associado não informado');
    }
    
    $associadoId = intval($_GET['id']);
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // CORRIGIDO: Buscar dados completos incluindo TODOS os campos da tabela Financeiro
    $stmt = $db->prepare("
        SELECT 
            a.*,
            e.cep,
            e.endereco,
            e.bairro,
            e.cidade,
            e.numero,
            e.complemento,
            m.corporacao,
            m.patente,
            m.categoria,
            m.lotacao,
            m.unidade,
            f.tipoAssociado,
            f.situacaoFinanceira,
            f.vinculoServidor,
            f.localDebito,
            f.agencia,
            f.operacao,
            f.contaCorrente,
            f.observacoes,
            f.doador,
            c.dataFiliacao,
            c.dataDesfiliacao
        FROM Associados a
        LEFT JOIN Endereco e ON a.id = e.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.id = ?
    ");
    
    $stmt->execute([$associadoId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        throw new Exception('Associado não encontrado');
    }
    
    // MELHORADO: Estrutura os dados de forma organizada
    $dadosEstruturados = [
        'dados_pessoais' => [
            'id' => $dados['id'],
            'nome' => $dados['nome'],
            'nasc' => $dados['nasc'],
            'sexo' => $dados['sexo'],
            'rg' => $dados['rg'],
            'cpf' => $dados['cpf'],
            'email' => $dados['email'],
            'telefone' => $dados['telefone'],
            'escolaridade' => $dados['escolaridade'],
            'estadoCivil' => $dados['estadoCivil'],
            'situacao' => $dados['situacao'],
            'indicacao' => $dados['indicacao'],
            'foto' => $dados['foto'],
            'pre_cadastro' => $dados['pre_cadastro'],
            'data_pre_cadastro' => $dados['data_pre_cadastro'],
            'data_aprovacao' => $dados['data_aprovacao']
        ],
        'endereco' => [
            'cep' => $dados['cep'],
            'endereco' => $dados['endereco'],
            'bairro' => $dados['bairro'],
            'cidade' => $dados['cidade'],
            'numero' => $dados['numero'],
            'complemento' => $dados['complemento']
        ],
        'dados_militares' => [
            'corporacao' => $dados['corporacao'],
            'patente' => $dados['patente'],
            'categoria' => $dados['categoria'],
            'lotacao' => $dados['lotacao'],
            'unidade' => $dados['unidade']
        ],
        'dados_financeiros' => [
            'tipoAssociado' => $dados['tipoAssociado'],
            'situacaoFinanceira' => $dados['situacaoFinanceira'],
            'vinculoServidor' => $dados['vinculoServidor'],
            'localDebito' => $dados['localDebito'],
            'agencia' => $dados['agencia'],
            'operacao' => $dados['operacao'],
            'contaCorrente' => $dados['contaCorrente'],
            'observacoes' => $dados['observacoes'],
            'doador' => intval($dados['doador'] ?? 0),
            'eh_doador' => ($dados['doador'] ?? 0) == 1 ? 'Sim' : 'Não'
        ],
        'contrato' => [
            'dataFiliacao' => $dados['dataFiliacao'],
            'dataDesfiliacao' => $dados['dataDesfiliacao']
        ]
    ];
    
    // Buscar dependentes
    $stmtDep = $db->prepare("
        SELECT * FROM Dependentes 
        WHERE associado_id = ? 
        ORDER BY nome ASC
    ");
    $stmtDep->execute([$associadoId]);
    $dependentes = $stmtDep->fetchAll(PDO::FETCH_ASSOC);
    
    $dadosEstruturados['dependentes'] = $dependentes;
    
    // Buscar redes sociais
    $stmtRedes = $db->prepare("
        SELECT * FROM Redes_sociais 
        WHERE associado_id = ? 
        ORDER BY id ASC
    ");
    $stmtRedes->execute([$associadoId]);
    $redesSociais = $stmtRedes->fetchAll(PDO::FETCH_ASSOC);
    
    $dadosEstruturados['redes_sociais'] = $redesSociais;
    
    // NOVO: Adicionar informações de status baseadas nos novos campos
    $dadosEstruturados['status_info'] = [
        'eh_pre_cadastro' => ($dados['pre_cadastro'] ?? 0) == 1,
        'tem_dados_financeiros' => !empty($dados['tipoAssociado']),
        'tem_observacoes_financeiras' => !empty($dados['observacoes']),
        'eh_doador' => ($dados['doador'] ?? 0) == 1,
        'situacao_cadastro' => $dados['situacao']
    ];
    
    // NOVO: Calcular idade se tem data de nascimento
    if (!empty($dados['nasc']) && $dados['nasc'] !== '0000-00-00') {
        $nascimento = new DateTime($dados['nasc']);
        $hoje = new DateTime();
        $dadosEstruturados['dados_pessoais']['idade'] = $nascimento->diff($hoje)->y;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $dadosEstruturados,
        'raw_data' => $dados // Mantém compatibilidade com código existente
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>