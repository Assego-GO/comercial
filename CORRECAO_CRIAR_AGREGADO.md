# ✅ CORREÇÃO APLICADA - criar_agregado.php

## 📋 Problema Identificado
A API `criar_agregado.php` ainda estava criando agregados na tabela legada `Socios_Agregados`.

## 🔧 Solução Implementada

### Arquivo Reescrito: `/api/criar_agregado.php`

**ANTES (versão legada):**
```php
// Usava tabela Socios_Agregados
INSERT INTO Socios_Agregados (
    nome, data_nascimento, telefone, celular, email, cpf, ...
    socio_titular_nome, socio_titular_cpf, ...
) VALUES (...)

// Documentos em Documentos_Agregado
INSERT INTO Documentos_Agregado (
    agregado_id, tipo_documento, ...
) VALUES (...)
```

**AGORA (versão unificada):**
```php
// Usa tabela Associados
INSERT INTO Associados (
    nome, nasc, cpf, email, telefone, 
    associado_titular_id, ... // ✅ Vínculo com titular
) VALUES (...)

// Insere em Militar com corporacao='Agregados'
INSERT INTO Militar (
    associado_id, corporacao, patente, categoria
) VALUES (?, 'Agregados', 'Agregado', 'Agregado')

// Documentos em Documentos_Associado (unificado)
INSERT INTO Documentos_Associado (
    associado_id, tipo_documento, nome_arquivo, ...
) VALUES (...)
```

## ✅ Validações Implementadas

1. **Titular obrigatório**: CPF ou ID do titular deve ser informado
2. **Titular existe**: Busca na tabela Associados
3. **Titular ativo**: Situação deve ser "Filiado"
4. **Titular não agregado**: Impede agregado de agregado
5. **CPF único**: Não permite CPF duplicado
6. **Transação**: Rollback automático em caso de erro

## 🎯 Estrutura Final

```
Associados
├── id = <novo_id>
├── nome = "Maria Silva"
├── cpf = "98765432100"
├── associado_titular_id = 2  ← Vínculo com titular
└── ... outros campos

Militar
├── associado_id = <novo_id>
├── corporacao = "Agregados"  ← Identifica como agregado
├── patente = "Agregado"
└── categoria = "Agregado"

Financeiro
├── associado_id = <novo_id>
├── tipoAssociado = "Agregado"
└── ... outros campos
```

## 📊 Fluxo de Criação

1. **POST** para `/api/criar_agregado.php`
2. **Validação** do CPF/ID do titular
3. **Verificação** se titular está filiado e não é agregado
4. **BEGIN TRANSACTION**
5. **INSERT** em Associados (com `associado_titular_id`)
6. **INSERT** em Endereco
7. **INSERT** em Militar (com `corporacao='Agregados'`)
8. **INSERT** em Financeiro
9. **INSERT** em Contrato
10. **COMMIT**
11. **Upload** de documento (se enviado) → `Documentos_Associado`

## 🔍 Como Identificar Agregados

### Query para listar agregados:
```sql
SELECT 
    a.id,
    a.nome as agregado,
    a.cpf,
    titular.nome as titular,
    m.corporacao
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
WHERE m.corporacao = 'Agregados';
```

### Query para contar agregados por titular:
```sql
SELECT 
    titular.nome,
    COUNT(a.id) as total_agregados
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
INNER JOIN Associados titular ON a.associado_titular_id = titular.id
WHERE m.corporacao = 'Agregados'
GROUP BY titular.id;
```

## 📝 Backup

Arquivo antigo salvo em: `/api/criar_agregado.php.backup`

## ⚠️ Importante

- Formulários que chamam `/api/criar_agregado.php` continuam funcionando
- Compatibilidade mantida com campos antigos (`socioTitularCpf`, `dataNascimento`, etc.)
- Sistema aceita tanto CPF quanto ID do titular
- Documentos agora são salvos em `Documentos_Associado` (estrutura unificada)

## ✅ Status

**CONCLUÍDO** - Sistema agora cria agregados exclusivamente na tabela `Associados`

---

**Data**: 01/12/2025  
**Arquivo**: `/api/criar_agregado.php`  
**Status**: ✅ Operacional
