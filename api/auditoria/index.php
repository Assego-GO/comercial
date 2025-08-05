<?php
/**
 * Documentação da API de Auditoria
 * /api/auditoria/index.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Documentação da API
$apiDocumentation = [
    'api_info' => [
        'name' => 'API Sistema de Auditoria ASSEGO',
        'version' => '1.0.0',
        'description' => 'API completa para gerenciamento de auditoria do sistema',
        'base_url' => '/api/auditoria/',
        'author' => 'Sistema ASSEGO',
        'last_updated' => '2025-01-31'
    ],
    
    'endpoints' => [
        [
            'endpoint' => '/estatisticas.php',
            'method' => 'GET',
            'description' => 'Obtém estatísticas gerais do sistema de auditoria',
            'parameters' => 'Nenhum',
            'response_example' => [
                'status' => 'success',
                'data' => [
                    'total_registros' => 15420,
                    'acoes_hoje' => 127,
                    'usuarios_ativos' => 8,
                    'alertas' => 2,
                    'mudanca_hoje' => 15.3,
                    'acoes_periodo' => [
                        'labels' => ['25/01', '26/01', '27/01'],
                        'data' => [45, 67, 89]
                    ],
                    'tipos_acao' => [
                        'labels' => ['INSERT', 'UPDATE', 'LOGIN'],
                        'data' => [245, 189, 156]
                    ]
                ]
            ]
        ],
        
        [
            'endpoint' => '/registros.php',
            'method' => 'GET',
            'description' => 'Lista registros de auditoria com paginação e filtros',
            'parameters' => [
                'page' => 'Número da página (padrão: 1)',
                'limit' => 'Registros por página (padrão: 50, máx: 100)',
                'tabela' => 'Filtrar por tabela específica',
                'acao' => 'Filtrar por tipo de ação',
                'funcionario_id' => 'Filtrar por funcionário',
                'data_inicio' => 'Data início (formato: YYYY-MM-DD)',
                'data_fim' => 'Data fim (formato: YYYY-MM-DD)'
            ],
            'response_example' => [
                'status' => 'success',
                'data' => [
                    'registros' => '[]',
                    'paginacao' => [
                        'pagina_atual' => 1,
                        'total_paginas' => 15,
                        'total_registros' => 743
                    ]
                ]
            ]
        ],
        
        [
            'endpoint' => '/funcionarios.php',
            'method' => 'GET',
            'description' => 'Lista funcionários que aparecem na auditoria (para filtros)',
            'parameters' => 'Nenhum',
            'response_example' => [
                'status' => 'success',
                'data' => [
                    [
                        'id' => 1,
                        'nome' => 'João Silva',
                        'cargo' => 'Diretor',
                        'departamento' => 'Presidência',
                        'total_acoes' => 245
                    ]
                ]
            ]
        ],
        
        [
            'endpoint' => '/detalhes.php',
            'method' => 'GET',
            'description' => 'Obtém detalhes completos de um registro específico',
            'parameters' => [
                'id' => 'ID do registro de auditoria (obrigatório)'
            ],
            'response_example' => [
                'status' => 'success',
                'data' => [
                    'id' => 12345,
                    'funcionario_nome' => 'João Silva',
                    'acao' => 'UPDATE',
                    'tabela' => 'Associados',
                    'alteracoes' => '{}',
                    'detalhes_alteracoes' => '[]',
                    'informacoes_adicionais' => '{}',
                    'registros_relacionados' => '[]'
                ]
            ]
        ],
        
        [
            'endpoint' => '/exportar.php',
            'method' => 'GET',
            'description' => 'Exporta dados de auditoria em formato CSV',
            'parameters' => [
                'Mesmos filtros do /registros.php',
                'Nota' => 'Retorna arquivo CSV para download'
            ],
            'response_example' => 'Arquivo CSV com dados de auditoria'
        ],
        
        [
            'endpoint' => '/relatorios.php',
            'method' => 'GET, POST',
            'description' => 'Gera relatórios avançados de auditoria',
            'parameters' => [
                'tipo' => 'Tipo do relatório: geral, por_funcionario, por_acao, acessos, seguranca, performance',
                'periodo' => 'Período: hoje, semana, mes, trimestre, ano'
            ],
            'response_example' => [
                'status' => 'success',
                'data' => [
                    'tipo' => 'geral',
                    'periodo' => 'mes',
                    'data_inicio' => '2025-01-01',
                    'data_fim' => '2025-01-31',
                    'estatisticas' => '{}'
                ]
            ]
        ],
        
        [
            'endpoint' => '/limpeza.php',
            'method' => 'POST',
            'description' => 'Executa limpeza de registros antigos (apenas diretores)',
            'parameters' => [
                'dias' => 'Idade dos registros para exclusão (mín: 30, máx: 3650)',
                'confirmar' => 'true para confirmar exclusão',
                'simulacao' => 'true para apenas simular (padrão: true)'
            ],
            'request_example' => [
                'dias' => 365,
                'confirmar' => true,
                'simulacao' => false
            ],
            'response_example' => [
                'status' => 'success',
                'data' => [
                    'executado' => true,
                    'resultado_limpeza' => [
                        'registros' => 1520,
                        'detalhes' => 3040
                    ]
                ]
            ]
        ]
    ],
    
    'status_codes' => [
        200 => 'Sucesso',
        'success' => 'Operação executada com sucesso',
        'error' => 'Erro na operação',
        'info' => 'Informação (usado em simulações)',
        'warning' => 'Aviso'
    ],
    
    'common_errors' => [
        'Método não permitido' => 'Endpoint chamado com método HTTP incorreto',
        'Usuário não autenticado' => 'Sessão expirada ou usuário não logado',
        'Acesso negado' => 'Usuário sem permissão para a operação',
        'Parâmetros inválidos' => 'Parâmetros da requisição incorretos ou ausentes',
        'Registro não encontrado' => 'ID fornecido não existe na base de dados'
    ],
    
    'authentication' => [
        'method' => 'Session-based',
        'description' => 'Utiliza sistema de sessões do ASSEGO',
        'requirements' => 'Usuário deve estar logado no sistema',
        'special_permissions' => [
            'limpeza.php' => 'Apenas diretores podem executar limpeza'
        ]
    ],
    
    'rate_limiting' => [
        'enabled' => false,
        'note' => 'Não há limitação de rate, mas recomenda-se uso responsável'
    ],
    
    'data_formats' => [
        'dates' => 'YYYY-MM-DD (ISO 8601)',
        'datetime' => 'YYYY-MM-DD HH:MM:SS',
        'encoding' => 'UTF-8',
        'json' => 'Todas as respostas em formato JSON, exceto exportações'
    ],
    
    'examples' => [
        'buscar_registros_hoje' => '/registros.php?data_inicio=' . date('Y-m-d') . '&data_fim=' . date('Y-m-d'),
        'filtrar_por_funcionario' => '/registros.php?funcionario_id=5&page=1&limit=25',
        'exportar_mes_atual' => '/exportar.php?data_inicio=' . date('Y-m-01') . '&data_fim=' . date('Y-m-d'),
        'relatorio_seguranca' => '/relatorios.php?tipo=seguranca&periodo=semana'
    ],
    
    'changelog' => [
        '1.0.0 (2025-01-31)' => [
            'Lançamento inicial da API',
            'Endpoints básicos implementados',
            'Sistema de paginação',
            'Filtros avançados',
            'Exportação CSV',
            'Relatórios customizados',
            'Sistema de limpeza'
        ]
    ]
];

// Se chamado via GET, retorna documentação
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($apiDocumentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Para outros métodos, retorna apenas informações básicas
    echo json_encode([
        'message' => 'API Sistema de Auditoria ASSEGO',
        'version' => '1.0.0',
        'endpoints_available' => count($apiDocumentation['endpoints']),
        'documentation_url' => '/api/auditoria/',
        'status' => 'active'
    ]);
}
?>