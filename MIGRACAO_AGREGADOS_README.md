# Migração de Agregados - Documentação

## Data: 2025-12-01

## Objetivo
Unificar agregados na tabela `Associados`, removendo as tabelas `Socios_Agregados` e `Documentos_Agregado`.
Agregados agora são identificados por `Militar.corporacao = 'Agregados'`.

## Mudanças Realizadas

### 1. Banco de Dados
- **Script de Migração**: `/migrations/migrate_agregados_to_associados.sql`
  - Adiciona campo `associado_titular_id` na tabela `Associados`
  - Migra dados de `Socios_Agregados` para `Associados`
  - Migra documentos de `Documentos_Agregado` para `Documentos_Associado`
  - Cria índices para melhor performance

### 2. APIs Atualizadas

#### `/api/documentos/documentos_unificados_listar.php`
- Query unificada usando apenas `Documentos_Associado`
- Filtro por tipo: `SOCIO` ou `AGREGADO` baseado em `Militar.corporacao`
- Suporta busca por titular

#### `/api/buscar_associado_por_cpf.php`
- Remove referências a `Socios_Agregados`
- Usa JOIN com `Militar` para identificar agregados
- Retorna dados do titular quando aplicável

#### `/api/criar_associado.php`
- Adiciona validação de agregados
- Verifica se titular existe e está ativo
- Impede que agregado seja titular de outro agregado
- Salva `associado_titular_id` na tabela `Associados`

### 3. Classes Atualizadas

#### `/classes/Associados.php`
- INSERT agora inclui campo `associado_titular_id`
- Suporta criação de agregados vinculados a titulares

### 4. Páginas Atualizadas

#### `/pages/cadastroForm.php`
- Verificação de agregado usa `Militar.corporacao = 'Agregados'`
- Busca dados do titular via `associado_titular_id`
- Remove dependência de `Socios_Agregados`

## Como Usar

### 1. Executar Migração
```bash
mysql -u usuario -p wwasse_cadastro < migrations/migrate_agregados_to_associados.sql
```

### 2. Verificar Migração
O script inclui queries de verificação que mostram:
- Total de agregados migrados
- Agregados com titular vinculado
- Documentos migrados
- Dados não migrados (se houver)

### 3. Cadastrar Novo Agregado
1. Acesse o formulário de cadastro
2. Selecione "Tipo de Associado" = "Agregado"
3. Campo "Associado Titular" aparecerá automaticamente
4. Selecione o titular na lista (com busca)
5. Corporação será automaticamente definida como "Agregados"

### 4. Filtrar Agregados
**Na API de listagem:**
- `?tipo=AGREGADO` - Lista apenas agregados
- `?tipo=SOCIO` - Lista apenas sócios (excl ui agregados)
- `?busca=nome` - Busca em agregados e titulares

## Estrutura de Dados

### Tabela Associados (Campo Novo)
```sql
associado_titular_id INT NULL
  - ID do associado titular (para agregados)
  - NULL para associados normais
  - NOT NULL para agregados
```

### Identificação de Agregados
```sql
-- Um agregado é identificado por:
SELECT * FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
WHERE m.corporacao = 'Agregados'
AND a.associado_titular_id IS NOT NULL
```

### Buscar Titular de um Agregado
```sql
SELECT 
    a.*,
    titular.nome as titular_nome,
    titular.cpf as titular_cpf
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
WHERE m.corporacao = 'Agregados'
```

## Arquivos Modificados

1. `/migrations/migrate_agregados_to_associados.sql` (NOVO)
2. `/api/documentos/documentos_unificados_listar.php` (REESCRITO)
3. `/api/buscar_associado_por_cpf.php` (ATUALIZADO)
4. `/api/criar_associado.php` (ATUALIZADO)
5. `/classes/Associados.php` (ATUALIZADO)
6. `/pages/cadastroForm.php` (ATUALIZADO)

## Backups Criados

- `/api/documentos/documentos_unificados_listar_OLD_BACKUP.php`

## Próximos Passos

### Após Confirmar Migração Bem-Sucedida:
1. Descomente as linhas de backup no script SQL
2. Execute criação de tabelas de backup
3. Descomente as linhas de DROP TABLE
4. Execute remoção das tabelas antigas

### Arquivos que Podem Ser Removidos Após Migração:
- `/api/criar_agregado.php`
- `/api/atualizar_agregado.php`
- `/api/zapsign_agregado_api.php`
- `/api/documentos/documentos_agregados_*.php`

## Notas Importantes

- ⚠️ **NÃO remova as tabelas antigas antes de confirmar que tudo funciona!**
- ✅ Agregados existentes continuarão funcionando após a migração
- ✅ Novos agregados serão criados no novo formato
- ✅ Documentos de agregados migrados estarão em `Documentos_Associado`
- ✅ A busca por CPF funciona tanto para agregados quanto para titulares

## Testes Recomendados

1. ✓ Listar documentos unificados (sócios + agregados)
2. ✓ Filtrar apenas agregados
3. ✓ Filtrar apenas sócios
4. ✓ Buscar por CPF de agregado
5. ✓ Buscar por nome de titular
6. ✓ Cadastrar novo agregado
7. ✓ Verificar vínculo titular-agregado
8. ✓ Editar dados de agregado existente

## Suporte

Para dúvidas ou problemas, consulte os logs:
- PHP: `/var/log/php_errors.log`
- Apache: `/var/log/apache2/error.log`
- Application: Verifique `error_log()` nos arquivos PHP

---
**Autor**: Claude (GitHub Copilot)
**Data**: 2025-12-01
