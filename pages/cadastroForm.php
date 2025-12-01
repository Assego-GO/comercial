<?php

/**
 * Página de Serviços Financeiros - Sistema ASSEGO
 * pages/cadastroForm.php
 * VERSÃO COMPLETA COM TODAS AS FUNCIONALIDADES ORIGINAIS
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';
require_once './components/header.php';
require_once '../classes/Permissoes.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Serviços Financeiros - ASSEGO';

// Verificar permissões - FINANCEIRO, PRESIDÊNCIA OU PERMISSÃO COMERCIAL
$temPermissaoFinanceiro = false;
$motivoNegacao = '';
$isFinanceiro = false;
$isPresidencia = false;
$isComercialComPermissao = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES CADASTRO FORM ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));

// Primeiro verifica a permissão COMERCIAL_CRIAR_ASSOCIADO
if (Permissoes::tem('COMERCIAL_CRIAR_ASSOCIADO')) {
    $temPermissaoFinanceiro = true;
    $isComercialComPermissao = true;
    error_log("✅ Permissão concedida: Usuário tem COMERCIAL_CRIAR_ASSOCIADO");
}

// Se não tem a permissão específica, verifica departamento
if (!$temPermissaoFinanceiro && isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;

    if ($deptId == 10) { // Comercial
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 10)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } else {
        $motivoNegacao = 'Acesso restrito a: Setor Financeiro, Presidência ou usuários com permissão de criar associados.';
        error_log("❌ Acesso negado. Sem permissão COMERCIAL_CRIAR_ASSOCIADO e departamento não autorizado.");
    }
} else if (!$temPermissaoFinanceiro) {
    $motivoNegacao = 'Você não tem permissão para acessar este formulário.';
    error_log("❌ Sem permissão e sem departamento identificado");
}

// Log final
if (!$temPermissaoFinanceiro) {
    error_log("❌ ACESSO NEGADO AO FORMULÁRIO: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Tipo: " . 
        ($isComercialComPermissao ? "Comercial com permissão" : 
        ($isFinanceiro ? "Financeiro" : "Presidência")));
}

// Se não tem permissão, só renderiza a tela de acesso negado
if (!$temPermissaoFinanceiro) {
    $headerComponent = HeaderComponent::create([
        'usuario' => $usuarioLogado,
        'isDiretor' => $auth->isDiretor(),
        'activeTab' => 'financeiro',
        'notificationCount' => 0,
        'showSearch' => true
    ]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - ASSEGO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
    <body>
        <?php echo $headerComponent; ?>
        
        <div class="container" style="margin-top: 2rem;">
            <div class="card" style="max-width: 600px; margin: 2rem auto; text-align: center; padding: 3rem;">
                <div style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 style="color: #dc3545; margin-bottom: 1rem;">Acesso Negado</h2>
                <p style="color: #666; font-size: 1.1rem; margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($motivoNegacao); ?>
                </p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// VERIFICAÇÃO SE É MODO EDIÇÃO
// ============================================
$isEdit = isset($_GET['id']) && !empty($_GET['id']);
$associadoId = $isEdit ? intval($_GET['id']) : null;
$associadoData = [];

if ($isEdit) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

        // ==================================================
        // 1. BUSCA DADOS BÁSICOS DO ASSOCIADO
        // ==================================================
    $stmt = $db->prepare('SELECT * FROM Associados WHERE id = ? LIMIT 1');
        $stmt->execute([$associadoId]);
        $associadoData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$associadoData) {
            error_log("❌ Associado não encontrado - ID: " . $associadoId);
            echo '<div style="max-width:600px;margin:3rem auto;padding:2rem;border:1px solid #dc3545;background:#fff3f3;color:#dc3545;text-align:center;font-size:1.2rem;">';
            echo '<h2>Erro ao carregar dados</h2>';
            echo '<p>O associado de ID <b>' . htmlspecialchars($associadoId) . '</b> não foi encontrado no sistema.</p>';
            echo '<a href="dashboard.php" style="display:inline-block;margin-top:1.5rem;padding:0.7rem 2rem;background:#dc3545;color:#fff;text-decoration:none;border-radius:5px;">Voltar ao Dashboard</a>';
            echo '</div>';
            exit;
        }

        error_log("✅ Modo edição - Associado ID: " . $associadoId . " - Nome: " . $associadoData['nome']);
        error_log("✓ Dados básicos do associado carregados");

        // ==================================================
        // 2. BUSCA DADOS MILITARES
        // ==================================================
        $stmtMilitar = $db->prepare("SELECT * FROM Militar WHERE associado_id = ?");
        $stmtMilitar->execute([$associadoId]);
        $dadosMilitar = $stmtMilitar->fetch(PDO::FETCH_ASSOC);

        if ($dadosMilitar) {
            $associadoData['corporacao'] = $dadosMilitar['corporacao'];
            $associadoData['patente'] = $dadosMilitar['patente'];
            $associadoData['categoria'] = $dadosMilitar['categoria'];
            $associadoData['lotacao'] = $dadosMilitar['lotacao'];
            $associadoData['unidade'] = $dadosMilitar['unidade'];

            error_log("✓ Dados militares encontrados:");
            error_log("  - Patente: '" . ($dadosMilitar['patente'] ?? 'VAZIO') . "'");
            error_log("  - Corporação: '" . ($dadosMilitar['corporacao'] ?? 'VAZIO') . "'");
        } else {
            error_log("⚠ Nenhum dado militar encontrado. Criando registro...");

            $stmtInsert = $db->prepare("
                INSERT INTO Militar (associado_id, corporacao, patente, categoria, lotacao, unidade) 
                VALUES (?, '', '', '', '', '')
            ");
            $stmtInsert->execute([$associadoId]);

            $associadoData['corporacao'] = '';
            $associadoData['patente'] = '';
            $associadoData['categoria'] = '';
            $associadoData['lotacao'] = '';
            $associadoData['unidade'] = '';

            error_log("✓ Registro militar criado com valores vazios");
        }

        // ==================================================
        // 3. BUSCA DADOS DE ENDEREÇO
        // ==================================================
        $stmtEndereco = $db->prepare("SELECT * FROM Endereco WHERE associado_id = ?");
        $stmtEndereco->execute([$associadoId]);
        $dadosEndereco = $stmtEndereco->fetch(PDO::FETCH_ASSOC);

        if ($dadosEndereco) {
            $associadoData['cep'] = $dadosEndereco['cep'];
            $associadoData['endereco'] = $dadosEndereco['endereco'];
            $associadoData['bairro'] = $dadosEndereco['bairro'];
            $associadoData['cidade'] = $dadosEndereco['cidade'];
            $associadoData['numero'] = $dadosEndereco['numero'];
            $associadoData['complemento'] = $dadosEndereco['complemento'];
            error_log("✓ Dados de endereço carregados");
        }

        // ==================================================
        // 4. BUSCA DADOS FINANCEIROS
        // ==================================================
        $stmtFinanceiro = $db->prepare("SELECT * FROM Financeiro WHERE associado_id = ?");
        $stmtFinanceiro->execute([$associadoId]);
        $dadosFinanceiro = $stmtFinanceiro->fetch(PDO::FETCH_ASSOC);

        if ($dadosFinanceiro) {
            $associadoData['tipoAssociado'] = $dadosFinanceiro['tipoAssociado'];
            $associadoData['situacaoFinanceira'] = $dadosFinanceiro['situacaoFinanceira'];
            $associadoData['vinculoServidor'] = $dadosFinanceiro['vinculoServidor'];
            $associadoData['localDebito'] = $dadosFinanceiro['localDebito'];
            $associadoData['agencia'] = $dadosFinanceiro['agencia'];
            $associadoData['operacao'] = $dadosFinanceiro['operacao'];
            $associadoData['contaCorrente'] = $dadosFinanceiro['contaCorrente'];
            $associadoData['doador'] = $dadosFinanceiro['doador'];
            error_log("✓ Dados financeiros carregados");
        }

        // ==================================================
        // 5. BUSCA DADOS DE CONTRATO/FILIAÇÃO
        // ==================================================
        $stmtContrato = $db->prepare("SELECT * FROM Contrato WHERE associado_id = ?");
        $stmtContrato->execute([$associadoId]);
        $dadosContrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);

        if ($dadosContrato) {
            $associadoData['data_filiacao'] = $dadosContrato['dataFiliacao'];
            $associadoData['dataDesfiliacao'] = $dadosContrato['dataDesfiliacao'];
            error_log("✓ Dados de contrato carregados");
        }

        // ==================================================
        // 6. BUSCA DEPENDENTES
        // ==================================================
        $stmtDep = $db->prepare("SELECT * FROM Dependentes WHERE associado_id = ? ORDER BY nome ASC");
        $stmtDep->execute([$associadoId]);
        $dependentes = $stmtDep->fetchAll(PDO::FETCH_ASSOC);
        $associadoData['dependentes'] = $dependentes;
        error_log("✓ Dependentes carregados: " . count($dependentes));

        // ==================================================
        // DEBUG FINAL
        // ==================================================
        error_log("=== RESULTADO FINAL ===");
        error_log("Patente final: '" . ($associadoData['patente'] ?? 'NULL') . "'");
        error_log("Corporação final: '" . ($associadoData['corporacao'] ?? 'NULL') . "'");
        error_log("Total de campos carregados: " . count($associadoData));
        error_log("=== FIM BUSCA DADOS ===");
        
    } catch (Exception $e) {
    error_log("ERRO na busca de dados: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo '<div style="max-width:600px;margin:3rem auto;padding:2rem;border:1px solid #dc3545;background:#fff3f3;color:#dc3545;text-align:center;font-size:1.2rem;">';
    echo '<h2>Erro ao carregar dados do associado</h2>';
    echo '<p><b>Mensagem:</b> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre style="text-align:left;font-size:0.95rem;color:#333;background:#f8d7da;padding:1rem;border-radius:5px;overflow-x:auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '<a href="dashboard.php" style="display:inline-block;margin-top:1.5rem;padding:0.7rem 2rem;background:#dc3545;color:#fff;text-decoration:none;border-radius:5px;">Voltar ao Dashboard</a>';
    echo '</div>';
    exit;
    }

    $page_title = 'Editar Associado - ASSEGO (Setor Financeiro)';
}

// ============================================
// VERIFICAÇÃO AGREGADO - VERSÃO UNIFICADA
// ============================================
$isSocioAgregado = false;
$nomeResponsavelAgregado = '';
$dadosTitular = null;
$relacionamentoAgregado = null;

if ($isEdit && !empty($associadoData['cpf'])) {
    $cpfAgregado = preg_replace('/\D/', '', $associadoData['cpf']);
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    error_log("🔍 Verificando se é agregado - CPF: " . $cpfAgregado);
    
    try {
        // Busca se o associado é agregado e seus dados do titular
        // Agregados são identificados por Militar.corporacao = 'Agregados'
        $stmt = $db->prepare('
            SELECT 
                a.id,
                a.nome,
                a.cpf,
                a.associado_titular_id,
                m.corporacao,
                m.patente,
                titular.id as titular_id,
                titular.nome as titular_nome,
                titular.cpf as titular_cpf,
                titular.situacao as titular_situacao,
                titular.telefone as titular_telefone
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
            WHERE REPLACE(REPLACE(REPLACE(a.cpf, ".", ""), "-", ""), " ", "") = ?
            AND m.corporacao = "Agregados"
            LIMIT 1
        ');
        
        $stmt->execute([$cpfAgregado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row['associado_titular_id'])) {
            $isSocioAgregado = true;
            $relacionamentoAgregado = $row;
            
            // Nome do responsável
            $nomeResponsavelAgregado = !empty($row['titular_nome']) ? 
                $row['titular_nome'] : 'Titular não identificado';
            
            // CPF do titular
            $cpfTitular = !empty($row['titular_cpf']) ? 
                preg_replace('/\D/', '', $row['titular_cpf']) : '';
            
            // Formata CPF do titular
            if ($cpfTitular && strlen($cpfTitular) === 11) {
                $cpfTitularFormatado = substr($cpfTitular, 0, 3) . '.' . 
                                       substr($cpfTitular, 3, 3) . '.' . 
                                       substr($cpfTitular, 6, 3) . '-' . 
                                       substr($cpfTitular, 9, 2);
            } else {
                $cpfTitularFormatado = 'CPF não disponível';
            }
            
            // Monta array com dados do titular
            $dadosTitular = [
                'id' => $row['titular_id'],
                'nome' => $nomeResponsavelAgregado,
                'cpf' => $cpfTitular,
                'cpf_formatado' => $cpfTitularFormatado,
                'situacao' => !empty($row['titular_situacao']) ? $row['titular_situacao'] : 'Não identificada',
                'telefone' => !empty($row['titular_telefone']) ? $row['titular_telefone'] : ''
            ];
            
            error_log("✅ AGREGADO DETECTADO!");
            error_log("   - Agregado: " . $associadoData['nome'] . " (CPF: " . $cpfAgregado . ")");
            error_log("   - Titular: " . $nomeResponsavelAgregado . " (CPF: " . $cpfTitularFormatado . ")");
            error_log("   - Situação do Titular: " . $dadosTitular['situacao']);
            
        } else {
            error_log("ℹ️ Associado NÃO é agregado - CPF verificado: " . $cpfAgregado);
        }
        
    } catch (Exception $e) {
        error_log("❌ Erro ao verificar agregado: " . $e->getMessage());
    }
} else {
    if (!$isEdit) {
        error_log("ℹ️ Modo CRIAÇÃO - verificação de agregado não aplicável");
    } else {
        error_log("⚠️ CPF não disponível para verificar se é agregado");
    }
}
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Buscar serviços ativos
    $stmt = $db->prepare("SELECT id, nome, valor_base FROM Servicos WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar tipos de associado únicos ordenados
    $stmt = $db->prepare("
        SELECT DISTINCT tipo_associado 
        FROM Regras_Contribuicao 
        ORDER BY 
            CASE 
                WHEN tipo_associado = 'Contribuinte' THEN 1
                WHEN tipo_associado = 'Aluno' THEN 2
                WHEN tipo_associado = 'Soldado 1ª Classe' THEN 3
                WHEN tipo_associado = 'Soldado 2ª Classe' THEN 4
                WHEN tipo_associado = 'Agregado' THEN 5
                WHEN tipo_associado = 'Remido 50%' THEN 6
                WHEN tipo_associado = 'Remido' THEN 7
                WHEN tipo_associado = 'Benemerito' THEN 8
                ELSE 9
            END
    ");
    $stmt->execute();
    $tiposAssociado = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Buscar regras de contribuição para usar no JavaScript
    $stmt = $db->prepare("
        SELECT rc.tipo_associado, rc.servico_id, rc.percentual_valor, rc.opcional, s.nome as servico_nome 
        FROM Regras_Contribuicao rc 
        INNER JOIN Servicos s ON rc.servico_id = s.id 
        WHERE s.ativo = 1
        ORDER BY rc.tipo_associado, s.nome
    ");
    $stmt->execute();
    $regrasContribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se não há dados, cria os dados padrão
    if (empty($servicos) || empty($tiposAssociado) || empty($regrasContribuicao)) {
        // Chama a API para criar dados padrão
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../api/buscar_dados_servicos.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                // Recarrega os dados após criação
                $stmt = $db->prepare("SELECT id, nome, valor_base FROM Servicos WHERE ativo = 1 ORDER BY nome");
                $stmt->execute();
                $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $db->prepare("SELECT DISTINCT tipo_associado FROM Regras_Contribuicao ORDER BY tipo_associado");
                $stmt->execute();
                $tiposAssociado = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt = $db->prepare("
                    SELECT rc.tipo_associado, rc.servico_id, rc.percentual_valor, rc.opcional, s.nome as servico_nome 
                    FROM Regras_Contribuicao rc 
                    INNER JOIN Servicos s ON rc.servico_id = s.id 
                    WHERE s.ativo = 1
                    ORDER BY rc.tipo_associado, s.nome
                ");
                $stmt->execute();
                $regrasContribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar dados para serviços: " . $e->getMessage());
    $servicos = [];
    $tiposAssociado = [];
    $regrasContribuicao = [];
}

// Array com as lotações
$lotacoes = [
    "1. BATALHAO BOMBEIRO MILITAR",
    "1. BATALHAO DE POLICIA MILITAR AMBIENTAL DO ESTADO (BPMAmb)",
    "1. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "1. BATALHAO DE POLICIA MILITAR RODOVIARIO DO ESTADO DE GOIAS (BPMRv)",
    "1. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "1. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "1. COMPANHIA INDEPENDENTE DE POLICIA MILITAR AMBIENTAL",
    "1. COMPANHIA INDEPENDENTE DE POLICIA MILITAR RODOVIARIO",
    "1. COMPANHIA INDEPENDENTE POLICIA MILITAR DO ESTADO DE GOIAS",
    "1. DIRETORIA REGIONAL PRISIONAL - METROPOLITANA",
    "1. PELOTAO / 15. COMPANHIA DO CORPO DE BOMBEIROS MILITAR DO ESTADO DE GOIAS",
    "1. PELOTAO BOMBEIRO MILITAR",
    "1. REGIONAL DO CORPO DE BOMBEIROS MILITAR DE GOIANIA",
    "1. SECAO DO ESTADO MAIOR",
    "10. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "10. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "10. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "10. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "10. PELOTAO DE BOMBEIROS MILITAR",
    "10a COMPANHIA INDEPENDENTE POLICIA MILITAR",
    "11. BATALHAO BOMBEIRO MILITAR",
    "11. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "11. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "11. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "11. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "12. BATALHAO BOMBEIRO MILITAR",
    "12. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "12. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "12. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "12. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "13. BATALHAO BOMBEIRO MILITAR",
    "13. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "13. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "13. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "13. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DE GOIAS",
    "14. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "14. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "14. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "14. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "15. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "15. CIA INDEPENDENTE DE BOMBEIROS",
    "15. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "15. COMPANHIA INDEPENDENTE POLICIA MILITAR DO ESTADO DE GOIAS",
    "16. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "16. COMANDO REGIONAL DE PM DO ESTADO DE GOIAS",
    "16. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "16a COMPANHIA INDEPENDENTE DE POLICIA MILITAR/COMPANHIA DE POLICIAMENTO ESPECIALIZADO",
    "17. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "17. CIA INDEPENDENTE DE BOMBEIROS",
    "17. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "17. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "18. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "18. CIPM - COMPANHIA DE POLICIAMENTO ESPECIALIZADO",
    "18. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "19. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "19. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "19o COMANDO REGIONAL DE POLICIA MILITAR",
    "2. BATALHAO BOMBEIRO MILITAR",
    "2. BATALHAO DE POLICIA MILITAR DE GOIAS",
    "2. BATALHAO DE POLICIA MILITAR RODOVIARIO DO ESTADO DE GOIAS",
    "2. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "2. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "2. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "2. PELOTAO DE BOMBEIROS MILITAR",
    "20. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "20. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "21. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "21. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "22. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "22. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "23. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "23. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "24. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "24. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "25. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "25a COMPANHIA INDEPENDENTE BOMBEIRO MILITAR",
    "25a COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "26. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "26a COMPANHIA INDEPENDENTE BOMBEIRO MILITAR",
    "27. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "27. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "28. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "28. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "29. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "29. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "2a COMPANHIA DE POLICIA MILITAR RURAL",
    "3. BATALHAO DE BOMBEIROS MILITAR",
    "3. BATALHAO DE POLICIA MILITAR RODOVIARIO DO ESTADO DE GOIAS",
    "3. BATALHAO DE POLICIA MILITAR DE GOIAS",
    "3. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "3. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "3. PELOTAO DE BOMBEIROS MILITAR",
    "3. REGIONAL DO CORPO DE BOMBEIROS MILITAR DE ANAPOLIS",
    "30. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "31. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "31. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "32. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "32. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "33. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "33. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "34. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "34. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "35. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "36. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "36. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "37. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "38. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "39. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "39. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "3ª COMPANHIA INDEPENDENTE DE POLICIA MILITAR DE GOIAS",
    "3ª SEÇÃO DE RECRUTAMENTO E SELEÇÃO DE PESSOAL",
    "3o PELOTAO BOMBEIRO MILITAR",
    "4. BATALHAO DE BOMBEIROS MILITAR",
    "4. BATALHAO DE POLICIA MILITAR DE GOIAS",
    "4. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "4. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "4. PELOTAO BOMBEIRO MILITAR",
    "4. SECAO DO ESTADO MAIOR",
    "40. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "41. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "41.  COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "42.  COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "42o BATALHAO DE POLICIA MILITAR/01o CRPM",
    "43.  COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "44. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "45. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "46. COMPANHIA INDEPENDENTE POLICIA MILITAR DO ESTADO DE GOIAS",
    "47. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "48. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "4a COMPANHIA DE POLICIA MILITAR RURAL",
    "4a COMPANHIA DE POLICIAMENTO RURAL",
    "4a COMPANHIA DO COMANDO DE DIVISAS - BASE CABECEIRAS",
    "4ª COMPANHIA DE ROTAM",
    "4ª SECAO DE ADMINISTRACAO DE PESSOAL",
    "4o PELOTAO BOMBEIRO MILITAR",
    "5. BATALHAO DE BOMBEIROS MILITAR",
    "5. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "5. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "5. COMPANHIA BOMBEIRO MILITAR",
    "5. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "5. PELOTAO BOMBEIRO MILITAR",
    "5. SECAO DO ESTADO MAIOR",
    "5a COMPANHIA DE POLICIAMENTO RURAL",
    "5a COMPANHIA INDEPENDENTE DE POLICIA MILITAR AMBIENTAL",
    "6. BATALHAO DE BOMBEIROS MILITAR",
    "6. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "6. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "6. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "6. PELOTAO BOMBEIRO MILITAR",
    "6. SECAO DO ESTADO MAIOR",
    "6a COMPANHIA DO COMANDO DE DIVISAS - CIDADE OCIDENTAL",
    "6a COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "6o COMANDO REGIONAL BOMBEIRO MILITAR",
    "7. BATALHAO DE BOMBEIROS MILITAR",
    "7. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "7. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "7. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "7. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "7. PELOTAO BOMBEIRO MILITAR",
    "7. SECAO DO ESTADO MAIOR",
    "7a COMPANHIA INDEPENDENTE DE POLICIA MILITAR - (CPE)",
    "7o COMANDO REGIONAL BOMBEIRO MILITAR",
    "8. BATALHAO DE BOMBEIROS MILITAR",
    "8. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "8. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "8. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "8a SECAO DO ESTADO-MAIOR GERAL",
    "9. BATALHAO BOMBEIRO MILITAR",
    "9. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "9. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "9. COMPANHIA BOMBEIRO MILITAR",
    "9. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "9. PELOTAO BOMBEIRO MILITAR",
    "AGENFA LUZIANIA",
    "ASSESSORIA FUNDACIONAL - DOM PEDRO II",
    "ASSISTENCIA POLICIA MILITAR DO ESTADO DE GOIAS - M",
    "ASSISTENCIA POLICIAL MILITAR - ASSEMBLEIA LEGISLAT",
    "ASSISTENCIA POLICIAL MILITAR - SECRETARIA DE SEGUR",
    "ASSISTENCIA POLICIAL MILITAR - TRIBUNAL DE CONTAS",
    "ASSISTENCIA POLICIAL MILITAR - TRIBUNAL DE JUSTICA",
    "ASSISTENCIA POLICIAL MILITAR DA GOIAS PREVIDENCIA",
    "ASSISTENCIA POLICIAL MILITAR NO MPGO - GOI",
    "BASE ADMINISTRATIVA DA POLICIA MILITAR",
    "BATALHAO DE OPERACOES ESPECIAIS (BOPE)",
    "BATALHAO DE GIRO (GIRO)",
    "BATALHAO DE POLICIA MILITAR DE CHOQUE DO ESTADO DE GOIAS (CHOQUE)",
    "BATALHAO DE POLICIA MILITAR DE EVENTOS (BEPE)",
    "BATALHAO DE POLICIA MILITAR DE TERMINAL (TERMINAL)",
    "BATALHAO DE POLICIA MILITAR DE TRANSITO DO ESTADO DE GOIAS (BPTRAN)",
    "BATALHAO DE POLICIA MILITAR ESCOLAR DO ESTADO DE GOIAS (ESCOLAR)",
    "BATALHAO DE POLICIA MILITAR FAZENDARIA",
    "BATALHAO DE POLICIA MILITAR MARIA DA PENHA - CPC",
    "BATALHAO DE POLICIA MILITAR RURAL/COC",
    "BATALHAO DE PROTECAO SOCIOAMBIENTA",
    "BATALHAO DE ROTAM (ROTAM)",
    "BATALHAO DE SALVAMENTO EM EMERGENCIA",
    "CENTRO  DE MANUTENCAO",
    "CENTRO DE INSTRUCAO DA POLICIA MILITAR DE GOIAS",
    "CENTRO DE OPERACOES AEREAS",
    "CENTRO DE OPERACOES DA POLICIA MILITAR DO ESTADO DO ESTADO DE GOIAS",
    "CENTRO DE POLICIA COMUNITARIA",
    "CENTRO EST. DE ATEND. OP. DE BOMBEIROS",
    "CENTRO INTEGRADO DE OPERACOES ESTRATEGICAS POLICIA",
    "CHEFIA DA 2a SECAO DO ESTADO-MAIOR ESTRATEGICO PM/2",
    "CHEFIA DO ESTADO-MAIOR ESTRATEGICO",
    "COL DA PM DO EST DE GO - BENEDITA B DE ANDRADE– GO",
    "COL DA PM DO EST DE GO - PROF IVAN F PIRES DO RIO",
    "COL DA PM DO ESTADO DE GOIAS - JOAO AUGUSTO PERILO",
    "COLÉGIO DA PM DO ESTADO DE GOIÁS - XAVIER DE ALMEI",
    "COLÉGIO DA POLÍCIA MILITAR DO ESTADO DE GOIÁS - AM",
    "COLÉGIO DA POLÍCIA MILITAR DO ESTADO DE GOIÁS - DE",
    "COLÉGIO ESTADUAL DA POLÍCIA MILITAR DE GOIÁS JOSÉ",
    "COLEGIO DA PM DO EST DE GO -  JUSSARA",
    "COLEGIO DA PM DO EST DE GO -  PALMEIRAS",
    "COLEGIO DA PM DO EST DE GO - APARECIDA DE GOIANIA",
    "COLEGIO DA PM DO EST DE GO - ARLINDO COSTA",
    "COLEGIO DA PM DO EST DE GO - CALDAS NOVAS",
    "COLEGIO DA PM DO EST DE GO - DOM PRUDENCIO - POSSE",
    "COLEGIO DA PM DO EST DE GO - FORMOSA",
    "COLEGIO DA PM DO EST DE GO - HELIO VELOSO - CERES",
    "COLEGIO DA PM DO EST DE GO - JATAI",
    "COLEGIO DA PM DO EST DE GO - MAJOR OSCAR ALVELOS",
    "COLEGIO DA PM DO EST DE GO - MARIA HELENY PERILLO",
    "COLEGIO DA PM DO EST DE GO - MIRIAM B. FERREIRA",
    "COLEGIO DA PM DO EST DE GO - SENADOR CANEDO",
    "COLEGIO DA PM DO EST DE GO ARISTON GOMES DA SILVA",
    "COLEGIO DA PM DO EST DE GO – APARECIDA DE GOIANIA",
    "COLEGIO DA PM DO EST DE GO – GOIANESIA",
    "COLEGIO DA PM DO EST DE GO – INHUMAS",
    "COLEGIO DA PM DO EST DE GO – JARAGUA",
    "COLEGIO DA PM DO EST DE GO – NOVO GAMA",
    "COLEGIO DA PM DO EST DE GO – VALPARAISO",
    "COLEGIO DA PM DO EST DE GO GERALDA ANDRADE MARTINS",
    "COLEGIO DA PM DO EST DE GO JOSE S O GOIANIRA",
    "COLEGIO DA PM DO EST DE GOIAS - JARDIM GUANABARA",
    "COLEGIO DA PM DO ESTADO DE GOIAS - COLINA AZUL",
    "COLEGIO DA PM DO ESTADO DE GOIAS - GOIATUBA",
    "COLEGIO DA PM DO ESTADO DE GOIAS - ITAUCU",
    "COLEGIO DA PM DO ESTADO DE GOIAS - MANSOES PARAISO",
    "COLEGIO DA PM DO ESTADO DE GOIAS - WALDEMAR MUNDIM",
    "COLEGIO DA PM DO ESTADO DE GOIAS DR NEGREIRO",
    "COLEGIO DA POLICIA MILITAR DE GOIAS - PADRE PELAGIO/GOIANIRA",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS - PEDRO LUDOVICO TEIXEIRA - TRINDADE",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS - PO",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS - QU",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS - VA",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS -ANA",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS -AYR",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS -HUG",
    "COLEGIO DA POLICIA MILITAR DO ESTADO DE GOIAS -RIO",
    "COLEGIO EST. DA PM - CASTELO BRANCO - TRINDADE",
    "COLEGIO EST. PM - 13 DE MAIO",
    "COLEGIO EST. PM - AUGUSTA MACHADO - HIDROLÂNDIA",
    "COLEGIO EST. PM - DOUTOR JOSE FELICIANO FERREIRA",
    "COLEGIO EST. PM - PASTOR JOSE ANTERO RIBEIRO",
    "COLEGIO EST. PM - PROFESSOR JOSE DOS REIS MENDES",
    "COLEGIO EST. PM - ROSA TURISCO DE ARAUJO - ANICUNS",
    "COMANDO DA ACADEMIA DE POLICIA MILITAR",
    "COMANDO DA ACADEMIA E ENSINO BOMBEIRO MILITAR",
    "COMANDO DE APOIO LOGISTICO",
    "COMANDO DE APOIO LOGISTICO E TECNOLOGIA DA INFORMA",
    "COMANDO DE ATIVIDADES TECNICAS",
    "COMANDO DE CORREICOES E DISCIPLINA",
    "COMANDO DE CORREICOES E DISCIPLINA DA POLICIA MILI",
    "COMANDO DE ENSINO POLICIAL MILITAR",
    "COMANDO DE GESTAO E FINANCAS",
    "COMANDO DE OPERACOES DE DEFESA CIVIL",
    "COMANDO DE OPERACOES DE DIVISA (COD)",
    "COMANDO DE OPERACOES DE RECOBRIMENTO",
    "COMANDO DE POLICIAMENTO AMBIENTAL",
    "COMANDO DE POLICIAMENTO ESPECIALIZADO",
    "COMANDO DE SAUDE",
    "COMANDO DE SAUDE BOMBEIRO MILITAR",
    "COMANDO GERAL DA POLICIA MILITAR",
    "COMISSAO DE PROMOCAO DE PRACAS",
    "COMISSAO PERMANENTE DE MEDALHAS",
    "COMPANHIA AMBIENTAL DE OPERACOES COM PRODUTOS PERIGOSOS",
    "COMPANHIA DE POLICIAMENTO COM COES",
    "COMPANHIA DE POLICIAMENTO ESPECIALIZADO - 20aCIPM - CPE(11oCRPM)",
    "COMPANHIA INDEPENDENTE BOMBEIRO MILITAR DE GOIANIR",
    "COMPANHIA INDEPENDENTE BOMBEIRO MILITAR DE NERÓPOL",
    "COMPANHIA INDEPENDENTE DE OPERACOES ESPECIAIS",
    "COORDENACAO DE GESTAO DE PESSOAS",
    "COORDENACAO TCO/PM",
    "CORPO MUSICAL DA POLICIA MILITAR DO ESTADO DE GOIAS",
    "CPMG 5 DE JANEIRO/CEPM",
    "DIRETORIA DE MILITARES",
    "E.E. VICENCA MARIA DE JESUS",
    "GAB DA SEC  DE EST DE AGRICULTURA",
    "GABINETE DO COMANDANTE GERAL DO CBMGO",
    "GABINETE DO ESTADO-MAIOR GERAL",
    "GABINETE DO SECRETARIO",
    "GABINETE DO SECRETARIO-CHEFE",
    "GERENCIA DA SECRETARIA GERAL",
    "GERENCIA DE AJUDANCIA DE ORDENS 3",
    "GERENCIA DE CONTABILIDADE",
    "GERENCIA DE EXECUCAO ORCAMENTARIA E FINANCEIRA",
    "GERENCIA DE FOLHA DE PAGAMENTO DE BENEFICIOS",
    "GERENCIA DE GESTAO DE ATIVOS",
    "GERENCIA DE GESTAO DE PESSOAS E APOIO LOGISTICO",
    "GERENCIA DE INFORMATICA E TELECOMUNICACOES",
    "GERENCIA DE LICITACOES",
    "GERENCIA DE OPERACOES DE INTELIGENCIA",
    "GERENCIA DE PENSAO E DIREITOS DE MILITARES",
    "GERENCIA DE PLANEJAMENTO E GESTAO ESTRATEGICA",
    "GERENCIA DE SEGURANCA",
    "GERENCIA DE SEGURANCA DE VOO E CONTROLE DE DADOS A",
    "GERENCIA DE SEGURANCA E MONITORAMENTO",
    "GERENCIA DE SEGURANCA PESSOAL, FISICA E DE INSTALA",
    "GERENCIA DE TRANSPORTE , OPERACIONAL E ADMINISTRAT",
    "GERENCIA DO OBSERVATORIO DE SEGURANCA PUBLICA",
    "GRUPAMENTO DE POLICIA MILITAR AEREO ESTADO DE GOIAS",
    "GRUPAMENTO DE RADIO PATRULHA AEREA",
    "NAO IDENTIFICADO",
    "OITAVA SECAO DO ESTADO MAIOR",
    "PELOTAO BOMBEIRO MILITAR DE SILVANIA",
    "POSTO DE POLICIAMENTO RODOVIARIO DA GO 010 KM 162 - LUZIANIA",
    "POSTO DE POLICIAMENTO RODOVIARIO DA GO 080 KM 139 - GOIANESIA",
    "POSTO DE POLICIAMENTO RODOVIARIO DA GO 080 KM 203 - BARRO ALTO",
    "POSTO DE POLICIAMENTO RODOVIARIO DA GO 118 KM 095 - SAO JOAO D ALIANCA",
    "POSTO DE POLICIAMENTO RODOVIARIO DA GO 338 KM 043 - PIRENOPOLIS",
    "PRIMEIRA SECAO DO ESTADO MAIOR",
    "PRIMEIRO BATALHAO DE POLICIA MILITAR DE OPERACOES",
    "QUARTA SECAO DO ESTADO MAIOR",
    "QUARTEL DA AJUDANCIA GERAL POLICIA MILITAR ESTADO DE GOIAS",
    "QUARTEL DO COMANDO GERAL",
    "QUINTA SECAO DO ESTADO MAIOR",
    "REGIMENTO DE POLICIA MONTADA DO ESTADO DE GOIAS",
    "SECAO PARLAMENTAR NO CONGRESSO NACIONAL",
    "SECRETARIA DE ESTADO DA CASA MILITAR",
    "SEGUNDA SECAO DO ESTADO MAIOR",
    "SENADOR ONOFRE QUINAN",
    "SETIMA SECAO DO ESTADO MAIOR",
    "SEXTA SECAO DO ESTADO MAIOR",
    "SUBCOMANDANTE-GERAL DA POLICIA MILITAR",
    "SUBCOMANDO-GERAL DO CORPO DE BOMBEIROS MILITAR",
    "SUBCONTROLADORIA DE GOVERNO ABERTO E OUVIDORIA GERAL",
    "SUPERINTENDENCIA DE ACOES E OPERACOES INTEGRADAS",
    "SUPERINTENDENCIA DE ADMINISTRACAO DO PALACIO PEDRO",
    "SUPERINTENDENCIA DE GESTAO, PLANEJAMENTO E FINANCA",
    "SUPERINTENDENCIA DE INTELIGENCIA",
    "SUPERINTENDENCIA DE SEGURANCA PENITENCIARIA",
    "TERCEIRA SECAO DO ESTADO MAIOR"
];

// Array de patentes com encoding correto E hífens corretos (sem duplicação)
$patentes = [
    'Praças' => [
        'Aluno Soldado',
        'Soldado 2ª Classe',
        'Soldado 1ª Classe',
        'Cabo',
        'Terceiro Sargento',
        'Terceiro-Sargento',
    
        'Segundo-Sargento',
        'Primeiro Sargento',
        'Primeiro-Sargento',
        'Subtenente',
        'Suboficial'
    ],
    'Oficiais' => [
        'Cadete',
        'Aluno Oficial',
        'Aspirante-a-Oficial',
        'Segundo-Tenente',
        'Primeiro-Tenente',
        'Capitão',
        'Major',
        'Tenente-Coronel',
        'Coronel'
    ],
    'Outros' => [
        'Civil'
    ]
];

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
    'notificationCount' => 0,
    'showSearch' => true
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- jQuery Mask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS Files -->
    <link rel="stylesheet" href="estilizacao/cadastroForm.css">
    <link rel="stylesheet" href="estilizacao/autocomplete.css">

    <!-- CSS Adicional para botões de salvar -->
    <style>
        .btn-save-step {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-save-step:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .btn-save-step:active {
            transform: translateY(0);
        }

        .btn-save-step.saving {
            opacity: 0.7;
            cursor: wait;
        }

        .btn-save-step.saved {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
        }

        .form-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            background: var(--white);
            border-top: 1px solid var(--gray-200);
            border-radius: 0 0 16px 16px;
            margin-top: 2rem;
        }

        .nav-buttons-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-buttons-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .step-save-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--success);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .step-save-indicator.show {
            opacity: 1;
        }

        /* ADIÇÃO ESPECÍFICA PARA INDICAR QUE É O SETOR FINANCEIRO */
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 0 0 8px 8px;
        }

        .page-header {
            position: relative;
            background: rgba(40, 167, 69, 0.05);
            border: 1px solid rgba(40, 167, 69, 0.2);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .setor-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
    </style>

    <!-- Passar dados para o JavaScript -->
    <script>
        // Dados essenciais para o JavaScript
        window.pageData = {
            isEdit: <?php echo $isEdit ? 'true' : 'false'; ?>,
            associadoId: <?php echo $associadoId ? $associadoId : 'null'; ?>,
            regrasContribuicao: <?php echo json_encode($regrasContribuicao); ?>,
            servicos: <?php echo json_encode($servicos); ?>,
            associadoData: <?php echo json_encode($associadoData); ?>
        };
    </script>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando...</div>
    </div>

    <!-- Header Component -->
    <?php $headerComponent->render(); ?>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <nav style="display: flex; align-items: center; gap: 1rem;">
            <button type="button" class="btn-breadcrumb-back" onclick="window.location.href='dashboard.php'" title="Voltar ao Dashboard">
                <i class="fas fa-arrow-left"></i>
            </button>
            <ol class="breadcrumb-custom">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="dashboard.php">Setor Financeiro</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li class="active"><?php echo $isEdit ? 'Editar' : 'Nova Filiação'; ?></li>
            </ol>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header Simples -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 700; color: #343a40; margin: 0 0 0.5rem 0;">
                    <?php echo $isEdit ? 'Editar Associado' : 'Filiar Novo Associado'; ?>
                </h1>
                <p style="color: #6c757d; margin: 0; font-size: 0.95rem;">
                    <?php echo $isEdit ? 'Atualize os dados do associado' : 'Preencha todos os campos obrigatórios para filiar um novo associado'; ?>
                </p>
            </div>
            <button type="button" class="btn-dashboard" onclick="window.location.href='dashboard.php'">
                <i class="fas fa-arrow-left"></i>
                Voltar ao Dashboard
                <span style="font-size: 0.7rem; opacity: 0.8; margin-left: 0.5rem;">(ESC)</span>
            </button>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer">
        <?php if ($isSocioAgregado): ?>
            <div class="alert alert-warning" style="font-size:1.1rem;">
                <b>Atenção:</b> Este associado é um <b>Sócio Agregado</b>.<br>
                Responsável: <b><?php echo htmlspecialchars($nomeResponsavelAgregado); ?></b>
            </div>
        <?php endif; ?>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Progress Bar -->
            <div class="progress-bar-container">
                <div class="progress-steps">
                    <div class="progress-line" id="progressLine"></div>

                    <div class="step active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Dados Pessoais</div>
                    </div>

                    <div class="step" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Dados Militares</div>
                    </div>

                    <div class="step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Endereço</div>
                    </div>

                    <div class="step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Financeiro</div>
                    </div>

                    <div class="step" data-step="5">
                        <div class="step-circle">5</div>
                        <div class="step-label">Dependentes</div>
                    </div>

                    <div class="step" data-step="6">
                        <div class="step-circle">6</div>
                        <div class="step-label">Revisão</div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form id="formAssociado" class="form-content" enctype="multipart/form-data">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $associadoId; ?>">
                <?php endif; ?>

                <!-- Step 1: Dados Pessoais -->
                <div class="section-card active" data-step="1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Pessoais</h2>
                            <p class="section-subtitle">Informações básicas do associado</p>
                        </div>
                    </div>

                    <div class="form-grid">
                <!-- Agregado: Checkbox e CPF do Titular -->
            <?php if (!$isSocioAgregado): ?>
                <div class="form-group agregado-toggle-row" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <input type="checkbox" id="isAgregado" name="isAgregado" onchange="toggleAgregadoCampos()" style="width: 22px; height: 22px; accent-color: #1976d2;">
                    <label for="isAgregado" style="margin: 0; font-size: 1.15rem; font-weight: 500; color: #222;"> Cadastrar como Agregado <br> <p style="margin: 0; font-size: 12px; color: #e80c0cff;"><strong> * Caso o Associado ja for Agregado ignore esse checkbox </strong></p></br></label>
                   
                </div>

                <div class="form-group full-width agregado-campos" id="campoCpfTitular" style="display: none; margin-bottom: 1.5rem;">
                    <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-end;">
                        <div style="flex: 1 1 220px; min-width: 220px;">
                            <label class="form-label">CPF do Titular <span class="required">*</span></label>
                            <input type="text" 
                                class="form-input" 
                                name="cpfTitular" 
                                id="cpfTitular" 
                                placeholder="000.000.000-00" 
                                maxlength="14" 
                                autocomplete="off">
                            <input type="hidden" id="associadoTitular" name="associadoTitular" value="">
                        </div>
                        <div style="flex: 2 1 320px; min-width: 220px;">
                            <label class="form-label">Nome do Sócio Titular</label>
                            <input type="text" 
                                class="form-input" 
                                id="nomeTitularInfo" 
                                name="nomeTitularInfo" 
                                placeholder="Nome do titular será preenchido automaticamente" 
                                readonly 
                                style="background: #f5f5f5; color: #666; font-weight: 600;">
                        </div>
                    </div>
                    <span class="form-error" id="erroCpfTitular" style="display:none; margin-top: 0.5rem; color: #dc3545; font-size: 0.875rem;"></span>
                </div>
            <?php endif; ?>
                        <div class="form-group full-width">
                            <label class="form-label">
                                Nome Completo <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="nome" id="nome" required
                                value="<?php echo $associadoData['nome'] ?? ''; ?>"
                                placeholder="Digite o nome completo do associado">
                            <span class="form-error">Por favor, insira o nome completo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Nascimento <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="nasc" id="nasc" required
                                value="<?php echo $associadoData['nasc'] ?? ''; ?>">
                            <span class="form-error">Por favor, insira a data de nascimento</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Sexo <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_m" value="M" required <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'M') ? 'checked' : ''; ?>>
                                    <label for="sexo_m">Masculino</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_f" value="F" required <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'F') ? 'checked' : ''; ?>>
                                    <label for="sexo_f">Feminino</label>
                                </div>
                            </div>
                            <span class="form-error">Por favor, selecione o sexo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Estado Civil
                            </label>
                            <select class="form-input form-select" name="estadoCivil" id="estadoCivil">
                                <option value="">Selecione...</option>
                                <option value="Solteiro(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Solteiro(a)') ? 'selected' : ''; ?>>Solteiro(a)
                                </option>
                                <option value="Casado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Casado(a)') ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Divorciado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Divorciado(a)') ? 'selected' : ''; ?>>Divorciado(a)
                                </option>
                                <option value="Separado(a) Judicialmente" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Separado(a) Judicialmente') ? 'selected' : ''; ?>>Separado(a) Judicialmente</option>
                                <option value="Viúvo(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Viúvo(a)') ? 'selected' : ''; ?>>Viúvo(a)</option>
                                <option value="União Estável" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'União Estável') ? 'selected' : ''; ?>>União Estável
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                RG <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="rg" id="rg" required
                                value="<?php echo $associadoData['rg'] ?? ''; ?>" placeholder="Número do RG">
                            <span class="form-error">Por favor, insira o RG</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="cpf" id="cpf" required
                                value="<?php echo $associadoData['cpf'] ?? ''; ?>" placeholder="000.000.000-00">
                            <span class="form-error">Por favor, insira um CPF válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="telefone" id="telefone" required
                                value="<?php echo $associadoData['telefone'] ?? ''; ?>" placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o telefone</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail
                            </label>
                            <input type="email" class="form-input" name="email" id="email"
                                value="<?php echo $associadoData['email'] ?? ''; ?>" placeholder="email@exemplo.com">
                            <span class="form-error">Por favor, insira um e-mail válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Escolaridade
                            </label>
                            <select class="form-input form-select" name="escolaridade" id="escolaridade">
                                <option value="">Selecione...</option>
                                <option value="Fundamental Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Incompleto') ? 'selected' : ''; ?>>
                                    Fundamental Incompleto</option>
                                <option value="Fundamental Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Completo') ? 'selected' : ''; ?>>
                                    Fundamental Completo</option>
                                <option value="Médio Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Incompleto') ? 'selected' : ''; ?>>Médio
                                    Incompleto</option>
                                <option value="Médio Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Completo') ? 'selected' : ''; ?>>Médio
                                    Completo</option>
                                <option value="Superior Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Incompleto') ? 'selected' : ''; ?>>
                                    Superior Incompleto</option>
                                <option value="Superior Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Completo') ? 'selected' : ''; ?>>Superior
                                    Completo</option>
                                <option value="Pós-graduação" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Pós-graduação') ? 'selected' : ''; ?>>Pós-graduação
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Indicado por
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Nome da pessoa que indicou o associado"></i>
                            </label>
                            <div class="autocomplete-container" style="position: relative;">
                                <input type="text" class="form-input" name="indicacao" id="indicacao"
                                    value="<?php echo $associadoData['indicacao'] ?? ''; ?>"
                                    placeholder="Digite o nome de quem indicou..." autocomplete="off">
                                <div id="indicacaoSuggestions" class="autocomplete-suggestions"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Situação <span class="required">*</span>
                            </label>
                            <select class="form-input form-select" name="situacao" id="situacao" required>
                                <option value="Filiado" <?php echo (!isset($associadoData['situacao']) || $associadoData['situacao'] == 'Filiado') ? 'selected' : ''; ?>>Filiado</option>
                                <option value="Desfiliado" <?php echo (isset($associadoData['situacao']) && $associadoData['situacao'] == 'Desfiliado') ? 'selected' : ''; ?>>Desfiliado</option>
                               
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Filiação <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="dataFiliacao" id="dataFiliacao" required
                                value="<?php echo $associadoData['data_filiacao'] ?? date('Y-m-d'); ?>">
                            <span class="form-error">Por favor, insira a data de filiação</span>
                        </div>


                        <div class="form-group full-width">
                            <label class="form-label">
    Foto do Associado <span style="color: #6c757d; font-weight: normal;">(opcional)</span>
</label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <?php if (isset($associadoData['foto']) && $associadoData['foto']): ?>
                                        <?php
                                        // Corrige o caminho da foto
                                        $fotoPath = $associadoData['foto'];
                                        if (!str_starts_with($fotoPath, 'http') && !str_starts_with($fotoPath, '../')) {
                                            $fotoPath = '../' . $fotoPath;
                                        }
                                        ?>
                                        <img src="<?php echo $fotoPath; ?>" alt="Foto do associado" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="photo-preview-placeholder">
                                            <i class="fas fa-camera"></i>
                                            <p>Sem foto</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <input type="file" name="foto" id="foto" accept="image/*" style="display: none;">
                                    <button type="button" class="photo-upload-btn"
                                        onclick="document.getElementById('foto').click();">
                                        <i class="fas fa-upload"></i>
                                        Escolher Foto
                                    </button>
                                    <p class="text-muted mt-2" style="font-size: 0.75rem;">
                                        Formatos aceitos: JPG, PNG, GIF<br>
                                        Tamanho máximo: 5MB
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Campo para upload da ficha assinada - DISPONÍVEL SEMPRE -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Ficha de Filiação Assinada 
                                <?php if (!$isEdit): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Anexe a foto ou PDF da ficha preenchida e assinada pelo associado"></i>
                            </label>
                            <div class="ficha-upload-container"
                                style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 2rem; border-radius: 16px; border: 2px dashed #4caf50;">
                                <div style="display: flex; align-items: center; gap: 2rem;">
                                    <div class="ficha-preview" id="fichaPreview"
                                        style="width: 200px; height: 250px; background: var(--white); border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 2px solid #4caf50;">
                                        <div class="ficha-preview-placeholder"
                                            style="text-align: center; color: #4caf50;">
                                            <i class="fas fa-file-contract"
                                                style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                            <p style="font-weight: 600;">Ficha de Filiação</p>
                                            <p style="font-size: 0.875rem;">
                                                <?php echo $isEdit ? 'Anexar nova ficha (opcional)' : 'Nenhum arquivo anexado'; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div style="flex: 1;">
                                        <h4 style="color: #2e7d32; margin-bottom: 1rem;">
                                            <i class="fas fa-<?php echo $isEdit ? 'plus-circle' : 'check-circle'; ?>"></i> 
                                            <?php echo $isEdit ? 'Documento Opcional' : 'Documento Obrigatório'; ?>
                                        </h4>
                                        <p style="color: #1b5e20; margin-bottom: 1rem;">
                                            <?php if ($isEdit): ?>
                                                Você pode anexar uma nova ficha de filiação que será enviada automaticamente para aprovação da presidência.
                                                Este processo é opcional para associados em edição.
                                            <?php else: ?>
                                                É obrigatório anexar a ficha de filiação preenchida e assinada pelo associado.
                                                Este documento será enviado automaticamente para aprovação da presidência.
                                            <?php endif; ?>
                                        </p>

                                        <input type="file" name="ficha_assinada" id="ficha_assinada"
                                            accept=".pdf,.jpg,.jpeg,.png" style="display: none;" 
                                            <?php echo $isEdit ? '' : 'required'; ?>>

                                        <button type="button" class="btn"
                                            onclick="document.getElementById('ficha_assinada').click();"
                                            style="background: #4caf50; color: white; border: none; padding: 0.875rem 1.5rem; border-radius: 12px; font-weight: 600; cursor: pointer;">
                                            <i class="fas fa-upload"></i> 
                                            <?php echo $isEdit ? 'Anexar Nova Ficha' : 'Anexar Ficha Assinada'; ?>
                                        </button>

                                        <p style="font-size: 0.75rem; color: #2e7d32; margin-top: 0.5rem;">
                                            Formatos aceitos: PDF, JPG, PNG | Tamanho máximo: 10MB
                                        </p>
                                    </div>
                                </div>

                                <!-- Campo hidden para sempre enviar automaticamente -->
                                <input type="hidden" name="enviar_presidencia" id="enviar_presidencia" value="1">

                                <!-- Informação visual de que será enviado automaticamente -->
                                <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(76, 175, 80, 0.1); border-radius: 8px; border-left: 4px solid #4caf50;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; color: #2e7d32;">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Envio Automático Ativado</strong>
                                    </div>
                                    <p style="font-size: 0.875rem; color: #2e7d32; margin-top: 0.5rem; margin-left: 1.5rem;">
                                        <?php if ($isEdit): ?>
                                            Se uma ficha for anexada, ela será enviada automaticamente para aprovação da presidência após a atualização do cadastro.
                                        <?php else: ?>
                                            A ficha de filiação será enviada automaticamente para aprovação da presidência após a conclusão do cadastro.
                                            Este processo é obrigatório para validar a filiação do novo associado.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Dados Militares -->
                <div class="section-card" data-step="2">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Militares</h2>
                            <p class="section-subtitle">Informações sobre a carreira militar</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Corporação
                            </label>
                            <select class="form-input form-select" name="corporacao" id="corporacao">
                                <option value="">Selecione...</option>
                                <option value="Polícia Militar" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Militar') ? 'selected' : ''; ?>>Polícia Militar</option>
                                <option value="Bombeiro Militar" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Bombeiro Militar') ? 'selected' : ''; ?>>Bombeiro Militar</option>
                            </select>
                        </div>

                        <div class="form-group">
    <label class="form-label">Patente</label>
    <select class="form-input form-select" name="patente" id="patente" data-current-value="<?php echo isset($associadoData['patente']) ? htmlspecialchars($associadoData['patente'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        <option value="">Selecione...</option>
        
        <!-- 🆕 OPÇÃO "NENHUMA" ADICIONADA -->
        <option value="Nenhuma" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Nenhuma') ? 'selected' : ''; ?>>
            Nenhuma
        </option>
        
        <?php
        // Lista todas as patentes sem grupos
        $todasPatentes = array();
        foreach ($patentes as $grupo => $listPatentes) {
            foreach ($listPatentes as $patente) {
                $todasPatentes[] = $patente;
            }
        }
        sort($todasPatentes);

        foreach ($todasPatentes as $patente): ?>
            <option value="<?php echo htmlspecialchars($patente, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == $patente) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($patente, ENT_QUOTES, 'UTF-8'); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                        <div class="form-group">
                            <label class="form-label">
                                Situação Funcional
                            </label>
                            <select class="form-input form-select" name="categoria" id="categoria">
                                <option value="">Selecione...</option>
                                <option value="Ativa" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Ativa') ? 'selected' : ''; ?>>Ativa</option>
                                <option value="Reserva" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Reserva') ? 'selected' : ''; ?>>Reserva</option>
                                <option value="Pensionista" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
                                <option value="Afastado" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Afastado') ? 'selected' : ''; ?>>Afastado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Lotação
                            </label>
                            <select class="form-input form-select" name="lotacao" id="lotacao">
                                <option value="">Selecione...</option>
                                <?php foreach ($lotacoes as $lotacao): ?>
                                    <option value="<?php echo htmlspecialchars($lotacao); ?>"
                                        <?php echo (isset($associadoData['lotacao']) && $associadoData['lotacao'] == $lotacao) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lotacao); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Unidade
                            </label>
                            <input type="text" class="form-input" name="unidade" id="unidade"
                                value="<?php echo $associadoData['unidade'] ?? ''; ?>"
                                placeholder="Unidade em que serve/serviu">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Endereço -->
                <div class="section-card" data-step="3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Endereço</h2>
                            <p class="section-subtitle">Dados de localização do associado</p>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="cep-search-container">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">
                                    CEP
                                </label>
                                <input type="text" class="form-input" name="cep" id="cep"
                                    value="<?php echo $associadoData['cep'] ?? ''; ?>" placeholder="00000-000">
                            </div>
                            <button type="button" class="btn-search-cep" onclick="buscarCEP()">
                                <i class="fas fa-search"></i>
                                Buscar CEP
                            </button>
                        </div>

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">
                                    Endereço
                                </label>
                                <input type="text" class="form-input" name="endereco" id="endereco"
                                    value="<?php echo $associadoData['endereco'] ?? ''; ?>"
                                    placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Número
                                </label>
                                <input type="text" class="form-input" name="numero" id="numero"
                                    value="<?php echo $associadoData['numero'] ?? ''; ?>" placeholder="Nº">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Complemento
                                </label>
                                <input type="text" class="form-input" name="complemento" id="complemento"
                                    value="<?php echo $associadoData['complemento'] ?? ''; ?>"
                                    placeholder="Apto, Bloco, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Bairro
                                </label>
                                <input type="text" class="form-input" name="bairro" id="bairro"
                                    value="<?php echo $associadoData['bairro'] ?? ''; ?>" placeholder="Nome do bairro">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Cidade
                                </label>
                                <input type="text" class="form-input" name="cidade" id="cidade"
                                    value="<?php echo $associadoData['cidade'] ?? ''; ?>" placeholder="Nome da cidade">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Financeiro -->
                <div class="section-card" data-step="4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Financeiros</h2>
                            <p class="section-subtitle">Informações para cobrança e pagamentos</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <!-- Tipo de Associado (controla percentuais) -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Tipo de Associado <span class="required">*</span>
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Define o percentual de cobrança dos serviços. Benemérito e Agregado não têm direito ao serviço jurídico."></i>
                            </label>
                            <select class="form-input form-select" name="tipoAssociadoServico" id="tipoAssociadoServico"
                                required onchange="calcularServicos()">
                                <option value="">Selecione o tipo de associado...</option>
                                <?php foreach ($tiposAssociado as $tipo): ?>
                                    <option value="<?php echo $tipo; ?>"
                                        <?php echo (isset($associadoData['tipoAssociadoServico']) && $associadoData['tipoAssociadoServico'] == $tipo) ? 'selected' : ''; ?>
                                        <?php echo (in_array($tipo, ['Benemérito', 'Agregado'])) ? 'data-restricao="sem-juridico"' : ''; ?>>
                                        <?php echo $tipo; ?>
                                        <?php echo (in_array($tipo, ['Benemérito', 'Agregado'])) ? ' (Sem serviço jurídico)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-error">Por favor, selecione o tipo de associado</span>
                            <div class="tipo-associado-info" id="infoTipoAssociado" style="display: none;">
                                <i class="fas fa-info-circle"></i>
                                <span id="textoInfoTipo"></span>
                            </div>
                        </div>

                        <!-- Seção de Serviços -->
                        <div class="form-group full-width">
                            <div
                                style="background: var(--white); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--gray-200);">
                                <h4 style="margin-bottom: 1rem; color: var(--primary);">
                                    <i class="fas fa-clipboard-list"></i> Serviços do Associado
                                </h4>

                                <!-- Serviço Social (Obrigatório) -->
                                <div class="servico-item"
                                    style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div>
                                            <span style="font-weight: 600; color: var(--success);">
                                                <i class="fas fa-check-circle"></i> Serviço Social
                                            </span>
                                            <span
                                                style="background: var(--success); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; margin-left: 0.5rem;">
                                                OBRIGATÓRIO
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span
                                                    id="valorBaseSocial">173,10</span></div>
                                            <div style="font-weight: 700; color: var(--success);">Total: R$ <span
                                                    id="valorFinalSocial">0,00</span></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Percentual aplicado: <span id="percentualSocial">0</span>%
                                        <span style="margin-left: 1rem;">Contribuição social para associados</span>
                                    </div>
                                    <input type="hidden" name="servicoSocial" value="1">
                                    <input type="hidden" name="valorSocial" id="valorSocial" value="0">
                                    <input type="hidden" name="percentualAplicadoSocial" id="percentualAplicadoSocial"
                                        value="0">
                                </div>

                                <!-- Serviço Jurídico (Opcional) -->
                                <div class="servico-item" id="servicoJuridicoItem"
                                    style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="checkbox" name="servicoJuridico" id="servicoJuridico" value="2"
                                                onchange="calcularServicos()" style="width: 20px; height: 20px;"
                                                <?php echo (isset($associadoData['servicoJuridico']) && $associadoData['servicoJuridico']) ? 'checked' : ''; ?>>
                                            <label for="servicoJuridico"
                                                style="font-weight: 600; color: var(--info); cursor: pointer;">
                                                <i class="fas fa-balance-scale"></i> Serviço Jurídico
                                            </label>
                                            <span id="badgeJuridico"
                                                style="background: var(--info); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
                                                OPCIONAL
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span
                                                    id="valorBaseJuridico">43,28</span></div>
                                            <div style="font-weight: 700; color: var(--info);">Total: R$ <span
                                                    id="valorFinalJuridico">0,00</span></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Percentual aplicado: <span id="percentualJuridico">0</span>%
                                        <span style="margin-left: 1rem;">Serviço jurídico opcional</span>
                                    </div>
                                    <input type="hidden" name="valorJuridico" id="valorJuridico" value="0">
                                    <input type="hidden" name="percentualAplicadoJuridico"
                                        id="percentualAplicadoJuridico" value="0">
                                    <div id="mensagemRestricaoJuridico" style="display: none;"></div>
                                </div>

                                <!-- Total Geral -->
                                <div
                                    style="padding: 1rem; background: var(--primary-light); border-radius: 8px; border: 2px solid var(--primary);">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-weight: 700; color: var(--primary); font-size: 1.1rem;">
                                            <i class="fas fa-calculator"></i> VALOR TOTAL MENSAL
                                        </span>
                                        <span style="font-weight: 800; color: var(--primary); font-size: 1.3rem;">
                                            R$ <span id="valorTotalGeral">0,00</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categoria do Associado -->
                        <div class="form-group">
                            <label class="form-label">
                                Categoria do Associado <span class="required">*</span>
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Categoria oficial do associado na associação"></i>
                            </label>
                            <select class="form-input form-select" name="tipoAssociado" id="tipoAssociado" required>
                                <option value="">Selecione...</option>
                                <option value="Contribuinte" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Contribuinte') ? 'selected' : ''; ?>>Contribuinte</option>
                                <option value="Benemérito" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Benemérito') ? 'selected' : ''; ?>>Benemérito</option>
                                <option value="Remido" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Remido') ? 'selected' : ''; ?>>Remido</option>
                                <option value="Agregado" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Agregado') ? 'selected' : ''; ?>>Agregado</option>
                                <option value="Especial" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Especial') ? 'selected' : ''; ?>>Especial</option>
                            </select>
                            <span class="form-error">Por favor, selecione a categoria do associado</span>
                        </div>

                        <!-- Situação Financeira -->
                        <div class="form-group">
                            <label class="form-label">
                                Situação Financeira
                            </label>
                            <select class="form-input form-select" name="situacaoFinanceira" id="situacaoFinanceira">
                                <option value="">Selecione...</option>
                                <option value="Adimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Adimplente') ? 'selected' : ''; ?>>Adimplente</option>
                                <option value="Inadimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Inadimplente') ? 'selected' : ''; ?>>Inadimplente</option>
                            </select>
                        </div>

                        <!-- Vínculo Servidor -->
                        <div class="form-group">
                            <label class="form-label">
                                Vínculo do Servidor
                                <i class="fas fa-info-circle info-tooltip" title="Digite o número do vínculo"></i>
                            </label>
                            <input type="text" class="form-input" name="vinculoServidor" id="vinculoServidor"
                                value="<?php echo $associadoData['vinculoServidor'] ?? ''; ?>"
                                placeholder="Digite o número do vínculo">
                        </div>

                        <!-- Local de Débito -->
                        <div class="form-group">
                            <label class="form-label">
                                Local de Débito
                            </label>
                            <select class="form-input form-select" name="localDebito" id="localDebito">
                                <option value="">Selecione...</option>
                                <option value="CEF" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'CEF') ? 'selected' : ''; ?>>CEF</option>
                                <option value="SEGPLAN" <?php echo (!isset($associadoData['localDebito']) || $associadoData['localDebito'] == 'SEGPLAN') ? 'selected' : ''; ?>>SEGPLAN</option>
                                <option value="ITAU" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'ITAU') ? 'selected' : ''; ?>>ITAU</option>
                                <option value="Assego" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Assego') ? 'selected' : ''; ?>>Assego</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Agência
                            </label>
                            <input type="text" class="form-input" name="agencia" id="agencia"
                                value="<?php echo $associadoData['agencia'] ?? ''; ?>" placeholder="Número da agência">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Operação
                            </label>
                            <input type="text" class="form-input" name="operacao" id="operacao"
                                value="<?php echo $associadoData['operacao'] ?? ''; ?>"
                                placeholder="Código da operação">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Conta Corrente
                            </label>
                            <input type="text" class="form-input" name="contaCorrente" id="contaCorrente"
                                value="<?php echo $associadoData['contaCorrente'] ?? ''; ?>"
                                placeholder="Número da conta">
                        </div>

                        <!-- Doador -->
                        <div class="form-group">
                            <label class="form-label">
                                Doador
                                <i class="fas fa-info-circle info-tooltip" title="Se o associado é doador da ASSEGO"></i>
                            </label>
                            <select class="form-input form-select" name="doador" id="doador">
                                <option value="">Selecione...</option>
                                <option value="Sim" <?php echo (isset($associadoData['doador']) && $associadoData['doador'] == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                                <option value="Não" <?php echo (isset($associadoData['doador']) && $associadoData['doador'] == 'Não') ? 'selected' : ''; ?>>Não</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Dependentes -->
                <div class="section-card" data-step="5">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dependentes</h2>
                            <p class="section-subtitle">Adicione os dependentes do associado</p>
                        </div>
                    </div>

                    <div id="dependentesContainer">
                        <?php if (isset($associadoData['dependentes']) && count($associadoData['dependentes']) > 0): ?>
                            <?php foreach ($associadoData['dependentes'] as $index => $dependente): ?>
                                <div class="dependente-card" data-index="<?php echo $index; ?>">
                                    <div class="dependente-header">
                                        <span class="dependente-number">Dependente <?php echo $index + 1; ?></span>
                                        <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group full-width">
                                            <label class="form-label">Nome Completo</label>
                                            <input type="text" class="form-input"
                                                name="dependentes[<?php echo $index; ?>][nome]"
                                                value="<?php echo $dependente['nome'] ?? ''; ?>"
                                                placeholder="Nome do dependente">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Data de Nascimento</label>
                                            <input type="date" class="form-input"
                                                name="dependentes[<?php echo $index; ?>][data_nascimento]"
                                                value="<?php echo $dependente['data_nascimento'] ?? ''; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Parentesco</label>
                                            <select class="form-input form-select"
                                                name="dependentes[<?php echo $index; ?>][parentesco]">
                                                <option value="">Selecione...</option>
                                                <option value="Cônjuge" <?php echo ($dependente['parentesco'] == 'Cônjuge') ? 'selected' : ''; ?>>Cônjuge</option>
                                                <option value="Filho(a)" <?php echo ($dependente['parentesco'] == 'Filho(a)') ? 'selected' : ''; ?>>Filho(a)</option>
                                                <option value="Pai" <?php echo ($dependente['parentesco'] == 'Pai') ? 'selected' : ''; ?>>Pai</option>
                                                <option value="Mãe" <?php echo ($dependente['parentesco'] == 'Mãe') ? 'selected' : ''; ?>>Mãe</option>
                                                <option value="Irmão(ã)" <?php echo ($dependente['parentesco'] == 'Irmão(ã)') ? 'selected' : ''; ?>>Irmão(ã)</option>
                                                <option value="Outro" <?php echo ($dependente['parentesco'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Sexo</label>
                                            <select class="form-input form-select"
                                                name="dependentes[<?php echo $index; ?>][sexo]">
                                                <option value="">Selecione...</option>
                                                <option value="M" <?php echo ($dependente['sexo'] == 'M') ? 'selected' : ''; ?>>
                                                    Masculino</option>
                                                <option value="F" <?php echo ($dependente['sexo'] == 'F') ? 'selected' : ''; ?>>
                                                    Feminino</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="btn-add-dependente" onclick="adicionarDependente()">
                        <i class="fas fa-plus"></i>
                        Adicionar Dependente
                    </button>
                </div>

                <!-- Step 6: Revisão -->
                <div class="section-card" data-step="6">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Revisão dos Dados</h2>
                            <p class="section-subtitle">Confira todos os dados antes de salvar</p>
                        </div>
                    </div>

                    <div id="revisaoContainer">
                        <!-- Conteúdo será preenchido dinamicamente -->
                    </div>
                </div>
            </form>

            <!-- Navigation - VERSÃO REORGANIZADA COM BOTÕES DE SALVAR -->
            <div class="form-navigation">
                <div class="nav-buttons-left">
                    <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </button>
                </div>

                <div class="nav-buttons-right">
                    <!-- Indicador de salvamento -->
                    <div class="step-save-indicator" id="saveIndicator">
                        <i class="fas fa-check-circle"></i>
                        <span>Salvo com sucesso!</span>
                    </div>

                    <!-- Botão de salvar step atual -->
                    <button type="button" class="btn-save-step" id="btnSalvarStep" onclick="salvarStepAtual()">
                        <i class="fas fa-save"></i>
                        <span class="save-text">Salvar</span>
                    </button>

                    <!-- Botão cancelar -->
                    <button type="button" class="btn-nav btn-back" onclick="cancelarEdicao()" title="Voltar ao Dashboard sem salvar">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>

                    <!-- Botão próximo -->
                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <!-- Botão salvar completo (apenas no step final) -->
                    <button type="button" class="btn-nav btn-submit" id="btnSalvar" onclick="salvarAssociado()"
                        style="display: none;">
                        <i class="fas fa-save"></i>
                        <?php echo $isEdit ? 'Atualizar' : 'Salvar'; ?> Associado
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/pt-BR.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <!-- Scripts separados para melhor organização -->
    <script src="js/cadastroForm.js"></script>
    <script src="js/cadastroFormAutocomplete.js"></script>

    <script>
    // Função para definir valor do select após carregar
    function definirValorSelect(selectId, valor) {
        console.log(`=== DEFININDO VALOR PARA ${selectId} ===`);
        console.log(`Valor procurado: "${valor}"`);
        
        const select = document.getElementById(selectId);
        if (!select) {
            console.warn(`❌ Select ${selectId} não encontrado`);
            return false;
        }
        
        // Lista todas as opções disponíveis para debug
        const options = select.querySelectorAll('option');
        console.log(`Opções disponíveis em ${selectId}:`);
        options.forEach((option, index) => {
            if (option.value) {
                console.log(`  [${index}] "${option.value}"`);
            }
        });
        
        // Procura a opção exata
        let encontrou = false;
        
        options.forEach(option => {
            if (option.value === valor) {
                option.selected = true;
                encontrou = true;
                console.log(`✅ Opção encontrada e selecionada: "${valor}"`);
            }
        });
        
        if (!encontrou) {
            console.warn(`❌ Valor "${valor}" NÃO encontrado nas opções do select ${selectId}`);
            console.warn(`Verifique se o valor está exatamente igual no banco e no array PHP`);
        }
        
        // Atualiza Select2 se estiver inicializado
        if (typeof $ !== 'undefined' && $(`#${selectId}`).hasClass('select2-hidden-accessible')) {
            $(`#${selectId}`).trigger('change');
            console.log(`🔄 Select2 atualizado para ${selectId}`);
        }
        
        console.log(`=== FIM ${selectId} ===\n`);
        return encontrou;
    }

    // Inicializa Select2 para os campos
    $(document).ready(function() {
        console.log('=== INICIALIZANDO PÁGINA ===');
        
        // Inicializa Select2 para lotação
        $('#lotacao').select2({
            placeholder: 'Selecione ou digite para buscar...',
            language: 'pt-BR',
            width: '100%',
            allowClear: true
        });
        
        // Inicializa Select2 para patente
        $('#patente').select2({
            placeholder: 'Selecione a patente...',
            language: 'pt-BR',
            width: '100%',
            allowClear: true,
            dropdownParent: $('#patente').parent()
        });
        
        // Inicializa Select2 para corporação
        $('#corporacao').select2({
            placeholder: 'Selecione a corporação...',
            language: 'pt-BR',
            width: '100%',
            allowClear: true
        });
        
        // Inicializa Select2 para categoria
        $('#categoria').select2({
            placeholder: 'Selecione a situação funcional...',
            language: 'pt-BR',  
            width: '100%',
            allowClear: true
        });

        // Se estiver editando, define valores após inicialização
        <?php if ($isEdit && isset($associadoData)): ?>
            console.log('=== MODO EDIÇÃO DETECTADO ===');
            
            setTimeout(function() {
                console.log('Definindo valores dos campos militares...');
                
                <?php if (isset($associadoData['patente']) && !empty($associadoData['patente'])): ?>
                    const patenteAtual = <?php echo json_encode($associadoData['patente']); ?>;
                    console.log('Patente do banco:', patenteAtual);
                    
                    if (!definirValorSelect('patente', patenteAtual)) {
                        console.error('Falha ao definir patente:', patenteAtual);
                    }
                <?php endif; ?>
                
                <?php if (isset($associadoData['corporacao']) && !empty($associadoData['corporacao'])): ?>
                    const corporacaoAtual = <?php echo json_encode($associadoData['corporacao']); ?>;
                    console.log('Corporação do banco:', corporacaoAtual);
                    
                    if (!definirValorSelect('corporacao', corporacaoAtual)) {
                        console.error('Falha ao definir corporação:', corporacaoAtual);
                    }
                <?php endif; ?>
                
                <?php if (isset($associadoData['categoria']) && !empty($associadoData['categoria'])): ?>
                    const categoriaAtual = <?php echo json_encode($associadoData['categoria']); ?>;
                    console.log('Categoria do banco:', categoriaAtual);
                    
                    if (!definirValorSelect('categoria', categoriaAtual)) {
                        console.error('Falha ao definir categoria:', categoriaAtual);
                    }
                <?php endif; ?>
                
                <?php if (isset($associadoData['lotacao']) && !empty($associadoData['lotacao'])): ?>
                    const lotacaoAtual = <?php echo json_encode($associadoData['lotacao']); ?>;
                    console.log('Lotação do banco:', lotacaoAtual);
                    
                    $('#lotacao').val(lotacaoAtual).trigger('change');
                    console.log('✅ Lotação definida via Select2');
                <?php endif; ?>
                
                <?php if (isset($associadoData['unidade']) && !empty($associadoData['unidade'])): ?>
                    const unidadeAtual = <?php echo json_encode($associadoData['unidade']); ?>;
                    console.log('Unidade do banco:', unidadeAtual);
                    
                    const unidadeInput = document.getElementById('unidade');
                    if (unidadeInput) {
                        unidadeInput.value = unidadeAtual;
                        console.log('✅ Unidade definida');
                    }
                <?php endif; ?>
                
                console.log('✓ Valores militares definidos');
            }, 500);
            
            // Busca dados dos serviços ao carregar página de edição
            buscarDadosServicosAssociado(<?php echo $associadoId; ?>);
            
            setTimeout(function() {
                console.log('🔄 Garantindo que todas as patentes apareçam no dropdown...');
                
                $('#patente').select2('destroy').select2({
                    placeholder: 'Selecione a patente...',
                    language: 'pt-BR',
                    width: '100%',
                    allowClear: true
                });
                
                <?php if (isset($associadoData['patente']) && !empty($associadoData['patente'])): ?>
                    const patenteParaRedefinir = <?php echo json_encode($associadoData['patente']); ?>;
                    $('#patente').val(patenteParaRedefinir).trigger('change');
                    console.log('✅ Patente redefinida após recarregar Select2');
                <?php endif; ?>
                
                console.log('✅ Select2 da patente recarregado com todas as opções');
            }, 1200);
        <?php endif; ?>
        
        $('#patente').on('select2:open', function() {
            console.log('🔽 Dropdown da patente aberto - verificando se todas as opções estão disponíveis');
            
            const totalOptions = $('#patente option').length;
            console.log(`📊 Total de opções de patente disponíveis: ${totalOptions}`);
            
            if (totalOptions < 15) {
                console.warn('⚠️ Poucas opções encontradas, pode haver problema no carregamento');
            }
        });
        
        $('#patente').on('change', function() {
            const valorSelecionado = $(this).val();
            console.log(`✅ Patente selecionada: "${valorSelecionado}"`);
        });
        
        console.log('✓ Página inicializada');
    });

    // Função para buscar dados dos serviços do associado em edição
    function buscarDadosServicosAssociado(associadoId) {
        fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data) {
                    if (data.data.servicos.social) {
                        const social = data.data.servicos.social;
                        document.getElementById('valorSocial').value = social.valor_aplicado;
                        document.getElementById('percentualAplicadoSocial').value = social.percentual_aplicado;
                        document.getElementById('valorFinalSocial').textContent = parseFloat(social.valor_aplicado).toFixed(2).replace('.', ',');
                        document.getElementById('percentualSocial').textContent = parseFloat(social.percentual_aplicado).toFixed(0);
                    }
                    
                    if (data.data.servicos.juridico) {
                        const juridico = data.data.servicos.juridico;
                        document.getElementById('servicoJuridico').checked = true;
                        document.getElementById('valorJuridico').value = juridico.valor_aplicado;
                        document.getElementById('percentualAplicadoJuridico').value = juridico.percentual_aplicado;
                        document.getElementById('valorFinalJuridico').textContent = parseFloat(juridico.valor_aplicado).toFixed(2).replace('.', ',');
                        document.getElementById('percentualJuridico').textContent = parseFloat(juridico.percentual_aplicado).toFixed(0);
                    }
                    
                    document.getElementById('valorTotalGeral').textContent = parseFloat(data.data.valor_total_mensal || 0).toFixed(2).replace('.', ',');
                    
                    if (data.data.tipo_associado_servico) {
                        const selectTipo = document.getElementById('tipoAssociadoServico');
                        if (selectTipo) {
                            selectTipo.value = data.data.tipo_associado_servico;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao buscar dados dos serviços:', error);
            });
    }

    // Função para cancelar edição
    function cancelarEdicao() {
        if (confirm('Deseja cancelar? Os dados não salvos serão perdidos.')) {
            window.location.href = 'dashboard.php';
        }
    }
    // Agregado: lógica JS
    function toggleAgregadoCampos() {
        const isAgregado = document.getElementById('isAgregado').checked;
        document.getElementById('campoCpfTitular').style.display = isAgregado ? 'block' : 'none';
        document.getElementById('cpfTitular').required = isAgregado;
        if (!isAgregado) {
            document.getElementById('cpfTitular').value = '';
            document.getElementById('nomeTitularInfo').value = '';
            document.getElementById('associadoTitular').value = '';
            document.getElementById('erroCpfTitular').style.display = 'none';
        }
        
        // Atualizar tipoAssociado para Agregado quando checkbox está marcado
        if (isAgregado) {
            const tipoSelect = document.getElementById('tipoAssociado');
            if (tipoSelect) {
                tipoSelect.value = 'Agregado';
                tipoSelect.dispatchEvent(new Event('change'));
            }
        }
    }

    $(document).ready(function() {
        function buscarNomeTitularPorCpf() {
            const cpf = $('#cpfTitular').val().replace(/\D/g, '');
            if (cpf.length === 11) {
                $.get('../api/buscar_associado_por_cpf.php', { cpf: cpf }, function(resp) {
                    if (resp.status === 'success' && resp.data) {
                        let nome = resp.data.titular_nome || resp.data.nome || '';
                        let cpfFormatado = resp.data.titular_cpf || resp.data.cpf || '';
                        let titularId = resp.data.titular_id || resp.data.id || '';
                        
                        if (cpfFormatado.length === 11) {
                            cpfFormatado = cpfFormatado.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                        }
                        
                        // Preencher ID do titular (essencial para o backend)
                        if (titularId) {
                            $('#associadoTitular').val(titularId);
                            console.log('✅ ID do titular preenchido:', titularId);
                        }
                        
                        if (nome && cpfFormatado) {
                            $('#nomeTitularInfo').val(nome + ' - ' + cpfFormatado);
                        } else if (nome) {
                            $('#nomeTitularInfo').val(nome);
                        } else {
                            $('#nomeTitularInfo').val('');
                        }
                        
                        let situacao = resp.data.titular_situacao || resp.data.situacao || '';
                        let corporacao = resp.data.corporacao || '';
                        
                        // Validar: titular não pode ser agregado
                        if (corporacao === 'Agregados') {
                            $('#nomeTitularInfo').val('');
                            $('#associadoTitular').val('');
                            $('#erroCpfTitular').text('Titular não pode ser um agregado').show();
                            return;
                        }
                        
                        // Validar: titular deve estar filiado
                        if (situacao && situacao !== 'Filiado') {
                            $('#erroCpfTitular').text('Titular não está com situação Filiado').show();
                        } else {
                            $('#erroCpfTitular').hide();
                        }
                    } else {
                        $('#nomeTitularInfo').val('');
                        $('#associadoTitular').val('');
                        $('#erroCpfTitular').text('CPF não encontrado ou inválido').show();
                    }
                }, 'json').fail(function() {
                    $('#nomeTitularInfo').val('');
                    $('#associadoTitular').val('');
                    $('#erroCpfTitular').text('Erro ao buscar titular').show();
                });
            } else {
                $('#nomeTitularInfo').val('');
                $('#associadoTitular').val('');
                $('#erroCpfTitular').text('CPF incompleto').show();
            }
        }

        $('#cpfTitular').on('blur', buscarNomeTitularPorCpf);
        $('#cpfTitular').on('keyup', function() {
            if ($(this).val().replace(/\D/g, '').length === 11) {
                buscarNomeTitularPorCpf();
            } else {
                $('#nomeTitularInfo').val('');
                $('#erroCpfTitular').show();
            }
        });

        $('#formAssociado').on('submit', function(e) {
            if ($('#isAgregado').is(':checked')) {
                const cpfTitular = $('#cpfTitular').val();
                const associadoTitularId = $('#associadoTitular').val();
                const erroVisivel = $('#erroCpfTitular').is(':visible');
                
                if (!cpfTitular || !associadoTitularId || erroVisivel) {
                    e.preventDefault();
                    alert('Preencha corretamente o CPF do titular e verifique se está filiado.');
                    $('#cpfTitular').focus();
                    return false;
                }
                
                // Garantir que tipoAssociado seja Agregado
                const tipoSelect = $('#tipoAssociado');
                if (tipoSelect.val() !== 'Agregado') {
                    tipoSelect.val('Agregado');
                }
                
                console.log('✅ Submetendo agregado com titular ID:', associadoTitularId);
            }
        });
    });
    // Fim agregado
    </script>
</body>

</html>