<?php
/**
 * Formulário de Recadastramento de Associados - VERSÃO COMPLETA
 * pages/recadastramentoForm.php
 */

// Limpar parâmetros GET se existirem
if (!empty($_GET)) {
    header('Location: ' . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
    exit;
}

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';

// Define o título da página
$page_title = 'Recadastramento de Dados - ASSEGO';

// Verificar se tem dados na sessão
$associadoData = isset($_SESSION['recadastramento_data']) ? $_SESSION['recadastramento_data'] : null;
$associadoId = isset($_SESSION['recadastramento_id']) ? $_SESSION['recadastramento_id'] : null;

// Array com as lotações (mesmas do cadastro)
$lotacoes = [
    "1. BATALHAO BOMBEIRO MILITAR",
    "1. BATALHAO DE POLICIA MILITAR AMBIENTAL DO ESTADO",
    "1. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "1. BATALHAO DE POLICIA MILITAR RODOVIARIO DO ESTAD",
    "1. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "1. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "1. COMPANHIA INDEPENDENTE DE POLICIA MILITAR AMBIE",
    "1. COMPANHIA INDEPENDENTE DE POLICIA MILITAR RODOV",
    "1. COMPANHIA INDEPENDENTE POLICIA MILITAR DO ESTAD",
    "1. DIRETORIA REGIONAL PRISIONAL - METROPOLITANA",
    "1. PELOTAO / 15. COMPANHIA DO CORPO DE BOMBEIROS M",
    "1. PELOTAO BOMBEIRO MILITAR",
    "1. REGIONAL DO CORPO DE BOMBEIROS MILITAR DE GOIAN",
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
    "11. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "11. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "12. BATALHAO BOMBEIRO MILITAR",
    "12. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "12. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "12. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "12. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "13. BATALHAO BOMBEIRO MILITAR",
    "13. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "13. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "13. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "13. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DE G",
    "14. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "14. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "14. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "14. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "15. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "15. CIA INDEPENDENTE DE BOMBEIROS",
    "15. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "15. COMPANHIA INDEPENDENTE POLICIA MILITAR DO ESTA",
    "16. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "16. COMANDO REGIONAL DE PM DO ESTADO DE GOIAS",
    "16. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "16a COMPANHIA INDEPENDENTE DE POLICIA MILITAR/COMPANHIA DE POLICIAMENTO ESPECIALIZADO",
    "17. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "17. CIA INDEPENDENTE DE BOMBEIROS",
    "17. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "17. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "18. BATALHAO DE POLICIA MILTIAR DO ESTADO DE GOIAS",
    "18. CIPM - COMPANHIA DE POLICIAMENTO ESPECIALIZADO",
    "18. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO",
    "19. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "19.. COMPANHIA INDEPENDENTE DEPOLICIA MILITAR DO E",
    "19o COMANDO REGIONAL DE POLICIA MILITAR",
    "2. BATALHAO BOMBEIRO MILITAR",
    "2. BATALHAO DE POLICIA MILITAR DE GOIAS",
    "2. BATALHAO DE POLICIA MILITAR RODOVIARIO DO ESTAD",
    "2. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "2. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "2. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ES",
    "2. PELOTAO DE BOMBEIROS MILITAR",
    "20. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "20. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "21. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "21. COMPANHIA INDENDENTE DE POLICIA MILITAR DO EST",
    "22. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "22. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "23. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "23. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "24. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "24. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "25. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "25a COMPANHIA INDEPENDENTE BOMBEIRO MILITAR",
    "25a COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "26. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "26a COMPANHIA INDEPENDENTE BOMBEIRO MILITAR",
    "27. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "27. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "28. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "28. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "29. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "29. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "2a COMPANHIA DE POLICIA MILITAR RURAL",
    "3. BATALHAO DE BOMBEIROS MILITAR",
    "3. BATALHAO DE POLICIA MILITAR RODOVIARIO DO ESTAD",
    "3. BATANHAO DE POLICIA MILITAR DE GOIAS",
    "3. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "3. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "3. PELOTAO DE BOMBEIROS MILITAR",
    "3. REGIONAL DO CORPO DE BOMBEIROS MILITAR DE ANAPO",
    "30. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "31. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "31. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "32. BATALHAO DE POLICIA MILTIAR DO ESTADO DE GOIAS",
    "32. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "33. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "33. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "34. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "34. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "35. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "36. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "36. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "37. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "38. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "39. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "39. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "3ª COMPANHIA INDEPENDENTE DE POLICIA MILITAR DE GO",
    "3ª SEÇÃO DE RECRUTAMENTO E SELEÇÃO DE PESSOAL",
    "3o PELOTAO BOMBEIRO MILITAR",
    "4. BATALHAO DE BOMBEIROS MILITAR",
    "4. BATALHAO DE POLICIA MILITAR DE GOIAS",
    "4. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "4. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ES",
    "4. PELOTAO BOMBEIRO MILITAR",
    "4. SECAO DO ESTADO MAIOR",
    "40. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "41 BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "41.  COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO",
    "42.  COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO",
    "42o BATALHAO DE POLICIA MILITAR/01o CRPM",
    "43.  COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO",
    "44. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "45. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "46. COMPANHIA INDEPENDENTE POLICIA MILITAR DO ESTA",
    "47. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "48. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO E",
    "4a COMPANHIA DE POLICIA MILITAR RURAL",
    "4a COMPANHIA DE POLICIAMENTO RURAL",
    "4a COMPANHIA DO COMANDO DE DIVISAS - BASE CABECEIRAS",
    "4ª COMPANHIA DE ROTAM",
    "4ª SECAO DE ADMINISTRACAO DE PESSOAL",
    "4o PELOTAO BOMBEIRO MILITAR",
    "5. BATALHAO DE BOMBEIROS MILITAR",
    "5. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "5. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "5. COMPANHIA BOMBEIRO MILITAR",
    "5. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ES",
    "5. PELOTAO BOMBEIRO MILITAR",
    "5. SECAO DO ESTADO MAIOR",
    "5a COMPANHIA DE POLICIAMENTO RURAL",
    "5a COMPANHIA INDEPENDENTE DE POLICIA MILITAR AMBIENTAL",
    "6. BATALHAO DE BOMBEIROS MILITAR",
    "6. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "6. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "6. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "6. PELOTAO BOMBEIRO MILITAR",
    "6. SECAO DO ESTADO MAIOR",
    "6a COMPANHIA DO COMANDO DE DIVISAS - CIDADE OCIDENTAL",
    "6a COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "6o COMANDO REGIONAL BOMBEIRO MILITAR",
    "7. BATALHAO DE BOMBEIROS MILITAR",
    "7. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "7. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "7. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "7. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ES",
    "7. PELOTAO BOMBEIRO MILITAR",
    "7. SECAO DO ESTADO MAIOR",
    "7a COMPANHIA INDEPENDENTE DE POLICIA MILITAR - CPE",
    "7o COMANDO REGIONAL BOMBEIRO MILITAR",
    "8. BATALHAO DE BOMBEIROS MILITAR",
    "8. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "8. CIA INDEPENDENTE DE BOMBEIROS MILITAR",
    "8. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "8a SECAO DO ESTADO-MAIOR GERAL",
    "9. BATALHAO BOMBEIRO MILITAR",
    "9. BATALHAO DE POLICIA MILITAR DO ESTADO DE GOIAS",
    "9. COMANDO REGIONAL DE POLICIA MILITAR DO ESTADO D",
    "9. COMPANHIA BOMBEIRO MILITAR",
    "9. COMPANHIA INDEPENDENTE DE POLICIA MILITAR DO ES",
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
    "BATALHAO DE GIRO (GRUPAMENTO DE INTERVENCAO DE RON",
    "BATALHAO DE POLICIA MILITAR DE CHOQUE DO ESTADO DE",
    "BATALHAO DE POLICIA MILITAR DE EVENTOS",
    "BATALHAO DE POLICIA MILITAR DE TERMINAL",
    "BATALHAO DE POLICIA MILITAR DE TRANSITO DO ESTADO",
    "BATALHAO DE POLICIA MILITAR ESCOLAR DO ESTADO DE G",
    "BATALHAO DE POLICIA MILITAR FAZENDARIA",
    "BATALHAO DE POLICIA MILITAR MARIA DA PENHA - CPC",
    "BATALHAO DE POLICIA MILITAR RURAL/COC",
    "BATALHAO DE PROTECAO SOCIOAMBIENTA",
    "BATALHAO DE ROTAM",
    "BATALHAO DE SALVAMENTO EM EMERGENCIA",
    "CENTRO  DE MANUTENCAO",
    "CENTRO DE INSTRUCAO DA POLICIA MILITAR DE GOIAS",
    "CENTRO DE OPERACOES AEREAS",
    "CENTRO DE OPERACOES DA POLICIA MILITAR DO ESTADO D",
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
    "COMANDO DE OPERACOES DE DIVISA",
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
    "GRUPAMENTO DE POLICIA MILITAR AEREO ESTADO DE GOIA",
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
    "QUARTEL DA AJUDANCIA GERAL POLICIA MILITAR ESTADO",
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

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <style>
        :root {
            --primary: #0056D2;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.075);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .main-header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow-md);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .system-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0;
        }

        .content-area {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--gray-600);
            opacity: 0.9;
        }

        .busca-container {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: var(--shadow-md);
            max-width: 600px;
            margin: 0 auto 2rem;
            text-align: center;
        }

        .busca-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            justify-content: center;
        }

        .form-container {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
        }

        .progress-bar-container {
            margin-bottom: 2rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
            width: 0;
            z-index: 0;
        }

        .step {
            position: relative;
            text-align: center;
            flex: 1;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-300);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background: var(--primary);
            color: white;
        }

        .step.completed .step-circle {
            background: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .section-card {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .section-card.active {
            display: block;
        }

        .section-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .section-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .section-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            align-items: start;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: white;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
        }

        .form-input[readonly] {
            background: var(--gray-100);
            cursor: not-allowed;
            color: var(--gray-600);
        }

        .required {
            color: var(--danger);
            font-weight: 700;
        }

        .form-error {
            color: var(--danger);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .dados-antigos {
            background: linear-gradient(to right, #fff3cd, #ffeaa7);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-left: 3px solid var(--warning);
        }
        
        .dados-antigos i {
            color: var(--warning);
            font-size: 0.875rem;
        }
        
        .campo-alterado {
            background-color: #fffbf0 !important;
            border-color: #ffc107 !important;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.1);
        }
        
        .badge-alterado {
            background: var(--warning);
            color: var(--dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .form-hint {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .form-hint i {
            font-size: 0.7rem;
        }

        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn-nav {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-next {
            background: var(--primary);
            color: white;
        }

        .btn-next:hover {
            background: #0047B3;
            transform: translateY(-2px);
        }

        .btn-back {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-back:hover {
            background: var(--gray-300);
        }

        .btn-submit {
            background: var(--success);
            color: white;
        }

        .btn-submit:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-custom {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.3s ease;
        }

        .alert-success {
            background: var(--success);
            color: white;
        }

        .alert-error {
            background: var(--danger);
            color: white;
        }

        .alert-warning {
            background: var(--warning);
            color: var(--dark);
        }

        .alert-info {
            background: var(--info);
            color: white;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .dependente-card {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .dependente-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-remove-dependente {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-remove-dependente:hover {
            background: #c82333;
        }
        
        .alteracoes-resumo {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .alteracoes-lista {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }
        
        .alteracoes-lista li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .servicos-section {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
        }
        
        .servico-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border: 1px solid var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .servico-contratado {
            border-left: 4px solid var(--success);
        }
        
        .servico-nao-contratado {
            border-left: 4px solid var(--gray-400);
            opacity: 0.7;
        }
        
        .radio-group {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .radio-item input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(0, 86, 210, 0.25);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <div class="logo-section">
                <div style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                    A
                </div>
                <div>
                    <h1 class="logo-text">ASSEGO</h1>
                    <p class="system-subtitle">Sistema de Recadastramento</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-sync-alt"></i>
                Ficha de Recadastramento
            </h1>
            <p class="page-subtitle">#AssegoNãoPara - Atualize suas informações cadastrais</p>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <?php if (!$associadoData): ?>
        <!-- Container de Busca -->
        <div class="busca-container">
            <i class="fas fa-search" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
            <h2 style="margin-bottom: 1rem;">Identifique-se</h2>
            <p style="color: var(--gray-600); margin-bottom: 2rem;">Digite seu CPF ou RG para buscar seus dados</p>
            
            <div class="busca-form">
                <div class="form-group" style="flex: 1; max-width: 300px;">
                    <label class="form-label">CPF ou RG</label>
                    <input type="text" class="form-input" id="documento" placeholder="Digite CPF ou RG">
                </div>
                <button type="button" class="btn-nav btn-next" onclick="buscarAssociado(event)">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
            </div>
        </div>
        <?php else: ?>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Info sobre dados atuais -->
            <div style="background: var(--info); color: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span>Você está atualizando os dados de: <strong><?php echo htmlspecialchars($associadoData['nome']); ?></strong></span>
            </div>

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
                        <div class="step-label">Dependentes</div>
                    </div>
                    
                    <div class="step" data-step="5">
                        <div class="step-circle">5</div>
                        <div class="step-label">Financeiro</div>
                    </div>
                    
                    <div class="step" data-step="6">
                        <div class="step-circle">6</div>
                        <div class="step-label">Revisão</div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form id="formRecadastramento" class="form-content" enctype="multipart/form-data">
                <input type="hidden" name="associado_id" value="<?php echo $associadoId; ?>">
                <input type="hidden" name="tipo_solicitacao" value="recadastramento">
                <input type="hidden" id="campos_alterados" name="campos_alterados" value="{}">

                <!-- Step 1: Dados Pessoais -->
                <div class="section-card active" data-step="1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Pessoais</h2>
                            <p class="section-subtitle">Atualize suas informações pessoais</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Nome Completo</label>
                            <?php if(!empty($associadoData['nome'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['nome']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="nome" id="nome" 
                                   data-original="<?php echo htmlspecialchars($associadoData['nome'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['nome'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" class="form-input" name="nasc" id="nasc" readonly
                                   value="<?php echo $associadoData['nasc'] ?? ''; ?>">
                            <small class="text-muted">Data de nascimento não pode ser alterada</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">RG</label>
                            <input type="text" class="form-input" name="rg" id="rg" readonly
                                   value="<?php echo htmlspecialchars($associadoData['rg'] ?? ''); ?>">
                            <small class="text-muted">RG não pode ser alterado</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">CPF</label>
                            <input type="text" class="form-input" name="cpf" id="cpf" readonly
                                   value="<?php echo htmlspecialchars($associadoData['cpf'] ?? ''); ?>">
                            <small class="text-muted">CPF não pode ser alterado</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estado Civil</label>
                            <?php if(!empty($associadoData['estadoCivil'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['estadoCivil']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input rastreavel" name="estadoCivil" id="estadoCivil"
                                    data-original="<?php echo htmlspecialchars($associadoData['estadoCivil'] ?? ''); ?>">
                                <option value="">Selecione...</option>
                                <option value="Solteiro" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Solteiro' ? 'selected' : ''; ?>>Solteiro</option>
                                <option value="Casado" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Casado' ? 'selected' : ''; ?>>Casado</option>
                                <option value="Divorciado" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Divorciado' ? 'selected' : ''; ?>>Divorciado</option>
                                <option value="Separado Judicial" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Separado Judicial' ? 'selected' : ''; ?>>Separado Judicial</option>
                                <option value="Viúvo" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Viúvo' ? 'selected' : ''; ?>>Viúvo</option>
                                <option value="Outro" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Telefones <span class="required">*</span></label>
                            <?php if(!empty($associadoData['telefone'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['telefone']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="telefone" id="telefone" required
                                   data-original="<?php echo htmlspecialchars($associadoData['telefone'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['telefone'] ?? ''); ?>"
                                   placeholder="(00) 00000-0000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-mail</label>
                            <?php if(!empty($associadoData['email'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['email']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="email" class="form-input rastreavel" name="email" id="email"
                                   data-original="<?php echo htmlspecialchars($associadoData['email'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Escolaridade</label>
                            <?php if(!empty($associadoData['escolaridade'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['escolaridade']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input rastreavel" name="escolaridade" id="escolaridade"
                                    data-original="<?php echo htmlspecialchars($associadoData['escolaridade'] ?? ''); ?>">
                                <option value="">Selecione...</option>
                                <option value="Fundamental Incompleto" <?php echo ($associadoData['escolaridade'] ?? '') == 'Fundamental Incompleto' ? 'selected' : ''; ?>>Fundamental Incompleto</option>
                                <option value="Fundamental Completo" <?php echo ($associadoData['escolaridade'] ?? '') == 'Fundamental Completo' ? 'selected' : ''; ?>>Fundamental Completo</option>
                                <option value="Médio Incompleto" <?php echo ($associadoData['escolaridade'] ?? '') == 'Médio Incompleto' ? 'selected' : ''; ?>>Médio Incompleto</option>
                                <option value="Médio Completo" <?php echo ($associadoData['escolaridade'] ?? '') == 'Médio Completo' ? 'selected' : ''; ?>>Médio Completo</option>
                                <option value="Superior Incompleto" <?php echo ($associadoData['escolaridade'] ?? '') == 'Superior Incompleto' ? 'selected' : ''; ?>>Superior Incompleto</option>
                                <option value="Superior Completo" <?php echo ($associadoData['escolaridade'] ?? '') == 'Superior Completo' ? 'selected' : ''; ?>>Superior Completo</option>
                                <option value="Pós-graduação" <?php echo ($associadoData['escolaridade'] ?? '') == 'Pós-graduação' ? 'selected' : ''; ?>>Pós-graduação</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Indicado por</label>
                            <input type="text" class="form-input" name="indicacao" id="indicacao"
                                   value="<?php echo htmlspecialchars($associadoData['indicacao'] ?? ''); ?>"
                                   readonly
                                   style="background: var(--gray-100); cursor: not-allowed;">
                            <small class="text-muted">Indicação não pode ser alterada</small>
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
                            <p class="section-subtitle">Atualize suas informações militares</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <!-- Corporação atualizada -->
                        <div class="form-group">
                            <label class="form-label">Corporação</label>
                            <?php if(!empty($associadoData['corporacao'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['corporacao']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input rastreavel" name="corporacao" id="corporacao"
                                    data-original="<?php echo htmlspecialchars($associadoData['corporacao'] ?? ''); ?>">
                                <option value="">Selecione...</option>
                                <option value="Polícia Militar" <?php echo ($associadoData['corporacao'] ?? '') == 'Polícia Militar' ? 'selected' : ''; ?>>Polícia Militar</option>
                                <option value="Bombeiro Militar" <?php echo ($associadoData['corporacao'] ?? '') == 'Bombeiro Militar' ? 'selected' : ''; ?>>Bombeiro Militar</option>
                                <option value="Pensionista" <?php echo ($associadoData['corporacao'] ?? '') == 'Pensionista' ? 'selected' : ''; ?>>Pensionista</option>
                                <option value="Civil" <?php echo ($associadoData['corporacao'] ?? '') == 'Civil' ? 'selected' : ''; ?>>Civil</option>
                                <option value="Agregados" <?php echo ($associadoData['corporacao'] ?? '') == 'Agregados' ? 'selected' : ''; ?>>Agregados</option>
                                <option value="Exército" <?php echo ($associadoData['corporacao'] ?? '') == 'Exército' ? 'selected' : ''; ?>>Exército</option>
                                <option value="Outros" <?php echo ($associadoData['corporacao'] ?? '') == 'Outros' ? 'selected' : ''; ?>>Outros</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Posto/Graduação</label>
                            <?php if(!empty($associadoData['patente'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['patente']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input rastreavel" name="patente" id="patente"
                                    data-original="<?php echo htmlspecialchars($associadoData['patente'] ?? ''); ?>">
                                <option value="">Selecione...</option>
                                <optgroup label="Praças">
                                    <option value="Aluno Soldado" <?php echo ($associadoData['patente'] ?? '') == 'Aluno Soldado' ? 'selected' : ''; ?>>Aluno Soldado</option>
                                    <option value="Soldado 2ª Classe" <?php echo ($associadoData['patente'] ?? '') == 'Soldado 2ª Classe' ? 'selected' : ''; ?>>Soldado 2ª Classe</option>
                                    <option value="Soldado 1ª Classe" <?php echo ($associadoData['patente'] ?? '') == 'Soldado 1ª Classe' ? 'selected' : ''; ?>>Soldado 1ª Classe</option>
                                    <option value="Cabo" <?php echo ($associadoData['patente'] ?? '') == 'Cabo' ? 'selected' : ''; ?>>Cabo</option>
                                    <option value="Terceiro Sargento" <?php echo ($associadoData['patente'] ?? '') == 'Terceiro Sargento' ? 'selected' : ''; ?>>Terceiro Sargento</option>
                                    <option value="Segundo Sargento" <?php echo ($associadoData['patente'] ?? '') == 'Segundo Sargento' ? 'selected' : ''; ?>>Segundo Sargento</option>
                                    <option value="Primeiro Sargento" <?php echo ($associadoData['patente'] ?? '') == 'Primeiro Sargento' ? 'selected' : ''; ?>>Primeiro Sargento</option>
                                    <option value="Subtenente" <?php echo ($associadoData['patente'] ?? '') == 'Subtenente' ? 'selected' : ''; ?>>Subtenente</option>
                                    <option value="Suboficial" <?php echo ($associadoData['patente'] ?? '') == 'Suboficial' ? 'selected' : ''; ?>>Suboficial</option>
                                </optgroup>
                                <optgroup label="Oficiais">
                                    <option value="Cadete" <?php echo ($associadoData['patente'] ?? '') == 'Cadete' ? 'selected' : ''; ?>>Cadete</option>
                                    <option value="Aluno Oficial" <?php echo ($associadoData['patente'] ?? '') == 'Aluno Oficial' ? 'selected' : ''; ?>>Aluno Oficial</option>
                                    <option value="Aspirante-a-Oficial" <?php echo ($associadoData['patente'] ?? '') == 'Aspirante-a-Oficial' ? 'selected' : ''; ?>>Aspirante-a-Oficial</option>
                                    <option value="Segundo-Tenente" <?php echo ($associadoData['patente'] ?? '') == 'Segundo-Tenente' ? 'selected' : ''; ?>>Segundo-Tenente</option>
                                    <option value="Primeiro-Tenente" <?php echo ($associadoData['patente'] ?? '') == 'Primeiro-Tenente' ? 'selected' : ''; ?>>Primeiro-Tenente</option>
                                    <option value="Capitão" <?php echo ($associadoData['patente'] ?? '') == 'Capitão' ? 'selected' : ''; ?>>Capitão</option>
                                    <option value="Major" <?php echo ($associadoData['patente'] ?? '') == 'Major' ? 'selected' : ''; ?>>Major</option>
                                    <option value="Tenente-Coronel" <?php echo ($associadoData['patente'] ?? '') == 'Tenente-Coronel' ? 'selected' : ''; ?>>Tenente-Coronel</option>
                                    <option value="Coronel" <?php echo ($associadoData['patente'] ?? '') == 'Coronel' ? 'selected' : ''; ?>>Coronel</option>
                                </optgroup>
                                <optgroup label="Outros">
                                    <option value="Civil" <?php echo ($associadoData['patente'] ?? '') == 'Civil' ? 'selected' : ''; ?>>Civil</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lotado no (a)</label>
                            <?php if(!empty($associadoData['lotacao'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['lotacao']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input rastreavel" name="lotacao" id="lotacao"
                                    data-original="<?php echo htmlspecialchars($associadoData['lotacao'] ?? ''); ?>">
                                <option value="">Selecione...</option>
                                <?php foreach($lotacoes as $lotacao): ?>
                                    <option value="<?php echo htmlspecialchars($lotacao); ?>" 
                                        <?php echo (isset($associadoData['lotacao']) && $associadoData['lotacao'] == $lotacao) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lotacao); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Endereço -->
                <div class="section-card" data-step="3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Endereço</h2>
                            <p class="section-subtitle">Atualize seu endereço residencial</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Rua/Av.</label>
                            <?php if(!empty($associadoData['endereco'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['endereco']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="endereco" id="endereco"
                                   data-original="<?php echo htmlspecialchars($associadoData['endereco'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['endereco'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nº</label>
                            <?php if(!empty($associadoData['numero'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['numero']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="numero" id="numero"
                                   data-original="<?php echo htmlspecialchars($associadoData['numero'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['numero'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bairro</label>
                            <?php if(!empty($associadoData['bairro'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['bairro']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="bairro" id="bairro"
                                   data-original="<?php echo htmlspecialchars($associadoData['bairro'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['bairro'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">CEP</label>
                            <?php if(!empty($associadoData['cep'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['cep']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="cep" id="cep"
                                   data-original="<?php echo htmlspecialchars($associadoData['cep'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['cep'] ?? ''); ?>"
                                   placeholder="00000-000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <?php if(!empty($associadoData['cidade'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['cidade']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input rastreavel" name="cidade" id="cidade"
                                   data-original="<?php echo htmlspecialchars($associadoData['cidade'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($associadoData['cidade'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <?php if(!empty($associadoData['estado'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['estado']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input rastreavel" name="estado" id="estado"
                                    data-original="<?php echo htmlspecialchars($associadoData['estado'] ?? 'GO'); ?>">
                                <option value="GO" <?php echo ($associadoData['estado'] ?? 'GO') == 'GO' ? 'selected' : ''; ?>>GO</option>
                                <option value="DF" <?php echo ($associadoData['estado'] ?? '') == 'DF' ? 'selected' : ''; ?>>DF</option>
                                <option value="MT" <?php echo ($associadoData['estado'] ?? '') == 'MT' ? 'selected' : ''; ?>>MT</option>
                                <option value="MS" <?php echo ($associadoData['estado'] ?? '') == 'MS' ? 'selected' : ''; ?>>MS</option>
                                <option value="TO" <?php echo ($associadoData['estado'] ?? '') == 'TO' ? 'selected' : ''; ?>>TO</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Dependentes -->
                <div class="section-card" data-step="4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dependentes</h2>
                            <p class="section-subtitle">Atualize as informações dos dependentes</p>
                        </div>
                    </div>

                    <!-- Esposa(o) / companheira(o) -->
                    <div class="form-group full-width">
                        <label class="form-label">Esposa(o) / companheira(o)</label>
                        <div class="form-grid">
                            <div style="flex: 2;">
                                <input type="text" class="form-input rastreavel" name="conjuge_nome" id="conjuge_nome"
                                       data-original="<?php echo htmlspecialchars($associadoData['conjuge_nome'] ?? ''); ?>"
                                       value="<?php echo htmlspecialchars($associadoData['conjuge_nome'] ?? ''); ?>"
                                       placeholder="Nome do cônjuge">
                            </div>
                            <div style="flex: 1;">
                                <input type="text" class="form-input rastreavel" name="conjuge_telefone" id="conjuge_telefone"
                                       data-original="<?php echo htmlspecialchars($associadoData['conjuge_telefone'] ?? ''); ?>"
                                       value="<?php echo htmlspecialchars($associadoData['conjuge_telefone'] ?? ''); ?>"
                                       placeholder="Telefone">
                            </div>
                        </div>
                    </div>

                    <!-- Filhos -->
                    <div class="form-group full-width">
                        <label class="form-label">Filhos menores de 18 anos ou estudante até os 21 anos</label>
                        <div id="dependentesContainer">
                            <?php if(isset($associadoData['dependentes']) && is_array($associadoData['dependentes'])): ?>
                                <?php foreach($associadoData['dependentes'] as $index => $dep): ?>
                                    <?php if($dep['parentesco'] == 'Filho(a)'): ?>
                                    <div class="dependente-card" data-index="<?php echo $index; ?>">
                                        <div class="form-grid">
                                            <div style="flex: 2;">
                                                <input type="text" class="form-input rastreavel" 
                                                       name="dependente_nome[]" 
                                                       data-original="<?php echo htmlspecialchars($dep['nome'] ?? ''); ?>"
                                                       value="<?php echo htmlspecialchars($dep['nome'] ?? ''); ?>"
                                                       placeholder="Nome do filho(a)">
                                            </div>
                                            <div style="flex: 1;">
                                                <input type="date" class="form-input rastreavel" 
                                                       name="dependente_nascimento[]"
                                                       data-original="<?php echo $dep['data_nascimento'] ?? ''; ?>"
                                                       value="<?php echo $dep['data_nascimento'] ?? ''; ?>"
                                                       placeholder="Data Nascimento">
                                            </div>
                                            <div style="flex: 0;">
                                                <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-nav btn-next" onclick="adicionarDependente()">
                            <i class="fas fa-plus"></i>
                            Adicionar Filho(a)
                        </button>
                    </div>
                </div>

                <!-- Step 5: Financeiro (Novo) -->
                <div class="section-card" data-step="5">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Financeiros</h2>
                            <p class="section-subtitle">Informações sobre serviços e pagamentos</p>
                        </div>
                    </div>

                    <!-- Serviços Contratados -->
                    <div class="servicos-section">
                        <h4><i class="fas fa-clipboard-list"></i> Serviços Contratados</h4>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1rem;">
                            Seus serviços atuais contratados junto à ASSEGO
                        </p>
                        
                        <!-- Informação sobre valor atual da mensalidade -->
                        <div style="background: var(--gray-100); padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.875rem; color: var(--gray-700);">
                                    <i class="fas fa-calculator"></i> Valor atual da mensalidade:
                                </span>
                                <strong style="color: var(--primary); font-size: 1.1rem;">
                                    R$ <?php echo number_format($associadoData['valor_mensalidade'] ?? 173.10, 2, ',', '.'); ?>
                                </strong>
                            </div>
                        </div>
                        
                        <!-- Serviço Social -->
                        <div class="servico-item servico-contratado">
                            <div>
                                <strong style="color: var(--success);">
                                    <i class="fas fa-check-circle"></i> Serviço Social
                                </strong>
                                <div style="font-size: 0.8rem; color: var(--gray-600);">
                                    Serviço obrigatório para todos os associados
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge" style="background: var(--success); color: white; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                    ATIVO
                                </span>
                            </div>
                        </div>
                        
                        <!-- Serviço Jurídico -->
                        <?php if(isset($associadoData['servico_juridico']) && $associadoData['servico_juridico']): ?>
                            <!-- Já tem o serviço - não pode remover -->
                            <div class="servico-item servico-contratado">
                                <div>
                                    <strong style="color: var(--info);">
                                        <i class="fas fa-balance-scale"></i> Serviço Jurídico
                                    </strong>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Assessoria jurídica opcional
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--warning); margin-top: 0.25rem;">
                                        <i class="fas fa-info-circle"></i> Para cancelar este serviço, utilize a ficha de desfiliação de serviço
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge" style="background: var(--info); color: white; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                        ATIVO
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Não tem o serviço - pode adicionar -->
                            <div class="servico-item" style="background: #f0f8ff; border: 2px dashed var(--info); padding: 1rem;">
                                <div style="flex: 1;">
                                    <strong style="color: var(--info);">
                                        <i class="fas fa-balance-scale"></i> Serviço Jurídico
                                    </strong>
                                    <div style="font-size: 0.8rem; color: var(--gray-600); margin: 0.5rem 0;">
                                        Assessoria jurídica opcional - Você pode contratar este serviço agora!
                                    </div>
                                    
                                    <!-- Checkbox para optar pelo serviço -->
                                    <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 8px; border: 2px solid transparent; transition: all 0.3s ease;" id="containerServicoJuridico">
                                        <div style="display: flex; align-items: flex-start;">
                                            <input type="checkbox" 
                                                   name="adicionar_servico_juridico" 
                                                   id="adicionar_servico_juridico" 
                                                   value="1"
                                                   onchange="mostrarAvisoJuridico(this)"
                                                   style="width: 24px; height: 24px; cursor: pointer; margin-top: 2px; accent-color: var(--primary);">
                                            <label for="adicionar_servico_juridico" 
                                                   style="margin-left: 0.75rem; cursor: pointer; font-weight: 600; color: var(--primary); flex: 1; font-size: 1rem;">
                                                <i class="fas fa-plus-circle"></i> Quero contratar o Serviço Jurídico
                                            </label>
                                        </div>
                                        
                                        <!-- Aviso sobre o valor -->
                                        <div id="avisoValorJuridico" style="display: none; margin-top: 1rem; padding: 1rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #856404;">
                                                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                                                <strong>Atenção: Aumento no valor da mensalidade</strong>
                                            </div>
                                            <div style="margin: 0.5rem 0 0 1.5rem; color: #856404;">
                                                <p style="margin: 0.25rem 0; font-size: 0.875rem;">
                                                    Valor do Serviço Jurídico: <strong>R$ 43,28</strong>
                                                </p>
                                                <p style="margin: 0.25rem 0; font-size: 0.875rem;">
                                                    Mensalidade atual: <strong>R$ <?php echo number_format($associadoData['valor_mensalidade'] ?? 173.10, 2, ',', '.'); ?></strong>
                                                </p>
                                                <div style="border-top: 1px solid #f0ad4e; margin: 0.5rem 0;"></div>
                                                <p style="margin: 0.25rem 0; font-size: 1rem;">
                                                    <strong>Nova mensalidade total: R$ <?php echo number_format(($associadoData['valor_mensalidade'] ?? 173.10) + 43.28, 2, ',', '.'); ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.5rem; margin-left: 1.75rem;">
                                            Ao marcar esta opção, você estará solicitando a inclusão do serviço jurídico.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Dados Financeiros (Somente Leitura) -->
                    <div class="form-grid" style="margin-top: 2rem;">
                        <div class="form-group">
                            <label class="form-label">Tipo de Associado</label>
                            <input type="text" class="form-input" 
                                   value="<?php echo htmlspecialchars($associadoData['tipoAssociadoServico'] ?? 'Contribuinte'); ?>"
                                   readonly>
                            <small class="text-muted">Define o percentual de cobrança</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Situação Financeira</label>
                            <input type="text" class="form-input"
                                   value="<?php echo htmlspecialchars($associadoData['situacaoFinanceira'] ?? 'Adimplente'); ?>"
                                   readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Vínculo do Servidor
                                <i class="fas fa-info-circle info-tooltip" title="Número do vínculo"></i>
                            </label>
                            <input type="text" class="form-input" name="vinculoServidor" id="vinculoServidor"
                                   value="<?php echo htmlspecialchars($associadoData['vinculoServidor'] ?? ''); ?>"
                                   readonly
                                   style="background: var(--gray-100); cursor: not-allowed;">
                            <small class="text-muted">Vínculo não pode ser alterado</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Local de Débito</label>
                            <input type="text" class="form-input"
                                   value="<?php echo htmlspecialchars($associadoData['localDebito'] ?? 'SEGPLAN'); ?>"
                                   readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Agência</label>
                            <input type="text" class="form-input"
                                   value="<?php echo htmlspecialchars($associadoData['agencia'] ?? ''); ?>"
                                   readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Operação</label>
                            <input type="text" class="form-input"
                                   value="<?php echo htmlspecialchars($associadoData['operacao'] ?? ''); ?>"
                                   readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Conta Corrente</label>
                            <input type="text" class="form-input"
                                   value="<?php echo htmlspecialchars($associadoData['contaCorrente'] ?? ''); ?>"
                                   readonly>
                        </div>
                    </div>
                </div>

                <!-- Step 6: Revisão -->
                <div class="section-card" data-step="6">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Revisão dos Dados</h2>
                            <p class="section-subtitle">Confira as alterações antes de enviar</p>
                        </div>
                    </div>

                    <div id="revisaoContainer"></div>

                    <div class="form-group full-width">
                        <label class="form-label">Especificar alteração <span class="required">*</span></label>
                        <textarea class="form-input" name="especificar_alteracao" id="especificar_alteracao" 
                                  required rows="4" 
                                  placeholder="Descreva detalhadamente o motivo da solicitação de recadastramento e as alterações necessárias"></textarea>
                    </div>

                    <div class="alteracoes-resumo" id="alteracoesResumo" style="display: none;">
                        <h4><i class="fas fa-exclamation-triangle"></i> Campos Alterados</h4>
                        <ul class="alteracoes-lista" id="listaAlteracoes"></ul>
                    </div>
                </div>
            </form>

            <!-- Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()" style="display: none;">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </button>
                
                <div>
                    <button type="button" class="btn-nav btn-back me-2" onclick="cancelarRecadastramento()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    
                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <button type="button" class="btn-nav btn-submit" id="btnEnviar" 
                            onclick="enviarRecadastramento()" style="display: none;">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Solicitação
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
// Estado do formulário
let currentStep = 1;
const totalSteps = 6;
let camposAlterados = {};
let dadosOriginais = {};

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Configurar campo de busca
    const documentoInput = document.getElementById('documento');
    if (documentoInput) {
        documentoInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarAssociado(e);
            }
        });
    }
    
    // Aplicar máscaras
    if (typeof $ !== 'undefined' && $.fn.mask) {
        $('#telefone').mask('(00) 00000-0000');
        $('#conjuge_telefone').mask('(00) 00000-0000');
        $('#cep').mask('00000-000');
    }
    
    // Inicializar Select2 para lotação
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#lotacao').select2({
            placeholder: 'Selecione ou digite para buscar...',
            language: 'pt-BR',
            width: '100%',
            allowClear: true
        });
    }
    
    // Rastrear alterações
    inicializarRastreamento();
});

// Inicializar rastreamento de alterações
function inicializarRastreamento() {
    // Armazenar valores originais
    document.querySelectorAll('.rastreavel').forEach(campo => {
        const nome = campo.name;
        const valorOriginal = campo.getAttribute('data-original') || '';
        dadosOriginais[nome] = valorOriginal;
        
        // Adicionar listener para detectar mudanças
        campo.addEventListener('change', function() {
            verificarAlteracao(this);
        });
        
        campo.addEventListener('input', function() {
            verificarAlteracao(this);
        });
    });
}

// Verificar se campo foi alterado
function verificarAlteracao(campo) {
    const nome = campo.name;
    const valorOriginal = dadosOriginais[nome] || '';
    const valorAtual = campo.value;
    
    if (valorAtual !== valorOriginal) {
        campo.classList.add('campo-alterado');
        camposAlterados[nome] = {
            original: valorOriginal,
            novo: valorAtual,
            label: campo.closest('.form-group')?.querySelector('.form-label')?.textContent || nome
        };
    } else {
        campo.classList.remove('campo-alterado');
        delete camposAlterados[nome];
    }
    
    // Atualizar campo hidden com JSON dos campos alterados
    document.getElementById('campos_alterados').value = JSON.stringify(camposAlterados);
}

// Mostrar aviso do valor do serviço jurídico
function mostrarAvisoJuridico(checkbox) {
    const avisoDiv = document.getElementById('avisoValorJuridico');
    const container = document.getElementById('containerServicoJuridico');
    
    if (avisoDiv) {
        if (checkbox.checked) {
            avisoDiv.style.display = 'block';
            // Adicionar efeito de animação
            avisoDiv.style.animation = 'slideInDown 0.3s ease';
            
            // Destacar o container quando marcado
            if (container) {
                container.style.borderColor = 'var(--primary)';
                container.style.background = '#f0f8ff';
                container.style.boxShadow = '0 0 0 3px rgba(0, 86, 210, 0.1)';
            }
            
            // Mudar o ícone e texto
            const label = checkbox.nextElementSibling;
            if (label) {
                label.innerHTML = '<i class="fas fa-check-circle"></i> <strong>Serviço Jurídico selecionado</strong>';
                label.style.color = 'var(--success)';
            }
        } else {
            avisoDiv.style.display = 'none';
            
            // Remover destaque
            if (container) {
                container.style.borderColor = 'transparent';
                container.style.background = 'white';
                container.style.boxShadow = 'none';
            }
            
            // Voltar ao texto original
            const label = checkbox.nextElementSibling;
            if (label) {
                label.innerHTML = '<i class="fas fa-plus-circle"></i> Quero contratar o Serviço Jurídico';
                label.style.color = 'var(--primary)';
            }
        }
    }
}

// Buscar associado
function buscarAssociado(event) {
    if (event) event.preventDefault();
    
    const documentoInput = document.getElementById('documento');
    if (!documentoInput) return;
    
    const documento = documentoInput.value.replace(/\D/g, '');
    
    if (!documento || documento.length < 7) {
        showAlert('Digite um CPF ou RG válido!', 'warning');
        return;
    }
    
    showLoading();
    
    fetch('../api/recadastro/buscar_associado_recadastramento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ documento: documento })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success' && data.data) {
            showAlert('Associado encontrado!', 'success');
            
            // Salvar na sessão
            return fetch('../api/recadastro/salvar_sessao_recadastramento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    associado_id: data.data.id,
                    associado_data: data.data
                })
            });
        } else {
            throw new Error(data.message || 'Associado não encontrado');
        }
    })
    .then(response => response.json())
    .then(sessionData => {
        if (sessionData.status === 'success') {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert(error.message, 'error');
    });
}

// Navegação
function proximoStep() {
    if (currentStep < totalSteps) {
        document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('completed');
        currentStep++;
        mostrarStep(currentStep);
        
        if (currentStep === totalSteps) {
            preencherRevisao();
        }
    }
}

function voltarStep() {
    if (currentStep > 1) {
        currentStep--;
        mostrarStep(currentStep);
    }
}

function mostrarStep(step) {
    document.querySelectorAll('.section-card').forEach(card => {
        card.classList.remove('active');
    });
    
    const currentCard = document.querySelector(`.section-card[data-step="${step}"]`);
    if (currentCard) {
        currentCard.classList.add('active');
    }
    
    updateProgressBar();
    updateNavigationButtons();
}

function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    if (progressLine) {
        const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressLine.style.width = progressPercent + '%';
    }
    
    document.querySelectorAll('.step').forEach((step, index) => {
        const stepNumber = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNumber === currentStep) {
            step.classList.add('active');
        } else if (stepNumber < currentStep) {
            step.classList.add('completed');
        }
    });
}

function updateNavigationButtons() {
    const btnVoltar = document.getElementById('btnVoltar');
    const btnProximo = document.getElementById('btnProximo');
    const btnEnviar = document.getElementById('btnEnviar');
    
    if (btnVoltar) btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    
    if (currentStep === totalSteps) {
        if (btnProximo) btnProximo.style.display = 'none';
        if (btnEnviar) btnEnviar.style.display = 'flex';
    } else {
        if (btnProximo) btnProximo.style.display = 'flex';
        if (btnEnviar) btnEnviar.style.display = 'none';
    }
}

function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    const listaAlteracoes = document.getElementById('listaAlteracoes');
    const alteracoesResumo = document.getElementById('alteracoesResumo');
    
    if (!container) return;
    
    // Verificar se solicitou serviço jurídico
    const checkboxJuridico = document.getElementById('adicionar_servico_juridico');
    if (checkboxJuridico && checkboxJuridico.checked) {
        camposAlterados['servico_juridico_novo'] = {
            original: 'Não contratado',
            novo: 'Solicitação de contratação',
            label: 'Serviço Jurídico'
        };
    }
    
    // Mostrar resumo se houver alterações
    if (Object.keys(camposAlterados).length > 0) {
        alteracoesResumo.style.display = 'block';
        listaAlteracoes.innerHTML = '';
        
        for (const [campo, dados] of Object.entries(camposAlterados)) {
            const li = document.createElement('li');
            if (campo === 'servico_juridico_novo') {
                li.innerHTML = `
                    <strong style="color: var(--info);">
                        <i class="fas fa-balance-scale"></i> ${dados.label}:
                    </strong><br>
                    <span style="color: var(--success); font-weight: 600;">
                        ✓ Solicitação para contratar o serviço jurídico
                    </span>
                `;
            } else {
                li.innerHTML = `
                    <strong>${dados.label}:</strong><br>
                    De: <span style="color: var(--gray-600);">${dados.original || '(vazio)'}</span><br>
                    Para: <span style="color: var(--success);">${dados.novo || '(vazio)'}</span>
                `;
            }
            listaAlteracoes.appendChild(li);
        }
    } else {
        alteracoesResumo.style.display = 'none';
    }
    
    container.innerHTML = `
        <div class="alert-info" style="padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <i class="fas fa-info-circle"></i>
            Revise todas as informações antes de enviar. Sua solicitação será processada e o documento será preparado para assinatura eletrônica.
        </div>
    `;
}

function adicionarDependente() {
    const container = document.getElementById('dependentesContainer');
    const index = container.children.length;
    
    const html = `
        <div class="dependente-card" data-index="${index}">
            <div class="form-grid">
                <div style="flex: 2;">
                    <input type="text" class="form-input rastreavel" 
                           name="dependente_nome[]" 
                           data-original=""
                           placeholder="Nome do filho(a)">
                </div>
                <div style="flex: 1;">
                    <input type="date" class="form-input rastreavel" 
                           name="dependente_nascimento[]"
                           data-original=""
                           placeholder="Data Nascimento">
                </div>
                <div style="flex: 0;">
                    <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    
    // Re-inicializar rastreamento para novos campos
    container.querySelectorAll('.rastreavel').forEach(campo => {
        if (!dadosOriginais[campo.name]) {
            dadosOriginais[campo.name] = '';
            campo.addEventListener('change', function() {
                verificarAlteracao(this);
            });
        }
    });
}

function removerDependente(btn) {
    if (confirm('Remover este dependente?')) {
        btn.closest('.dependente-card').remove();
    }
}

// FUNÇÃO PRINCIPAL - ENVIAR RECADASTRAMENTO
function enviarRecadastramento() {
    const especificarAlteracao = document.getElementById('especificar_alteracao').value.trim();
    
    if (!especificarAlteracao) {
        showAlert('Por favor, especifique o motivo da alteração!', 'error');
        return;
    }
    
    // Verificar se há solicitação de serviço jurídico
    const checkboxJuridico = document.getElementById('adicionar_servico_juridico');
    const solicitouServicoJuridico = checkboxJuridico && checkboxJuridico.checked;
    
    if (Object.keys(camposAlterados).length === 0 && !solicitouServicoJuridico) {
        showAlert('Nenhuma alteração foi detectada. Modifique pelo menos um campo ou solicite um novo serviço.', 'warning');
        return;
    }
    
    if (!confirm('Confirma o envio do recadastramento?\n\nSua solicitação será processada e você receberá o documento para assinatura eletrônica.')) {
        return;
    }
    
    showLoading();
    
    // Coletar TODOS os dados do formulário
    const formData = new FormData(document.getElementById('formRecadastramento'));
    
    // Adicionar o motivo do recadastramento
    formData.append('motivo_recadastramento', especificarAlteracao);
    
    // Adicionar informações sobre alterações (para referência)
    formData.append('campos_alterados_detalhes', JSON.stringify(camposAlterados));
    formData.append('total_alteracoes', Object.keys(camposAlterados).length);
    
    // Adicionar informação sobre solicitação de serviço jurídico
    if (solicitouServicoJuridico) {
        formData.append('solicitar_servico_juridico', '1');
        formData.append('adicionar_servico_juridico', '1');
    }
    
    // Coletar todos os dependentes
    const dependentes = [];
    document.querySelectorAll('#dependentesContainer .dependente-card').forEach((card, index) => {
        const nome = card.querySelector('input[name="dependente_nome[]"]')?.value;
        const nascimento = card.querySelector('input[name="dependente_nascimento[]"]')?.value;
        if (nome) {
            dependentes.push({
                nome: nome,
                data_nascimento: nascimento,
                parentesco: 'Filho(a)'
            });
        }
    });
    
    // Adicionar dependentes como JSON
    formData.append('dependentes_json', JSON.stringify(dependentes));
    
    // Coletar todos os valores dos campos do formulário individualmente
    // Dados Pessoais
    formData.append('nome', document.getElementById('nome')?.value || '');
    formData.append('nasc', document.getElementById('nasc')?.value || '');
    formData.append('rg', document.getElementById('rg')?.value || '');
    formData.append('cpf', document.getElementById('cpf')?.value || '');
    formData.append('estadoCivil', document.getElementById('estadoCivil')?.value || '');
    formData.append('telefone', document.getElementById('telefone')?.value || '');
    formData.append('email', document.getElementById('email')?.value || '');
    formData.append('escolaridade', document.getElementById('escolaridade')?.value || '');
    formData.append('indicacao', document.getElementById('indicacao')?.value || '');
    
    // Dados Militares
    formData.append('corporacao', document.getElementById('corporacao')?.value || '');
    formData.append('patente', document.getElementById('patente')?.value || '');
    formData.append('lotacao', document.getElementById('lotacao')?.value || '');
    
    // Endereço
    formData.append('endereco', document.getElementById('endereco')?.value || '');
    formData.append('numero', document.getElementById('numero')?.value || '');
    formData.append('bairro', document.getElementById('bairro')?.value || '');
    formData.append('cep', document.getElementById('cep')?.value || '');
    formData.append('cidade', document.getElementById('cidade')?.value || '');
    formData.append('estado', document.getElementById('estado')?.value || '');
    
    // Dependentes - Cônjuge
    formData.append('conjuge_nome', document.getElementById('conjuge_nome')?.value || '');
    formData.append('conjuge_telefone', document.getElementById('conjuge_telefone')?.value || '');
    
    // Financeiro
    formData.append('vinculoServidor', document.getElementById('vinculoServidor')?.value || '');
    
    // Criar objeto com todos os dados para o campo dados_alterados
    const todosOsDados = {
        // Dados Pessoais
        dados_pessoais: {
            nome: document.getElementById('nome')?.value || '',
            nasc: document.getElementById('nasc')?.value || '',
            rg: document.getElementById('rg')?.value || '',
            cpf: document.getElementById('cpf')?.value || '',
            estadoCivil: document.getElementById('estadoCivil')?.value || '',
            telefone: document.getElementById('telefone')?.value || '',
            email: document.getElementById('email')?.value || '',
            escolaridade: document.getElementById('escolaridade')?.value || '',
            indicacao: document.getElementById('indicacao')?.value || ''
        },
        // Dados Militares
        dados_militares: {
            corporacao: document.getElementById('corporacao')?.value || '',
            patente: document.getElementById('patente')?.value || '',
            lotacao: document.getElementById('lotacao')?.value || ''
        },
        // Endereço
        endereco: {
            endereco: document.getElementById('endereco')?.value || '',
            numero: document.getElementById('numero')?.value || '',
            bairro: document.getElementById('bairro')?.value || '',
            cep: document.getElementById('cep')?.value || '',
            cidade: document.getElementById('cidade')?.value || '',
            estado: document.getElementById('estado')?.value || ''
        },
        // Dependentes
        dependentes: {
            conjuge_nome: document.getElementById('conjuge_nome')?.value || '',
            conjuge_telefone: document.getElementById('conjuge_telefone')?.value || '',
            filhos: dependentes
        },
        // Financeiro
        financeiro: {
            vinculoServidor: document.getElementById('vinculoServidor')?.value || '',
            solicitar_servico_juridico: solicitouServicoJuridico ? '1' : '0'
        },
        // Alterações
        alteracoes: {
            motivo: especificarAlteracao,
            campos_modificados: camposAlterados,
            total_alteracoes: Object.keys(camposAlterados).length,
            data_solicitacao: new Date().toISOString()
        }
    };
    
    // Adicionar todos os dados como JSON para o campo dados_alterados
    formData.append('dados_completos_json', JSON.stringify(todosOsDados));
    
    // Debug - ver o que está sendo enviado
    console.log('Enviando dados para API...');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ', pair[1]);
    }
    
    // Enviar para a API
    fetch('../api/recadastro/processar_recadastramento.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            return response.json();
        } else {
            // Se não for JSON, tentar ler como texto para debug
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text);
                throw new Error('Resposta inválida do servidor. Verifique o console para detalhes.');
            });
        }
    })
    .then(data => {
        hideLoading();
        console.log('Resposta da API:', data);
        
        if (data.status === 'success') {
            showAlert('✅ ' + (data.message || 'Recadastramento registrado com sucesso!'), 'success');
            
            if (solicitouServicoJuridico) {
                showAlert('📋 Solicitação de contratação do Serviço Jurídico incluída!', 'info');
            }
            
            // Mostrar mensagem adicional se houver
            if (data.data && data.data.mensagem_adicional) {
                setTimeout(() => {
                    showAlert('ℹ️ ' + data.data.mensagem_adicional, 'info');
                }, 1500);
            }
            
            // Se tiver token do assinante (quando ZapSign estiver ativo)
            if (data.data && data.data.zapsign_signer_token) {
                const linkAssinatura = `https://app.zapsign.com.br/verificar/${data.data.zapsign_signer_token}`;
                showAlert('📝 Redirecionando para página de assinatura...', 'info');
                
                setTimeout(() => {
                    window.open(linkAssinatura, '_blank');
                }, 2000);
            }
            
            // Limpar sessão
            fetch('../api/recadastro/limpar_sessao_recadastramento.php')
                .catch(err => console.log('Erro ao limpar sessão:', err));
            
            // Mostrar botão para voltar ao início
            setTimeout(() => {
                if (confirm('Recadastramento enviado com sucesso!\n\nDeseja voltar para a página inicial?')) {
                    window.location.href = '../dashboard.php';
                } else {
                    // Recarregar a página para limpar o formulário
                    window.location.reload();
                }
            }, 3000);
            
        } else {
            showAlert('❌ ' + (data.message || 'Erro ao enviar recadastramento'), 'error');
            
            // Se houver informações de debug, mostrar no console
            if (data.debug) {
                console.error('Debug info:', data.debug);
            }
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro na requisição:', error);
        showAlert('❌ Erro ao processar recadastramento! ' + error.message, 'error');
    });
}

function cancelarRecadastramento() {
    if (confirm('Cancelar o recadastramento? Todas as alterações serão perdidas.')) {
        fetch('../api/recadastro/limpar_sessao_recadastramento.php')
            .then(() => {
                window.location.reload();
            });
    }
}

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('active');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.remove('active');
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    if (!container) return;
    
    const alertId = 'alert-' + Date.now();
    const icons = {
        success: 'check-circle',
        error: 'times-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const alertHtml = `
        <div id="${alertId}" class="alert-custom alert-${type}">
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) alert.remove();
    }, 5000);
}
    </script>
</body>
</html> 