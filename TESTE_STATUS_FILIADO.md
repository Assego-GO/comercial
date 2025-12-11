# 🧪 Teste da Correção: Status Filiado na Presidência

## Checklist de Validação

### ✅ Teste 1: Finalização de Sócio

**Objetivo**: Verificar se sócio fica "Filiado" após finalizar na presidência

**Pré-requisitos**:
- Documento de sócio em status "ASSINADO"
- Usuário logado com permissão de presidência

**Passos**:
1. Acesse `/pages/presidencia.php`
2. Localize um documento com status "ASSINADO" na seção de "SÓCIOS"
3. Anote o nome e CPF do associado
4. Clique em "Finalizar"
5. Confirme a ação
6. Aguarde a notificação de sucesso

**Resultado Esperado**: ✅
- Notificação: "Processo do sócio finalizado com sucesso!"
- Documento muda para status "FINALIZADO"
- **IMPORTANTE**: Associado aparece com situação "Filiado"

**Como Verificar**:
1. Vá para Dashboard (`/pages/dashboard.php`)
2. Procure pelo associado pelo CPF/nome
3. Verifique se a coluna "Situação" mostra "Filiado" ✅

**Se falhar**:
- Verifique console (F12) para erros JavaScript
- Verifique logs do servidor para erros PHP
- Procure por logs com `🔄 Atualizando` e `✅ Status`

---

### ✅ Teste 2: Finalização de Agregado

**Objetivo**: Verificar se agregado fica "Filiado" após finalizar na presidência

**Pré-requisitos**:
- Documento de agregado em status "ASSINADO"
- Usuário logado com permissão de presidência

**Passos**:
1. Acesse `/pages/presidencia.php`
2. Localize um agregado com status "ASSINADO" na seção de "AGREGADOS"
3. Anote o nome do agregado
4. Clique em "Finalizar"
5. Confirme a ação
6. Aguarde a notificação de sucesso

**Resultado Esperado**: ✅
- Notificação: "Processo do agregado finalizado com sucesso!"
- Documento muda para status "FINALIZADO"
- **IMPORTANTE**: Agregado aparece com situação "Filiado"
- `pre_cadastro` muda de 1 para 0

**Como Verificar**:
1. Vá para Dashboard (`/pages/dashboard.php`)
2. Procure pelo agregado pelo nome
3. Verifique se a coluna "Situação" mostra "Filiado" ✅

**Se falhar**:
- Verifique se é realmente um agregado (corporação = "Agregados")
- Verifique API `/api/documentos/documentos_agregados_finalizar.php`
- Procure por logs com `[FINALIZAR_AGREGADO]`

---

### ✅ Teste 3: Verificação de Logs

**Objetivo**: Confirmar que os logs aparecem ao finalizar

**Passos**:
1. Abra acesso aos logs do servidor:
   ```bash
   tail -f /var/log/apache2/error.log
   # ou
   tail -f /var/www/html/victor/comercial/logs/error.log
   ```
2. Em outra janela, execute Teste 1 ou 2
3. Observe os logs em tempo real

**Logs Esperados para Sócio**:
```
🔄 Atualizando status do associado [ID] para Filiado
✅ Status do associado [ID] atualizado para Filiado
```

**Logs Esperados para Agregado**:
```
[FINALIZAR_AGREGADO] Agregado encontrado: [NOME] (ID: [ID])
[FINALIZAR_AGREGADO] Status atual: ASSINADO
[FINALIZAR_AGREGADO] Documento atualizado para FINALIZADO - Doc ID: [ID]
[FINALIZAR_AGREGADO] Agregado atualizado - pre_cadastro: 0, situacao: Filiado - ID: [ID]
[FINALIZAR_AGREGADO] FINALIZAÇÃO CONCLUÍDA - ID: [ID], Nome: [NOME]
```

**Se não aparecerem**:
- Verifique se `error_log()` está ativado
- Verifique caminho correto do arquivo de logs
- Procure por erros (`Error`, `Exception`, `Fatal`)

---

### ✅ Teste 4: Query Direto no Banco

**Objetivo**: Validar mudança no banco de dados

**Pré-requisitos**:
- Acesso ao banco de dados
- Ter realizado Teste 1 ou 2

**Passos**:
```sql
-- Para sócio testado
SELECT id, nome, situacao, pre_cadastro 
FROM Associados 
WHERE nome = 'NOME_DO_ASSOCIADO'
LIMIT 1;

-- Resultado esperado:
-- +---------+-------------------+----------+--------------+
-- | id      | nome              | situacao | pre_cadastro |
-- +---------+-------------------+----------+--------------+
-- | 12345   | João Silva        | Filiado  | 0            |
-- +---------+-------------------+----------+--------------+
```

**Verificar Documento**:
```sql
SELECT id, associado_id, status_fluxo, data_finalizacao 
FROM Documentos_Associado 
WHERE associado_id = 12345
ORDER BY id DESC
LIMIT 1;

-- Resultado esperado:
-- +-------+--------------+---------------+--------------------+
-- | id    | associado_id  | status_fluxo  | data_finalizacao   |
-- +-------+--------------+---------------+--------------------+
-- | 5678  | 12345         | FINALIZADO    | 2025-12-11 15:30.. |
-- +-------+--------------+---------------+--------------------+
```

**Se não estiver correto**:
- Execute os testes novamente
- Verifique permissões do usuário
- Procure por erros de transação

---

### ✅ Teste 5: Refiliação Combinada

**Objetivo**: Testar que refiliação funciona após finalização na presidência

**Pré-requisitos**:
- Ter um associado que foi finalizado (Teste 1 ou 2)
- Ter permissão de edição no dashboard

**Passos**:
1. Acesse Dashboard
2. Procure pelo associado finalizado (deve estar "Filiado")
3. Clique em visualizar
4. Clique em "Editar"
5. Mude status de "Filiado" para "Desfiliado"
6. Clique "Salvar"
7. Mude novamente para "Filiado" na edição
8. Clique "Salvar"

**Resultado Esperado**: ✅
- Refiliação é detectada
- Redireciona para cadastroForm.php em modo refiliação
- Dados vêm preenchidos
- Processo de refiliação funciona normalmente

---

## Dados de Teste Recomendados

### Criar Sócio de Teste
```sql
INSERT INTO Associados (nome, cpf, rg, situacao, email, telefone)
VALUES (
    'João Teste Presidência',
    '11122233344',
    '1234567',
    'Desfiliado',
    'joao.teste@email.com',
    '61999999999'
);
```

### Criar Documento de Teste
```sql
INSERT INTO Documentos_Associado (
    associado_id,
    tipo_documento,
    status_fluxo,
    departamento_origem,
    departamento_atual,
    data_criacao
)
VALUES (
    (SELECT id FROM Associados WHERE cpf = '11122233344'),
    'SOCIO',
    'ASSINADO',
    10,
    1,
    NOW()
);
```

---

## Problemas Comuns e Soluções

### Problema: "Documento não encontrado"
**Causa**: Documento não está em status "ASSINADO"
**Solução**: Verifique status na presidência. Deve estar "ASSINADO" para finalizar

### Problema: Status não muda no Dashboard
**Causa**: 
- Cache do navegador
- Query não foi executada
**Solução**: 
- Limpe cache (Ctrl+Shift+Delete)
- Recarregue Dashboard (F5)
- Verifique banco direto

### Problema: Erro ao finalizar
**Causa**: Permissão ou dados inválidos
**Solução**:
- Abra console (F12)
- Verifique mensagem de erro
- Consulte logs do servidor

### Problema: Logs não aparecem
**Causa**: `error_log()` não está configurado
**Solução**:
- Verifique `config/config.php`
- Procure por `error_log` ou `log_errors`
- Configure se necessário

### Problema: Transação falha
**Causa**: Campo não existe ou tipo errado
**Solução**:
- Verifique estrutura do banco
- Coluna `situacao` existe em `Associados`?
- Tipo é VARCHAR ou similar?

---

## Performance

### Tempos Esperados:
- Clique finalizar → notificação: < 2 segundos
- Dashboard atualizar: < 1 segundo
- Banco atualizar: < 500ms

### Se demorar mais:
- Verifique número de registros no banco
- Verifique velocidade da conexão
- Verifique índices do banco

---

## Rollback (Se Necessário)

Se precisar reverter a mudança:

### Opção 1: Revert do Git
```bash
git revert HEAD  # Se foi o último commit
git checkout HEAD -- classes/Documentos.php
git checkout HEAD -- api/documentos/documentos_agregados_finalizar.php
```

### Opção 2: Manual - Desfazer Mudanças
```php
// Remover do classes/Documentos.php (linhas 356-365):
if (!empty($documento['associado_id'])) {
    error_log("🔄 Atualizando status do associado " . $documento['associado_id'] . " para Filiado");
    $stmtAssociado = $this->db->prepare("UPDATE Associados SET situacao = 'Filiado' WHERE id = ?");
    $stmtAssociado->execute([$documento['associado_id']]);
    error_log("✅ Status do associado " . $documento['associado_id'] . " atualizado para Filiado");
}

// Remover de documentos_agregados_finalizar.php:
// situacao = 'Filiado'
```

---

## Checklist Final

Antes de considerar a correção concluída:

- [ ] Teste 1 (Sócio) passou
- [ ] Teste 2 (Agregado) passou
- [ ] Teste 3 (Logs) passou
- [ ] Teste 4 (Query) confirmou mudanças
- [ ] Teste 5 (Refiliação) passou
- [ ] Não há erros no console
- [ ] Não há erros nos logs do servidor
- [ ] Dashboard mostra status correto
- [ ] Performance aceitável

---

## Suporte

Em caso de problemas:
1. Verifique este guia
2. Consulte CORRECAO_STATUS_FILIADO_README.md
3. Verifique logs (servidor e navegador)
4. Teste com dados simples primeiro
5. Documente o problema e entre em contato
