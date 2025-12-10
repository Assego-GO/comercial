<?php
/**
 * API de Importação - VERSÃO FINAL com campos compatíveis
 */

ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(0);
ob_start();

$LOG_FILE = __DIR__ . '/importacao_debug.log';
function logit($msg) {
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        logit("ERRO FATAL: " . $error['message']);
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal',
            'erro' => $error['message'],
            'linha' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

set_exception_handler(function($e) {
    while (ob_get_level()) ob_end_clean();
    logit("EXCEPTION: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Exception', 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

function sendJson($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function convData($d) {
    if (!$d) return null;
    $d = trim(str_replace('"', '', $d));
    $p = explode('/', $d);
    return count($p) === 3 ? sprintf('%04d-%02d-%02d', $p[2], $p[1], $p[0]) : null;
}

function convValor($v) {
    if (!$v) return 0.00;
    $v = trim(str_replace('"', '', $v));
    return floatval(str_replace(',', '.', str_replace('.', '', $v)));
}

function buscaAssoc($c, $cpf) {
    $sql = "SELECT a.id, a.nome, a.cpf, f.tipoAssociado
            FROM Associados a LEFT JOIN Financeiro f ON a.id=f.associado_id
            WHERE LPAD(REPLACE(REPLACE(REPLACE(a.cpf,'.',''),'-',''),' ',''),11,'0')=? LIMIT 1";
    $s = @$c->prepare($sql);
    if (!$s) return null;
    @$s->bind_param('s', $cpf);
    @$s->execute();
    $r = @$s->get_result();
    $a = @$r->fetch_assoc();
    @$s->close();
    return $a;
}

function buscaRegras($c, $t, $i) {
    $sql = "SELECT s.id as servico_id, s.nome as servico_nome, s.valor_base, rc.percentual_valor,
            (s.valor_base*rc.percentual_valor/100) as valor_esperado, sa.ativo as tem_servico_ativo
            FROM Servicos s
            INNER JOIN Regras_Contribuicao rc ON s.id=rc.servico_id AND rc.tipo_associado=?
            LEFT JOIN Servicos_Associado sa ON s.id=sa.servico_id AND sa.associado_id=? AND sa.ativo=1
            WHERE s.ativo=1 ORDER BY s.id";
    $s = @$c->prepare($sql);
    if (!$s) return [];
    @$s->bind_param('si', $t, $i);
    @$s->execute();
    $r = @$s->get_result();
    $reg = [];
    while ($row = @$r->fetch_assoc()) $reg[] = $row;
    @$s->close();
    return $reg;
}

function identServicos($v, $rs) {
    $TOL = 2.00;
    $at = array_filter($rs, fn($r) => $r['tem_servico_ativo'] == 1);
    if (empty($at)) $at = array_filter($rs, fn($r) => stripos($r['servico_nome'], 'Social') !== false);
    if (empty($at)) return [];
    foreach ($at as $s) {
        if (abs($s['valor_esperado'] - $v) < $TOL) {
            return [['nome' => $s['servico_nome'], 'valor' => $v, 'percentual' => $s['percentual_valor'], 'servico_id' => $s['servico_id']]];
        }
    }
    foreach ($at as $s) {
        if (stripos($s['servico_nome'], 'Social') !== false) {
            return [['nome' => $s['servico_nome'], 'valor' => $v, 'percentual' => $s['percentual_valor'], 'servico_id' => $s['servico_id']]];
        }
    }
    return [];
}

function identTipo($n) {
    $n = strtolower($n);
    if (stripos($n, 'social') !== false) return 'SOCIAL';
    if (stripos($n, 'jurídico') !== false) return 'JURIDICO';
    if (stripos($n, 'pecúlio') !== false) return 'PECULIO';
    return 'OUTROS';
}

function regPag($c, $aid, $mes, $val, $dt, $tp, $obs, $fid) {
    $sql = "SELECT id FROM Pagamentos_Associado WHERE associado_id=? AND mes_referencia=? AND tipo_divida=?";
    $s = @$c->prepare($sql);
    if (!$s) return false;
    @$s->bind_param('iss', $aid, $mes, $tp);
    @$s->execute();
    $r = @$s->get_result();
    $ex = @$r->fetch_assoc();
    @$s->close();
    
    if ($ex) {
        $sql = "UPDATE Pagamentos_Associado SET valor_pago=?, data_pagamento=?, status_pagamento='CONFIRMADO',
                origem_importacao='NEOCONSIG_CSV', funcionario_registro=?, observacoes=?, data_atualizacao=NOW() WHERE id=?";
        $s = @$c->prepare($sql);
        if (!$s) return false;
        $id = $ex['id'];
        @$s->bind_param('dsisi', $val, $dt, $fid, $obs, $id);
        $res = @$s->execute();
        @$s->close();
        return $res ? 'atualizado' : false;
    } else {
        $sql = "INSERT INTO Pagamentos_Associado (associado_id,mes_referencia,valor_pago,data_pagamento,
                status_pagamento,tipo_divida,origem_importacao,funcionario_registro,observacoes,data_registro)
                VALUES (?,?,?,?,'CONFIRMADO',?,'NEOCONSIG_CSV',?,?,NOW())";
        $s = @$c->prepare($sql);
        if (!$s) return false;
        @$s->bind_param('isdssis', $aid, $mes, $val, $dt, $tp, $fid, $obs);
        $res = @$s->execute();
        @$s->close();
        return $res ? 'inserido' : false;
    }
}

function detAus($c, $cpfs, $mes, $fid) {
    $LOTE = 1000;
    $tot = 0;
    $cri = 0;
    $lotes = ceil(count($cpfs) / $LOTE);
    
    for ($i = 0; $i < $lotes; $i++) {
        $lote = array_slice($cpfs, $i * $LOTE, $LOTE);
        $ph = implode(',', array_fill(0, count($lote), '?'));
        $sql = "SELECT a.id FROM Associados a INNER JOIN Financeiro f ON a.id=f.associado_id
                WHERE a.situacao='Filiado' AND LPAD(REPLACE(REPLACE(REPLACE(a.cpf,'.',''),'-',''),' ',''),11,'0') NOT IN ($ph) LIMIT 100";
        $s = @$c->prepare($sql);
        if (!$s) continue;
        $types = str_repeat('s', count($lote));
        @$s->bind_param($types, ...$lote);
        @$s->execute();
        $r = @$s->get_result();
        while ($row = @$r->fetch_assoc()) {
            $tot++;
            $sql2 = "INSERT IGNORE INTO Pagamentos_Associado (associado_id,mes_referencia,valor_pago,status_pagamento,
                    tipo_divida,origem_importacao,funcionario_registro,observacoes,data_registro)
                    VALUES (?,?,0,'PENDENTE','SOCIAL','VERIFICACAO',?,'Ausente CSV',NOW())";
            $s2 = @$c->prepare($sql2);
            if ($s2) {
                $assoc_id = $row['id'];
                @$s2->bind_param('isi', $assoc_id, $mes, $fid);
                if (@$s2->execute()) $cri++;
                @$s2->close();
            }
        }
        @$s->close();
    }
    return ['total' => $tot, 'criados' => $cri];
}

function regHist($c, $fid, $s) {
    $sql = "INSERT INTO Historico_Importacoes_ASAAS (data_importacao,funcionario_id,total_registros,
            adimplentes,inadimplentes,atualizados,erros,observacoes) VALUES (NOW(),?,?,?,?,?,?,?)";
    $obs = json_encode(['ausentes' => $s['aus']], JSON_UNESCAPED_UNICODE);
    
    $total = $s['tot'];
    $quitados = $s['qui'];
    $pendentes = $s['pen'];
    $atualizados_total = $s['nov'] + $s['atu'];
    $erros = $s['err'];
    
    $st = @$c->prepare($sql);
    if (!$st) return false;
    
    @$st->bind_param('iiiiiis', $fid, $total, $quitados, $pendentes, $atualizados_total, $erros, $obs);
    $res = @$st->execute();
    @$st->close();
    return $res;
}

// === INÍCIO ===

try {
    logit("=== INICIO ===");
    
    @session_start();
    
    if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Não autenticado'], 401);
    }
    
    $fid = $_SESSION['funcionario_id'] ?? $_SESSION['user_id'];
    
    $cfgPath = __DIR__ . '/../../config/database.php';
    if (file_exists($cfgPath)) {
        ob_start();
        @include_once $cfgPath;
        ob_end_clean();
    }
    
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $user = defined('DB_USER') ? DB_USER : 'superuser';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $db = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : 'wwasse_cadastro';
    
    $conn = @new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        logit("ERRO: Conexão");
        sendJson(['success' => false, 'message' => 'Erro de conexão'], 500);
    }
    
    @$conn->set_charset("utf8mb4");
    
    $act = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // LISTAR HISTÓRICO
    if ($act === 'listar_historico') {
        $sql = "SELECT h.id, h.data_importacao, h.total_registros, h.adimplentes as quitados,
                h.inadimplentes as pendentes, h.atualizados, h.erros, f.nome as funcionario_nome
                FROM Historico_Importacoes_ASAAS h
                LEFT JOIN Funcionarios f ON h.funcionario_id=f.id
                ORDER BY h.data_importacao DESC LIMIT 20";
        $res = @$conn->query($sql);
        if (!$res) sendJson(['success' => false, 'message' => 'Erro na query'], 500);
        $hist = [];
        while ($row = @$res->fetch_assoc()) $hist[] = $row;
        sendJson(['success' => true, 'data' => $hist]);
    }
    
    // PROCESSAR CSV
    elseif ($act === 'processar_csv') {
        $inicio = microtime(true);
        logit("Processar CSV");
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            sendJson(['success' => false, 'message' => 'Arquivo não enviado'], 400);
        }
        
        $arq = $_FILES['csv_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            sendJson(['success' => false, 'message' => 'Apenas CSV'], 400);
        }
        
        @$conn->begin_transaction();
        
        // Estatísticas com nomes compatíveis com o JS
        $stats = [
            'total_linhas' => 0,
            'processados' => 0,
            'quitados' => 0,
            'pendentes' => 0,
            'erros' => 0,
            'associados_atualizados' => 0,
            'nao_encontrados' => 0,
            'pagamentos_novos' => 0,
            'pagamentos_atualizados' => 0,
            'associados_ausentes' => 0,
            'servicos_pendentes_criados' => 0,
            'detalhes_erros' => []
        ];
        
        $cont = @file_get_contents($arq);
        $enc = @mb_detect_encoding($cont, ['UTF-8','Windows-1252','ISO-8859-1'], true);
        if ($enc && $enc !== 'UTF-8') {
            $cont = @mb_convert_encoding($cont, 'UTF-8', $enc);
            @file_put_contents($arq, $cont);
        }
        
        $h = @fopen($arq, 'r');
        if (!$h) sendJson(['success' => false, 'message' => 'Erro ao abrir'], 500);
        
        @fgetcsv($h, 0, ';');
        
        $cpfs = [];
        $ln = 0;
        
        while (($row = @fgetcsv($h, 0, ';')) !== false) {
            $ln++;
            $stats['total_linhas']++;
            if ($ln % 500 == 0) logit("Linha $ln");
            if (count($row) < 14) { $stats['erros']++; continue; }
            
            $nome = trim($row[1] ?? '');
            $cpf = trim($row[3] ?? '');
            $venc = trim($row[5] ?? '');
            $valor = trim($row[11] ?? '0');
            $status = strtoupper(trim($row[13] ?? ''));
            
            $cpfL = preg_replace('/[^0-9]/', '', $cpf);
            $cpfL = str_pad($cpfL, 11, '0', STR_PAD_LEFT);
            
            if (strlen($cpfL) === 11) $cpfs[] = $cpfL;
            
            if ($status === 'QUITADO') {
                $stats['quitados']++;
                $stats['processados']++;
            } elseif (strpos($status, 'PENDENTE') !== false) { 
                $stats['pendentes']++; 
                continue; 
            } else {
                continue;
            }
            
            if (strlen($cpfL) !== 11) { 
                $stats['erros']++; 
                $stats['detalhes_erros'][] = ['linha' => $ln, 'cpf' => $cpf, 'nome' => $nome, 'erro' => 'CPF inválido'];
                continue; 
            }
            
            $asc = buscaAssoc($conn, $cpfL);
            if (!$asc) { 
                $stats['erros']++; 
                $stats['nao_encontrados']++;
                $stats['detalhes_erros'][] = ['linha' => $ln, 'cpf' => $cpf, 'nome' => $nome, 'erro' => 'Associado não encontrado'];
                continue; 
            }
            
            $tipo = $asc['tipoAssociado'] ?? 'Contribuinte';
            $regs = buscaRegras($conn, $tipo, $asc['id']);
            if (empty($regs)) { 
                $stats['erros']++; 
                $stats['detalhes_erros'][] = ['linha' => $ln, 'cpf' => $cpf, 'nome' => $nome, 'erro' => "Tipo '{$tipo}' sem regras"];
                continue; 
            }
            
            $dataC = convData($venc);
            if (!$dataC) { 
                $stats['erros']++; 
                $stats['detalhes_erros'][] = ['linha' => $ln, 'cpf' => $cpf, 'nome' => $nome, 'erro' => 'Data inválida'];
                continue; 
            }
            
            $mes = substr($dataC, 0, 7) . '-01';
            $valF = convValor($valor);
            
            $svcs = identServicos($valF, $regs);
            if (empty($svcs)) { 
                $stats['erros']++; 
                $stats['detalhes_erros'][] = ['linha' => $ln, 'cpf' => $cpf, 'nome' => $nome, 'erro' => 'Valor não identificado: R$ ' . number_format($valF, 2, ',', '.')];
                continue; 
            }
            
            $regSuccess = 0;
            foreach ($svcs as $svc) {
                $tpD = identTipo($svc['nome']);
                $obs = "CSV | {$svc['percentual']}%";
                $res = regPag($conn, $asc['id'], $mes, $svc['valor'], $dataC, $tpD, $obs, $fid);
                if ($res === 'inserido') {
                    $stats['pagamentos_novos']++;
                    $regSuccess++;
                } elseif ($res === 'atualizado') {
                    $stats['pagamentos_atualizados']++;
                    $regSuccess++;
                }
            }
            
            if ($regSuccess > 0) {
                $stats['associados_atualizados']++;
            }
        }
        
        @fclose($h);
        
        logit("Linhas processadas: " . $stats['total_linhas']);
        
        if (!empty($cpfs)) {
            $aus = detAus($conn, $cpfs, $mes, $fid);
            $stats['associados_ausentes'] = $aus['total'];
            $stats['servicos_pendentes_criados'] = $aus['criados'];
        }
        
        // Registrar no histórico com valores corretos
        $statsHist = [
            'tot' => $stats['total_linhas'],
            'qui' => $stats['quitados'],
            'pen' => $stats['pendentes'],
            'nov' => $stats['pagamentos_novos'],
            'atu' => $stats['pagamentos_atualizados'],
            'err' => $stats['erros'],
            'aus' => $stats['associados_ausentes']
        ];
        regHist($conn, $fid, $statsHist);
        
        @$conn->commit();
        
        $fim = microtime(true);
        $stats['tempo_processamento'] = round($fim - $inicio, 2) . ' segundos';
        
        logit("=== SUCESSO ===");
        logit("Estatísticas: " . json_encode($stats));
        
        sendJson(['success' => true, 'message' => 'Importação concluída', 'data' => $stats]);
    }
    
    else {
        sendJson(['success' => false, 'message' => 'Ação inválida'], 400);
    }
    
} catch (Exception $e) {
    logit("ERRO: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
}