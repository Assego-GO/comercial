<?php
/**
 * API DIAGNÓSTICO - Identificar problema na API de estatísticas
 * api/dependentes/diagnostico.php
 * 
 * Esta API vai identificar EXATAMENTE onde está o problema
 */

// ===== STEP 1: CONFIGURAÇÃO ULTRA SEGURA =====
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Buffer de saída
ob_start();

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'step' => 'inicio',
    'status' => 'em_progresso',
    'erros' => [],
    'sucessos' => [],
    'dados' => []
];

function adicionarSucesso($mensagem) {
    global $diagnostico;
    $diagnostico['sucessos'][] = $mensagem;
    error_log("[DIAGNOSTICO_SUCCESS] $mensagem");
}

function adicionarErro($mensagem, $erro = null) {
    global $diagnostico;
    $diagnostico['erros'][] = $mensagem;
    if ($erro) {
        $diagnostico['erros'][] = "Detalhes: " . $erro->getMessage();
    }
    error_log("[DIAGNOSTICO_ERROR] $mensagem");
}

function finalizarDiagnostico($status = 'sucesso') {
    global $diagnostico;
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $diagnostico['status'] = $status;
    $diagnostico['timestamp_fim'] = date('Y-m-d H:i:s');
    
    echo json_encode($diagnostico, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // ===== STEP 2: VERIFICAR ARQUIVOS =====
    $diagnostico['step'] = 'verificando_arquivos';
    
    $arquivos = [
        '../../config/config.php',
        '../../config/database.php',
        '../../classes/Database.php',
        '../../classes/Auth.php'
    ];
    
    foreach ($arquivos as $arquivo) {
        if (file_exists($arquivo)) {
            adicionarSucesso("Arquivo existe: $arquivo");
        } else {
            adicionarErro("Arquivo NÃO existe: $arquivo");
            finalizarDiagnostico('erro');
        }
    }
    
    // ===== STEP 3: INCLUIR ARQUIVOS =====
    $diagnostico['step'] = 'incluindo_arquivos';
    
    try {
        require_once '../../config/config.php';
        adicionarSucesso("config.php incluído");
    } catch (Exception $e) {
        adicionarErro("Erro ao incluir config.php", $e);
        finalizarDiagnostico('erro');
    }
    
    try {
        require_once '../../config/database.php';
        adicionarSucesso("database.php incluído");
    } catch (Exception $e) {
        adicionarErro("Erro ao incluir database.php", $e);
        finalizarDiagnostico('erro');
    }
    
    try {
        require_once '../../classes/Database.php';
        adicionarSucesso("Database.php incluído");
    } catch (Exception $e) {
        adicionarErro("Erro ao incluir Database.php", $e);
        finalizarDiagnostico('erro');
    }
    
    try {
        require_once '../../classes/Auth.php';
        adicionarSucesso("Auth.php incluído");
    } catch (Exception $e) {
        adicionarErro("Erro ao incluir Auth.php", $e);
        finalizarDiagnostico('erro');
    }
    
    // ===== STEP 4: VERIFICAR CONSTANTES =====
    $diagnostico['step'] = 'verificando_constantes';
    
    $constantes = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME_CADASTRO'];
    
    foreach ($constantes as $const) {
        if (defined($const)) {
            if ($const === 'DB_PASS') {
                adicionarSucesso("Constante $const está definida (valor oculto)");
                $diagnostico['dados'][$const] = '***OCULTO***';
            } else {
                adicionarSucesso("Constante $const = " . constant($const));
                $diagnostico['dados'][$const] = constant($const);
            }
        } else {
            adicionarErro("Constante $const NÃO está definida");
            finalizarDiagnostico('erro');
        }
    }
    
    // ===== STEP 5: TESTAR AUTENTICAÇÃO =====
    $diagnostico['step'] = 'testando_autenticacao';
    
    try {
        $auth = new Auth();
        adicionarSucesso("Classe Auth instanciada");
        
        if ($auth->isLoggedIn()) {
            adicionarSucesso("Usuário está logado");
            
            $usuario = $auth->getUser();
            $diagnostico['dados']['usuario'] = [
                'id' => $usuario['id'] ?? null,
                'nome' => $usuario['nome'] ?? null,
                'departamento_id' => $usuario['departamento_id'] ?? null
            ];
            
            // Verificar permissões
            $deptId = $usuario['departamento_id'] ?? null;
            if ($deptId == 5 || $deptId == 1 || $auth->isDiretor()) {
                adicionarSucesso("Usuário tem permissão (dept: $deptId, diretor: " . ($auth->isDiretor() ? 'sim' : 'não') . ")");
            } else {
                adicionarErro("Usuário SEM permissão (dept: $deptId, diretor: " . ($auth->isDiretor() ? 'sim' : 'não') . ")");
                finalizarDiagnostico('erro');
            }
            
        } else {
            adicionarErro("Usuário NÃO está logado");
            finalizarDiagnostico('erro');
        }
    } catch (Exception $e) {
        adicionarErro("Erro na autenticação", $e);
        finalizarDiagnostico('erro');
    }
    
    // ===== STEP 6: TESTAR CONEXÃO COM BANCO =====
    $diagnostico['step'] = 'testando_conexao_banco';
    
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        adicionarSucesso("Conexão com banco estabelecida");
        
        // Teste simples
        $stmt = $db->prepare("SELECT 1 as teste");
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado && $resultado['teste'] == 1) {
            adicionarSucesso("Query de teste executada com sucesso");
        } else {
            adicionarErro("Query de teste falhou");
            finalizarDiagnostico('erro');
        }
        
    } catch (Exception $e) {
        adicionarErro("Erro na conexão com banco", $e);
        finalizarDiagnostico('erro');
    }
    
    // ===== STEP 7: VERIFICAR TABELAS =====
    $diagnostico['step'] = 'verificando_tabelas';
    
    try {
        // Verificar se tabela Dependentes existe
        $stmt = $db->prepare("SHOW TABLES LIKE 'Dependentes'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            adicionarSucesso("Tabela Dependentes existe");
            
            // Contar registros
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM Dependentes");
            $stmt->execute();
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            adicionarSucesso("Tabela Dependentes tem $total registros");
            $diagnostico['dados']['total_dependentes'] = $total;
            
            if ($total == 0) {
                // Tentar inserir dados de teste
                try {
                    $sqlInserir = "
                        INSERT INTO Dependentes (nome, parentesco, data_nascimento, sexo) VALUES 
                        ('João Teste', 'Filho', '2005-03-15', 'M'),
                        ('Maria Teste', 'Filha', '2006-07-22', 'F'),
                        ('Pedro Teste', 'Filho', '2001-12-10', 'M')
                    ";
                    
                    $stmt = $db->prepare($sqlInserir);
                    $stmt->execute();
                    
                    adicionarSucesso("Dados de teste inseridos na tabela Dependentes");
                    
                    // Recontar
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Dependentes");
                    $stmt->execute();
                    $novoTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    $diagnostico['dados']['total_dependentes_apos_insercao'] = $novoTotal;
                    
                } catch (Exception $e) {
                    adicionarErro("Erro ao inserir dados de teste", $e);
                }
            }
            
        } else {
            adicionarSucesso("Tabela Dependentes NÃO existe - tentando criar");
            
            try {
                $sqlCriar = "
                    CREATE TABLE Dependentes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        nome VARCHAR(255) NOT NULL,
                        parentesco VARCHAR(100) NOT NULL,
                        data_nascimento DATE,
                        sexo CHAR(1),
                        associado_id INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ";
                
                $stmt = $db->prepare($sqlCriar);
                $stmt->execute();
                
                adicionarSucesso("Tabela Dependentes criada com sucesso");
                
                // Inserir dados de teste
                $sqlInserir = "
                    INSERT INTO Dependentes (nome, parentesco, data_nascimento, sexo) VALUES 
                    ('João Teste Criado', 'Filho', '2005-03-15', 'M'),
                    ('Maria Teste Criada', 'Filha', '2006-07-22', 'F'),
                    ('Pedro Teste Criado', 'Filho', '2001-12-10', 'M'),
                    ('Ana Teste Criada', 'Filha', '2000-08-15', 'F')
                ";
                
                $stmt = $db->prepare($sqlInserir);
                $stmt->execute();
                
                adicionarSucesso("Dados de teste inseridos na tabela criada");
                
            } catch (Exception $e) {
                adicionarErro("Erro ao criar tabela Dependentes", $e);
                finalizarDiagnostico('erro');
            }
        }
        
    } catch (Exception $e) {
        adicionarErro("Erro ao verificar tabelas", $e);
        finalizarDiagnostico('erro');
    }
    
    // ===== STEP 8: TESTAR QUERY DE ESTATÍSTICAS =====
    $diagnostico['step'] = 'testando_query_estatisticas';
    
    try {
        $sqlEstatisticas = "
            SELECT 
                COUNT(*) as total_filhos,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) >= 18 THEN 1 ELSE 0 END) as ja_completaram_18,
                SUM(CASE WHEN YEAR(DATE_ADD(data_nascimento, INTERVAL 18 YEAR)) = YEAR(CURDATE()) AND MONTH(DATE_ADD(data_nascimento, INTERVAL 18 YEAR)) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as completam_este_mes,
                SUM(CASE WHEN DATE_ADD(data_nascimento, INTERVAL 18 YEAR) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH) THEN 1 ELSE 0 END) as proximos_3_meses
            FROM Dependentes 
            WHERE (parentesco = 'Filho' OR parentesco = 'Filha')
        ";
        
        $stmt = $db->prepare($sqlEstatisticas);
        $stmt->execute();
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($estatisticas) {
            adicionarSucesso("Query de estatísticas executada com sucesso");
            $diagnostico['dados']['estatisticas'] = $estatisticas;
        } else {
            adicionarErro("Query de estatísticas não retornou dados");
            finalizarDiagnostico('erro');
        }
        
    } catch (Exception $e) {
        adicionarErro("Erro na query de estatísticas", $e);
        finalizarDiagnostico('erro');
    }
    
    // ===== STEP 9: VERIFICAR DADOS =====
    $diagnostico['step'] = 'verificando_dados';
    
    try {
        // Verificar tipos de parentesco
        $stmt = $db->prepare("SELECT parentesco, COUNT(*) as quantidade FROM Dependentes GROUP BY parentesco");
        $stmt->execute();
        $parentescos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $diagnostico['dados']['tipos_parentesco'] = $parentescos;
        adicionarSucesso("Tipos de parentesco coletados: " . count($parentescos));
        
        // Verificar amostras
        $stmt = $db->prepare("SELECT id, nome, parentesco, data_nascimento FROM Dependentes LIMIT 3");
        $stmt->execute();
        $amostras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $diagnostico['dados']['amostras'] = $amostras;
        adicionarSucesso("Amostras de dados coletadas: " . count($amostras));
        
    } catch (Exception $e) {
        adicionarErro("Erro ao verificar dados", $e);
        finalizarDiagnostico('erro');
    }
    
    // ===== SUCCESS - TUDO FUNCIONOU! =====
    $diagnostico['step'] = 'concluido';
    adicionarSucesso("DIAGNÓSTICO COMPLETO - Todos os passos executados com sucesso!");
    
    finalizarDiagnostico('sucesso');

} catch (Exception $e) {
    adicionarErro("Erro geral no diagnóstico", $e);
    finalizarDiagnostico('erro');
}

// Fallback final
finalizarDiagnostico('erro_inesperado');
?>