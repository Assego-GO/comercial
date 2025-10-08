<?php
/**
 * Página de Gerenciamento do Fluxo de Assinatura - VERSÃO COM BUSCA POR RG
 * pages/desfiliacao.php
 * 
 * NOVA FUNCIONALIDADE: Busca por RG e preenchimento automático de ficha de desfiliação
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Documentos.php';

// CORREÇÃO: Incluir a classe HeaderComponent ANTES de tentar usá-la
require_once './components/header.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Fluxo de Assinatura - ASSEGO';

// Busca estatísticas de documentos em fluxo
try {
    $documentos = new Documentos();
    $statsFluxo = $documentos->getEstatisticasFluxo();
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de fluxo: " . $e->getMessage());
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'documentos',
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

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/documentos.css">

    <!-- CSS PERSONALIZADO PARA BUSCA POR RG -->
    <style>
        /* Seção de Busca por RG */
        .busca-associado-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(44, 90, 160, 0.1);
        }

        .busca-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .busca-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light) 0%, #e3f2fd 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .busca-icon i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .busca-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .busca-subtitle {
            color: var(--secondary);
            font-size: 0.95rem;
            margin: 0;
        }

        .busca-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .busca-input-group {
            flex: 1;
            min-width: 200px;
        }

        .btn-buscar {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-buscar:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-buscar:disabled {
            background: var(--secondary);
            cursor: not-allowed;
            transform: none;
        }

        .btn-limpar-busca {
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-limpar-busca:hover {
            background: #5a6268;
        }

        /* Dados do Associado */
        .dados-associado-container {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f8f9fa;
        }

        .dados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .dados-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1rem;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .dados-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.1);
        }

        .dados-label {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dados-value {
            color: var(--dark);
            font-size: 1rem;
            font-weight: 500;
            word-break: break-word;
        }

        /* Ficha de Desfiliação */
        .ficha-desfiliacao-container {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            margin-top: 2rem;
            overflow: hidden;
        }

        .ficha-header-container {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .ficha-header-container h4 {
            margin: 0;
            font-weight: 600;
        }

        .ficha-content {
            padding: 3rem;
        }

        .ficha-desfiliacao {
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            color: #333;
            font-size: 14px;
        }

        .ficha-title {
            text-align: center;
            font-size: 1.6rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
        }

        .campo-preenchimento {
            border-bottom: 1px solid #333;
            min-width: 150px;
            display: inline-block;
            padding: 2px 8px;
            margin: 0 3px;
            font-weight: bold;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 3px;
        }

        .campo-preenchimento.largo {
            min-width: 400px;
        }

        .campo-preenchimento.medio {
            min-width: 250px;
        }

        .motivo-area {
            border: 2px solid var(--primary);
            min-height: 100px;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            background: #f8f9fa;
            font-style: italic;
        }

        .assinatura-area {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #333;
        }

        .linha-assinatura {
            border-top: 2px solid #333;
            width: 300px;
            margin: 2rem auto 1rem;
            padding-top: 0.5rem;
            font-weight: bold;
        }

        /* Botões de ação da ficha */
        .ficha-actions {
            padding: 2rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            text-align: center;
        }

        .btn-imprimir {
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin-right: 1rem;
        }

        .btn-imprimir:hover {
            background: #146c43;
            transform: translateY(-2px);
        }

        .btn-gerar-pdf {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-gerar-pdf:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }

        /* Loading overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            z-index: 1000;
        }

        .busca-associado-section {
            position: relative;
        }

        /* Alertas personalizados */
        .alert-busca {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
        }

        .alert-busca i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .alert-success-busca {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger-busca {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-info-busca {
            background: linear-gradient(135deg, var(--primary-light) 0%, #e3f2fd 100%);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .busca-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .dados-grid {
                grid-template-columns: 1fr;
            }
            
            .ficha-content {
                padding: 2rem 1.5rem;
            }
            
            .campo-preenchimento.largo {
                min-width: 100%;
                display: block;
                margin: 5px 0;
            }
            
            .tipo-desfiliacao-section .row {
                flex-direction: column;
            }
            
            .tipo-desfiliacao-section .col-md-6 {
                margin-bottom: 1rem;
            }
        }

        /* Estilo para seleção do tipo de desfiliação */
        .tipo-desfiliacao-section .form-check {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tipo-desfiliacao-section .form-check:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.15);
        }

        .tipo-desfiliacao-section .form-check-input:checked + .form-check-label {
            color: var(--primary);
        }

        .tipo-desfiliacao-section .form-check-input:checked + .form-check-label strong {
            color: var(--primary);
        }

        #textoDesfiliacao {
            font-weight: normal;
            color: #333;
        }

        /* Modo impressão */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .ficha-desfiliacao-container {
                box-shadow: none;
                border: 2px solid #000;
            }
            
            .ficha-content {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Title -->
            <div class="page-header mb-4" data-aos="fade-right">
                <div>
                    <h1 class="page-title">Processo de Desfiliação</h1>
                    <p class="page-subtitle">Gerencie o processo de assinatura e consulte dados de associados</p>
                </div>
            </div>

            <!-- NOVA SEÇÃO: Busca por RG e Ficha de Desfiliação -->
            <div class="busca-associado-section" data-aos="fade-up">
                <div class="busca-header">
                    <div class="busca-icon">
                        <i class="fas fa-user-search"></i>
                    </div>
                    <div>
                        <h3 class="busca-title">Consulta de Associado</h3>
                        <p class="busca-subtitle">Digite o RG militar ou CPF para buscar dados e gerar ficha de desfiliação</p>
                    </div>
                </div>

                <form class="busca-form" onsubmit="buscarAssociadoPorRG(event)">
                    <div class="busca-input-group">
                        <label class="form-label" for="rgBusca">RG Militar</label>
                        <input type="text" class="form-control" id="rgBusca" 
                               placeholder="Digite o RG militar..." required>
                    </div>
                    <button type="submit" class="btn-buscar" id="btnBuscarRG">
                        <i class="fas fa-search me-2"></i>
                        Buscar Associado
                    </button>
                    <button type="button" class="btn-limpar-busca" onclick="limparBuscaRG()">
                        <i class="fas fa-eraser me-2"></i>
                        Limpar
                    </button>
                </form>

                <!-- Alert para mensagens de busca -->
                <div id="alertBusca" class="alert-busca" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    <span id="alertBuscaText"></span>
                </div>

                <!-- Container para dados do associado -->
                <div id="dadosAssociadoContainer" class="dados-associado-container" style="display: none;">
                    <h5 class="mb-3">
                        <i class="fas fa-user me-2" style="color: var(--primary);"></i>
                        Dados do Associado Encontrado
                    </h5>
                    
                    <div class="dados-grid" id="dadosAssociadoGrid">
                        <!-- Dados serão inseridos aqui dinamicamente -->
                    </div>
                </div>

                <!-- Container para ficha de desfiliação -->
                <div id="fichaDesfiliacao" class="ficha-desfiliacao-container" style="display: none;">
                    <!-- NOVA SEÇÃO: Seleção do Tipo de Desfiliação -->
                    <div class="tipo-desfiliacao-section no-print" style="background: #f8f9fa; padding: 1.5rem 2rem; border-bottom: 1px solid #dee2e6;">
                        <h5 class="mb-3" style="color: var(--primary);">
                            <i class="fas fa-list-ul me-2"></i>
                            Selecione o Tipo de Desfiliação
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded" style="background: white;">
                                    <input class="form-check-input" type="radio" name="tipoDesfiliacao" id="desfiliacao_total" value="total" checked onchange="atualizarTipoDesfiliacao()">
                                    <label class="form-check-label" for="desfiliacao_total">
                                        <strong>Desfiliação Total</strong><br>
                                        <small class="text-muted">Desfiliação completa da ASSEGO</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded" style="background: white;">
                                    <input class="form-check-input" type="radio" name="tipoDesfiliacao" id="desfiliacao_juridico" value="juridico" onchange="atualizarTipoDesfiliacao()">
                                    <label class="form-check-label" for="desfiliacao_juridico">
                                        <strong>Desfiliação do Jurídico</strong><br>
                                        <small class="text-muted">Desfiliação apenas do Departamento Jurídico</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ficha-header-container no-print">
                        <h4>
                            <i class="fas fa-file-alt me-2"></i>
                            Ficha de Desfiliação - ASSEGO
                        </h4>
                        <p class="mb-0">Documento oficial preenchido automaticamente</p>
                    </div>

                    <div class="ficha-content">
                        <div class="ficha-desfiliacao">
                            <div class="ficha-title">
                                SOLICITAÇÃO DE DESFILIAÇÃO<br>
                                ASSEGO
                            </div>

                            <p>
                                Goiânia, <span class="campo-preenchimento" id="diaAtual"></span> de 
                                <span class="campo-preenchimento" id="mesAtual"></span> de 
                                <span class="campo-preenchimento" id="anoAtual"></span>
                            </p>

                            <br>

                            <p><strong>Prezado Sr. Presidente,</strong></p>

                            <br>

                            <p>
                                Eu, <span class="campo-preenchimento largo" id="nomeCompleto" contenteditable="true"></span>,
                                portador do RG militar: <span class="campo-preenchimento" id="rgMilitar" contenteditable="true"></span>, 
                                Instituição: <span class="campo-preenchimento medio" id="corporacao" contenteditable="true"></span>,
                                residente e domiciliado: 
                                <span class="campo-preenchimento largo" id="endereco1" contenteditable="true"></span>
                            </p>

                            <p>
                                <span class="campo-preenchimento largo" id="endereco2" contenteditable="true"></span>
                            </p>

                            <p>
                                <span class="campo-preenchimento largo" id="endereco3" contenteditable="true"></span>,
                                telefone <span class="campo-preenchimento" id="telefoneFormatado" contenteditable="true"></span>, 
                                Lotação: <span class="campo-preenchimento medio" id="lotacao" contenteditable="true"></span>,
                                solicito <span id="textoDesfiliacao">minha desfiliação total da Associação dos Subtenentes e Sargentos do Estado de Goiás – ASSEGO</span>, pelo motivo:
                            </p>

                            <div class="motivo-area" contenteditable="true" id="motivoDesfiliacao">
                                Clique aqui para digitar o motivo da desfiliação...
                            </div>

                            <br>

                            <p>
                                Me coloco à disposição, através do telefone informado acima para informações
                                adicionais necessárias à conclusão deste processo e, desde já, <strong>DECLARO ESTAR 
                                CIENTE QUE O PROCESSO INTERNO TEM UM PRAZO DE ATÉ 30 DIAS, A CONTAR DA 
                                DATA DE SOLICITAÇÃO, PARA SER CONCLUÍDO.</strong>
                            </p>

                            <br>

                            <p><strong>Respeitosamente,</strong></p>

                            <div class="assinatura-area">
                                <div class="linha-assinatura">
                                    Assinatura do requerente
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botões de ação -->
                    <div class="ficha-actions no-print">
                        <button class="btn-imprimir" onclick="imprimirFicha()">
                            <i class="fas fa-print me-2"></i>
                            Imprimir Ficha
                        </button>
                        <button class="btn-gerar-pdf" onclick="gerarPDFFicha()">
                            <i class="fas fa-file-pdf me-2"></i>
                            Gerar PDF
                        </button>
                    </div>
                </div>

                <!-- Loading overlay -->
                <div id="loadingBusca" class="loading-overlay" style="display: none;">
                    <div class="text-center">
                        <div class="loading-spinner mb-3"></div>
                        <p class="text-muted">Buscando dados do associado...</p>
                    </div>
                </div>
            </div>

            
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>
    
    <!-- JavaScript customizado -->
    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        let dadosAssociadoAtual = null;

        // Inicialização
        $(document).ready(function () {
            preencherDataAtual();
            configurarFichaDesfiliacao();
            atualizarTipoDesfiliacao(); // Inicializa o texto da desfiliação

            // Event listener para Enter no campo RG
            $('#rgBusca').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAssociadoPorRG(e);
                }
            });
        });

        // NOVAS FUNÇÕES PARA BUSCA POR RG

        // Preencher data atual
        function preencherDataAtual() {
            const hoje = new Date();
            const dia = hoje.getDate();
            const meses = [
                'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
            ];
            const mes = meses[hoje.getMonth()];
            const ano = hoje.getFullYear();

            document.getElementById('diaAtual').textContent = dia.toString().padStart(2, '0');
            document.getElementById('mesAtual').textContent = mes;
            document.getElementById('anoAtual').textContent = ano.toString();
        }

        // Buscar associado por RG com melhor tratamento de erros
        async function buscarAssociadoPorRG(event) {
            event.preventDefault();
            
            const rgInput = document.getElementById('rgBusca');
            const rg = rgInput.value.trim();
            const btnBuscar = document.getElementById('btnBuscarRG');
            const loadingOverlay = document.getElementById('loadingBusca');
            const dadosContainer = document.getElementById('dadosAssociadoContainer');
            const fichaContainer = document.getElementById('fichaDesfiliacao');
            
            console.log('🔍 Iniciando busca por RG:', rg);
            
            if (!rg) {
                mostrarAlertaBusca('Por favor, digite um RG para buscar.', 'danger');
                return;
            }

            // Mostra loading
            loadingOverlay.style.display = 'flex';
            btnBuscar.disabled = true;
            dadosContainer.style.display = 'none';
            fichaContainer.style.display = 'none';
            esconderAlertaBusca();

            try {
                // URL da API - ajuste conforme necessário
                const apiUrl = `../api/associados/buscar_por_rg.php?rg=${encodeURIComponent(rg)}&debug=1`;
                console.log('🌐 URL da API:', apiUrl);
                
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin'
                });

                console.log('📡 Response status:', response.status);

                // Verifica se a resposta é OK
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
                }

                // Pega o texto da resposta primeiro para debug
                const responseText = await response.text();
                console.log('📄 Response text (primeiros 500 chars):', responseText.substring(0, 500));

                // Tenta fazer parse do JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('❌ Erro ao fazer parse do JSON:', jsonError);
                    console.error('📄 Resposta completa:', responseText);
                    
                    // Se não é JSON válido, pode ser HTML de erro
                    if (responseText.includes('<html>') || responseText.includes('<!DOCTYPE')) {
                        throw new Error('A API retornou uma página HTML ao invés de JSON. Verifique se o arquivo da API existe e está funcionando corretamente.');
                    }
                    
                    throw new Error(`Resposta inválida da API: ${jsonError.message}`);
                }

                console.log('✅ Resultado da API:', result);

                if (result.status === 'success') {
                    dadosAssociadoAtual = result.data;
                    exibirDadosAssociado(dadosAssociadoAtual);
                    preencherFichaDesfiliacao(dadosAssociadoAtual);
                    
                    dadosContainer.style.display = 'block';
                    fichaContainer.style.display = 'block';
                    
                    mostrarAlertaBusca('Associado encontrado! Dados carregados e ficha preenchida automaticamente.', 'success');
                    
                    // Scroll suave até os dados
                    setTimeout(() => {
                        dadosContainer.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start' 
                        });
                    }, 300);
                } else {
                    console.warn('⚠️ Erro da API:', result.message);
                    mostrarAlertaBusca(result.message || 'Erro desconhecido na busca', 'danger');
                    
                    // Mostra debug se disponível
                    if (result.debug) {
                        console.log('🐛 Debug da API:', result.debug);
                    }
                }

            } catch (error) {
                console.error('❌ Erro na busca completa:', error);
                console.error('❌ Stack trace:', error.stack);
                
                let mensagemErro = 'Erro ao buscar associado. ';
                
                if (error.message.includes('HTTP Error')) {
                    mensagemErro += 'Problema no servidor da API.';
                } else if (error.message.includes('Failed to fetch')) {
                    mensagemErro += 'Problema de conexão com o servidor.';
                } else if (error.message.includes('JSON')) {
                    mensagemErro += 'Erro no formato da resposta da API.';
                } else {
                    mensagemErro += error.message;
                }
                
                mostrarAlertaBusca(mensagemErro, 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        // Exibir dados do associado
        function exibirDadosAssociado(dados) {
            const grid = document.getElementById('dadosAssociadoGrid');
            grid.innerHTML = '';

            // Função auxiliar para criar item de dados
            function criarDadosItem(label, value, icone = 'fa-info') {
                if (!value || value === 'null' || value === '') return '';
                
                return `
                    <div class="dados-item">
                        <div class="dados-label">
                            <i class="fas ${icone} me-1"></i>
                            ${label}
                        </div>
                        <div class="dados-value">${value}</div>
                    </div>
                `;
            }

            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            grid.innerHTML += criarDadosItem('Nome Completo', pessoais.nome, 'fa-user');
            grid.innerHTML += criarDadosItem('RG Militar', pessoais.rg, 'fa-id-card');
            grid.innerHTML += criarDadosItem('CPF', formatarCPFBusca(pessoais.cpf), 'fa-id-card');
            grid.innerHTML += criarDadosItem('Data Nascimento', formatarDataBusca(pessoais.data_nascimento), 'fa-calendar');
            grid.innerHTML += criarDadosItem('Email', pessoais.email, 'fa-envelope');
            grid.innerHTML += criarDadosItem('Telefone', formatarTelefoneBusca(pessoais.telefone), 'fa-phone');

            // Dados militares
            const militares = dados.dados_militares || {};
            grid.innerHTML += criarDadosItem('Corporação', militares.corporacao, 'fa-shield-alt');
            grid.innerHTML += criarDadosItem('Patente', militares.patente, 'fa-medal');
            grid.innerHTML += criarDadosItem('Lotação', militares.lotacao, 'fa-building');
            grid.innerHTML += criarDadosItem('Unidade', militares.unidade, 'fa-map-marker-alt');

            // Endereço
            const endereco = dados.endereco || {};
            if (endereco.endereco) {
                const enderecoCompleto = [
                    endereco.endereco,
                    endereco.numero ? `nº ${endereco.numero}` : '',
                    endereco.bairro,
                    endereco.cidade
                ].filter(Boolean).join(', ');
                
                grid.innerHTML += criarDadosItem('Endereço', enderecoCompleto, 'fa-home');
            }
            grid.innerHTML += criarDadosItem('CEP', formatarCEPBusca(endereco.cep), 'fa-map-pin');

            // Dados financeiros
            const financeiros = dados.dados_financeiros || {};
            grid.innerHTML += criarDadosItem('Tipo Associado', financeiros.tipo_associado, 'fa-user-tag');
            grid.innerHTML += criarDadosItem('Situação Financeira', financeiros.situacao_financeira, 'fa-dollar-sign');
            
            // Contrato
            const contrato = dados.contrato || {};
            grid.innerHTML += criarDadosItem('Data Filiação', formatarDataBusca(contrato.data_filiacao), 'fa-handshake');
            
            // Status
            const statusBadge = dados.status_cadastro === 'PRE_CADASTRO' 
                ? '<span class="badge bg-warning">Pré-cadastro</span>'
                : '<span class="badge bg-success">Cadastro Definitivo</span>';
            grid.innerHTML += `
                <div class="dados-item">
                    <div class="dados-label">
                        <i class="fas fa-info-circle me-1"></i>
                        Status do Cadastro
                    </div>
                    <div class="dados-value">${statusBadge}</div>
                </div>
            `;
        }

        // Preencher ficha de desfiliação
        function preencherFichaDesfiliacao(dados) {
            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            document.getElementById('nomeCompleto').textContent = pessoais.nome || '';
            document.getElementById('rgMilitar').textContent = pessoais.rg || '';
            document.getElementById('telefoneFormatado').textContent = formatarTelefoneBusca(pessoais.telefone) || '';

            // Dados militares
            const militares = dados.dados_militares || {};
            document.getElementById('corporacao').textContent = militares.corporacao || '';
            document.getElementById('lotacao').textContent = militares.lotacao || '';

            // Endereço
            const endereco = dados.endereco || {};
            const enderecoCompleto = montarEnderecoCompleto(endereco);
            
            // Divide o endereço em até 3 linhas
            const linhas = quebrarEnderecoEmLinhas(enderecoCompleto);
            document.getElementById('endereco1').textContent = linhas[0] || '';
            document.getElementById('endereco2').textContent = linhas[1] || '';
            document.getElementById('endereco3').textContent = linhas[2] || '';

            // Limpa o motivo para o usuário digitar
            document.getElementById('motivoDesfiliacao').textContent = '';
        }

        // Montar endereço completo
        function montarEnderecoCompleto(endereco) {
            const partes = [];
            
            if (endereco.endereco) {
                let linha = endereco.endereco;
                if (endereco.numero) linha += `, nº ${endereco.numero}`;
                if (endereco.complemento) linha += `, ${endereco.complemento}`;
                partes.push(linha);
            }
            
            if (endereco.bairro) {
                partes.push(`Bairro: ${endereco.bairro}`);
            }
            
            if (endereco.cidade) {
                let cidade = endereco.cidade;
                if (endereco.cep) cidade += ` - CEP: ${formatarCEPBusca(endereco.cep)}`;
                partes.push(cidade);
            }
            
            return partes.join(', ');
        }

        // Quebrar endereço em linhas
        function quebrarEnderecoEmLinhas(enderecoCompleto, maxPorLinha = 60) {
            if (!enderecoCompleto) return ['', '', ''];
            
            const palavras = enderecoCompleto.split(' ');
            const linhas = [];
            let linhaAtual = '';
            
            for (const palavra of palavras) {
                if ((linhaAtual + ' ' + palavra).length <= maxPorLinha) {
                    linhaAtual += (linhaAtual ? ' ' : '') + palavra;
                } else {
                    if (linhaAtual) {
                        linhas.push(linhaAtual);
                        linhaAtual = palavra;
                    } else {
                        linhas.push(palavra);
                    }
                }
            }
            
            if (linhaAtual) linhas.push(linhaAtual);
            
            // Garante 3 linhas
            while (linhas.length < 3) {
                linhas.push('');
            }
            
            return linhas.slice(0, 3);
        }

        // Configurar ficha de desfiliação
        function configurarFichaDesfiliacao() {
            // Limpar placeholder do motivo ao clicar
            const motivoArea = document.getElementById('motivoDesfiliacao');
            
            motivoArea.addEventListener('focus', function() {
                if (this.textContent === 'Clique aqui para digitar o motivo da desfiliação...') {
                    this.textContent = '';
                }
            });

            // Restaurar placeholder se vazio
            motivoArea.addEventListener('blur', function() {
                if (this.textContent.trim() === '') {
                    this.textContent = 'Clique aqui para digitar o motivo da desfiliação...';
                }
            });
        }

        // Limpar busca por RG
        function limparBuscaRG() {
            document.getElementById('rgBusca').value = '';
            document.getElementById('dadosAssociadoContainer').style.display = 'none';
            document.getElementById('fichaDesfiliacao').style.display = 'none';
            document.getElementById('dadosAssociadoGrid').innerHTML = '';
            dadosAssociadoAtual = null;
            esconderAlertaBusca();

            // Limpa campos da ficha
            const campos = [
                'nomeCompleto', 'rgMilitar', 'corporacao', 'endereco1', 
                'endereco2', 'endereco3', 'telefoneFormatado', 'lotacao'
            ];
            
            campos.forEach(campo => {
                const elemento = document.getElementById(campo);
                if (elemento) elemento.textContent = '';
            });
            
            // Restaura placeholder do motivo
            const motivoArea = document.getElementById('motivoDesfiliacao');
            if (motivoArea) {
                motivoArea.textContent = 'Clique aqui para digitar o motivo da desfiliação...';
            }

            // Reseta o tipo de desfiliação para "total"
            document.getElementById('desfiliacao_total').checked = true;
            atualizarTipoDesfiliacao();
        }

        // Mostrar alerta de busca
        function mostrarAlertaBusca(mensagem, tipo) {
            const alertDiv = document.getElementById('alertBusca');
            const alertText = document.getElementById('alertBuscaText');
            const icon = alertDiv.querySelector('i');
            
            alertText.textContent = mensagem;
            
            // Remove classes anteriores
            alertDiv.className = 'alert-busca';
            
            // Adiciona classe e ícone baseado no tipo
            switch (tipo) {
                case 'success':
                    alertDiv.classList.add('alert-success-busca');
                    icon.className = 'fas fa-check-circle';
                    break;
                case 'danger':
                    alertDiv.classList.add('alert-danger-busca');
                    icon.className = 'fas fa-exclamation-triangle';
                    break;
                case 'info':
                    alertDiv.classList.add('alert-info-busca');
                    icon.className = 'fas fa-info-circle';
                    break;
            }
            
            alertDiv.style.display = 'flex';
            
            // Auto-hide após 5 segundos se for sucesso
            if (tipo === 'success') {
                setTimeout(esconderAlertaBusca, 5000);
            }
        }

        // Esconder alerta de busca
        function esconderAlertaBusca() {
            document.getElementById('alertBusca').style.display = 'none';
        }

        // NOVA FUNÇÃO: Atualizar tipo de desfiliação
        function atualizarTipoDesfiliacao() {
            const tipoTotal = document.getElementById('desfiliacao_total').checked;
            const textoDesfiliacao = document.getElementById('textoDesfiliacao');
            
            if (tipoTotal) {
                textoDesfiliacao.textContent = 'minha desfiliação total da Associação dos Subtenentes e Sargentos do Estado de Goiás – ASSEGO';
            } else {
                textoDesfiliacao.textContent = 'minha desfiliação do DEPARTAMENTO JURÍDICO da Associação dos Subtenentes e Sargentos do Estado de Goiás – ASSEGO';
            }
        }

        // FUNÇÃO OTIMIZADA: Imprimir ficha de desfiliação em UMA PÁGINA APENAS
        function imprimirFicha() {
            // Verifica se os campos obrigatórios estão preenchidos
            const nome = document.getElementById('nomeCompleto').textContent.trim();
            const rg = document.getElementById('rgMilitar').textContent.trim();
            const motivo = document.getElementById('motivoDesfiliacao').textContent.trim();
            
            if (!nome || !rg) {
                mostrarAlertaBusca('Por favor, busque um associado antes de imprimir.', 'danger');
                return;
            }
            
            if (!motivo || motivo === 'Clique aqui para digitar o motivo da desfiliação...') {
                mostrarAlertaBusca('Por favor, informe o motivo da desfiliação antes de imprimir.', 'danger');
                return;
            }
            
            // Coleta os dados da ficha
            const dadosFicha = {
                dia: document.getElementById('diaAtual').textContent,
                mes: document.getElementById('mesAtual').textContent,
                ano: document.getElementById('anoAtual').textContent,
                nome: document.getElementById('nomeCompleto').textContent,
                rg: document.getElementById('rgMilitar').textContent,
                corporacao: document.getElementById('corporacao').textContent,
                endereco1: document.getElementById('endereco1').textContent,
                endereco2: document.getElementById('endereco2').textContent,
                endereco3: document.getElementById('endereco3').textContent,
                telefone: document.getElementById('telefoneFormatado').textContent,
                lotacao: document.getElementById('lotacao').textContent,
                motivo: document.getElementById('motivoDesfiliacao').textContent,
                tipoDesfiliacao: document.getElementById('desfiliacao_total').checked ? 'total' : 'juridico'
            };

            // Define o texto da desfiliação baseado no tipo
            const textoDesfiliacao = dadosFicha.tipoDesfiliacao === 'total' 
                ? 'minha desfiliação total da Associação dos Subtenentes e Sargentos do Estado de Goiás – ASSEGO'
                : 'minha desfiliação do DEPARTAMENTO JURÍDICO da Associação dos Subtenentes e Sargentos do Estado de Goiás – ASSEGO';
            
            // Cria nova janela para impressão
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // HTML OTIMIZADO para impressão em UMA PÁGINA
            const printHTML = `
                <!DOCTYPE html>
                <html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Ficha de Desfiliação - ASSEGO</title>
                    <style>
                        /* Reset */
                        * {
                            margin: 0;
                            padding: 0;
                            box-sizing: border-box;
                        }
                        
                        /* Configurações da página - MARGENS REDUZIDAS */
                        @page {
                            size: A4;
                            margin: 1.5cm 2cm;
                        }
                        
                        body {
                            font-family: 'Times New Roman', serif;
                            line-height: 1.4;
                            color: #333;
                            font-size: 13px;
                            background: white;
                            padding: 10px;
                            max-width: 100%;
                            height: 100vh;
                            margin: 0;
                        }
                        
                        /* Título da ficha - COMPACTO */
                        .ficha-title {
                            text-align: center;
                            font-size: 18px;
                            font-weight: bold;
                            color: #2c5aa0;
                            margin-bottom: 20px;
                            padding-bottom: 10px;
                            border-bottom: 2px solid #2c5aa0;
                            text-transform: uppercase;
                        }
                        
                        /* Parágrafos - ESPAÇAMENTO REDUZIDO */
                        p {
                            margin-bottom: 8px;
                            text-align: justify;
                            line-height: 1.3;
                        }
                        
                        /* Campos preenchidos */
                        .campo-preenchimento {
                            border-bottom: 1px solid #333;
                            padding: 1px 4px;
                            margin: 0 2px;
                            font-weight: bold;
                            background: #f8f9fa;
                            display: inline-block;
                            min-width: 60px;
                        }
                        
                        /* Área do motivo - COMPACTA */
                        .motivo-area {
                            border: 2px solid #2c5aa0;
                            min-height: 60px;
                            padding: 10px;
                            margin: 12px 0;
                            border-radius: 5px;
                            background: #f8f9fa;
                            text-align: justify;
                            font-size: 12px;
                            line-height: 1.3;
                        }
                        
                        /* Área da assinatura - COMPACTA */
                        .assinatura-area {
                            text-align: center;
                            margin-top: 25px;
                            padding-top: 15px;
                        }
                        
                        .linha-assinatura {
                            border-top: 2px solid #333;
                            width: 250px;
                            margin: 25px auto 5px;
                            padding-top: 8px;
                            font-weight: bold;
                            text-align: center;
                            font-size: 12px;
                        }
                        
                        /* Texto em negrito */
                        strong {
                            font-weight: bold;
                        }
                        
                        /* Quebras de linha - COMPACTAS */
                        br {
                            line-height: 1.2;
                        }
                        
                        /* Otimizações para UMA PÁGINA */
                        .container-compacto {
                            max-height: 100vh;
                            overflow: hidden;
                        }
                        
                        /* Parágrafo final compacto */
                        .paragrafo-final {
                            font-size: 11px;
                            line-height: 1.2;
                            margin-bottom: 6px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container-compacto">
                        <div class="ficha-title">
                            Solicitação de Desfiliação<br>
                            ASSEGO
                        </div>

                        <p>
                            Goiânia, <span class="campo-preenchimento">${dadosFicha.dia}</span> de 
                            <span class="campo-preenchimento">${dadosFicha.mes}</span> de 
                            <span class="campo-preenchimento">${dadosFicha.ano}</span>
                        </p>

                        <p><strong>Prezado Sr. Presidente,</strong></p>

                        <p>
                            Eu, <span class="campo-preenchimento" style="min-width: 280px;">${dadosFicha.nome}</span>, 
                            portador do RG militar: <span class="campo-preenchimento">${dadosFicha.rg}</span>, 
                            Instituição: <span class="campo-preenchimento" style="min-width: 180px;">${dadosFicha.corporacao}</span>, 
                            residente e domiciliado:
                        </p>
                        
                        <p>
                            <span class="campo-preenchimento" style="min-width: 350px;">${dadosFicha.endereco1}</span>
                        </p>
                        
                        ${dadosFicha.endereco2 ? `<p><span class="campo-preenchimento" style="min-width: 350px;">${dadosFicha.endereco2}</span></p>` : ''}
                        
                        ${dadosFicha.endereco3 ? `<p><span class="campo-preenchimento" style="min-width: 350px;">${dadosFicha.endereco3}</span></p>` : ''}

                        <p>
                            telefone <span class="campo-preenchimento">${dadosFicha.telefone}</span>, 
                            Lotação: <span class="campo-preenchimento" style="min-width: 180px;">${dadosFicha.lotacao}</span>, 
                            solicito ${textoDesfiliacao}, pelo motivo:
                        </p>

                        <div class="motivo-area">
                            ${dadosFicha.motivo}
                        </div>

                        <p class="paragrafo-final">
                            Me coloco à disposição, através do telefone informado acima para informações 
                            adicionais necessárias à conclusão deste processo e, desde já, <strong>DECLARO ESTAR 
                            CIENTE QUE O PROCESSO INTERNO TEM UM PRAZO DE ATÉ 30 DIAS, A CONTAR DA 
                            DATA DE SOLICITAÇÃO, PARA SER CONCLUÍDO.</strong>
                        </p>

                        <p><strong>Respeitosamente,</strong></p>

                        <div class="assinatura-area">
                            <div class="linha-assinatura">
                                Assinatura do requerente
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            // Escreve o HTML na nova janela
            printWindow.document.open();
            printWindow.document.write(printHTML);
            printWindow.document.close();
            
            // Aguarda o carregamento e imprime
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                }, 250);
            };
        }

        // Gerar PDF
        function gerarPDFFicha() {
            mostrarAlertaBusca('Funcionalidade de geração de PDF será implementada em breve.', 'info');
        }

        // Funções auxiliares de formatação
        function formatarCPFBusca(cpf) {
            if (!cpf) return '';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length === 11) {
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
            return cpf;
        }

        function formatarTelefoneBusca(telefone) {
            if (!telefone) return '';
            telefone = telefone.toString().replace(/\D/g, '');
            if (telefone.length === 11) {
                return telefone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (telefone.length === 10) {
                return telefone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            }
            return telefone;
        }

        function formatarCEPBusca(cep) {
            if (!cep) return '';
            cep = cep.toString().replace(/\D/g, '');
            if (cep.length === 8) {
                return cep.replace(/(\d{5})(\d{3})/, "$1-$2");
            }
            return cep;
        }

        function formatarDataBusca(data) {
            if (!data) return '';
            try {
                const dataObj = new Date(data + 'T00:00:00');
                return dataObj.toLocaleDateString('pt-BR');
            } catch (e) {
                return data;
            }
        }

        console.log('✓ Sistema de busca por RG e geração de ficha de desfiliação carregado!');
    </script>

</body>

</html>