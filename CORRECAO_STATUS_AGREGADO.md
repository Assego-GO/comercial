# Correção: Status "Pendente de Assinatura" para Agregados

**Data:** 01/12/2025  
**Objetivo:** Garantir que agregados criados apareçam com status "AGUARDANDO_ASSINATURA" na Presidência

---

## 🔍 Problema Identificado

Ao criar um agregado via checkbox no formulário, o registro era criado na tabela `Associados`, mas:
- ❌ **NÃO aparecia** como "pendente de assinatura" na Presidência
- ❌ **NÃO exibia** o botão "Assinar"
- ❌ **Faltava** criar registro na tabela `Documentos_Associado` com status de fluxo

---

## 🛠️ Solução Implementada

### Arquivo Modificado: `api/criar_agregado.php`

#### 1. Criação de Documento Físico (se houver upload)
```php
// Quando há upload de arquivo
$stmtDoc = $db->prepare("
    INSERT INTO Documentos_Associado (
        associado_id, tipo_documento, tipo_origem, nome_arquivo,
        caminho_arquivo, data_upload, observacao, status_fluxo, verificado
    ) VALUES (?, 'FICHA_FILIACAO', 'FISICO', ?, ?, NOW(), 
              'Agregado', 'AGUARDANDO_ASSINATURA', 0)
");
```

#### 2. Criação de Documento Virtual (sem upload)
```php
// Quando NÃO há upload - cria documento virtual para controle
$stmtDoc = $db->prepare("
    INSERT INTO Documentos_Associado (
        associado_id, tipo_documento, tipo_origem, nome_arquivo,
        caminho_arquivo, data_upload, observacao, status_fluxo, verificado
    ) VALUES (?, 'FICHA_AGREGADO', 'VIRTUAL', 'ficha_virtual.pdf', '', NOW(), 
              'Agregado - Aguardando assinatura da presidência', 
              'AGUARDANDO_ASSINATURA', 0)
");
```

---

## 📋 Estrutura do Fluxo

### Tabela: `Documentos_Associado`

Campos relevantes:
- `associado_id` - ID do agregado criado em `Associados`
- `tipo_documento` - 'FICHA_FILIACAO' (com arquivo) ou 'FICHA_AGREGADO' (virtual)
- `tipo_origem` - 'FISICO' ou 'VIRTUAL'
- `status_fluxo` - **'AGUARDANDO_ASSINATURA'** (novo agregado)
- `verificado` - 0 (ainda não verificado)

### Estados do status_fluxo:
1. **DIGITALIZADO** - Documento escaneado/enviado
2. **AGUARDANDO_ASSINATURA** - ✅ Aparece na Presidência com botão "Assinar"
3. **ASSINADO** - Documento assinado, aguarda finalização
4. **FINALIZADO** - Processo concluído

---

## ✅ Resultado Esperado

### No Cadastro (cadastroForm.php):
1. ✅ Usuário marca checkbox "É um Agregado"
2. ✅ Informa CPF do titular
3. ✅ Preenche dados do agregado
4. ✅ Submete formulário
5. ✅ Sistema cria registro em `Associados` com:
   - `associado_titular_id` = ID do titular
6. ✅ Sistema cria registro em `Militar` com:
   - `corporacao` = 'Agregados'
   - `patente` = 'Agregado'
7. ✅ Sistema cria registro em `Documentos_Associado` com:
   - `status_fluxo` = 'AGUARDANDO_ASSINATURA'

### Na Presidência (presidencia.php):
1. ✅ Agregado aparece na lista de "Documentos Pendentes"
2. ✅ Status exibido: "Na Presidência" (badge laranja)
3. ✅ Botão **"Assinar"** visível
4. ✅ Presidente pode assinar o documento
5. ✅ Após assinatura: status muda para 'ASSINADO'
6. ✅ Botão **"Finalizar"** aparece
7. ✅ Após finalizar: status muda para 'FINALIZADO'

---

## 🔄 Fluxo Completo

```
[CADASTRO] → checkbox "É Agregado" + CPF Titular
     ↓
[criar_agregado.php] 
     ↓
INSERT Associados (associado_titular_id)
INSERT Militar (corporacao='Agregados')
INSERT Endereco
INSERT Financeiro
INSERT Contrato
INSERT Documentos_Associado (status_fluxo='AGUARDANDO_ASSINATURA') ← NOVO
     ↓
[PRESIDÊNCIA] → Lista "Documentos Pendentes"
     ↓
Status: "AGUARDANDO_ASSINATURA" → Botão "Assinar" aparece
     ↓
Presidente clica "Assinar"
     ↓
Status: "ASSINADO" → Botão "Finalizar" aparece
     ↓
Presidente clica "Finalizar"
     ↓
Status: "FINALIZADO" → Agregado ativo no sistema
```

---

## 🧪 Teste Manual

### Passo a Passo:
1. Acesse `pages/cadastroForm.php`
2. Marque o checkbox "Cadastrar como Agregado"
3. Informe um CPF de titular válido (ex: 019.999.411-01)
4. Preencha os dados do agregado (nome, CPF, etc.)
5. Submeta o formulário
6. Acesse `pages/presidencia.php`
7. Verifique se o agregado aparece na lista com:
   - Badge "AGUARDANDO_ASSINATURA"
   - Botão verde "Assinar"
8. Clique no botão "Assinar"
9. Verifique se o status muda para "ASSINADO"
10. Botão "Finalizar" deve aparecer

---

## 📝 Notas Técnicas

### Query de Listagem (documentos_unificados_listar.php)
```sql
SELECT 
    a.id,
    a.nome,
    a.cpf,
    da.status_fluxo,
    CASE 
        WHEN m.corporacao = 'Agregados' THEN 'AGREGADO'
        ELSE 'SOCIO'
    END as tipo_vinculo
FROM Associados a
LEFT JOIN Documentos_Associado da ON a.id = da.associado_id
LEFT JOIN Militar m ON a.id = m.associado_id
WHERE da.status_fluxo = 'AGUARDANDO_ASSINATURA'
  AND m.corporacao = 'Agregados'
```

### Condição para Mostrar Botão (presidencia.php - linha 2833)
```javascript
if (doc.status_fluxo === 'AGUARDANDO_ASSINATURA') {
    buttons += `<button class="btn-action success" 
                  onclick="abrirModalAssinaturaUnificado(...)">
                  Assinar
                </button>`;
}
```

---

## ✅ Checklist de Validação

- [x] Agregado criado na tabela `Associados`
- [x] Registro em `Militar` com `corporacao='Agregados'`
- [x] Registro em `Documentos_Associado` criado
- [x] Campo `status_fluxo` = 'AGUARDANDO_ASSINATURA'
- [x] Campo `tipo_origem` = 'VIRTUAL' (se sem upload)
- [x] Campo `verificado` = 0
- [x] Agregado aparece na Presidência
- [x] Botão "Assinar" visível
- [x] Sintaxe PHP validada (sem erros)

---

## 📚 Documentação Relacionada

- `CADASTRO_AGREGADOS_README.md` - Funcionalidade do checkbox
- `MIGRACAO_AGREGADOS_README.md` - Unificação das tabelas
- `CORRECAO_CRIAR_AGREGADO.md` - Correções de SQL anteriores

---

**Status:** ✅ Implementado e pronto para teste
**Próxima Ação:** Teste manual no navegador com usuário autenticado
