# 📋 Guia de Teste - Funcionalidade de Re-Filiação

## Checklist de Testes

### ✅ Teste 1: Acesso ao Modal de Edição

**Objetivo**: Verificar se é possível abrir o modal de associado desfiliado e ativar modo edição

**Passos**:
1. Acesse `/pages/dashboard.php`
2. Filtre para mostrar apenas "Desfiliado"
3. Clique em um associado para abrir o modal
4. Verifique se o botão "Editar" aparece
5. Clique em "Editar"

**Resultado esperado**: ✅
- Modal mostra botões "Salvar" e "Cancelar"
- Campos ficam editáveis
- Campo "Situação" mostra dropdown com opções

**Se falhar**: 
- Verifique se o usuário tem permissão `podeEditarCompleto`
- Verifique se o JavaScript foi carregado corretamente
- Abra console (F12) para verificar erros

---

### ✅ Teste 2: Detecção de Mudança de Status

**Objetivo**: Verificar se a mudança de status é detectada corretamente

**Passos**:
1. Com o modal em modo edição (após teste 1)
2. Localize o campo "Situação" (na aba Visão Geral)
3. Altere de "Desfiliado" para "Filiado"
4. Abra o console do navegador (F12)
5. Clique em "Salvar"

**Resultado esperado**: ✅
- Console mostra logs:
  ```
  🔍 Detecção de refiliação:
    Status anterior: Desfiliado
    Status atual: Filiado
    É refiliação esperada? true
  
  🔄 DETECÇÃO DE REFILIAÇÃO: Mudança de Desfiliado → Filiado
  🔄 Associado ID: [número]
  🔄 Nome: [nome do associado]
  ```
- Notificação aparece: "Iniciando processo de refiliação..."
- Modal fecha

**Se falhar**:
- Verifique no console qual é o status anterior e atual
- Verifique se o select tem id="edit_situacao"
- Verifique se datosOriginaisAssociado tem o valor correto

---

### ✅ Teste 3: Redirecionamento para cadastroForm.php

**Objetivo**: Verificar se o redirecionamento é feito corretamente

**Passos**:
1. Execute o Teste 2 completo
2. Aguarde ~3 segundos
3. Verifique se a página redireciona para `cadastroForm.php`

**Resultado esperado**: ✅
- URL muda para: `cadastroForm.php?id={ID}&refiliacao=true`
- Página carrega com o formulário de filiação

**Se falhar**:
- Verifique se há erros JavaScript no console
- Verifique se `window.location.href` está funcionando
- Verifique se `fecharModal()` está funcionando

---

### ✅ Teste 4: Carregamento de Dados no cadastroForm.php

**Objetivo**: Verificar se todos os dados foram carregados corretamente

**Passos**:
1. Após redirecionamento (Teste 3), a página deve mostrar:
   - Badge azul com "🔄 Re-filiação"
   - Breadcrumb mostrando "Refiliação"
   - Título: "Refiliação de Associado"
   - Descrição: "Complete o processo de refiliação deste associado"

2. Verifique se os campos estão preenchidos:
   - Nome completo ✓
   - CPF ✓
   - RG ✓
   - Data de nascimento ✓
   - Corporação ✓
   - Patente ✓
   - Endereço ✓
   - Contatos ✓

**Resultado esperado**: ✅
- Todos os dados aparecem preenchidos
- A interface mostra claramente que é uma refiliação
- Usuário pode passar por todas as etapas do formulário

**Se falhar**:
- Verifique se `$isRefiliacao` é detectado corretamente em PHP
- Verifique logs do servidor (error_log)
- Verifique se os dados estão sendo carregados do banco

---

### ✅ Teste 5: Validação de Permissões

**Objetivo**: Verificar se usuários sem permissão não conseguem refiliar

**Passos**:
1. Faça login com um usuário que NÃO tem `podeEditarCompleto`
2. Acesse dashboard
3. Tente abrir modal de associado desfiliado
4. Clique em "Editar"

**Resultado esperado**: ✅
- Botão "Editar" não aparece OU fica desabilitado
- Campo "Situação" fica desabilitado (cinza)
- Não é possível mudar o status

**Se falhar**:
- Verifique as permissões do usuário no banco
- Verifique se `Permissoes::tem()` está funcionando
- Verifique `permissoesUsuario.podeEditarCompleto` no JavaScript

---

### ✅ Teste 6: Outros Tipos de Mudança de Status

**Objetivo**: Verificar se outras mudanças funcionam normalmente (sem refiliação)

**Passos**:
1. Abra um associado "Filiado"
2. Clique em "Editar"
3. Altere de "Filiado" para "Desfiliado"
4. Clique em "Salvar"

**Resultado esperado**: ✅
- A mudança é salva normalmente
- Não há redirecionamento
- Mensagem "Dados atualizados com sucesso!" aparece
- Modal continua aberto com dados atualizados

**Se falhar**:
- Verifique se `salvarEdicaoModal()` continua funcionando para outros casos
- Verifique se API `/api/atualizar_associado.php` funciona

---

### ✅ Teste 7: Logs no Servidor PHP

**Objetivo**: Verificar se os logs aparecem no servidor

**Passos**:
1. Execute Teste 2 novamente
2. Acesse o arquivo de logs do servidor
3. Procure por linhas contendo "REFILIAÇÃO"

**Resultado esperado**: ✅
- Log mostra: `🔄 MODO REFILIAÇÃO ATIVADO - Associado ID: [número]`
- Log aparece quando cadastroForm.php carrega com `refiliacao=true`

**Local dos logs**:
- Linux/Apache: `/var/log/apache2/error.log` ou definido em `config/config.php`
- Procure por: `error_log()`

**Se falhar**:
- Verifique se `error_log()` está ativado
- Verifique caminho correto do arquivo de logs

---

## Dados de Teste Recomendados

### Criar Associado de Teste
```sql
INSERT INTO Associados (nome, cpf, rg, situacao, email, telefone) 
VALUES (
    'João Silva Teste', 
    '12345678900', 
    '1234567', 
    'Desfiliado',
    'joao@teste.com',
    '61999999999'
);
```

### Dados para Refiliação Completa
- **Nome**: João Silva Teste
- **CPF**: 12345678900
- **RG**: 1234567
- **Data Nascimento**: 1985-05-15
- **Corporação**: PMESP
- **Patente**: Tenente
- **Endereço**: Rua das Flores, 123
- **Bairro**: Centro
- **Cidade**: São Paulo
- **CEP**: 01310100

---

## Troubleshooting

### Problema: "Nenhum associado encontrado"
**Solução**: Certifique-se que o ID é válido no banco antes de testar

### Problema: Modal não fecha
**Solução**: Verifique função `fecharModal()` no JavaScript

### Problema: Redirecionamento não acontece
**Solução**: 
- Verifique logs do console (F12)
- Verifique se `window.location.href` está definido
- Teste com URL direta: `cadastroForm.php?id=123&refiliacao=true`

### Problema: Dados não carregam no cadastroForm
**Solução**:
- Verifique logs do servidor (`error_log`)
- Teste query SQL manualmente
- Verifique se `$isEdit` é detectado corretamente

### Problema: Badge "Re-filiação" não aparece
**Solução**:
- Verifique se `$isRefiliacao` é true
- Verifique sintaxe PHP `<?php if ($isRefiliacao): ?>`
- Limpe cache do navegador (Ctrl+Shift+Delete)

### Problema: Campo de situação fica desabilitado
**Solução**: Isso é normal se o usuário não tem `podeEditarCompleto`
- Teste com usuário que tem permissão
- Ou altere as permissões no banco de dados

---

## Performance

### Tempos Esperados:
- Abrir modal: < 1 segundo
- Ativar modo edição: < 0.5 segundos
- Clicar em salvar → ver redirecionamento: 1.5-2 segundos
- Carregar cadastroForm: < 2 segundos

### Se demorar mais:
- Verifique velocidade da rede
- Verifique logs do servidor para erros
- Verifique número de registros no banco (pode estar lento)

---

## Checklist Final de Deployment

Antes de disponibilizar em produção:

- [ ] Todos os 7 testes passando
- [ ] Console não mostra erros JavaScript
- [ ] Logs do servidor não mostram erros PHP
- [ ] Permissões estão configuradas corretamente
- [ ] Usuários de teste conseguem fazer refiliação completa
- [ ] Outros usuários não conseguem (sem permissão)
- [ ] Dados persistem após conclusão da refiliação
- [ ] Página volta ao dashboard após conclusão
- [ ] Notificações aparecem corretamente

---

## Contato para Suporte

Se encontrar problemas:
1. Verifique este guia de teste
2. Consulte REFILIACAO_FEATURE_README.md
3. Verifique logs (console + servidor)
4. Abra issue com descrição do problema + logs
