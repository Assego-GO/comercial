<?php
/**
 * API para gerar PDF do Relatório de Filiações e Desfiliações
 * VERSÃO PROFISSIONAL ESTILIZADA - 5 PÁGINAS
 * Página 1: Relação de Indicações por Representante
 * Página 2: Resumo Filiações por Posto/Graduação
 * Página 3: Lista Detalhada de Filiados
 * Página 4: Resumo Desfiliações por Posto/Graduação
 * Página 5: Lista Detalhada de Desfiliados
 * api/relatorios/gerar_pdf_filiacoes.php
 */

// Desabilitar saída de erros para não corromper o PDF
error_reporting(0);
ini_set('display_errors', 0);

// Limpar qualquer saída anterior
if (ob_get_level()) ob_end_clean();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';
require_once '../../vendor/autoload.php';

// ============================================
// CLASSE PERSONALIZADA COM DESIGN PROFISSIONAL
// ============================================
class ASSEGOPDF extends TCPDF {
    
    // Cores institucionais
    private $corPrimaria = [30, 58, 95];      // Azul escuro elegante
    private $corSecundaria = [52, 73, 94];    // Azul petróleo
    private $corAcento = [231, 76, 60];       // Vermelho institucional
    private $corOuro = [212, 175, 55];        // Dourado elegante
    private $corCinzaEscuro = [44, 62, 80];   // Cinza azulado escuro
    private $corCinzaClaro = [236, 240, 241]; // Cinza claro para fundos
    
    // ============================================
    // CABEÇALHO PROFISSIONAL
    // ============================================
    public function Header() {
        // Todas as páginas: cabeçalho completo igual
        $this->headerCompleto();
    }
    
    private function headerCompleto() {
        // Faixa superior decorativa com gradiente simulado
        $this->SetFillColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->Rect(0, 0, 210, 4, 'F');
        
        // Linha dourada fina
        $this->SetFillColor($this->corOuro[0], $this->corOuro[1], $this->corOuro[2]);
        $this->Rect(0, 4, 210, 1, 'F');
        
        // Área do cabeçalho
        $this->SetY(8);
        
        // Logo ASSEGO à esquerda
        $logoPath = dirname(__DIR__, 2) . '/pages/img/logoassego.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 10, 25, 0, '', '', 'T', false, 300, '', false, false, 0);
        }
        
        // Texto central com tipografia elegante
        $this->SetY(12);
        
        // Nome da associação - fonte mais elegante
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->SetX(42);
        $this->Cell(126, 6, 'ASSOCIAÇÃO DOS SUBTENENTES E SARGENTOS', 0, 1, 'C');
        
        // Subtítulo
        $this->SetFont('helvetica', '', 11);
        $this->SetTextColor($this->corSecundaria[0], $this->corSecundaria[1], $this->corSecundaria[2]);
        $this->SetX(42);
        $this->Cell(126, 5, 'PM & BM DO ESTADO DE GOIÁS', 0, 1, 'C');
        
        // Slogan em itálico
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->SetX(42);
        $this->Cell(126, 4, 'Servindo e protegendo há 60 anos', 0, 1, 'C');
        
        // Logo 60 anos à direita
        $logo60Path = dirname(__DIR__, 2) . '/pages/img/logo60.png';
        if (file_exists($logo60Path)) {
            $this->Image($logo60Path, 170, 10, 25, 0, '', '', 'T', false, 300, '', false, false, 0);
        }
        
        // Linha divisória elegante (dupla)
        $this->SetY(33);
        $this->SetDrawColor($this->corOuro[0], $this->corOuro[1], $this->corOuro[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, 33, 195, 33);
        $this->SetDrawColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->SetLineWidth(0.3);
        $this->Line(15, 34.5, 195, 34.5);
        
        $this->SetY(38);
    }
    
    private function headerSimplificado() {
        // Faixa superior fina
        $this->SetFillColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->Rect(0, 0, 210, 3, 'F');
        $this->SetFillColor($this->corOuro[0], $this->corOuro[1], $this->corOuro[2]);
        $this->Rect(0, 3, 210, 0.5, 'F');
        
        // Logo pequeno à esquerda
        $logoPath = dirname(__DIR__, 2) . '/pages/img/logoassego.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 12, 0, '', '', 'T', false, 300);
        }
        
        // Texto CENTRALIZADO na página
        $this->SetY(8);
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->Cell(190, 5, 'ASSEGO - Associação dos Subtenentes e Sargentos', 0, 0, 'C');
        
        // Número da página à direita
        $this->SetXY(-35, 8);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(25, 5, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'R');
        
        // Linha divisória
        $this->SetDrawColor($this->corCinzaClaro[0] - 20, $this->corCinzaClaro[1] - 20, $this->corCinzaClaro[2] - 20);
        $this->SetLineWidth(0.3);
        $this->Line(10, 16, 200, 16);
        
        $this->SetY(20);
    }
    
    // ============================================
    // RODAPÉ PROFISSIONAL COM ÍCONES SVG
    // ============================================
    public function Footer() {
        $this->SetY(-28);
        
        // Caminho para os ícones SVG (na pasta icons)
        $iconsPath = dirname(__DIR__, 2) . '/pages/img/';
        
        // Linha fina dourada superior
        $this->SetDrawColor($this->corOuro[0], $this->corOuro[1], $this->corOuro[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        $this->Ln(3);
        
        // ===== LINHA 1 - #ASSEGONÃOPARA centralizado =====
        $this->SetTextColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(190, 5, '#ASSEGONÃOPARA', 0, 1, 'C');
        
        $this->Ln(1);
        
        // ===== LINHA 2 - Contatos em linha =====
        $yContato = $this->GetY();
        $iconSize = 4;
        
        // Configuração de texto padrão - NEGRITO
        $this->SetTextColor($this->corCinzaEscuro[0], $this->corCinzaEscuro[1], $this->corCinzaEscuro[2]);
        $this->SetFont('helvetica', 'B', 7);
        
        $xPos = 15;
        
        // Endereço
        if (file_exists($iconsPath . 'location.svg')) {
            $this->ImageSVG($iconsPath . 'location.svg', $xPos, $yContato, $iconSize, $iconSize);
        }
        $this->SetXY($xPos + $iconSize + 1, $yContato + 0.5);
        $this->Cell(55, 3, 'Rua 87, esq. c/132, nº 561 - Setor Sul, Goiânia-GO', 0, 0, 'L');
        
        // Separador
        $xPos = 80;
        $this->SetTextColor(180, 180, 180);
        $this->SetXY($xPos, $yContato + 0.5);
        $this->Cell(3, 3, '|', 0, 0, 'C');
        
        // Telefone
        $xPos = 85;
        $this->SetTextColor($this->corCinzaEscuro[0], $this->corCinzaEscuro[1], $this->corCinzaEscuro[2]);
        if (file_exists($iconsPath . 'phone.svg')) {
            $this->ImageSVG($iconsPath . 'phone.svg', $xPos, $yContato, $iconSize, $iconSize);
        }
        $this->SetXY($xPos + $iconSize + 1, $yContato + 0.5);
        $this->Cell(22, 3, '(62) 3281-3177', 0, 0, 'L');
        
        // Separador
        $xPos = 113;
        $this->SetTextColor(180, 180, 180);
        $this->SetXY($xPos, $yContato + 0.5);
        $this->Cell(3, 3, '|', 0, 0, 'C');
        
        // WhatsApp
        $xPos = 118;
        $this->SetTextColor($this->corCinzaEscuro[0], $this->corCinzaEscuro[1], $this->corCinzaEscuro[2]);
        if (file_exists($iconsPath . 'whatsapp.svg')) {
            $this->ImageSVG($iconsPath . 'whatsapp.svg', $xPos, $yContato, $iconSize, $iconSize);
        }
        $this->SetXY($xPos + $iconSize + 1, $yContato + 0.5);
        $this->Cell(22, 3, '(62) 9.9246-9099', 0, 0, 'L');
        
        // Separador
        $xPos = 146;
        $this->SetTextColor(180, 180, 180);
        $this->SetXY($xPos, $yContato + 0.5);
        $this->Cell(3, 3, '|', 0, 0, 'C');
        
        // Site
        $xPos = 151;
        $this->SetTextColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        if (file_exists($iconsPath . 'website.svg')) {
            $this->ImageSVG($iconsPath . 'website.svg', $xPos, $yContato, $iconSize, $iconSize);
        }
        $this->SetXY($xPos + $iconSize + 1, $yContato + 0.5);
        $this->Cell(28, 3, 'www.assego.com.br', 0, 0, 'L');
        
        // Separador
        $xPos = 183;
        $this->SetTextColor(180, 180, 180);
        $this->SetXY($xPos, $yContato + 0.5);
        $this->Cell(3, 3, '|', 0, 0, 'C');
        
        // Instagram @assego
        $xPos = 188;
        $this->SetTextColor($this->corCinzaEscuro[0], $this->corCinzaEscuro[1], $this->corCinzaEscuro[2]);
        $this->SetXY($xPos, $yContato + 0.5);
        $this->Cell(12, 3, '@assego', 0, 0, 'L');
        
        // Número da página no canto inferior direito
        $this->SetXY(185, $yContato + 5);
        $this->SetTextColor(150, 150, 150);
        $this->SetFont('helvetica', 'B', 7);
        $this->Cell(10, 3, $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
    
    // ============================================
    // MÉTODO PARA TÍTULO DE SEÇÃO
    // ============================================
    public function tituloSecao($titulo, $subtitulo = '') {
        // Fundo do título
        $this->SetFillColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 12);
        
        $this->Cell(190, 8, mb_strtoupper($titulo, 'UTF-8'), 0, 1, 'C', true);
        
        if ($subtitulo) {
            $this->SetFillColor($this->corSecundaria[0], $this->corSecundaria[1], $this->corSecundaria[2]);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(190, 6, $subtitulo, 0, 1, 'C', true);
        }
        
        $this->Ln(3);
    }
    
    // ============================================
    // MÉTODO PARA CABEÇALHO DE TABELA
    // ============================================
    public function headerTabela($colunas, $larguras, $alturaLinha = 7) {
        // Fundo escuro elegante
        $this->SetFillColor($this->corCinzaEscuro[0], $this->corCinzaEscuro[1], $this->corCinzaEscuro[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 7);
        $this->SetDrawColor($this->corCinzaEscuro[0] - 10, $this->corCinzaEscuro[1] - 10, $this->corCinzaEscuro[2] - 10);
        $this->SetLineWidth(0.1);
        
        foreach ($colunas as $i => $col) {
            $borda = ($i == count($colunas) - 1) ? 1 : 'LTB';
            $this->Cell($larguras[$i], $alturaLinha, $col, 1, ($i == count($colunas) - 1) ? 1 : 0, 'C', true);
        }
    }
    
    // ============================================
    // MÉTODO PARA LINHA DE TABELA
    // ============================================
    public function linhaTabela($dados, $larguras, $alinhamentos, $alturaLinha = 5, $zebra = false) {
        if ($zebra) {
            $this->SetFillColor($this->corCinzaClaro[0], $this->corCinzaClaro[1], $this->corCinzaClaro[2]);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        
        $this->SetTextColor(50, 50, 50);
        $this->SetFont('helvetica', '', 7);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.1);
        
        foreach ($dados as $i => $dado) {
            $this->Cell($larguras[$i], $alturaLinha, $dado, 1, ($i == count($dados) - 1) ? 1 : 0, $alinhamentos[$i], true);
        }
    }
    
    // ============================================
    // MÉTODO PARA LINHA DE TOTAL
    // ============================================
    public function linhaTotal($dados, $larguras, $alinhamentos, $alturaLinha = 6) {
        $this->SetFillColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 7);
        $this->SetDrawColor($this->corPrimaria[0] - 10, $this->corPrimaria[1] - 10, $this->corPrimaria[2] - 10);
        
        foreach ($dados as $i => $dado) {
            $this->Cell($larguras[$i], $alturaLinha, $dado, 1, ($i == count($dados) - 1) ? 1 : 0, $alinhamentos[$i], true);
        }
    }
    
    // ============================================
    // MÉTODO PARA CAIXA DE INFORMAÇÃO
    // ============================================
    public function caixaInfo($titulo, $conteudo) {
        $this->SetFillColor($this->corCinzaClaro[0], $this->corCinzaClaro[1], $this->corCinzaClaro[2]);
        $this->SetDrawColor($this->corOuro[0], $this->corOuro[1], $this->corOuro[2]);
        $this->SetLineWidth(0.5);
        
        // Borda esquerda colorida
        $yInicio = $this->GetY();
        $this->Rect(10, $yInicio, 190, 20, 'DF');
        $this->SetFillColor($this->corOuro[0], $this->corOuro[1], $this->corOuro[2]);
        $this->Rect(10, $yInicio, 3, 20, 'F');
        
        $this->SetXY(16, $yInicio + 2);
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor($this->corPrimaria[0], $this->corPrimaria[1], $this->corPrimaria[2]);
        $this->Cell(180, 5, $titulo, 0, 1, 'L');
        
        $this->SetX(16);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(80, 80, 80);
        $this->MultiCell(180, 4, $conteudo, 0, 'L');
        
        $this->SetY($yInicio + 22);
    }
}

// ============================================
// VERIFICAÇÃO DE AUTENTICAÇÃO
// ============================================
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Não autenticado');
}

if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
    die('Sem permissão');
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // Formatar mês/ano
    $meses = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
    ];
    $mes = $meses[date('m', strtotime($dataFim))] ?? date('M', strtotime($dataFim));
    $ano = date('Y', strtotime($dataFim));
    $mesAnoFormatado = "$mes/$ano";
    
    // Valores de comissão
    $valoresComissao = [
        'Jurídico+Social' => 113.42,
        'Social' => 90.73,
        'Jurídico+50% Social' => 68.05,
        '50% Social' => 45.37,
        'Agregado/Al Sd' => 45.37
    ];
    
    $valoresMensalidade = [
        'Jurídico+Social' => 226.83,
        'Social' => 181.46,
        'Jurídico+50% Social' => 136.10,
        '50% Social' => 90.73,
        'Agregado/Al Sd' => 90.73
    ];
    
    // ============================================
    // BUSCAR INDICADORES COM FILIAÇÕES NO PERÍODO (baseado em dataFiliacao)
    // ============================================
    // VERSÃO CORRIGIDA: Busca por dataFiliacao em Contrato E também considera campo indicacao
    // Faz match do campo indicacao com a tabela Indicadores para pegar dados do indicador (PIX, etc)
    $sql = "
        SELECT 
            COALESCE(i.id, i_match.id, 0) as id,
            COALESCE(i.nome_completo, hi.indicador_nome, i_match.nome_completo, NULLIF(TRIM(a.indicacao), ''), 'SEM INDICAÇÃO') as nome_completo,
            COALESCE(i.patente, i_match.patente) as patente,
            COALESCE(i.corporacao, i_match.corporacao) as corporacao,
            COALESCE(i.pix_tipo, i_match.pix_tipo) as pix_tipo,
            COALESCE(i.pix_chave, i_match.pix_chave) as pix_chave,
            COALESCE(i.ativo, i_match.ativo, 1) as ativo,
            
            -- Contagem por tipo de serviço
            SUM(CASE 
                WHEN sa.tipo_associado = 'Contribuinte' 
                     AND NOT EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2 AND sa2.ativo = 1)
                THEN 1 ELSE 0 END) as qtd_social,
            
            SUM(CASE 
                WHEN sa.tipo_associado = 'Contribuinte'
                     AND EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2 AND sa2.ativo = 1)
                THEN 1 ELSE 0 END) as qtd_juridico_social,
            
            SUM(CASE 
                WHEN sa.tipo_associado IN ('Aluno', 'Soldado 1ª Classe', 'Soldado 2ª Classe')
                THEN 1 ELSE 0 END) as qtd_aluno_sd,
            
            SUM(CASE 
                WHEN sa.tipo_associado IN ('Agregado', 'Agregado (Sem serviço jurídico)')
                THEN 1 ELSE 0 END) as qtd_agregado,
            
            COUNT(DISTINCT a.id) as qtd_total
            
        FROM Associados a
        INNER JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Historico_Indicacoes hi ON a.id = hi.associado_id
        LEFT JOIN Indicadores i ON hi.indicador_id = i.id
        -- Match do campo indicacao com a tabela Indicadores (busca por nome exato)
        LEFT JOIN Indicadores i_match ON i.id IS NULL 
            AND i_match.ativo = 1
            AND i_match.nome_completo COLLATE utf8mb4_unicode_ci = TRIM(a.indicacao) COLLATE utf8mb4_unicode_ci
        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1 AND sa.servico_id = 1
        WHERE DATE(c.dataFiliacao) BETWEEN :di1 AND :df1
        GROUP BY 
            COALESCE(i.id, i_match.id, 0),
            COALESCE(i.nome_completo, hi.indicador_nome, i_match.nome_completo, NULLIF(TRIM(a.indicacao), ''), 'SEM INDICAÇÃO'),
            COALESCE(i.patente, i_match.patente), 
            COALESCE(i.corporacao, i_match.corporacao), 
            COALESCE(i.pix_tipo, i_match.pix_tipo), 
            COALESCE(i.pix_chave, i_match.pix_chave),
            COALESCE(i.ativo, i_match.ativo, 1)
        HAVING qtd_total > 0
        ORDER BY qtd_total DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':di1' => $dataInicio, ':df1' => $dataFim
    ]);
    $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // BUSCAR DESFILIAÇÕES POR INDICADOR NO PERÍODO
    // ============================================
    $sqlDesfiliacoesPorIndicador = "
        SELECT 
            hi.indicador_id,
            COUNT(DISTINCT a.id) as qtd_desfiliados,
            SUM(CASE 
                WHEN sa.tipo_associado IN ('Agregado', 'Agregado (Sem servico juridico)') THEN :val_agreg1
                WHEN sa.tipo_associado IN ('Aluno', 'Soldado 1a Classe', 'Soldado 2a Classe') THEN :val_aluno1
                WHEN EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2) THEN :val_jur_soc1
                ELSE :val_social1
            END) as valor_desconto
        FROM Associados a
        INNER JOIN Historico_Indicacoes hi ON hi.associado_id = a.id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.servico_id = 1
        WHERE c.dataDesfiliacao IS NOT NULL 
        AND c.dataDesfiliacao > '1900-01-01'
        AND c.dataDesfiliacao BETWEEN :data_inicio AND :data_fim
        AND LOWER(a.situacao) = 'desfiliado'
        GROUP BY hi.indicador_id
    ";
    
    $stmtDesfilPorInd = $db->prepare($sqlDesfiliacoesPorIndicador);
    $stmtDesfilPorInd->execute([
        ':data_inicio' => $dataInicio, 
        ':data_fim' => $dataFim,
        ':val_agreg1' => $valoresComissao['Agregado/Al Sd'],
        ':val_aluno1' => $valoresComissao['Agregado/Al Sd'],
        ':val_jur_soc1' => $valoresComissao['Jurídico+Social'],
        ':val_social1' => $valoresComissao['Social']
    ]);
    $desfiliacoesPorIndicador = $stmtDesfilPorInd->fetchAll(PDO::FETCH_ASSOC);
    
    // Indexar desfiliações por indicador_id
    $desfiliacoesByIndicador = [];
    foreach ($desfiliacoesPorIndicador as $desfil) {
        $desfiliacoesByIndicador[$desfil['indicador_id']] = [
            'qtd' => (int)$desfil['qtd_desfiliados'],
            'desconto' => (float)$desfil['valor_desconto']
        ];
    }
    
    // Processar dados
    $dados = [];
    $totais = [
        'social' => 0,
        'juridico_social' => 0,
        'aluno_sd' => 0,
        'agregado' => 0,
        'total' => 0,
        'desfiliados' => 0,
        'desconto' => 0,
        'comissao' => 0
    ];
    
    foreach ($indicadores as $ind) {
        $comissaoBruta = 0;
        $comissaoBruta += ($ind['qtd_social'] ?? 0) * $valoresComissao['Social'];
        $comissaoBruta += ($ind['qtd_juridico_social'] ?? 0) * $valoresComissao['Jurídico+Social'];
        $comissaoBruta += ($ind['qtd_aluno_sd'] ?? 0) * $valoresComissao['Agregado/Al Sd'];
        $comissaoBruta += ($ind['qtd_agregado'] ?? 0) * $valoresComissao['Agregado/Al Sd'];
        
        // Buscar desfiliações deste indicador
        $desfiliacoesInd = $desfiliacoesByIndicador[$ind['id']] ?? ['qtd' => 0, 'desconto' => 0];
        $qtdDesfiliados = $desfiliacoesInd['qtd'];
        $valorDesconto = $desfiliacoesInd['desconto'];
        
        // Calcular comissão final (não pode ser negativa)
        $comissaoFinal = max(0, $comissaoBruta - $valorDesconto);
        
        $dados[] = [
            'id' => $ind['id'],
            'nome' => $ind['nome_completo'],
            'social' => (int)($ind['qtd_social'] ?? 0),
            'juridico_social' => (int)($ind['qtd_juridico_social'] ?? 0),
            'aluno_sd' => (int)($ind['qtd_aluno_sd'] ?? 0),
            'agregado' => (int)($ind['qtd_agregado'] ?? 0),
            'total' => (int)($ind['qtd_total'] ?? 0),
            'desfiliados' => $qtdDesfiliados,
            'desconto' => $valorDesconto,
            'pix_tipo' => $ind['pix_tipo'] ?? '',
            'pix_chave' => $ind['pix_chave'] ?? '',
            'comissao_bruta' => $comissaoBruta,
            'comissao' => $comissaoFinal
        ];
        
        $totais['social'] += (int)($ind['qtd_social'] ?? 0);
        $totais['juridico_social'] += (int)($ind['qtd_juridico_social'] ?? 0);
        $totais['aluno_sd'] += (int)($ind['qtd_aluno_sd'] ?? 0);
        $totais['agregado'] += (int)($ind['qtd_agregado'] ?? 0);
        $totais['total'] += (int)($ind['qtd_total'] ?? 0);
        $totais['desfiliados'] += $qtdDesfiliados;
        $totais['desconto'] += $valorDesconto;
        $totais['comissao'] += $comissaoFinal;
    }
    
    // ============================================
    // CRIAR PDF PROFISSIONAL
    // ============================================
    $pdf = new ASSEGOPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configurações do documento
    $pdf->SetCreator('ASSEGO - Sistema de Gestão');
    $pdf->SetAuthor('Associação dos Subtenentes e Sargentos PM & BM de Goiás');
    $pdf->SetTitle('Relatório Comercial - Filiações e Desfiliações ' . $mesAnoFormatado);
    $pdf->SetSubject('Relação de Filiações e Desfiliações de Associados');
    $pdf->SetKeywords('ASSEGO, Relatório, Filiações, Desfiliações, Comercial');
    
    // Configurações de página
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 42, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(30);
    $pdf->SetAutoPageBreak(true, 35);
    
    // ============================================
    // PÁGINA 1 - RELAÇÃO DE INDICAÇÕES
    // ============================================
    $pdf->AddPage();
    
    // Título da seção
    $pdf->tituloSecao('Relatório Comercial', $mesAnoFormatado);
    $pdf->Ln(2);
    
    // Subtítulo
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(190, 6, 'RELAÇÃO DE INDICAÇÕES POR REPRESENTANTE', 0, 1, 'C');
    $pdf->Ln(3);
    
    // Cabeçalho da tabela principal
    $colunas = ['DIRETOR / REPRESENTANTE', 'Social', 'Jur+Soc', 'Al/Sd', 'Agreg.', 'TOTAL', 'Desfil.', 'PIX Tipo', 'PIX Chave', 'BRUTO', 'DESC. DESFIL.', 'LÍQUIDO'];
    $larguras = [40, 10, 10, 10, 10, 10, 10, 14, 20, 18, 20, 18];
    $alinhamentos = ['L', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'R', 'R', 'R'];
    
    $pdf->headerTabela($colunas, $larguras);
    
    // Dados
    $zebra = false;
    foreach ($dados as $row) {
        $descontoStr = $row['desconto'] > 0 ? '-R$ ' . number_format($row['desconto'], 2, ',', '.') : 'R$ 0,00';
        $dadosLinha = [
            mb_strimwidth($row['nome'], 0, 22, '...'),
            $row['social'],
            $row['juridico_social'],
            $row['aluno_sd'],
            $row['agregado'],
            $row['total'],
            $row['desfiliados'],
            $row['pix_tipo'] ?: '-',
            mb_strimwidth($row['pix_chave'] ?: '-', 0, 12, '...'),
            'R$ ' . number_format($row['comissao_bruta'], 2, ',', '.'),
            $descontoStr,
            'R$ ' . number_format($row['comissao'], 2, ',', '.')
        ];
        $pdf->linhaTabela($dadosLinha, $larguras, $alinhamentos, 5, $zebra);
        $zebra = !$zebra;
    }
    
    // Linha de total
    $descontoTotalStr = $totais['desconto'] > 0 ? '-R$ ' . number_format($totais['desconto'], 2, ',', '.') : 'R$ 0,00';
    // Calcular comissão bruta total
    $comissaoBrutaTotal = $totais['comissao'] + $totais['desconto'];
    $dadosTotal = [
        'TOTAL GERAL',
        $totais['social'],
        $totais['juridico_social'],
        $totais['aluno_sd'],
        $totais['agregado'],
        $totais['total'],
        $totais['desfiliados'],
        '-',
        '-',
        'R$ ' . number_format($comissaoBrutaTotal, 2, ',', '.'),
        $descontoTotalStr,
        'R$ ' . number_format($totais['comissao'], 2, ',', '.')
    ];
    $pdf->linhaTotal($dadosTotal, $larguras, $alinhamentos);
    
    $pdf->Ln(10);
    
    // ============================================
    // TABELA DE VALORES - MENSALIDADE E COMISSÃO (CENTRALIZADA)
    // ============================================
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(190, 6, 'TABELA DE VALORES (vigente a partir de Mai/25)', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(190, 4, '* Valores aplicáveis para troca direta de banco', 0, 1, 'C');
    $pdf->Ln(2);
    
    // Calcular margem para centralizar a tabela (largura total = 160)
    $larguraTabela = 160;
    $margemEsquerda = (190 - $larguraTabela) / 2 + 10;
    
    // Cabeçalho da tabela de valores - CENTRALIZADA
    $colValores = ['TIPO DE ASSOCIAÇÃO', 'MENSALIDADE', 'COMISSÃO'];
    $largValores = [80, 40, 40];
    $alinhValores = ['L', 'R', 'R'];
    
    // Posicionar à esquerda para centralizar
    $pdf->SetX($margemEsquerda);
    
    // Header manual centralizado
    $pdf->SetFillColor(44, 62, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetDrawColor(34, 52, 70);
    $pdf->SetLineWidth(0.1);
    
    foreach ($colValores as $i => $col) {
        $pdf->Cell($largValores[$i], 7, $col, 1, ($i == count($colValores) - 1) ? 1 : 0, 'C', true);
    }
    
    // Dados da tabela de valores - CENTRALIZADA
    $tiposValores = [
        ['tipo' => 'Jurídico + Social', 'mensalidade' => 226.83, 'comissao' => 113.42],
        ['tipo' => 'Social', 'mensalidade' => 181.46, 'comissao' => 90.73],
        ['tipo' => 'Jurídico + 50% Social', 'mensalidade' => 136.10, 'comissao' => 68.05],
        ['tipo' => '50% Social', 'mensalidade' => 90.73, 'comissao' => 45.37],
        ['tipo' => 'Agregado / Aluno Soldado', 'mensalidade' => 90.73, 'comissao' => 45.37],
    ];
    
    $zebra = false;
    foreach ($tiposValores as $tv) {
        $pdf->SetX($margemEsquerda);
        
        if ($zebra) {
            $pdf->SetFillColor(236, 240, 241);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetDrawColor(200, 200, 200);
        
        $pdf->Cell($largValores[0], 6, $tv['tipo'], 1, 0, 'L', true);
        $pdf->Cell($largValores[1], 6, 'R$ ' . number_format($tv['mensalidade'], 2, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell($largValores[2], 6, 'R$ ' . number_format($tv['comissao'], 2, ',', '.'), 1, 1, 'R', true);
        
        $zebra = !$zebra;
    }
    
    // ============================================
    // PÁGINA 2 - RESUMO POR POSTO/GRADUAÇÃO
    // ============================================
    $pdf->AddPage();
    
    $pdf->tituloSecao('Quantitativo de Novos Associados', 'Por Posto/Graduação e Corporação');
    
    // Buscar dados de resumo
    $ordemPatentes = [
        'Cel' => 1, 'TC' => 2, 'Maj' => 3, 'Cap' => 4, '1º Ten' => 5, '2º Ten' => 6,
        'ST' => 7, '1º Sgt' => 8, '2º Sgt' => 9, '3º Sgt' => 10, 'Cb' => 11,
        'Sd 1ª Cl' => 12, 'Sd 2ª Cl' => 13, 'Sd' => 14, 'Pensionista' => 15, 
        'Agregado' => 16, 'Civil' => 17, 'N/Inform.' => 99
    ];
    
    $sqlResumo = "
        SELECT 
            COALESCE(NULLIF(TRIM(m.patente), ''), 'N/Inform.') as patente,
            COALESCE(NULLIF(TRIM(m.corporacao), ''), 'N/Inform.') as corporacao,
            COUNT(*) as quantidade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE DATE(c.dataFiliacao) BETWEEN :data_inicio AND :data_fim
        GROUP BY 
            COALESCE(NULLIF(TRIM(m.patente), ''), 'N/Inform.'),
            COALESCE(NULLIF(TRIM(m.corporacao), ''), 'N/Inform.')
        ORDER BY patente, corporacao
    ";
    
    $stmtResumo = $db->prepare($sqlResumo);
    $stmtResumo->execute([':data_inicio' => $dataInicio, ':data_fim' => $dataFim]);
    $dadosResumo = $stmtResumo->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar dados
    $resumoPorPosto = [];
    $totaisPorCorporacao = [
        'PM' => 0, 'BM' => 0, 'Pensionista' => 0, 'Agregados' => 0,
        'Exército' => 0, 'Civil' => 0, 'N/Inform.' => 0
    ];
    
    foreach ($dadosResumo as $dado) {
        $patente = $dado['patente'];
        $corp = $dado['corporacao'];
        $qtd = (int)$dado['quantidade'];
        
        // Normalizar corporação
        $corpNormalizada = 'N/Inform.';
        if (stripos($corp, 'PM') !== false || $corp === 'Polícia Militar') {
            $corpNormalizada = 'PM';
        } elseif (stripos($corp, 'BM') !== false || $corp === 'Bombeiro Militar' || $corp === 'Corpo de Bombeiros') {
            $corpNormalizada = 'BM';
        } elseif (stripos($corp, 'Pensionista') !== false || stripos($patente, 'Pensionista') !== false) {
            $corpNormalizada = 'Pensionista';
        } elseif (stripos($corp, 'Agregado') !== false || stripos($patente, 'Agregado') !== false) {
            $corpNormalizada = 'Agregados';
        } elseif (stripos($corp, 'Exército') !== false || stripos($corp, 'EB') !== false) {
            $corpNormalizada = 'Exército';
        } elseif (stripos($corp, 'Civil') !== false) {
            $corpNormalizada = 'Civil';
        }
        
        // Normalizar patente
        if (stripos($patente, 'Pensionista') !== false) {
            $patente = 'Pensionista';
            $corpNormalizada = 'Pensionista';
        }
        if (stripos($patente, 'Agregado') !== false) {
            $patente = 'Agregado';
            $corpNormalizada = 'Agregados';
        }
        
        if (!isset($resumoPorPosto[$patente])) {
            $resumoPorPosto[$patente] = [
                'patente' => $patente,
                'PM' => 0, 'BM' => 0, 'Pensionista' => 0, 'Agregados' => 0,
                'Exército' => 0, 'Civil' => 0, 'N/Inform.' => 0, 'total' => 0
            ];
        }
        
        $resumoPorPosto[$patente][$corpNormalizada] += $qtd;
        $resumoPorPosto[$patente]['total'] += $qtd;
        $totaisPorCorporacao[$corpNormalizada] += $qtd;
    }
    
    // Ordenar
    uasort($resumoPorPosto, function($a, $b) use ($ordemPatentes) {
        $ordemA = $ordemPatentes[$a['patente']] ?? 99;
        $ordemB = $ordemPatentes[$b['patente']] ?? 99;
        return $ordemA - $ordemB;
    });
    
    // Tabela de resumo
    $colResumo = ['POSTO/GRAD.', 'PM', 'BM', 'Pens.', 'Agreg.', 'Exérc.', 'Civil', 'N/Inform.', 'TOTAL'];
    $largResumo = [32, 20, 20, 20, 20, 20, 18, 18, 18];
    $alinhResumo = ['L', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];
    
    $pdf->headerTabela($colResumo, $largResumo);
    
    $zebra = false;
    foreach ($resumoPorPosto as $row) {
        $dadosLinha = [
            $row['patente'],
            $row['PM'] ?: '-',
            $row['BM'] ?: '-',
            $row['Pensionista'] ?: '-',
            $row['Agregados'] ?: '-',
            $row['Exército'] ?: '-',
            $row['Civil'] ?: '-',
            $row['N/Inform.'] ?: '-',
            $row['total']
        ];
        $pdf->linhaTabela($dadosLinha, $largResumo, $alinhResumo, 5, $zebra);
        $zebra = !$zebra;
    }
    
    // Total
    $totalGeral = array_sum($totaisPorCorporacao);
    $dadosTotalResumo = [
        'TOTAL',
        $totaisPorCorporacao['PM'],
        $totaisPorCorporacao['BM'],
        $totaisPorCorporacao['Pensionista'],
        $totaisPorCorporacao['Agregados'],
        $totaisPorCorporacao['Exército'],
        $totaisPorCorporacao['Civil'],
        $totaisPorCorporacao['N/Inform.'],
        $totalGeral
    ];
    $pdf->linhaTotal($dadosTotalResumo, $largResumo, $alinhResumo);
    
    // ============================================
    // PÁGINA 3 - RELATÓRIO DE FILIADOS DETALHADO
    // ============================================
    $pdf->AddPage();
    
    $pdf->tituloSecao('Relatório de Filiados', 'Lista Detalhada de Novos Associados');
    
    // Buscar filiados
    $sqlFiliados = "
        SELECT 
            a.id as matricula,
            a.rg,
            a.nome,
            m.patente as pg,
            m.corporacao as instituicao,
            COALESCE(NULLIF(TRIM(a.indicacao), ''), 'SEM INDICAÇÃO') as indicacao,
            CASE 
                WHEN sa.tipo_associado IN ('Agregado', 'Agregado (Sem servico juridico)') THEN 'Agregado'
                WHEN EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2 AND sa2.ativo = 1) THEN 'Jurídico + Social'
                ELSE 'Social'
            END as tipo
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1 AND sa.servico_id = 1
        WHERE DATE(c.dataFiliacao) BETWEEN :data_inicio AND :data_fim
        ORDER BY a.nome ASC
    ";
    
    $stmtFiliados = $db->prepare($sqlFiliados);
    $stmtFiliados->execute([':data_inicio' => $dataInicio, ':data_fim' => $dataFim]);
    $filiados = $stmtFiliados->fetchAll(PDO::FETCH_ASSOC);
    
    // Tabela de filiados
    $colFiliados = ['MATR.', 'RG', 'NOME COMPLETO', 'P/G', 'INSTITUIÇÃO', 'INDICAÇÃO', 'TIPO'];
    $largFiliados = [14, 18, 48, 16, 28, 35, 28];
    $alinhFiliados = ['C', 'C', 'L', 'C', 'C', 'C', 'C'];
    
    $pdf->headerTabela($colFiliados, $largFiliados);
    
    $zebra = false;
    foreach ($filiados as $fil) {
        // Verificar quebra de página (ajustado para footer maior)
        if ($pdf->GetY() > 220) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(52, 73, 94);
            $pdf->Cell(190, 6, 'RELATÓRIO DE FILIADOS (continuação)', 0, 1, 'C');
            $pdf->Ln(3);
            $pdf->headerTabela($colFiliados, $largFiliados);
        }
        
        // Normalizar instituição
        $instituicao = $fil['instituicao'] ?? '';
        if (stripos($instituicao, 'PM') !== false) {
            $instituicao = 'Polícia Militar';
        } elseif (stripos($instituicao, 'BM') !== false) {
            $instituicao = 'Bombeiro Militar';
        } elseif (stripos($fil['tipo'], 'Agregado') !== false) {
            $instituicao = 'Agregados';
        }
        
        // Normalizar patente (abreviar patentes longas)
        $pgFil = $fil['pg'] ?? '';
        $abrevPatentesFil = [
            'Subtenente ou Suboficial' => 'ST',
            'Subtenente' => 'ST',
            'Aluno Oficial' => 'Al. Of.',
            'Aluno Soldado' => 'Al. Sd',
            'Primeiro Tenente' => '1º Ten',
            'Primeiro-Tenente' => '1º Ten',
            'Segundo Tenente' => '2º Ten',
            'Segundo-Tenente' => '2º Ten',
            'Primeiro Sargento' => '1º Sgt',
            'Primeiro-Sargento' => '1º Sgt',
            'Segundo Sargento' => '2º Sgt',
            'Segundo-Sargento' => '2º Sgt',
            'Terceiro Sargento' => '3º Sgt',
            'Terceiro-Sargento' => '3º Sgt',
            'Soldado 1ª Classe' => 'Sd 1ª Cl',
            'Soldado 2ª Classe' => 'Sd 2ª Cl',
            'Soldado' => 'Sd',
            'Coronel' => 'Cel',
            'Tenente Coronel' => 'TC',
            'Tenente-Coronel' => 'TC',
            'Major' => 'Maj',
            'Capitão' => 'Cap',
            'Cabo' => 'Cb',
            'Pensionista' => 'Pens.',
            'Agregado' => 'Agreg.',
        ];
        foreach ($abrevPatentesFil as $completo => $abrev) {
            if (stripos($pgFil, $completo) !== false) {
                $pgFil = $abrev;
                break;
            }
        }
        
        $indicacao = trim($fil['indicacao'] ?? '');
        if (empty($indicacao)) {
            $indicacao = 'SEM INDICAÇÃO';
        }
        
        $dadosLinha = [
            $fil['matricula'],
            $fil['rg'] ?? '',
            mb_strimwidth(mb_strtoupper($fil['nome'] ?? ''), 0, 30, '...'),
            $pgFil,
            mb_strimwidth($instituicao, 0, 16, '...'),
            mb_strimwidth($indicacao, 0, 20, '...'),
            $fil['tipo'] ?? ''
        ];
        
        $pdf->linhaTabela($dadosLinha, $largFiliados, $alinhFiliados, 5, $zebra);
        $zebra = !$zebra;
    }
    
    // Total de filiados
    $pdf->Ln(2);
    $pdf->SetFillColor(30, 58, 95);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(190, 7, 'TOTAL DE NOVOS FILIADOS NO PERÍODO: ' . count($filiados), 0, 1, 'C', true);
    
    // ============================================
    // PÁGINA 4 - RESUMO DE DESFILIAÇÕES POR POSTO/GRADUAÇÃO
    // ============================================
    $pdf->AddPage();
    
    $pdf->tituloSecao('Relatório de Desfiliações', 'Resumo por Posto/Graduação e Tempo de Permanência');
    
    // Buscar dados de desfiliações por posto/graduação
    $sqlDesfilResumo = "
        SELECT 
            COALESCE(NULLIF(TRIM(m.patente), ''), 'N/Inform.') as patente,
            COALESCE(NULLIF(TRIM(m.corporacao), ''), 'N/Inform.') as corporacao,
            COUNT(*) as quantidade,
            AVG(
                CASE 
                    WHEN c.dataFiliacao IS NOT NULL AND c.dataFiliacao > '1900-01-01' 
                    THEN TIMESTAMPDIFF(MONTH, c.dataFiliacao, c.dataDesfiliacao)
                    ELSE NULL 
                END
            ) as media_meses
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE c.dataDesfiliacao IS NOT NULL 
        AND c.dataDesfiliacao > '1900-01-01'
        AND c.dataDesfiliacao BETWEEN :data_inicio AND :data_fim
        AND LOWER(a.situacao) = 'desfiliado'
        GROUP BY 
            COALESCE(NULLIF(TRIM(m.patente), ''), 'N/Inform.'),
            COALESCE(NULLIF(TRIM(m.corporacao), ''), 'N/Inform.')
        ORDER BY patente, corporacao
    ";
    
    $stmtDesfilResumo = $db->prepare($sqlDesfilResumo);
    $stmtDesfilResumo->execute([':data_inicio' => $dataInicio, ':data_fim' => $dataFim]);
    $dadosDesfilResumo = $stmtDesfilResumo->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar dados de desfiliação
    $resumoDesfilPosto = [];
    $totaisDesfilCorporacao = [
        'PM' => 0, 'BM' => 0, 'Pensionista' => 0, 'Agregados' => 0,
        'Exército' => 0, 'Civil' => 0, 'N/Inform.' => 0
    ];
    $somaMediasPermanencia = [];
    
    foreach ($dadosDesfilResumo as $dado) {
        $patente = $dado['patente'];
        $corp = $dado['corporacao'];
        $qtd = (int)$dado['quantidade'];
        $mediaMeses = (float)$dado['media_meses'];
        
        // Normalizar corporação
        $corpNormalizada = 'N/Inform.';
        if (stripos($corp, 'PM') !== false || $corp === 'Polícia Militar') {
            $corpNormalizada = 'PM';
        } elseif (stripos($corp, 'BM') !== false || $corp === 'Bombeiro Militar' || $corp === 'Corpo de Bombeiros') {
            $corpNormalizada = 'BM';
        } elseif (stripos($corp, 'Pensionista') !== false || stripos($patente, 'Pensionista') !== false) {
            $corpNormalizada = 'Pensionista';
        } elseif (stripos($corp, 'Agregado') !== false || stripos($patente, 'Agregado') !== false) {
            $corpNormalizada = 'Agregados';
        } elseif (stripos($corp, 'Exército') !== false || stripos($corp, 'EB') !== false) {
            $corpNormalizada = 'Exército';
        } elseif (stripos($corp, 'Civil') !== false) {
            $corpNormalizada = 'Civil';
        }
        
        // Normalizar patente
        if (stripos($patente, 'Pensionista') !== false) {
            $patente = 'Pensionista';
            $corpNormalizada = 'Pensionista';
        }
        if (stripos($patente, 'Agregado') !== false) {
            $patente = 'Agregado';
            $corpNormalizada = 'Agregados';
        }
        
        if (!isset($resumoDesfilPosto[$patente])) {
            $resumoDesfilPosto[$patente] = [
                'patente' => $patente,
                'PM' => 0, 'BM' => 0, 'Pensionista' => 0, 'Agregados' => 0,
                'Exército' => 0, 'Civil' => 0, 'N/Inform.' => 0, 'total' => 0,
                'soma_meses' => 0, 'qtd_meses' => 0
            ];
        }
        
        $resumoDesfilPosto[$patente][$corpNormalizada] += $qtd;
        $resumoDesfilPosto[$patente]['total'] += $qtd;
        $resumoDesfilPosto[$patente]['soma_meses'] += $mediaMeses * $qtd;
        $resumoDesfilPosto[$patente]['qtd_meses'] += $qtd;
        $totaisDesfilCorporacao[$corpNormalizada] += $qtd;
        
        if (!isset($somaMediasPermanencia[$corpNormalizada])) {
            $somaMediasPermanencia[$corpNormalizada] = ['soma' => 0, 'qtd' => 0];
        }
        $somaMediasPermanencia[$corpNormalizada]['soma'] += $mediaMeses * $qtd;
        $somaMediasPermanencia[$corpNormalizada]['qtd'] += $qtd;
    }
    
    // Ordenar
    uasort($resumoDesfilPosto, function($a, $b) use ($ordemPatentes) {
        $ordemA = $ordemPatentes[$a['patente']] ?? 99;
        $ordemB = $ordemPatentes[$b['patente']] ?? 99;
        return $ordemA - $ordemB;
    });
    
    // Tabela de resumo de desfiliações
    $colDesfilResumo = ['POSTO/GRAD.', 'PM', 'BM', 'Pens.', 'Agreg.', 'Exérc.', 'Civil', 'N/Inform.', 'TOTAL', 'MÉDIA PERM.'];
    $largDesfilResumo = [28, 16, 16, 16, 16, 16, 16, 16, 16, 22];
    $alinhDesfilResumo = ['L', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];
    
    $pdf->headerTabela($colDesfilResumo, $largDesfilResumo);
    
    $zebra = false;
    foreach ($resumoDesfilPosto as $row) {
        // Calcular média de permanência em anos
        $mediaPerm = $row['qtd_meses'] > 0 ? round($row['soma_meses'] / $row['qtd_meses'] / 12, 1) : 0;
        $mediaPermStr = $mediaPerm > 0 ? $mediaPerm . ' anos' : '-';
        
        $dadosLinha = [
            $row['patente'],
            $row['PM'] ?: '-',
            $row['BM'] ?: '-',
            $row['Pensionista'] ?: '-',
            $row['Agregados'] ?: '-',
            $row['Exército'] ?: '-',
            $row['Civil'] ?: '-',
            $row['N/Inform.'] ?: '-',
            $row['total'],
            $mediaPermStr
        ];
        $pdf->linhaTabela($dadosLinha, $largDesfilResumo, $alinhDesfilResumo, 5, $zebra);
        $zebra = !$zebra;
    }
    
    // Total de desfiliações
    $totalDesfilGeral = array_sum($totaisDesfilCorporacao);
    
    // Calcular média geral de permanência
    $somaGeralMeses = 0;
    $qtdGeralMeses = 0;
    foreach ($resumoDesfilPosto as $row) {
        $somaGeralMeses += $row['soma_meses'];
        $qtdGeralMeses += $row['qtd_meses'];
    }
    $mediaGeralPerm = $qtdGeralMeses > 0 ? round($somaGeralMeses / $qtdGeralMeses / 12, 1) : 0;
    $mediaGeralPermStr = $mediaGeralPerm > 0 ? $mediaGeralPerm . ' anos' : '-';
    
    $dadosTotalDesfil = [
        'TOTAL',
        $totaisDesfilCorporacao['PM'],
        $totaisDesfilCorporacao['BM'],
        $totaisDesfilCorporacao['Pensionista'],
        $totaisDesfilCorporacao['Agregados'],
        $totaisDesfilCorporacao['Exército'],
        $totaisDesfilCorporacao['Civil'],
        $totaisDesfilCorporacao['N/Inform.'],
        $totalDesfilGeral,
        $mediaGeralPermStr
    ];
    $pdf->linhaTotal($dadosTotalDesfil, $largDesfilResumo, $alinhDesfilResumo);
    
    // ============================================
    // PÁGINA 5 - RELATÓRIO DETALHADO DE DESFILIADOS
    // ============================================
    $pdf->AddPage();
    
    $pdf->tituloSecao('Relatório de Desfiliados', 'Lista Detalhada com Tempo de Permanência');
    
    // Buscar desfiliados (somente quem está atualmente desfiliado, excluindo refiliados)
    $sqlDesfiliados = "
        SELECT 
            a.id as matricula,
            a.rg,
            a.nome,
            m.patente as pg,
            m.corporacao as instituicao,
            COALESCE(NULLIF(TRIM(a.indicacao), ''), 'SEM INDICAÇÃO') as indicacao,
            c.dataFiliacao,
            c.dataDesfiliacao,
            CASE 
                WHEN c.dataFiliacao IS NULL OR c.dataFiliacao <= '1900-01-01' THEN NULL
                ELSE TIMESTAMPDIFF(MONTH, c.dataFiliacao, c.dataDesfiliacao)
            END as meses_permanencia,
            CASE 
                WHEN sa.tipo_associado IN ('Agregado', 'Agregado (Sem servico juridico)') THEN 'Agregado'
                WHEN EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2 AND sa2.ativo = 1) THEN 'Jurídico'
                ELSE 'Social'
            END as tipo
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.servico_id = 1
        WHERE c.dataDesfiliacao IS NOT NULL 
        AND c.dataDesfiliacao > '1900-01-01'
        AND c.dataDesfiliacao BETWEEN :data_inicio AND :data_fim
        AND LOWER(a.situacao) = 'desfiliado'
        ORDER BY a.nome ASC
    ";
    
    $stmtDesfiliados = $db->prepare($sqlDesfiliados);
    $stmtDesfiliados->execute([':data_inicio' => $dataInicio, ':data_fim' => $dataFim]);
    $desfiliados = $stmtDesfiliados->fetchAll(PDO::FETCH_ASSOC);
    
    // Tabela de desfiliados
    $colDesfiliados = ['MATR.', 'NOME COMPLETO', 'P/G', 'INSTITUIÇÃO', 'INDICAÇÃO', 'DT.DESFIL.', 'PERMAN.'];
    $largDesfiliados = [14, 46, 22, 28, 32, 22, 22];
    $alinhDesfiliados = ['C', 'L', 'C', 'C', 'C', 'C', 'C'];
    
    $pdf->headerTabela($colDesfiliados, $largDesfiliados);
    
    $zebra = false;
    foreach ($desfiliados as $desf) {
        // Verificar quebra de página
        if ($pdf->GetY() > 220) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(52, 73, 94);
            $pdf->Cell(190, 6, 'RELATÓRIO DE DESFILIADOS (continuação)', 0, 1, 'C');
            $pdf->Ln(3);
            $pdf->headerTabela($colDesfiliados, $largDesfiliados);
        }
        
        // Normalizar instituição
        $instituicao = $desf['instituicao'] ?? '';
        if (stripos($instituicao, 'PM') !== false) {
            $instituicao = 'Polícia Militar';
        } elseif (stripos($instituicao, 'BM') !== false) {
            $instituicao = 'Bombeiro Militar';
        } elseif (stripos($desf['tipo'], 'Agregado') !== false) {
            $instituicao = 'Agregados';
        }
        
        // Normalizar patente (abreviar patentes longas)
        $pg = $desf['pg'] ?? '';
        // Mapeamento de patentes para abreviações
        $abrevPatentes = [
            'Subtenente ou Suboficial' => 'ST',
            'Subtenente' => 'ST',
            'Aluno Oficial' => 'Al. Of.',
            'Primeiro Tenente' => '1º Ten',
            'Segundo Tenente' => '2º Ten',
            'Primeiro Sargento' => '1º Sgt',
            'Segundo Sargento' => '2º Sgt',
            'Terceiro Sargento' => '3º Sgt',
            'Soldado 1ª Classe' => 'Sd 1ª Cl',
            'Soldado 2ª Classe' => 'Sd 2ª Cl',
            'Soldado' => 'Sd',
            'Coronel' => 'Cel',
            'Tenente Coronel' => 'TC',
            'Tenente-Coronel' => 'TC',
            'Major' => 'Maj',
            'Capitão' => 'Cap',
            'Cabo' => 'Cb',
            'Pensionista' => 'Pens.',
            'Agregado' => 'Agreg.',
        ];
        foreach ($abrevPatentes as $completo => $abrev) {
            if (stripos($pg, $completo) !== false) {
                $pg = $abrev;
                break;
            }
        }
        
        $indicacao = trim($desf['indicacao'] ?? '');
        if (empty($indicacao)) {
            $indicacao = 'SEM INDICAÇÃO';
        }
        
        // Formatar permanência em anos/meses
        $mesesPerm = $desf['meses_permanencia'];
        if ($mesesPerm !== null && $mesesPerm >= 0) {
            $anos = floor($mesesPerm / 12);
            $meses = $mesesPerm % 12;
            if ($anos > 0 && $meses > 0) {
                $permStr = $anos . 'a ' . $meses . 'm';
            } elseif ($anos > 0) {
                $permStr = $anos . ' anos';
            } else {
                $permStr = $meses . ' meses';
            }
        } else {
            $permStr = '-';
        }
        
        // Formatar data de desfiliação
        $dataDesf = $desf['dataDesfiliacao'] ? date('d/m/Y', strtotime($desf['dataDesfiliacao'])) : '-';
        
        $dadosLinha = [
            $desf['matricula'],
            mb_strimwidth(mb_strtoupper($desf['nome'] ?? ''), 0, 32, '...'),
            $pg,
            mb_strimwidth($instituicao, 0, 16, '...'),
            mb_strimwidth($indicacao, 0, 18, '...'),
            $dataDesf,
            $permStr
        ];
        
        $pdf->linhaTabela($dadosLinha, $largDesfiliados, $alinhDesfiliados, 5, $zebra);
        $zebra = !$zebra;
    }
    
    // Total de desfiliados
    $pdf->Ln(2);
    $pdf->SetFillColor(231, 76, 60); // Vermelho para desfiliações
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(190, 7, 'TOTAL DE DESFILIADOS NO PERÍODO: ' . count($desfiliados), 0, 1, 'C', true);
    
    // ============================================
    // OUTPUT DO PDF
    // ============================================
    $nomeArquivo = 'Relatorio_Filiacoes_Desfiliacoes_' . date('Y-m-d') . '.pdf';
    $pdf->Output($nomeArquivo, 'I');
    
} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>Erro ao gerar PDF</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>