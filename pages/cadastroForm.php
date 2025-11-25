<?php

/**
 * Página de Serviços Financeiros - Sistema ASSEGO
 * pages/cadastroForm.php
 * VERSÃO COM TODOS OS CAMPOS OPCIONAIS
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
// VERIFICAÇÃO SÓCIO AGREGADO - VERSÃO COMPLETA
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
        // Busca dados completos do agregado e do titular
        $stmt = $db->prepare('
            SELECT 
                sa.*,
                sa.socio_titular_nome as titular_nome_original,
                sa.socio_titular_cpf as cpf_titular_vinculo,
                t.nome as titular_nome,
                t.cpf as titular_cpf,
                t.situacao as titular_situacao,
                t.telefone as titular_telefone
            FROM Socios_Agregados sa
            LEFT JOIN associados t ON REPLACE(REPLACE(REPLACE(t.cpf, ".", ""), "-", ""), " ", "") = REPLACE(REPLACE(REPLACE(sa.socio_titular_cpf, ".", ""), "-", ""), " ", "")
            WHERE REPLACE(REPLACE(REPLACE(sa.cpf, ".", ""), "-", ""), " ", "") = ?
            LIMIT 1
        ');
        
        $stmt->execute([$cpfAgregado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $isSocioAgregado = true;
            $relacionamentoAgregado = $row;
            
            // Nome do responsável
            $nomeResponsavelAgregado = !empty($row['titular_nome']) ? 
                $row['titular_nome'] : 
                (!empty($row['titular_nome_original']) ? $row['titular_nome_original'] : 'Não identificado');
            
            // CPF do titular
            $cpfTitular = !empty($row['titular_cpf']) ? 
                preg_replace('/\D/', '', $row['titular_cpf']) : 
                (!empty($row['cpf_titular_vinculo']) ? preg_replace('/\D/', '', $row['cpf_titular_vinculo']) : '');
            
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
    "NAO IDENTIFICADO"
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

        /* Indicador de campo opcional */
        .optional-label {
            color: #6c757d;
            font-weight: normal;
            font-size: 0.85em;
        }

        /* ================================================
   CSS ADICIONAL - Agregado Campos Cinzas + Maiúsculas Militares
   Adicione este CSS ao seu arquivo estilizacao/cadastroForm.css
   ================================================ */

/* ===== CAMPOS FINANCEIROS DESABILITADOS PARA AGREGADO ===== */

/* Estilo para campos de input desabilitados */
.form-input:disabled,
.form-select:disabled {
    background-color: #e9ecef !important;
    color: #6c757d !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
    border-color: #dee2e6 !important;
}

/* Estilo para Select2 desabilitado */
.select2-container--disabled .select2-selection--single {
    background-color: #e9ecef !important;
    cursor: not-allowed !important;
    border-color: #dee2e6 !important;
    opacity: 0.6 !important;
}

.select2-container--disabled .select2-selection--single .select2-selection__rendered {
    color: #6c757d !important;
}

.select2-container--disabled .select2-selection--single .select2-selection__arrow {
    opacity: 0.5 !important;
}

/* Estilo para labels quando campos estão desabilitados */
.form-group:has(.form-input:disabled) .form-label,
.form-group:has(.form-select:disabled) .form-label {
    color: #6c757d !important;
    opacity: 0.7;
}

/* Aviso de agregado no financeiro */
#avisoAgregadoFinanceiro {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#avisoAgregadoFinanceiro i {
    color: #856404;
    font-size: 1.25rem;
}

#avisoAgregadoFinanceiro span {
    color: #856404;
    font-weight: 500;
}


/* ===== MAIÚSCULAS NOS DADOS MILITARES ===== */

/* Campo de Unidade em maiúsculas */
#unidade {
    text-transform: uppercase !important;
}

#unidade::placeholder {
    text-transform: none;
}

/* Select2 - Input de busca em maiúsculas para campos militares */
.select2-search__field {
    text-transform: uppercase !important;
}

/* Opções selecionadas em maiúsculas nos campos militares */
#corporacao + .select2-container .select2-selection__rendered,
#patente + .select2-container .select2-selection__rendered,
#categoria + .select2-container .select2-selection__rendered,
#lotacao + .select2-container .select2-selection__rendered {
    text-transform: uppercase !important;
}

/* Tags criadas nos Select2 militares em maiúsculas */
#corporacao + .select2-container .select2-selection__choice,
#patente + .select2-container .select2-selection__choice,
#categoria + .select2-container .select2-selection__choice,
#lotacao + .select2-container .select2-selection__choice {
    text-transform: uppercase !important;
}

/* Dropdown de opções em maiúsculas para campos militares */
.select2-results__option {
    text-transform: uppercase;
}


/* ===== EFEITO VISUAL PARA CAMPOS DESABILITADOS ===== */

/* Container do serviço quando desabilitado */
.servico-item[style*="opacity: 0.5"] {
    background: #f8f9fa !important;
    border: 1px dashed #dee2e6 !important;
}

/* Animação suave ao desabilitar/habilitar */
.form-input,
.form-select,
.select2-container .select2-selection--single {
    transition: background-color 0.3s ease, 
                color 0.3s ease, 
                opacity 0.3s ease,
                border-color 0.3s ease;
}


/* ===== INDICADOR VISUAL PARA CAMPOS DE AGREGADO ===== */

/* Seção financeira quando é agregado */
.section-card[data-step="4"].agregado-mode .form-grid {
    position: relative;
}

.section-card[data-step="4"].agregado-mode .form-grid::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(248, 249, 250, 0.5);
    pointer-events: none;
    z-index: 1;
    border-radius: 8px;
}

/* Badge para indicar campo desabilitado */
.campo-desabilitado-agregado::after {
    content: '(Gerenciado pelo titular)';
    font-size: 0.7rem;
    color: #856404;
    margin-left: 0.5rem;
    font-weight: normal;
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
            associadoData: <?php echo json_encode($associadoData); ?>,
            isSocioAgregado: <?php echo $isSocioAgregado ? 'true' : 'false'; ?>
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
                    <?php echo $isEdit ? 'Atualize os dados do associado' : 'Preencha os campos desejados para filiar um novo associado'; ?>
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
                            <label class="form-label">CPF do Titular</label>
                            <input type="text" 
                                class="form-input" 
                                name="cpfTitular" 
                                id="cpfTitular" 
                                placeholder="000.000.000-00" 
                                maxlength="14" 
                                autocomplete="off">
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
                                Nome Completo <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="nome" id="nome"
                                value="<?php echo $associadoData['nome'] ?? ''; ?>"
                                placeholder="Digite o nome completo do associado">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Nascimento <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="date" class="form-input" name="nasc" id="nasc"
                                value="<?php echo $associadoData['nasc'] ?? ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Sexo <span class="optional-label">(opcional)</span>
                            </label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_m" value="M" <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'M') ? 'checked' : ''; ?>>
                                    <label for="sexo_m">Masculino</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_f" value="F" <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'F') ? 'checked' : ''; ?>>
                                    <label for="sexo_f">Feminino</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Estado Civil <span class="optional-label">(opcional)</span>
                            </label>
                            <select class="form-input form-select" name="estadoCivil" id="estadoCivil">
                                <option value="">Selecione...</option>
                                <option value="Solteiro(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Solteiro(a)') ? 'selected' : ''; ?>>Solteiro(a)</option>
                                <option value="Casado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Casado(a)') ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Divorciado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Divorciado(a)') ? 'selected' : ''; ?>>Divorciado(a)</option>
                                <option value="Separado(a) Judicialmente" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Separado(a) Judicialmente') ? 'selected' : ''; ?>>Separado(a) Judicialmente</option>
                                <option value="Viúvo(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Viúvo(a)') ? 'selected' : ''; ?>>Viúvo(a)</option>
                                <option value="União Estável" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'União Estável') ? 'selected' : ''; ?>>União Estável</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                RG <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="rg" id="rg"
                                value="<?php echo $associadoData['rg'] ?? ''; ?>" placeholder="Número do RG">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="cpf" id="cpf"
                                value="<?php echo $associadoData['cpf'] ?? ''; ?>" placeholder="000.000.000-00">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="telefone" id="telefone"
                                value="<?php echo $associadoData['telefone'] ?? ''; ?>" placeholder="(00) 00000-0000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="email" class="form-input" name="email" id="email"
                                value="<?php echo $associadoData['email'] ?? ''; ?>" placeholder="email@exemplo.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Escolaridade <span class="optional-label">(opcional)</span>
                            </label>
                            <select class="form-input form-select" name="escolaridade" id="escolaridade">
                                <option value="">Selecione...</option>
                                <option value="Fundamental Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Incompleto') ? 'selected' : ''; ?>>Fundamental Incompleto</option>
                                <option value="Fundamental Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Completo') ? 'selected' : ''; ?>>Fundamental Completo</option>
                                <option value="Médio Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Incompleto') ? 'selected' : ''; ?>>Médio Incompleto</option>
                                <option value="Médio Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Completo') ? 'selected' : ''; ?>>Médio Completo</option>
                                <option value="Superior Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Incompleto') ? 'selected' : ''; ?>>Superior Incompleto</option>
                                <option value="Superior Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Completo') ? 'selected' : ''; ?>>Superior Completo</option>
                                <option value="Pós-graduação" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Pós-graduação') ? 'selected' : ''; ?>>Pós-graduação</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Indicado por <span class="optional-label">(opcional)</span>
                                <i class="fas fa-info-circle info-tooltip" title="Nome da pessoa que indicou o associado"></i>
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
                                Situação <span class="optional-label">(opcional)</span>
                            </label>
                            <select class="form-input form-select" name="situacao" id="situacao">
                                <option value="">Selecione...</option>
                                <option value="Filiado" <?php echo (!isset($associadoData['situacao']) || $associadoData['situacao'] == 'Filiado') ? 'selected' : ''; ?>>Filiado</option>
                                <option value="Desfiliado" <?php echo (isset($associadoData['situacao']) && $associadoData['situacao'] == 'Desfiliado') ? 'selected' : ''; ?>>Desfiliado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Filiação <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="date" class="form-input" name="dataFiliacao" id="dataFiliacao"
                                value="<?php echo $associadoData['data_filiacao'] ?? date('Y-m-d'); ?>">
                        </div>


                        <div class="form-group full-width">
                            <label class="form-label">
                                Foto do Associado <span class="optional-label">(opcional)</span>
                            </label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <?php if (isset($associadoData['foto']) && $associadoData['foto']): ?>
                                        <?php
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
                                    <button type="button" class="photo-upload-btn" onclick="document.getElementById('foto').click();">
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

                        <!-- Campo para upload da ficha assinada -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Ficha de Filiação Assinada <span class="optional-label">(opcional)</span>
                                <i class="fas fa-info-circle info-tooltip" title="Anexe a foto ou PDF da ficha preenchida e assinada pelo associado"></i>
                            </label>
                            <div class="ficha-upload-container" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 2rem; border-radius: 16px; border: 2px dashed #4caf50;">
                                <div style="display: flex; align-items: center; gap: 2rem;">
                                    <div class="ficha-preview" id="fichaPreview" style="width: 200px; height: 250px; background: var(--white); border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 2px solid #4caf50;">
                                        <div class="ficha-preview-placeholder" style="text-align: center; color: #4caf50;">
                                            <i class="fas fa-file-contract" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                            <p style="font-weight: 600;">Ficha de Filiação</p>
                                            <p style="font-size: 0.875rem;">Nenhum arquivo anexado</p>
                                        </div>
                                    </div>

                                    <div style="flex: 1;">
                                        <h4 style="color: #2e7d32; margin-bottom: 1rem;">
                                            <i class="fas fa-plus-circle"></i> Documento Opcional
                                        </h4>
                                        <p style="color: #1b5e20; margin-bottom: 1rem;">
                                            Você pode anexar a ficha de filiação preenchida e assinada pelo associado.
                                            Este documento será enviado automaticamente para aprovação da presidência.
                                        </p>

                                        <input type="file" name="ficha_assinada" id="ficha_assinada" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">

                                        <button type="button" class="btn" onclick="document.getElementById('ficha_assinada').click();" style="background: #4caf50; color: white; border: none; padding: 0.875rem 1.5rem; border-radius: 12px; font-weight: 600; cursor: pointer;">
                                            <i class="fas fa-upload"></i> Anexar Ficha
                                        </button>

                                        <p style="font-size: 0.75rem; color: #2e7d32; margin-top: 0.5rem;">
                                            Formatos aceitos: PDF, JPG, PNG | Tamanho máximo: 10MB
                                        </p>
                                    </div>
                                </div>

                                <!-- Campo hidden para sempre enviar automaticamente -->
                                <input type="hidden" name="enviar_presidencia" id="enviar_presidencia" value="1">
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
                            <p class="section-subtitle">Informações sobre a carreira militar (todos opcionais)</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Corporação <span class="optional-label">(opcional)</span>
                            </label>
                            <select class="form-input form-select" name="corporacao" id="corporacao">
                                <option value="">Selecione...</option>
                                <option value="Polícia Militar" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Militar') ? 'selected' : ''; ?>>Polícia Militar</option>
                                <option value="Bombeiro Militar" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Bombeiro Militar') ? 'selected' : ''; ?>>Bombeiro Militar</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Patente <span class="optional-label">(opcional)</span>
                            </label>
                            <select class="form-input form-select" name="patente" id="patente" data-current-value="<?php echo isset($associadoData['patente']) ? htmlspecialchars($associadoData['patente'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                <option value="">Selecione...</option>
                                <option value="Nenhuma" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Nenhuma') ? 'selected' : ''; ?>>Nenhuma</option>
                                <?php
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
                                Situação Funcional <span class="optional-label">(opcional)</span>
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
                                Lotação <span class="optional-label">(opcional)</span>
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
                                Unidade <span class="optional-label">(opcional)</span>
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
                            <p class="section-subtitle">Dados de localização do associado (todos opcionais)</p>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="cep-search-container">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">
                                    CEP <span class="optional-label">(opcional)</span>
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
                                    Endereço <span class="optional-label">(opcional)</span>
                                </label>
                                <input type="text" class="form-input" name="endereco" id="endereco"
                                    value="<?php echo $associadoData['endereco'] ?? ''; ?>"
                                    placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Número <span class="optional-label">(opcional)</span>
                                </label>
                                <input type="text" class="form-input" name="numero" id="numero"
                                    value="<?php echo $associadoData['numero'] ?? ''; ?>" placeholder="Nº">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Complemento <span class="optional-label">(opcional)</span>
                                </label>
                                <input type="text" class="form-input" name="complemento" id="complemento"
                                    value="<?php echo $associadoData['complemento'] ?? ''; ?>"
                                    placeholder="Apto, Bloco, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Bairro <span class="optional-label">(opcional)</span>
                                </label>
                                <input type="text" class="form-input" name="bairro" id="bairro"
                                    value="<?php echo $associadoData['bairro'] ?? ''; ?>" placeholder="Nome do bairro">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Cidade <span class="optional-label">(opcional)</span>
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
                            <p class="section-subtitle">Informações para cobrança e pagamentos (todos opcionais<?php echo $isSocioAgregado ? ' - não aplicável para agregados' : ''; ?>)</p>
                        </div>
                    </div>

                    <!-- Aviso para agregados -->
                    <div id="avisoAgregadoFinanceiro" style="display: <?php echo $isSocioAgregado ? 'block' : 'none'; ?>; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle" style="color: #856404;"></i>
                        <span style="color: #856404; font-weight: 500;">Para sócios agregados, os dados financeiros são gerenciados pelo titular. Estes campos são opcionais.</span>
                    </div>

                    <div class="form-grid">
                        <!-- Tipo de Associado (controla percentuais) -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Tipo de Associado <span class="optional-label">(opcional)</span>
                                <i class="fas fa-info-circle info-tooltip" title="Define o percentual de cobrança dos serviços. Benemérito e Agregado não têm direito ao serviço jurídico."></i>
                            </label>
                            <select class="form-input form-select" name="tipoAssociadoServico" id="tipoAssociadoServico" onchange="calcularServicos()">
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
                            <div class="tipo-associado-info" id="infoTipoAssociado" style="display: none;">
                                <i class="fas fa-info-circle"></i>
                                <span id="textoInfoTipo"></span>
                            </div>
                        </div>

                        <!-- Seção de Serviços -->
                        <div class="form-group full-width">
                            <div style="background: var(--white); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--gray-200);">
                                <h4 style="margin-bottom: 1rem; color: var(--primary);">
                                    <i class="fas fa-clipboard-list"></i> Serviços do Associado
                                </h4>

                                <!-- Serviço Social -->
                                <div class="servico-item" style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div>
                                            <span style="font-weight: 600; color: var(--success);">
                                                <i class="fas fa-check-circle"></i> Serviço Social
                                            </span>
                                            <span style="background: var(--success); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; margin-left: 0.5rem;">
                                                OBRIGATÓRIO
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span id="valorBaseSocial">173,10</span></div>
                                            <div style="font-weight: 700; color: var(--success);">Total: R$ <span id="valorFinalSocial">0,00</span></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Percentual aplicado: <span id="percentualSocial">0</span>%
                                        <span style="margin-left: 1rem;">Contribuição social para associados</span>
                                    </div>
                                    <input type="hidden" name="servicoSocial" value="1">
                                    <input type="hidden" name="valorSocial" id="valorSocial" value="0">
                                    <input type="hidden" name="percentualAplicadoSocial" id="percentualAplicadoSocial" value="0">
                                </div>

                                <!-- Serviço Jurídico (Opcional) -->
                                <div class="servico-item" id="servicoJuridicoItem" style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="checkbox" name="servicoJuridico" id="servicoJuridico" value="2" onchange="calcularServicos()" style="width: 20px; height: 20px;"
                                                <?php echo (isset($associadoData['servicoJuridico']) && $associadoData['servicoJuridico']) ? 'checked' : ''; ?>>
                                            <label for="servicoJuridico" style="font-weight: 600; color: var(--info); cursor: pointer;">
                                                <i class="fas fa-balance-scale"></i> Serviço Jurídico
                                            </label>
                                            <span id="badgeJuridico" style="background: var(--info); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
                                                OPCIONAL
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span id="valorBaseJuridico">43,28</span></div>
                                            <div style="font-weight: 700; color: var(--info);">Total: R$ <span id="valorFinalJuridico">0,00</span></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Percentual aplicado: <span id="percentualJuridico">0</span>%
                                        <span style="margin-left: 1rem;">Serviço jurídico opcional</span>
                                    </div>
                                    <input type="hidden" name="valorJuridico" id="valorJuridico" value="0">
                                    <input type="hidden" name="percentualAplicadoJuridico" id="percentualAplicadoJuridico" value="0">
                                    <div id="mensagemRestricaoJuridico" style="display: none;"></div>
                                </div>

                                <!-- Total Geral -->
                                <div style="padding: 1rem; background: var(--primary-light); border-radius: 8px; border: 2px solid var(--primary);">
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
                                Categoria do Associado <span class="optional-label">(opcional)</span>
                                <i class="fas fa-info-circle info-tooltip" title="Categoria oficial do associado na associação"></i>
                            </label>
                            <select class="form-input form-select" name="tipoAssociado" id="tipoAssociado">
                                <option value="">Selecione...</option>
                                <option value="Contribuinte" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Contribuinte') ? 'selected' : ''; ?>>Contribuinte</option>
                                <option value="Benemérito" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Benemérito') ? 'selected' : ''; ?>>Benemérito</option>
                                <option value="Remido" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Remido') ? 'selected' : ''; ?>>Remido</option>
                                <option value="Agregado" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Agregado') ? 'selected' : ''; ?>>Agregado</option>
                                <option value="Especial" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Especial') ? 'selected' : ''; ?>>Especial</option>
                            </select>
                        </div>

                        <!-- Situação Financeira -->
                        <div class="form-group">
                            <label class="form-label">
                                Situação Financeira <span class="optional-label">(opcional)</span>
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
                                Vínculo do Servidor <span class="optional-label">(opcional)</span>
                                <i class="fas fa-info-circle info-tooltip" title="Digite o número do vínculo"></i>
                            </label>
                            <input type="text" class="form-input" name="vinculoServidor" id="vinculoServidor"
                                value="<?php echo $associadoData['vinculoServidor'] ?? ''; ?>"
                                placeholder="Digite o número do vínculo">
                        </div>

                        <!-- Local de Débito -->
                        <div class="form-group">
                            <label class="form-label">
                                Local de Débito <span class="optional-label">(opcional)</span>
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
                                Agência <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="agencia" id="agencia"
                                value="<?php echo $associadoData['agencia'] ?? ''; ?>" placeholder="Número da agência">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Operação <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="operacao" id="operacao"
                                value="<?php echo $associadoData['operacao'] ?? ''; ?>"
                                placeholder="Código da operação">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Conta Corrente <span class="optional-label">(opcional)</span>
                            </label>
                            <input type="text" class="form-input" name="contaCorrente" id="contaCorrente"
                                value="<?php echo $associadoData['contaCorrente'] ?? ''; ?>"
                                placeholder="Número da conta">
                        </div>

                        <!-- Doador -->
                        <div class="form-group">
                            <label class="form-label">
                                Doador <span class="optional-label">(opcional)</span>
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
                            <p class="section-subtitle">Adicione os dependentes do associado (opcional)</p>
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
                                                <option value="M" <?php echo ($dependente['sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="F" <?php echo ($dependente['sexo'] == 'F') ? 'selected' : ''; ?>>Feminino</option>
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

            <!-- Navigation -->
            <div class="form-navigation">
                <div class="nav-buttons-left">
                    <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </button>
                </div>

                <div class="nav-buttons-right">
                    <div class="step-save-indicator" id="saveIndicator">
                        <i class="fas fa-check-circle"></i>
                        <span>Salvo com sucesso!</span>
                    </div>

                    <button type="button" class="btn-save-step" id="btnSalvarStep" onclick="salvarStepAtual()">
                        <i class="fas fa-save"></i>
                        <span class="save-text">Salvar</span>
                    </button>

                    <button type="button" class="btn-nav btn-back" onclick="cancelarEdicao()" title="Voltar ao Dashboard sem salvar">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>

                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <button type="button" class="btn-nav btn-submit" id="btnSalvar" onclick="salvarAssociado()" style="display: none;">
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

    <!-- Scripts separados -->
    <script src="js/cadastroForm.js"></script>
    <script src="js/cadastroFormAutocomplete.js"></script>

    <script>
    // Função para cancelar edição
    function cancelarEdicao() {
        if (confirm('Deseja cancelar? Os dados não salvos serão perdidos.')) {
            window.location.href = 'dashboard.php';
        }
    }

    // Função para mostrar/esconder campos de agregado no financeiro
    function atualizarCamposFinanceiroAgregado() {
        const isAgregado = document.getElementById('isAgregado')?.checked || window.pageData.isSocioAgregado;
        const aviso = document.getElementById('avisoAgregadoFinanceiro');
        
        if (aviso) {
            aviso.style.display = isAgregado ? 'block' : 'none';
        }
    }

    // Chama ao carregar e quando muda o checkbox
    document.addEventListener('DOMContentLoaded', function() {
        atualizarCamposFinanceiroAgregado();
        
        const checkboxAgregado = document.getElementById('isAgregado');
        if (checkboxAgregado) {
            checkboxAgregado.addEventListener('change', atualizarCamposFinanceiroAgregado);
        }
    });
    </script>
</body>

</html>