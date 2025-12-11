# 🔄 Funcionalidade de Re-Filiação de Associados

## Visão Geral

A funcionalidade de **Re-Filiação** permite que usuários do setor comercial/financeiro iniciem facilmente o processo de filiação novamente para associados que estão desfiliados.

### Fluxo de Funcionamento

O processo é simples e intuitivo:

1. **Dashboard**: Usuário localiza um associado desfiliado
2. **Visualizar**: Clica no botão de visualizar para abrir o modal de detalhes
3. **Editar**: Clica no botão "Editar" dentro do modal
4. **Mudar Status**: Altera o status de "Desfiliado" para "Filiado"
5. **Detectar Refiliação**: O sistema detecta a mudança e automaticamente redireciona
6. **Cadastro Form**: Abre `cadastroForm.php` em modo de **refiliação** com todos os dados preenchidos
7. **Completar Filiação**: Usuário passa por todas as etapas do formulário de filiação

## Mudanças Implementadas

### 1. Dashboard JavaScript (`pages/js/dashboard.js`)

**Função Modificada: `salvarEdicaoModal()`**

- Adicionada detecção de mudança de status: `Desfiliado → Filiado`
- Quando esta mudança é detectada:
  - Fecha o modal automaticamente
  - Mostra notificação de "Iniciando processo de refiliação"
  - Redireciona para `cadastroForm.php?id={ID}&refiliacao=true`
  - **NÃO** salva a mudança de status no banco (apenas dispara o redirecionamento)

**Logs Adicionados:**
```javascript
console.log('🔍 Detecção de refiliação:');
console.log('  Status anterior:', statusAnterior);
console.log('  Status atual:', statusAtual);
console.log('  É refiliação esperada?', ehRefiliacaoEsperada);
console.log('🔄 DETECÇÃO DE REFILIAÇÃO: Mudança de Desfiliado → Filiado');
```

### 2. Cadastro Form PHP (`pages/cadastroForm.php`)

**Novas Variáveis:**
```php
$isRefiliacao = isset($_GET['refiliacao']) && $_GET['refiliacao'] === 'true';
```

**Mudanças no Título:**
- Quando em modo refiliação: `"Refiliação de Associado - ASSEGO"`
- Quando em modo edição normal: `"Editar Associado - ASSEGO"`

**Mudanças na Interface:**
- Breadcrumb mostra "Refiliação" em vez de "Editar"
- Badge visual azul com ícone de sincronização: `🔄 Re-filiação`
- Descrição adaptada: "Complete o processo de refiliação deste associado"

## Fluxo Técnico

```
┌─────────────────────────────────────────────┐
│   Dashboard - Modal Detalhes Associado     │
│   Status: Desfiliado                        │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   Usuário clica em "Editar"                │
│   ativarModoEdicao()                       │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   Altera Status para "Filiado"              │
│   select#edit_situacao = "Filiado"         │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   Usuário clica em "Salvar"                │
│   salvarEdicaoModal()                      │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   DETECÇÃO DE REFILIAÇÃO                   │
│   statusAnterior = "Desfiliado"            │
│   statusAtual = "Filiado"                  │
│   → ehRefiliacaoEsperada = true            │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   ✅ REFILIAÇÃO DETECTADA                  │
│   - fecharModal()                          │
│   - Exibir notificação                     │
│   - window.location.href = cadastroForm.php│
│     ?id={ID}&refiliacao=true               │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   cadastroForm.php                         │
│   - $isEdit = true (id fornecido)          │
│   - $isRefiliacao = true                   │
│   - Carrega todos os dados do desfiliado  │
│   - Mostra interface especial de refiliação│
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   Usuário completa formulário              │
│   Passa por etapas de filiação             │
│   Sistema valida e salva                   │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│   ✅ REFILIAÇÃO CONCLUÍDA                  │
│   Associado retorna ao status "Filiado"    │
└─────────────────────────────────────────────┘
```

## Como Usar

### Passo 1: Acessar Dashboard
- Vá para `/pages/dashboard.php`
- Filtre por "Desfiliado" se necessário

### Passo 2: Visualizar Associado
- Clique no ícone de "olho" ou clique na linha do associado
- Abre o modal com detalhes completos

### Passo 3: Iniciar Edição
- Clique no botão "Editar" no header do modal
- Os campos ficam editáveis

### Passo 4: Mudar Status
- Localize o campo "Situação" na aba de Visão Geral
- Altere de "Desfiliado" para "Filiado"

### Passo 5: Salvar para Refiliação
- Clique no botão "Salvar"
- Sistema detecta a mudança e automaticamente redireciona

### Passo 6: Completar Refiliação
- Será aberto o `cadastroForm.php` com:
  - Todos os dados pessoais preenchidos
  - Badge visual indicando "Re-filiação"
  - Descrição adaptada do processo
  - Número do associado (matrícula) informado

## Dados Carregados no cadastroForm

Quando um associado é redirecionado para refiliação, os seguintes dados já vêm preenchidos:

### Dados Pessoais
- Nome completo
- CPF
- RG
- Data de nascimento
- Sexo
- Estado civil
- Escolaridade

### Dados Militares
- Corporação
- Patente
- Categoria
- Lotação
- Unidade

### Endereço
- CEP
- Endereço
- Número
- Complemento
- Bairro
- Cidade

### Dados Financeiros
- Tipo de associado
- Situação financeira
- Vínculo servidor
- Local de débito
- Agência
- Operação
- Conta corrente

### Outros
- Contatos (telefone, email)
- Dependentes (se houver)
- Indicação

## Segurança e Validações

1. **Autenticação**: Apenas usuários logados podem acessar
2. **Permissões**: Apenas usuários com `podeEditarCompleto = true` podem mudar status
3. **Detecção Específica**: Refiliação é detectada apenas na mudança específica:
   - Status anterior: `"Desfiliado"`
   - Status novo: `"Filiado"`
4. **Não Salva Prematuro**: A mudança de status NÃO é salva quando redirecionado
   - Apenas fecha modal e redireciona
   - Salvamento completo acontece ao terminar o cadastroForm

## Logs e Debug

Os seguintes logs estão disponíveis no console do navegador:

```javascript
// No dashboard.js - salvarEdicaoModal()
🔍 Detecção de refiliação:
  Status anterior: Desfiliado
  Status atual: Filiado
  É refiliação esperada? true

🔄 DETECÇÃO DE REFILIAÇÃO: Mudança de Desfiliado → Filiado
🔄 Associado ID: 123
🔄 Nome: João Silva
```

```php
// No cadastroForm.php
🔄 MODO REFILIAÇÃO ATIVADO - Associado ID: 123
```

## Limitações Conhecidas

1. **Mudança de Status**: Apenas a mudança de `Desfiliado → Filiado` dispara refiliação
   - Outras mudanças de status funcionam normalmente
2. **Permissões**: Requer permissão `podeEditarCompleto`
3. **Modal**: Deve estar em modo de edição (`toggleModoEdicao()` ativado)

## Testes Recomendados

### Teste 1: Refiliação Básica
1. Criar um associado e desfiliá-lo
2. Acessar dashboard e visualizá-lo
3. Clicar em Editar
4. Mudar status para Filiado
5. Clicar em Salvar
6. Verificar redirecionamento para cadastroForm.php

### Teste 2: Logs
1. Abrir console do navegador (F12)
2. Executar teste 1
3. Verificar se logs aparecem com prefixo 🔄

### Teste 3: Dados Preenchidos
1. Depois de redirecionar para cadastroForm.php
2. Verificar se todos os campos pessoais estão preenchidos
3. Verificar se badge "Re-filiação" aparece

### Teste 4: Permissões
1. Testar com usuário sem permissão `podeEditarCompleto`
2. Verificar se campo de situação fica desabilitado

## Versão
- **Versão**: 1.0
- **Data**: Dezembro 2025
- **Status**: Implementado e pronto para produção

## Próximas Melhorias (Sugestões)

1. [ ] Armazenar dados em sessão durante refiliação para facilitar retorno
2. [ ] Adicionar progresso visual (etapa 1 de 5, etc)
3. [ ] Permitir voltar ao dashboard sem perder refiliação parcial
4. [ ] Notificação por email ao completar refiliação
5. [ ] Histórico de tentativas de refiliação por associado
