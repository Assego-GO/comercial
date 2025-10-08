<?php
/**
 * Classe para gerar PDF da Ficha usando HTML
 * classes/FichaPDFHTML.php
 */

class FichaPDFHTML {
    private $associadoData;
    private $htmlContent;
    
    public function __construct($associadoData) {
        $this->associadoData = $associadoData;
    }
    
    public function gerarFicha() {
        $this->htmlContent = $this->gerarHTML();
    }
    
    private function gerarHTML() {
        // Dados pessoais
        $nome = strtoupper($this->associadoData['nome'] ?? '');
        $cpf = $this->formatarCPF($this->associadoData['cpf'] ?? '');
        $rg = $this->associadoData['rg'] ?? '';
        $nasc = $this->formatarData($this->associadoData['nasc'] ?? '');
        $sexo = $this->associadoData['sexo'] ?? '';
        $estadoCivil = $this->associadoData['estadoCivil'] ?? '';
        $escolaridade = $this->associadoData['escolaridade'] ?? '';
        $email = strtolower($this->associadoData['email'] ?? '');
        $telefone = $this->formatarTelefone($this->associadoData['telefone'] ?? '');
        
        // Endereço
        $cep = $this->formatarCEP($this->associadoData['cep'] ?? '');
        $endereco = $this->associadoData['endereco'] ?? '';
        $numero = $this->associadoData['numero'] ?? '';
        $complemento = $this->associadoData['complemento'] ?? '';
        $bairro = $this->associadoData['bairro'] ?? '';
        $cidade = $this->associadoData['cidade'] ?? 'GOIÂNIA';
        
        // Dados militares
        $corporacao = $this->associadoData['corporacao'] ?? '';
        $patente = $this->associadoData['patente'] ?? '';
        $categoria = $this->associadoData['categoria'] ?? '';
        $lotacao = $this->associadoData['lotacao'] ?? '';
        $unidade = $this->associadoData['unidade'] ?? '';
        
        // Dados financeiros
        $tipoAssociado = $this->associadoData['tipoAssociado'] ?? '';
        $vinculoServidor = $this->associadoData['vinculoServidor'] ?? '';
        $agencia = $this->associadoData['agencia'] ?? '';
        $operacao = $this->associadoData['operacao'] ?? '';
        $contaCorrente = $this->associadoData['contaCorrente'] ?? '';
        
        // Data de filiação
        $dataFiliacao = $this->formatarData($this->associadoData['dataFiliacao'] ?? date('Y-m-d'));
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Filiação - ASSEGO</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: white;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }
        
        /* Cabeçalho */
        .header {
            text-align: center;
            margin-bottom: 15mm;
            position: relative;
        }
        
        .header-logo {
            position: absolute;
            left: 0;
            top: 0;
            width: 30mm;
            height: 30mm;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8pt;
            color: #999;
        }
        
        .header-text {
            padding: 0 35mm;
        }
        
        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        
        .header h2 {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }
        
        .header .slogan {
            font-style: italic;
            font-size: 10pt;
            margin-bottom: 3mm;
        }
        
        .header .contatos {
            font-size: 9pt;
            margin-bottom: 2mm;
        }
        
        .header .endereco {
            font-size: 9pt;
        }
        
        .autorizo-box {
            position: absolute;
            right: 0;
            top: 0;
            border: 1px solid #000;
            padding: 3mm 8mm;
            font-weight: bold;
            font-size: 10pt;
        }
        
        .autorizo-data {
            margin-top: 5mm;
            font-weight: normal;
            font-size: 9pt;
        }
        
        /* Título principal */
        .titulo-principal {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 10mm 0;
            padding: 5mm 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        /* Campos do formulário */
        .campo-linha {
            display: flex;
            margin-bottom: 3mm;
            align-items: baseline;
        }
        
        .campo-label {
            font-weight: bold;
            margin-right: 2mm;
            white-space: nowrap;
        }
        
        .campo-valor {
            flex: 1;
            border-bottom: 1px solid #000;
            padding-left: 2mm;
            min-height: 5mm;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .campo-curto {
            flex: 0 0 auto;
            margin-right: 5mm;
        }
        
        /* Checkboxes */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 3mm;
        }
        
        .checkbox-item {
            margin-right: 5mm;
            display: flex;
            align-items: center;
        }
        
        .checkbox {
            border: 1px solid #000;
            width: 4mm;
            height: 4mm;
            margin-right: 2mm;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10pt;
        }
        
        /* Seção de dependentes */
        .secao-dependentes {
            margin-top: 8mm;
        }
        
        .tabela-dependentes {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }
        
        .tabela-dependentes th,
        .tabela-dependentes td {
            border: 1px solid #000;
            padding: 2mm;
            text-align: left;
        }
        
        .tabela-dependentes th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .tabela-dependentes td {
            height: 8mm;
            vertical-align: middle;
        }
        
        /* Autorização */
        .autorizacao {
            margin-top: 8mm;
            text-align: justify;
            font-size: 9pt;
            line-height: 1.5;
            padding: 3mm;
            border: 1px solid #000;
        }
        
        /* Assessoria jurídica */
        .assessoria {
            margin-top: 8mm;
        }
        
        .assessoria-opcoes {
            display: flex;
            justify-content: space-around;
            margin-top: 5mm;
        }
        
        /* Assinaturas */
        .assinaturas {
            margin-top: 15mm;
            display: flex;
            justify-content: space-between;
        }
        
        .assinatura {
            text-align: center;
            width: 45%;
        }
        
        .assinatura-linha {
            border-bottom: 1px solid #000;
            margin-bottom: 2mm;
            height: 10mm;
        }
        
        .assinatura-nome {
            font-size: 9pt;
        }
        
        .data-local {
            margin-top: 8mm;
            text-align: left;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Cabeçalho -->
        <div class="header">
            <div class="header-logo">LOGO</div>
            <div class="autorizo-box">
                AUTORIZO
                <div class="autorizo-data">Em: ___/___/_____</div>
            </div>
            <div class="header-text">
                <h1>ASSOCIAÇÃO DOS SUBTENENTES</h1>
                <h2>E SARGENTOS PM & BM - GO</h2>
                <p class="slogan">"Luta classista com responsabilidade"</p>
                <p class="contatos">(62) 3281-3177 &nbsp;&nbsp; WhatsApp &nbsp;&nbsp; Twitter &nbsp;&nbsp; Instagram &nbsp;&nbsp; Facebook</p>
                <p class="endereco">Rua 87 nº 561 - Setor Sul - CEP: 74093-300 - Goiânia-GO</p>
            </div>
        </div>
        
        <!-- Título -->
        <h1 class="titulo-principal">FICHA DE FILIAÇÃO</h1>
        
        <!-- Dados Pessoais -->
        <div class="campo-linha">
            <span class="campo-label">Nome:</span>
            <span class="campo-valor">{$nome}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">Rua/Av.:</span>
            <span class="campo-valor" style="flex: 0 0 50%">{$endereco}</span>
            <span class="campo-label" style="margin-left: 5mm;">Nº:</span>
            <span class="campo-valor" style="flex: 0 0 15%">{$numero}</span>
            <span class="campo-label" style="margin-left: 5mm;">Bairro:</span>
            <span class="campo-valor">{$bairro}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">CEP:</span>
            <span class="campo-valor" style="flex: 0 0 20%">{$cep}</span>
            <span class="campo-label" style="margin-left: 5mm;">Cidade:</span>
            <span class="campo-valor" style="flex: 0 0 35%">{$cidade}</span>
            <span class="campo-label" style="margin-left: 5mm;">Estado:</span>
            <span class="campo-valor">GOIÁS</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">E-mail:</span>
            <span class="campo-valor">{$email}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">Data nascimento:</span>
            <span class="campo-valor" style="flex: 0 0 20%">{$nasc}</span>
            <span class="campo-label" style="margin-left: 5mm;">Fone:</span>
            <span class="campo-valor" style="flex: 0 0 25%">{$telefone}</span>
            <span class="campo-label" style="margin-left: 5mm;">Celular:</span>
            <span class="campo-valor">{$telefone}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">CPF:</span>
            <span class="campo-valor" style="flex: 0 0 25%">{$cpf}</span>
            <span class="campo-label" style="margin-left: 5mm;">Documento identificação:</span>
            <span class="campo-valor">{$rg}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">Estado Civil:</span>
            <div class="checkbox-group" style="flex: 1">
                <span class="checkbox-item">
                    <span class="checkbox">{$this->marcarCheckbox($estadoCivil, 'Solteiro')}</span> Solteiro
                </span>
                <span class="checkbox-item">
                    <span class="checkbox">{$this->marcarCheckbox($estadoCivil, 'Casado')}</span> Casado
                </span>
                <span class="checkbox-item">
                    <span class="checkbox">{$this->marcarCheckbox($estadoCivil, 'Divorciado')}</span> Divorciado
                </span>
                <span class="checkbox-item">
                    <span class="checkbox">{$this->marcarCheckbox($estadoCivil, 'Separado Judicial')}</span> Separado Judicial
                </span>
                <span class="checkbox-item">
                    <span class="checkbox">{$this->marcarCheckbox($estadoCivil, 'Viúvo')}</span> Viúvo
                </span>
                <span class="checkbox-item">
                    <span class="checkbox">{$this->marcarCheckbox($estadoCivil, 'Outro')}</span> Outro
                </span>
            </div>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">Lotado no(a):</span>
            <span class="campo-valor" style="flex: 0 0 40%">{$unidade}</span>
            <span class="campo-label" style="margin-left: 5mm;">Vínculo:</span>
            <span class="campo-valor">{$vinculoServidor}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">Posto/Graduação:</span>
            <span class="campo-valor" style="flex: 0 0 35%">{$patente}</span>
            <span class="campo-label" style="margin-left: 5mm;">Data admissão:</span>
            <span class="campo-valor">{$dataFiliacao}</span>
        </div>
        
        <div class="campo-linha">
            <span class="campo-label">Fone OPM/BM:</span>
            <span class="campo-valor" style="flex: 0 0 25%"></span>
            <span class="campo-label" style="margin-left: 5mm;">Ramal:</span>
            <span class="campo-valor" style="flex: 0 0 15%"></span>
            <span class="campo-label" style="margin-left: 5mm;">Escolaridade:</span>
            <span class="campo-valor">{$escolaridade}</span>
        </div>
        
        <!-- Dependentes -->
        <div class="secao-dependentes">
            <table class="tabela-dependentes">
                <thead>
                    <tr>
                        <th colspan="2" style="background-color: #333; color: white;">Dependentes</th>
                    </tr>
                    <tr>
                        <th style="width: 70%">Esposa(o) / companheira(o)</th>
                        <th style="width: 30%">Telefone</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <th>Filhos menores de 18 anos ou estudante até os 21 anos</th>
                        <th>Data de Nascimento</th>
                    </tr>
HTML;

        // Adicionar linhas para dependentes
        $dependentes = $this->associadoData['dependentes'] ?? [];
        $totalLinhas = max(5, count($dependentes));
        
        for ($i = 0; $i < $totalLinhas; $i++) {
            if ($i < count($dependentes)) {
                $dep = $dependentes[$i];
                $depNome = strtoupper($dep['nome'] ?? '');
                $depData = $this->formatarData($dep['data_nascimento'] ?? '');
                $html .= "<tr><td>{$depNome}</td><td>{$depData}</td></tr>\n";
            } else {
                $html .= "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>\n";
            }
        }

        $html .= <<<HTML
                </tbody>
            </table>
        </div>
        
        <!-- Autorização -->
        <div class="autorizacao">
            <strong>AUTORIZAÇÃO PARA DESCONTO</strong>, em conformidade com as deliberações fixadas na ASSEMBLEIA GERAL, que instituiu o ESTATUTO SOCIAL da entidade registrado no 2º Cartório de Pessoas Jurídicas, Títulos, Documentos e Protestos da cidade de Goiânia, Livro A2, folhas 21/2, sob o nº 209, em 28/08/1991. Autorizo as consignações em folha de pagamento com base no Decreto nº 7475 de 09/03/2011, que altera o Decreto nº 1.968 de 15/04/1997, que dispõe sobre descontos em folha de pagamento ou conta corrente para repassar à ASSOCIAÇÃO DOS SUBTENENTES E SARGENTOS PM & BM DO ESTADO DE GOIÁS, o valor correspondente a 1,75% (um vírgula setenta e cinco por cento), calculado sobre o subsídio do 3º Sargento da PM, referente ao Art. 8º inciso III c/c a parte inicial do inciso IV do artigo 8º da CRFB.
        </div>
        
        <!-- Dados bancários -->
        <div class="campo-linha" style="margin-top: 5mm;">
            <span class="campo-label">AGÊNCIA:</span>
            <span class="campo-valor" style="flex: 0 0 20%">{$agencia}</span>
            <span class="campo-label" style="margin-left: 5mm;">CONTA CORRENTE:</span>
            <span class="campo-valor" style="flex: 0 0 25%">{$contaCorrente}</span>
            <span class="campo-label" style="margin-left: 5mm;">BANCO:</span>
            <span class="campo-valor"></span>
        </div>
        
        <!-- Assessoria Jurídica -->
        <div class="assessoria">
            <p style="font-size: 9pt; text-align: justify; line-height: 1.5;">
                DECLARO PARA OS DEVIDOS FINS DE DIREITO que tenho ciência do disposto no art. 18 do Estatuto Social, em especial o §6º que versa: "O associado contribuinte optante da assessoria jurídica que vier a utilizá-la dentro do período de carência e se desfiliar antes de decorrido o prazo de 12 (doze) meses de contribuição ininterrupta deverá indenizar a ASSEGO no valor correspondente ao restante até que se complete os 12 (doze) contribuições, sob pena, da devida ação de cobrança".
            </p>
            <div class="assessoria-opcoes">
                <div class="checkbox-item">
                    <span class="checkbox" style="width: 5mm; height: 5mm;">[ ]</span>
                    <strong>OPTANTE ASSESSORIA JURÍDICA</strong>
                </div>
                <div class="checkbox-item">
                    <span class="checkbox" style="width: 5mm; height: 5mm;">[ ]</span>
                    <strong>NÃO OPTANTE ASSESSORIA JURÍDICA</strong>
                </div>
            </div>
        </div>
        
        <!-- Data e Assinaturas -->
        <div class="data-local">
            Goiânia, {$this->dataAtual()}
        </div>
        
        <div class="assinaturas">
            <div class="assinatura">
                <div class="assinatura-linha"></div>
                <p class="assinatura-nome">Assinatura do Associado</p>
            </div>
            <div class="assinatura">
                <div class="assinatura-linha"></div>
                <p class="assinatura-nome">ASSEGO</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }
    
    private function marcarCheckbox($valor, $opcao) {
        return (strcasecmp(trim($valor), trim($opcao)) === 0) ? 'X' : '';
    }
    
    private function formatarCPF($cpf) {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) == 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
        return $cpf;
    }
    
    private function formatarCEP($cep) {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) == 8) {
            return substr($cep, 0, 2) . '.' . substr($cep, 2, 3) . '-' . substr($cep, 5, 3);
        }
        return $cep;
    }
    
    private function formatarTelefone($telefone) {
        $telefone = preg_replace('/\D/', '', $telefone);
        if (strlen($telefone) == 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
        } elseif (strlen($telefone) == 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        }
        return $telefone;
    }
    
    private function formatarData($data) {
        if ($data && $data != '0000-00-00') {
            $timestamp = strtotime($data);
            if ($timestamp !== false) {
                return date('d/m/Y', $timestamp);
            }
        }
        return '';
    }
    
    private function dataAtual() {
        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
        return strftime('%d de %B de %Y');
    }
    
    public function salvarPDF($caminho) {
        // Primeiro salvar como HTML
        $htmlPath = str_replace('.pdf', '.html', $caminho);
        file_put_contents($htmlPath, $this->htmlContent);
        
        // Tentar converter para PDF usando diferentes métodos
        
        // Método 1: wkhtmltopdf (se instalado)
        $wkhtmltopdf = $this->encontrarWkhtmltopdf();
        if ($wkhtmltopdf) {
            $cmd = escapeshellcmd($wkhtmltopdf) . ' --enable-local-file-access --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm ' . 
                   escapeshellarg($htmlPath) . ' ' . escapeshellarg($caminho) . ' 2>&1';
            exec($cmd, $output, $return);
            
            if ($return === 0 && file_exists($caminho)) {
                unlink($htmlPath); // Remover HTML temporário
                return true;
            }
        }
        
        // Método 2: Usar DomPDF se disponível
        if (class_exists('Dompdf\Dompdf')) {
            try {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($this->htmlContent);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                file_put_contents($caminho, $dompdf->output());
                unlink($htmlPath);
                return true;
            } catch (Exception $e) {
                error_log("Erro DomPDF: " . $e->getMessage());
            }
        }
        
        // Método 3: Criar PDF básico com PHP puro
        $this->criarPDFBasico($caminho);
        unlink($htmlPath);
        return true;
    }
    
    private function encontrarWkhtmltopdf() {
        $possiveis = [
            '/usr/local/bin/wkhtmltopdf',
            '/usr/bin/wkhtmltopdf',
            'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
            'C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltopdf.exe'
        ];
        
        foreach ($possiveis as $caminho) {
            if (file_exists($caminho) && is_executable($caminho)) {
                return $caminho;
            }
        }
        
        // Tentar comando direto
        exec('which wkhtmltopdf 2>&1', $output, $return);
        if ($return === 0 && !empty($output[0])) {
            return trim($output[0]);
        }
        
        return false;
    }
    
    private function criarPDFBasico($caminho) {
        // Criar um PDF básico mas legível com os dados principais
        $pdf = "%PDF-1.4\n";
        $objCount = 1;
        $xref = [];
        
        // Catalog
        $xref[] = strlen($pdf);
        $pdf .= "$objCount 0 obj\n<< /Type /Catalog /Pages " . ($objCount + 1) . " 0 R >>\nendobj\n";
        $objCount++;
        
        // Pages
        $xref[] = strlen($pdf);
        $pdf .= "$objCount 0 obj\n<< /Type /Pages /Kids [" . ($objCount + 1) . " 0 R] /Count 1 >>\nendobj\n";
        $objCount++;
        
        // Page
        $xref[] = strlen($pdf);
        $pdf .= "$objCount 0 obj\n<< /Type /Page /Parent " . ($objCount - 1) . " 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 " . ($objCount + 1) . " 0 R >> >> /Contents " . ($objCount + 2) . " 0 R >>\nendobj\n";
        $objCount++;
        
        // Font
        $xref[] = strlen($pdf);
        $pdf .= "$objCount 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $objCount++;
        
        // Content stream
        $content = $this->gerarConteudoPDFBasico();
        $xref[] = strlen($pdf);
        $pdf .= "$objCount 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream\nendobj\n";
        $objCount++;
        
        // Cross-reference table
        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 " . ($objCount) . "\n";
        $pdf .= "0000000000 65535 f\n";
        foreach ($xref as $offset) {
            $pdf .= sprintf("%010d 00000 n\n", $offset);
        }
        
        // Trailer
        $pdf .= "trailer\n<< /Size " . $objCount . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xrefStart\n";
        $pdf .= "%%EOF";
        
        file_put_contents($caminho, $pdf);
    }
    
    private function gerarConteudoPDFBasico() {
        $nome = $this->associadoData['nome'] ?? '';
        $cpf = $this->formatarCPF($this->associadoData['cpf'] ?? '');
        $patente = $this->associadoData['patente'] ?? '';
        
        $content = "BT\n";
        $content .= "/F1 18 Tf\n";
        $content .= "50 750 Td\n";
        $content .= "(FICHA DE FILIACAO - ASSEGO) Tj\n";
        $content .= "0 -30 Td\n";
        $content .= "/F1 12 Tf\n";
        $content .= "(Nome: " . $this->escapeString($nome) . ") Tj\n";
        $content .= "0 -20 Td\n";
        $content .= "(CPF: " . $cpf . ") Tj\n";
        $content .= "0 -20 Td\n";
        $content .= "(Patente: " . $this->escapeString($patente) . ") Tj\n";
        $content .= "0 -40 Td\n";
        $content .= "(Documento gerado em: " . date('d/m/Y H:i:s') . ") Tj\n";
        $content .= "ET\n";
        
        return $content;
    }
    
    private function escapeString($str) {
        // Escapar caracteres especiais para PDF
        $str = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $str);
        // Remover acentos para PDF básico
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        return $str;
    }
    
    public function exibirPDF() {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ficha_filiacao.pdf"');
        
        // Se temos HTML, converter ou retornar PDF básico
        $tempFile = tempnam(sys_get_temp_dir(), 'ficha_');
        $this->salvarPDF($tempFile);
        readfile($tempFile);
        unlink($tempFile);
    }
    
    public function obterPDFString() {
        $tempFile = tempnam(sys_get_temp_dir(), 'ficha_');
        $this->salvarPDF($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        return $content;
    }
}