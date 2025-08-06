<?php
/**
 * API para buscar associado por CPF ou RG
 * api/recadastro/buscar_associado_recadastramento.php
 */

// Ajustar paths conforme necessário
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Receber dados via POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['documento'])) {
        throw new Exception('Documento não informado');
    }
    
    // Limpar documento (remover caracteres especiais)
    $documento = preg_replace('/[^0-9]/', '', $input['documento']);
    
    if (!$documento || strlen($documento) < 6) {
        throw new Exception('Documento inválido. Digite um CPF ou RG válido.');
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Preparar documento para busca
    // CPF pode estar formatado ou não no banco
    $cpfFormatado = '';
    if (strlen($documento) == 11) { // É um CPF
        $cpfFormatado = substr($documento, 0, 3) . '.' . 
                        substr($documento, 3, 3) . '.' . 
                        substr($documento, 6, 3) . '-' . 
                        substr($documento, 9, 2);
    }
    
    // Buscar por CPF (formatado ou não) ou RG
    $sql = "SELECT 
                a.id,
                a.nome,
                a.nasc,
                a.sexo,
                a.rg,
                a.cpf,
                a.email,
                a.situacao,
                a.escolaridade,
                a.estadoCivil,
                a.telefone,
                a.foto,
                a.indicacao,
                e.cep, 
                e.endereco, 
                e.numero, 
                e.complemento, 
                e.bairro, 
                e.cidade,
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
                c.dataFiliacao, 
                c.dataDesfiliacao
            FROM Associados a
            LEFT JOIN Endereco e ON a.id = e.associado_id
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Financeiro f ON a.id = f.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE (
                REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = :doc 
                OR a.cpf = :cpfFormatado
                OR REPLACE(REPLACE(REPLACE(a.rg, '.', ''), '-', ''), ' ', '') = :doc2
                OR a.rg = :doc3
            )
            AND a.pre_cadastro = 0
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':doc' => $documento, 
        ':cpfFormatado' => $cpfFormatado,
        ':doc2' => $documento,
        ':doc3' => $documento
    ]);
    
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$associado) {
        // Verificar se existe mas é pré-cadastro
        $sqlPre = "SELECT id, nome, pre_cadastro FROM Associados 
                   WHERE (
                       REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :doc 
                       OR REPLACE(REPLACE(REPLACE(rg, '.', ''), '-', ''), ' ', '') = :doc2
                   ) 
                   AND pre_cadastro = 1 
                   LIMIT 1";
        
        $stmtPre = $db->prepare($sqlPre);
        $stmtPre->execute([':doc' => $documento, ':doc2' => $documento]);
        $preCadastro = $stmtPre->fetch(PDO::FETCH_ASSOC);
        
        if ($preCadastro) {
            throw new Exception('Este associado ainda está em pré-cadastro. O recadastramento só está disponível para associados com cadastro definitivo.');
        } else {
            throw new Exception('Associado não encontrado. Verifique o documento digitado.');
        }
    }
    
    // Verificar situação do associado
    if ($associado['situacao'] != 'Filiado' && $associado['situacao'] != 'Ativo') {
        throw new Exception('Apenas associados ativos podem fazer recadastramento. Situação atual: ' . $associado['situacao']);
    }
    
    // Buscar dependentes
    $sqlDep = "SELECT 
                id,
                nome,
                data_nascimento,
                parentesco,
                sexo
               FROM Dependentes 
               WHERE associado_id = :id
               ORDER BY data_nascimento DESC";
    
    $stmtDep = $db->prepare($sqlDep);
    $stmtDep->execute([':id' => $associado['id']]);
    $associado['dependentes'] = $stmtDep->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar serviços atuais
    $sqlServ = "SELECT 
                    sa.id,
                    sa.servico_id,
                    sa.ativo,
                    sa.valor_aplicado,
                    sa.percentual_aplicado,
                    sa.data_adesao,
                    s.nome as servico_nome,
                    s.valor_base,
                    s.descricao
                FROM Servicos_Associado sa
                INNER JOIN Servicos s ON sa.servico_id = s.id
                WHERE sa.associado_id = :id
                AND sa.ativo = 1
                ORDER BY s.nome";
    
    $stmtServ = $db->prepare($sqlServ);
    $stmtServ->execute([':id' => $associado['id']]);
    $associado['servicos'] = $stmtServ->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar se já tem solicitação em andamento
    $sqlSolic = "SELECT id, status, data_solicitacao 
                 FROM Solicitacoes_Recadastramento 
                 WHERE associado_id = :id 
                 AND status NOT IN ('CONCLUIDO', 'CANCELADO')
                 ORDER BY data_solicitacao DESC 
                 LIMIT 1";
    
    $stmtSolic = $db->prepare($sqlSolic);
    $stmtSolic->execute([':id' => $associado['id']]);
    $solicitacaoEmAndamento = $stmtSolic->fetch(PDO::FETCH_ASSOC);
    
    if ($solicitacaoEmAndamento) {
        $associado['solicitacao_em_andamento'] = $solicitacaoEmAndamento;
    }
    
    // Log de sucesso
    error_log("Associado encontrado: ID {$associado['id']} - {$associado['nome']}");
    
    echo json_encode([
        'status' => 'success',
        'data' => $associado,
        'message' => 'Associado encontrado com sucesso!'
    ]);
    
} catch (Exception $e) {
    error_log("Erro na busca de associado: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}