<?php
/**
 * Formulário de Cadastro de Associados - VERSÃO FICHA DE FILIAÇÃO CORRIGIDA
 * pages/cadastroForm.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';

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
$page_title = 'Filiar Novo Associado - ASSEGO';

// Verifica se é edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$associadoId = $isEdit ? intval($_GET['id']) : null;
$associadoData = null;

if ($isEdit) {
    $associados = new Associados();
    $associadoData = $associados->getById($associadoId);

    if (!$associadoData) {
        header('Location: dashboard.php');
        exit;
    }

    $page_title = 'Editar Associado - ASSEGO';
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

    <!-- Custom CSS Files -->
    <link rel="stylesheet" href="estilizacao/cadastroForm.css">
    <link rel="stylesheet" href="estilizacao/autocomplete.css">
    
    <!-- Passar dados para o JavaScript -->
    <script>
        // Dados essenciais para o JavaScript
        window.pageData = {
            isEdit: <?php echo $isEdit ? 'true' : 'false'; ?>,
            associadoId: <?php echo $associadoId ? $associadoId : 'null'; ?>,
            regrasContribuicao: <?php echo json_encode($regrasContribuicao); ?>,
            servicos: <?php echo json_encode($servicos); ?>
        };
    </script>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando...</div>
    </div>

    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <div class="logo-section">
                <div
                    style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                    A
                </div>
                <div>
                    <h1 class="logo-text">ASSEGO</h1>
                    <p class="system-subtitle">Sistema de Gestão</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <nav style="display: flex; align-items: center; gap: 1rem;">
            <button type="button" class="btn-breadcrumb-back" onclick="window.location.href='dashboard.php'" title="Voltar ao Dashboard">
                <i class="fas fa-arrow-left"></i>
            </button>
            <ol class="breadcrumb-custom">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="dashboard.php">Associados</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li class="active"><?php echo $isEdit ? 'Editar' : 'Nova Filiação'; ?></li>
            </ol>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-user-plus"></i>
                        <?php echo $isEdit ? 'Editar Associado' : 'Filiar Novo Associado'; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php echo $isEdit ? 'Atualize os dados do associado' : 'Preencha todos os campos obrigatórios para filiar um novo associado'; ?>
                    </p>
                </div>
                <button type="button" class="btn-dashboard" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                    Voltar ao Dashboard
                    <span style="font-size: 0.7rem; opacity: 0.8; margin-left: 0.5rem;">(ESC)</span>
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

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

                        <!-- CAMPO DE OBSERVAÇÕES ADICIONADO -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Observações
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Informações adicionais sobre o associado"></i>
                            </label>
                            <textarea class="form-input" name="observacoes" id="observacoes" rows="4"
                                placeholder="Digite observações gerais sobre o associado, informações especiais, restrições médicas, etc."><?php echo $associadoData['observacoes'] ?? ''; ?></textarea>
                            <small class="form-help" style="color: var(--gray-500); font-size: 0.75rem; margin-top: 0.5rem; display: block;">
                                Use este campo para registrar informações importantes que não se encaixam nos outros campos
                            </small>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Foto do Associado <span class="required">*</span>
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
                                    <input type="file" name="foto" id="foto" accept="image/*" style="display: none;"
                                        <?php echo $isEdit ? '' : 'required'; ?>>
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

                        <!-- Campo para upload da ficha assinada - APENAS PARA NOVOS CADASTROS -->
                        <?php if (!$isEdit): ?>
                            <div class="form-group full-width">
                                <label class="form-label">
                                    Ficha de Filiação Assinada <span class="required">*</span>
                                    <i class="fas fa-info-circle info-tooltip"
                                        title="Anexe a foto ou PDF da ficha preenchida e assinada pelo associado"></i>
                                </label>
                                <div class="ficha-upload-container"
                                    style="background: var(--warning); background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 2rem; border-radius: 16px; border: 2px dashed #f0ad4e;">
                                    <div style="display: flex; align-items: center; gap: 2rem;">
                                        <div class="ficha-preview" id="fichaPreview"
                                            style="width: 200px; height: 250px; background: var(--white); border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 2px solid var(--warning);">
                                            <div class="ficha-preview-placeholder"
                                                style="text-align: center; color: var(--warning);">
                                                <i class="fas fa-file-contract"
                                                    style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                                <p style="font-weight: 600;">Ficha de Filiação</p>
                                                <p style="font-size: 0.875rem;">Nenhum arquivo anexado</p>
                                            </div>
                                        </div>

                                        <div style="flex: 1;">
                                            <h4 style="color: var(--warning); margin-bottom: 1rem;">
                                                <i class="fas fa-exclamation-triangle"></i> Documento Obrigatório
                                            </h4>
                                            <p style="color: #856404; margin-bottom: 1rem;">
                                                É obrigatório anexar a ficha de filiação preenchida e assinada pelo
                                                associado.
                                                Este documento será enviado para aprovação da presidência.
                                            </p>

                                            <input type="file" name="ficha_assinada" id="ficha_assinada"
                                                accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required>

                                            <button type="button" class="btn btn-warning"
                                                onclick="document.getElementById('ficha_assinada').click();"
                                                style="background: var(--warning); color: var(--dark); border: none; padding: 0.875rem 1.5rem; border-radius: 12px; font-weight: 600; cursor: pointer;">
                                                <i class="fas fa-upload"></i> Anexar Ficha Assinada
                                            </button>

                                            <p style="font-size: 0.75rem; color: #856404; margin-top: 0.5rem;">
                                                Formatos aceitos: PDF, JPG, PNG | Tamanho máximo: 10MB
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Opção de enviar automaticamente -->
                                    <div
                                        style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(240, 173, 78, 0.3);">
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="enviar_presidencia" id="enviar_presidencia"
                                                value="1" checked>
                                            <label for="enviar_presidencia" style="color: #856404; font-weight: 600;">
                                                <i class="fas fa-paper-plane"></i> Enviar automaticamente para aprovação da
                                                presidência após a filiação
                                            </label>
                                        </div>
                                        <p
                                            style="font-size: 0.75rem; color: #856404; margin-top: 0.5rem; margin-left: 1.5rem;">
                                            Se desmarcado, a ficha de filiação ficará aguardando até que você envie manualmente
                                            para aprovação.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
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
                                <option value="Pensionista" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
                                <option value="Civil" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                                <option value="Agregados" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Agregados') ? 'selected' : ''; ?>>Agregados</option>
                                <option value="Exército" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Exército') ? 'selected' : ''; ?>>Exército</option>
                                <option value="Outros" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Outros') ? 'selected' : ''; ?>>Outros</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Patente
                            </label>
                            <select class="form-input form-select" name="patente" id="patente">
                                <option value="">Selecione...</option>
                                <optgroup label="Praças">
                                    <option value="Aluno Soldado" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Aluno Soldado') ? 'selected' : ''; ?>>Aluno Soldado</option>
                                    <option value="Soldado 2ª Classe" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Soldado 2ª Classe') ? 'selected' : ''; ?>>Soldado 2ª Classe</option>
                                    <option value="Soldado 1ª Classe" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Soldado 1ª Classe') ? 'selected' : ''; ?>>Soldado 1ª Classe</option>
                                    <option value="Cabo" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Cabo') ? 'selected' : ''; ?>>Cabo</option>
                                    <option value="Terceiro Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Terceiro Sargento') ? 'selected' : ''; ?>>Terceiro Sargento</option>
                                    <option value="Segundo Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Segundo Sargento') ? 'selected' : ''; ?>>Segundo Sargento</option>
                                    <option value="Primeiro Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Primeiro Sargento') ? 'selected' : ''; ?>>Primeiro Sargento</option>
                                    <option value="Subtenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Subtenente') ? 'selected' : ''; ?>>Subtenente</option>
                                    <option value="Suboficial" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Suboficial') ? 'selected' : ''; ?>>Suboficial</option>
                                </optgroup>
                                <optgroup label="Oficiais">
                                    <option value="Cadete" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Cadete') ? 'selected' : ''; ?>>Cadete</option>
                                    <option value="Aluno Oficial" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Aluno Oficial') ? 'selected' : ''; ?>>Aluno Oficial</option>
                                    <option value="Aspirante-a-Oficial" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Aspirante-a-Oficial') ? 'selected' : ''; ?>>Aspirante-a-Oficial</option>
                                    <option value="Segundo-Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Segundo-Tenente') ? 'selected' : ''; ?>>Segundo-Tenente</option>
                                    <option value="Primeiro-Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Primeiro-Tenente') ? 'selected' : ''; ?>>Primeiro-Tenente</option>
                                    <option value="Capitão" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Capitão') ? 'selected' : ''; ?>>Capitão</option>
                                    <option value="Major" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Major') ? 'selected' : ''; ?>>Major</option>
                                    <option value="Tenente-Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Tenente-Coronel') ? 'selected' : ''; ?>>Tenente-Coronel</option>
                                    <option value="Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Coronel') ? 'selected' : ''; ?>>Coronel</option>
                                </optgroup>
                                <optgroup label="Outros">
                                    <option value="Civil" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                                </optgroup>
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
                                <?php foreach($lotacoes as $lotacao): ?>
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

                <!-- Step 4: Financeiro CORRIGIDO E EXPANDIDO -->
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
                                    title="Define o percentual de cobrança dos serviços"></i>
                            </label>
                            <select class="form-input form-select" name="tipoAssociadoServico" id="tipoAssociadoServico"
                                required onchange="calcularServicos()">
                                <option value="">Selecione o tipo de associado...</option>
                                <?php foreach($tiposAssociado as $tipo): ?>
                                    <option value="<?php echo $tipo; ?>" 
                                        <?php echo (isset($associadoData['tipoAssociadoServico']) && $associadoData['tipoAssociadoServico'] == $tipo) ? 'selected' : ''; ?>>
                                        <?php echo $tipo; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-error">Por favor, selecione o tipo de associado</span>
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
                                <div class="servico-item"
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
                                            <span
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

                        <!-- Vínculo Servidor - CAMPO NUMÉRICO -->
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

                        <!-- Doador - NOVO CAMPO -->
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

            <!-- Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </button>

                <div>
                    <button type="button" class="btn-nav btn-back me-2" onclick="window.location.href='dashboard.php'" title="Voltar ao Dashboard sem salvar">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>

                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>

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

    <!-- Scripts separados para melhor organização -->
    <script src="js/cadastroForm.js"></script>
    <script src="js/cadastroFormAutocomplete.js"></script>
    
    <script>
    // Inicializa Select2 para o campo de lotação
    $(document).ready(function() {
        $('#lotacao').select2({
            placeholder: 'Selecione ou digite para buscar...',
            language: 'pt-BR',
            width: '100%',
            allowClear: true
        });

        // Se estiver editando, carrega os dados dos serviços
        <?php if ($isEdit && isset($associadoData)): ?>
            // Busca dados dos serviços ao carregar página de edição
            buscarDadosServicosAssociado(<?php echo $associadoId; ?>);
        <?php endif; ?>
    });

    // Função para buscar dados dos serviços do associado em edição
    function buscarDadosServicosAssociado(associadoId) {
        fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data) {
                    // Preenche os campos de serviço baseado nos dados retornados
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
                    
                    // Atualiza o total
                    document.getElementById('valorTotalGeral').textContent = parseFloat(data.data.valor_total_mensal || 0).toFixed(2).replace('.', ',');
                    
                    // Define o tipo de associado dos serviços se disponível
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
    </script>
</body>

</html>