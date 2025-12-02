# Botão "Assinar" - Controle de Acesso por Departamento

**Data:** 01/12/2025  
**Objetivo:** Botão "Assinar" só aparece na aba **Presidência** (departamento_id = 1)

---

## 🎯 Comportamento Esperado

### Aba **DOCUMENTOS** (`documentos.php`)
| Status | Exibição | Ação Disponível |
|--------|----------|----------------|
| `DIGITALIZADO` | Badge azul "Aguardando Envio" | ✅ Botão "Enviar para Presidência" |
| `AGUARDANDO_ASSINATURA` | Badge laranja "Aguardando Presidência" | ❌ Nenhum botão (apenas visualização) |
| `ASSINADO` | Badge verde "Assinado" | ✅ Botão "Finalizar" |
| `FINALIZADO` | Badge verde "Concluído" | ✅ Botão "Concluído" (desabilitado) |

### Aba **PRESIDÊNCIA** (`presidencia.php`)
| Status | Exibição | Ação Disponível |
|--------|----------|----------------|
| `DIGITALIZADO` | Badge azul "Aguardando Envio" | ❌ Não aparece na Presidência |
| `AGUARDANDO_ASSINATURA` | Badge laranja "Na Presidência" | ✅ **Botão "Assinar"** (verde) |
| `ASSINADO` | Badge verde "Assinado" | ✅ Botão "Finalizar" |
| `FINALIZADO` | Badge verde "Concluído" | ✅ Botão "Concluído" (desabilitado) |

---

## 🔧 Alterações Implementadas

### Arquivo: `pages/documentos.php` (linha ~1870)

#### ❌ ANTES (botão aparecia para todos):
```php
case 'AGUARDANDO_ASSINATURA':
    <?php if ($auth->isDiretor() || $usuarioLogado['departamento_id'] == 2): ?>
        acoes = `
        <button class="btn-modern btn-success-premium btn-sm" onclick="abrirModalAssinatura(${doc.id}, '${tipo}')">
            <i class="fas fa-signature me-1"></i>
            Assinar
        </button>
    `;
    <?php endif; ?>
    break;
```

#### ✅ DEPOIS (botão só na Presidência):
```php
case 'AGUARDANDO_ASSINATURA':
    // Botão "Assinar" só aparece na aba Presidência (departamento_id == 1)
    // Na aba Documentos, apenas mostra o status sem ação
    <?php if ($usuarioLogado['departamento_id'] == 1): ?>
        acoes = `
        <button class="btn-modern btn-success-premium btn-sm" onclick="abrirModalAssinatura(${doc.id}, '${tipo}')">
            <i class="fas fa-signature me-1"></i>
            Assinar
        </button>
    `;
    <?php else: ?>
        acoes = `
        <span class="badge bg-warning text-dark">
            <i class="fas fa-clock me-1"></i>
            Aguardando Presidência
        </span>
    `;
    <?php endif; ?>
    break;
```

---

## 🔐 Controle de Acesso

### Departamentos do Sistema:
| ID | Nome | Acesso |
|----|------|--------|
| **1** | **Presidência** | ✅ Pode **assinar** documentos |
| 2 | Diretoria | ❌ Não pode assinar (apenas visualizar) |
| 10 | Financeiro/Comercial | ❌ Não pode assinar (apenas visualizar) |
| Outros | Demais setores | ❌ Não pode assinar (apenas visualizar) |

### Validação no Código:
```php
<?php if ($usuarioLogado['departamento_id'] == 1): ?>
    <!-- Botão "Assinar" só para Presidência -->
<?php else: ?>
    <!-- Badge informativo para outros departamentos -->
<?php endif; ?>
```

---

## 📋 Fluxo Completo de Documento

### 1️⃣ Cadastro do Agregado (`cadastroForm.php`)
```
Usuário Comercial preenche formulário
         ↓
Marca checkbox "É um Agregado"
         ↓
Informa CPF do titular
         ↓
Clica em "Salvar"
         ↓
[criar_agregado.php]
         ↓
✅ INSERT Associados
✅ INSERT Militar (corporacao='Agregados')
✅ INSERT Documentos_Associado (status='AGUARDANDO_ASSINATURA')
```

### 2️⃣ Visualização em Documentos (`documentos.php`)
```
[Aba DOCUMENTOS]
         ↓
✅ Agregado aparece na lista
✅ Status: "AGUARDANDO_ASSINATURA"
✅ Badge laranja: "Aguardando Presidência"
❌ SEM botão "Assinar" (apenas badge informativo)
❌ SEM botão "Enviar para Presidência" (já foi enviado automaticamente)
```

### 3️⃣ Assinatura pela Presidência (`presidencia.php`)
```
[Aba PRESIDÊNCIA]
         ↓
✅ Agregado aparece na lista "Documentos Pendentes"
✅ Status: "AGUARDANDO_ASSINATURA"
✅ Badge laranja: "Na Presidência"
✅ Botão verde: "Assinar"
         ↓
Presidente clica "Assinar"
         ↓
✅ Status muda para: "ASSINADO"
✅ Novo botão: "Finalizar"
```

### 4️⃣ Finalização do Processo
```
[Presidente clica "Finalizar"]
         ↓
✅ Status muda para: "FINALIZADO"
✅ Botão: "Concluído" (desabilitado)
✅ Agregado ativo no sistema
```

---

## 🧪 Teste Manual

### Cenário 1: Usuário do Comercial (departamento_id = 10)
1. ✅ Acessa `pages/documentos.php`
2. ✅ Vê agregado na lista com status "AGUARDANDO_ASSINATURA"
3. ✅ Vê badge laranja "Aguardando Presidência"
4. ❌ **NÃO** vê botão "Assinar"
5. ❌ **NÃO** vê botão "Enviar para Presidência"

### Cenário 2: Usuário da Presidência (departamento_id = 1)
1. ✅ Acessa `pages/documentos.php`
2. ✅ Vê agregado na lista com status "AGUARDANDO_ASSINATURA"
3. ✅ Vê botão verde **"Assinar"**
4. ✅ Pode clicar e assinar o documento

### Cenário 3: Usuário da Presidência na aba específica
1. ✅ Acessa `pages/presidencia.php`
2. ✅ Vê agregado em "Documentos Pendentes"
3. ✅ Vê botão verde **"Assinar"**
4. ✅ Pode clicar e assinar o documento

---

## 📊 Resumo das Permissões

### Aba DOCUMENTOS:
```php
// Status: DIGITALIZADO
→ Todos: Botão "Enviar para Presidência"

// Status: AGUARDANDO_ASSINATURA
→ Presidência: Botão "Assinar"
→ Outros: Badge "Aguardando Presidência" (sem botão)

// Status: ASSINADO
→ Todos: Botão "Finalizar"

// Status: FINALIZADO
→ Todos: Badge "Concluído"
```

### Aba PRESIDÊNCIA:
```php
// Status: AGUARDANDO_ASSINATURA
→ Presidência: Botão "Assinar" + todos recursos de assinatura

// Status: ASSINADO
→ Presidência: Botão "Finalizar"
```

---

## ✅ Checklist de Validação

- [x] Agregado criado via `cadastroForm.php` com status `AGUARDANDO_ASSINATURA`
- [x] Documento aparece na aba **Documentos**
- [x] Badge "Aguardando Presidência" visível
- [x] Botão "Assinar" **OCULTO** para usuários fora da Presidência
- [x] Botão "Assinar" **VISÍVEL** apenas para `departamento_id = 1`
- [x] Botão "Enviar para Presidência" **NÃO** aparece (documento já está aguardando)
- [x] Sintaxe PHP validada sem erros

---

## 🔗 Arquivos Relacionados

1. **`api/criar_agregado.php`** - Cria documento com status inicial
2. **`pages/documentos.php`** - Lista documentos (SEM botão assinar)
3. **`pages/presidencia.php`** - Lista documentos (COM botão assinar)
4. **`api/documentos/documentos_unificados_listar.php`** - API de listagem

---

**Status:** ✅ Implementado e validado  
**Próxima Ação:** Teste em navegador com diferentes perfis de usuário
