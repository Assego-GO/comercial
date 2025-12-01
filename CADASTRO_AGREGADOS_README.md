# Cadastro de Agregados - Documentação

## 📋 Visão Geral

O sistema agora permite cadastrar **agregados diretamente na tabela `Associados`**, eliminando a necessidade da tabela legada `Socios_Agregados`. Os agregados são identificados através de:

1. **Campo `associado_titular_id`**: Vínculo com o associado titular (FK para `Associados.id`)
2. **Campo `Militar.corporacao`**: Valor `'Agregados'` identifica o associado como agregado

---

## 🔧 Alterações Implementadas

### 1. **Estrutura do Banco de Dados**

```sql
-- Coluna adicionada na tabela Associados
ALTER TABLE Associados 
ADD COLUMN associado_titular_id INT NULL COMMENT 'ID do associado titular (para agregados)';

-- Índice para performance
CREATE INDEX idx_associado_titular ON Associados(associado_titular_id);

-- Constraint de integridade referencial
ALTER TABLE Associados 
ADD CONSTRAINT fk_associado_titular 
FOREIGN KEY (associado_titular_id) REFERENCES Associados(id) 
ON DELETE SET NULL ON UPDATE CASCADE;
```

### 2. **Formulário de Cadastro** (`pages/cadastroForm.php`)

#### Elementos HTML:
- ✅ **Checkbox** "Cadastrar como Agregado" (apenas em modo criação)
- ✅ **Campo CPF do Titular** (com máscara e validação)
- ✅ **Campo oculto `associadoTitular`** para enviar o ID do titular
- ✅ **Campo readonly** para exibir nome do titular automaticamente

#### JavaScript:
```javascript
// Toggle dos campos quando checkbox é marcado
function toggleAgregadoCampos() {
    const isAgregado = document.getElementById('isAgregado').checked;
    // Exibe/oculta campos
    // Define tipoAssociado como "Agregado"
    // Limpa campos quando desmarcado
}

// Busca titular por CPF via AJAX
function buscarNomeTitularPorCpf() {
    // Busca dados do titular via API
    // Valida que titular não é agregado
    // Valida que titular está filiado
    // Preenche ID do titular no campo oculto
    // Exibe nome e CPF do titular
}

// Validação antes do submit
$('#formAssociado').on('submit', function(e) {
    if ($('#isAgregado').is(':checked')) {
        // Valida presença de CPF e ID do titular
        // Garante que tipoAssociado = 'Agregado'
        // Previne submit se dados inválidos
    }
});
```

### 3. **API de Busca** (`api/buscar_associado_por_cpf.php`)

Retorna dados completos do titular:
```json
{
  "status": "success",
  "data": {
    "titular_id": 2,
    "titular_nome": "AGOSTINHO PEREIRA DE CARVALHO",
    "titular_cpf": "3883906115",
    "titular_situacao": "Filiado",
    "corporacao": "Bombeiro Militar",
    "eh_agregado": 0
  }
}
```

### 4. **API de Criação** (`api/criar_associado.php`)

#### Validações implementadas:
```php
// Detecta se é agregado
$ehAgregado = ($tipoAssociado === 'Agregado');

if ($ehAgregado) {
    // ✅ Valida presença do associadoTitular
    // ✅ Verifica se titular existe e está filiado
    // ✅ Impede que titular seja agregado
    // ✅ Define automaticamente corporacao = 'Agregados'
    // ✅ Define patente = 'Agregado'
}

// Salva no banco com associado_titular_id preenchido
$dados = [
    // ... outros campos
    'associado_titular_id' => $associadoTitularId,
    'corporacao' => 'Agregados',
    'patente' => 'Agregado'
];
```

---

## 🎯 Fluxo Completo de Cadastro

### 1. **Usuário acessa formulário**
   - Modo: Criação de novo associado
   - Exibe checkbox "Cadastrar como Agregado"

### 2. **Usuário marca checkbox**
   ```
   ☑️ Cadastrar como Agregado
   ```
   - JavaScript exibe campo "CPF do Titular"
   - Campo nome do titular fica visível (readonly)
   - Campo oculto `associadoTitular` preparado

### 3. **Usuário digita CPF do titular**
   ```
   CPF: 388.390.611-5
   ```
   - **onBlur/onKeyUp**: JavaScript chama API
   - **API retorna**: ID, nome, situação, corporação
   - **JavaScript valida**:
     - ✅ Titular existe?
     - ✅ Titular está filiado?
     - ✅ Titular NÃO é agregado?
   - **JavaScript preenche**:
     - Campo visível: "AGOSTINHO PEREIRA DE CARVALHO - 388.390.611-5"
     - Campo oculto: `associadoTitular = 2`

### 4. **Usuário preenche demais campos**
   - Nome do agregado
   - CPF do agregado
   - Demais dados pessoais
   - **tipoAssociado**: Automaticamente definido como "Agregado"

### 5. **Usuário clica em "Salvar"**
   - **JavaScript valida antes do submit**:
     - Tem CPF do titular?
     - Tem ID do titular?
     - Sem erros visíveis?
   - **POST enviado**:
     ```php
     $_POST = [
         'nome' => 'João Silva',
         'cpf' => '12345678901',
         'tipoAssociado' => 'Agregado',
         'associadoTitular' => 2,  // ID do titular
         // ... outros campos
     ];
     ```

### 6. **API processa cadastro**
   ```php
   // 1. Detecta que é agregado (tipoAssociado = 'Agregado')
   // 2. Valida titular (ID 2):
   //    - Existe?
   //    - Está filiado?
   //    - NÃO é agregado?
   // 3. Define automaticamente:
   //    - corporacao = 'Agregados'
   //    - patente = 'Agregado'
   //    - associado_titular_id = 2
   // 4. Insere em Associados
   // 5. Insere em Militar com corporacao='Agregados'
   ```

### 7. **Resultado no banco**
   ```sql
   -- Tabela Associados
   INSERT INTO Associados (
       nome, cpf, associado_titular_id, ...
   ) VALUES (
       'João Silva', '12345678901', 2, ...
   );
   
   -- Tabela Militar
   INSERT INTO Militar (
       associado_id, corporacao, patente, ...
   ) VALUES (
       <novo_id>, 'Agregados', 'Agregado', ...
   );
   ```

---

## 🔍 Identificação de Agregados

### Método atual (UNIFICADO):
```sql
-- Buscar todos os agregados
SELECT a.*, m.patente, titular.nome as titular_nome
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
WHERE m.corporacao = 'Agregados';
```

### Vantagens:
- ✅ **Uma única tabela** para todos os associados
- ✅ **Vínculo claro** com titular via FK
- ✅ **Integridade referencial** garantida
- ✅ **Queries simplificadas** (sem UNIONs)
- ✅ **Compatível** com sistema de documentos, financeiro, etc.

---

## ⚠️ Validações Implementadas

### No Frontend (JavaScript):
1. **Checkbox marcado** → Campos de titular obrigatórios
2. **CPF do titular** → Deve ter 11 dígitos
3. **Titular encontrado** → Exibe nome automaticamente
4. **Titular válido** → Situação deve ser "Filiado"
5. **Titular não agregado** → Impede agregado de agregado
6. **Submit bloqueado** → Se validações falharem

### No Backend (PHP):
1. **tipoAssociado = 'Agregado'** → Exige `associadoTitular`
2. **Titular existe** → Query no banco
3. **Titular ativo** → `situacao = 'Filiado'`
4. **Titular não agregado** → `corporacao != 'Agregados'`
5. **Define automaticamente**:
   - `corporacao = 'Agregados'`
   - `patente = 'Agregado'`
   - `associado_titular_id = <ID do titular>`

---

## 📊 Exemplo Prático

### Cenário:
**Militar Ativo**: AGOSTINHO PEREIRA DE CARVALHO (ID: 2)  
**Agregado**: Esposa Maria Silva

### Passo a Passo:

1. **Acessar**: `/pages/cadastroForm.php`
2. **Marcar**: ☑️ Cadastrar como Agregado
3. **Preencher CPF do Titular**: `388.390.611-5`
4. **Sistema preenche automaticamente**:
   - Nome: "AGOSTINHO PEREIRA DE CARVALHO - 388.390.611-5"
   - ID oculto: `2`
5. **Preencher dados da agregada**:
   - Nome: Maria Silva
   - CPF: 987.654.321-00
   - Demais campos...
6. **Clicar em Salvar**
7. **Sistema cria**:
   ```sql
   -- Associados
   id: 100
   nome: Maria Silva
   cpf: 98765432100
   associado_titular_id: 2
   
   -- Militar
   associado_id: 100
   corporacao: Agregados
   patente: Agregado
   ```

### Consultar agregados do titular:
```sql
SELECT a.nome, a.cpf
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
WHERE a.associado_titular_id = 2
  AND m.corporacao = 'Agregados';
```

**Resultado**:
```
nome          | cpf
--------------+-----------
Maria Silva   | 98765432100
```

---

## 🎨 Interface do Usuário

### Antes de marcar o checkbox:
```
[ ] Cadastrar como Agregado
    * Caso o Associado ja for Agregado ignore esse checkbox
```

### Após marcar o checkbox:
```
[✓] Cadastrar como Agregado
    * Caso o Associado ja for Agregado ignore esse checkbox

┌─────────────────────────────────────────────────────────────┐
│ CPF do Titular *                                            │
│ [388.390.611-5                                          ]   │
│                                                             │
│ Nome do Sócio Titular                                       │
│ [AGOSTINHO PEREIRA DE CARVALHO - 388.390.611-5         ]   │
│ (campo desabilitado - preenchido automaticamente)           │
└─────────────────────────────────────────────────────────────┘
```

---

## 🧪 Testes Recomendados

### 1. Cadastro normal de agregado:
- ✅ Marcar checkbox
- ✅ Preencher CPF de titular válido
- ✅ Verificar nome preenchido automaticamente
- ✅ Completar formulário
- ✅ Salvar e verificar no banco

### 2. Validação de titular inativo:
- ❌ Tentar usar CPF de titular desfiliado
- ✅ Sistema deve exibir erro

### 3. Validação de agregado como titular:
- ❌ Tentar usar CPF de outro agregado como titular
- ✅ Sistema deve bloquear e exibir erro

### 4. Consulta de agregados:
```sql
-- Listar agregados com seus titulares
SELECT 
    a.nome as agregado,
    t.nome as titular
FROM Associados a
INNER JOIN Militar m ON a.id = m.associado_id
LEFT JOIN Associados t ON a.associado_titular_id = t.id
WHERE m.corporacao = 'Agregados';
```

---

## 📝 Notas Importantes

1. **Tabelas legadas**: `Socios_Agregados` e `Documentos_Agregado` podem ser removidas após migração completa
2. **Edição de agregados**: Formulário exibe dados do titular (readonly) quando editando agregado existente
3. **Checkbox oculto em edição**: Se já é agregado, checkbox não aparece (conforme texto explicativo)
4. **Compatibilidade**: Todas as APIs foram atualizadas para suportar estrutura unificada
5. **Documentos**: Sistema de documentos agora busca agregados via `Documentos_Associado` + `Militar.corporacao = 'Agregados'`

---

## 🚀 Próximos Passos (Opcional)

1. **Migrar dados legados**:
   ```sql
   -- Executar script de migração
   SOURCE /var/www/html/luis/comercial/migrations/migrate_agregados_to_associados.sql;
   ```

2. **Remover tabelas antigas** (após validação):
   ```sql
   DROP TABLE IF EXISTS Documentos_Agregado;
   DROP TABLE IF EXISTS Socios_Agregados;
   ```

3. **Auditar registros**:
   ```sql
   -- Verificar agregados sem titular
   SELECT * FROM Associados a
   INNER JOIN Militar m ON a.id = m.associado_id
   WHERE m.corporacao = 'Agregados' 
     AND a.associado_titular_id IS NULL;
   ```

---

## 📞 Suporte

Para dúvidas ou problemas:
- Verificar logs em `/var/www/html/luis/comercial/logs/`
- Consultar `MIGRACAO_AGREGADOS_README.md` para detalhes da estrutura
- Testar via browser com usuário autenticado

---

**Status**: ✅ **Sistema pronto para cadastrar agregados na tabela Associados**

**Última atualização**: 01/12/2025
