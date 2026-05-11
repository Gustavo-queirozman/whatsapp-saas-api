# AGENTS.md

# Projeto

SaaS multiempresa de atendimento WhatsApp utilizando Evolution Go.

Arquitetura separada em:

- Backend API Laravel
- Frontend React
- Evolution Go como gateway WhatsApp

---

# Objetivo

Criar uma plataforma SaaS para:

- multiatendimento
- múltiplas empresas
- múltiplos setores
- chatbot simples
- IA leve
- campanhas
- CRM leve
- dashboard operacional

---

# Repositórios

## Backend

```txt
whatsapp-saas-api
```

Stack:

- Laravel
- PostgreSQL
- Redis
- Sanctum
- Reverb/WebSocket
- Docker

---

## Frontend

```txt
whatsapp-saas-front
```

Stack:

- React
- TypeScript
- Vite
- Tailwind
- Axios
- Zustand

---

# Regras Gerais

## Obrigatórias

- todo código deve ser tipado
- seguir SOLID
- seguir Clean Code
- evitar lógica duplicada
- evitar código acoplado
- utilizar Services/Actions
- criar DTOs quando necessário
- criar testes para regras críticas
- nunca acessar dados sem company_id
- toda entidade de negócio deve respeitar multi-tenancy

---

# Multi-tenancy

Todas as tabelas devem possuir:

```txt
company_id
```

Exceto:

- companies
- plans
- system_configs

Toda query deve filtrar empresa atual.

Nunca confiar no company_id enviado pelo frontend.

A empresa atual deve vir do contexto autenticado.

---

# Estrutura Backend

Estrutura esperada:

```txt
app/
 ├─ Domains/
 ├─ Services/
 ├─ Actions/
 ├─ DTOs/
 ├─ Events/
 ├─ Jobs/
 ├─ Policies/
 ├─ Repositories/
 ├─ Support/
 └─ Http/
```

---

# Estrutura Frontend

Estrutura esperada:

```txt
src/
 ├─ components/
 ├─ pages/
 ├─ layouts/
 ├─ hooks/
 ├─ services/
 ├─ stores/
 ├─ routes/
 ├─ contexts/
 ├─ types/
 └─ utils/
```

---

# Integração Evolution Go

Evolution Go será utilizado apenas como gateway WhatsApp.

Nunca colocar regra de negócio dentro do Evolution Go.

Toda lógica deve existir na API Laravel.

Fluxo:

```txt
WhatsApp
   ↓
Evolution Go
   ↓ webhook
Laravel API
   ↓
Frontend React
```

---

# Convenções

# Backend

## Controllers

Controllers devem ser finos.

Responsabilidade:

- validar request
- chamar Action/Service
- retornar response

Nunca colocar regra de negócio em controller.

---

## Services

Services devem conter:

- integrações externas
- regras compartilhadas
- lógica reutilizável

---

## Actions

Actions devem executar:

- operações específicas
- fluxos únicos
- casos de uso

Exemplo:

```txt
AssignConversationAction
CreateWhatsappInstanceAction
ProcessWebhookMessageAction
```

---

## DTOs

Utilizar DTOs para:

- payloads externos
- webhook Evolution
- IA
- integrações

---

## Repositories

Utilizar repositories quando houver:

- queries complexas
- reutilização
- filtros multi-tenant

---

# Frontend

## Componentes

Separar:

- UI components
- business components

Componentes devem ser pequenos.

---

## Estado

Utilizar:

- Zustand para estado global
- React Query futuramente se necessário

Evitar prop drilling.

---

## API

Centralizar chamadas em:

```txt
src/services/api
```

Nunca chamar axios diretamente nas páginas.

---

# Módulos do Sistema

# 1. Autenticação

Responsável por:

- login
- logout
- sessão
- seleção de empresa
- permissões

---

# 2. Empresas

Responsável por:

- empresas
- usuários
- planos futuramente
- permissões

---

# 3. WhatsApp

Responsável por:

- instâncias
- QR Code
- status
- webhook
- envio de mensagens

---

# 4. Atendimento

Responsável por:

- conversas
- mensagens
- contatos
- histórico
- multiatendimento

---

# 5. Filas

Responsável por:

- setores
- distribuição
- fila de espera
- transferência

---

# 6. Tags

Responsável por:

- classificação
- filtros
- organização

---

# 7. Chatbot

Responsável por:

- menus
- respostas automáticas
- horário de atendimento
- transferência automática

---

# 8. IA

Responsável por:

- resumo
- sugestão de resposta
- classificação de intenção

Sempre utilizar providers desacoplados.

Criar interface:

```txt
AiProviderInterface
```

---

# 9. Campanhas

Responsável por:

- disparo
- agendamento
- filas
- controle de velocidade
- métricas

---

# 10. CRM

Responsável por:

- pipelines
- negócios
- estágios
- relacionamento com contatos

---

# 11. Dashboard

Responsável por:

- métricas
- gráficos
- produtividade
- indicadores

---

# Tempo Real

Eventos em tempo real:

- MessageReceived
- ConversationUpdated
- ConversationAssigned
- InstanceStatusChanged

Frontend deve atualizar automaticamente.

---

# Banco de Dados

Principais tabelas:

```txt
companies
users
company_users
roles
permissions

sectors
sector_users

whatsapp_instances

contacts
conversations
messages

tags
contact_tag
conversation_tag

bot_flows
bot_flow_options

campaigns
campaign_contacts
campaign_messages

pipelines
pipeline_stages
deals
```

---

# Regras de Segurança

Obrigatório:

- autenticação Sanctum
- policies
- middleware multi-tenant
- validação de permissões
- logs críticos
- rate limit
- validação de webhook

Nunca:

- expor tokens
- expor company_id de terceiros
- confiar em payload do frontend

---

# Docker

Serviços esperados:

```txt
api
frontend
postgres
redis
evolution-go
```

---

# Padrões de API

## Responses

Sucesso:

```json
{
  "success": true,
  "data": {}
}
```

Erro:

```json
{
  "success": false,
  "message": "Erro interno"
}
```

---

# Padrão de Branch

```txt
feature/nome-feature
fix/nome-fix
refactor/nome-refactor
```

---

# Pull Requests

Todo PR deve:

- ter descrição
- explicar objetivo
- listar alterações
- listar migrations
- listar endpoints novos
- incluir screenshots no frontend

---

# Testes

Backend:

- Feature tests
- Unit tests
- testes multi-tenant

Frontend:

- componentes críticos
- fluxos principais

---

# Performance

Evitar:

- N+1 queries
- queries sem índice
- carregamento excessivo
- polling excessivo

Utilizar:

- eager loading
- paginação
- cache quando necessário
- filas/jobs

---

# Roadmap

# MVP

- autenticação
- multi-tenant
- WhatsApp
- atendimento
- histórico
- filas
- tags
- dashboard
- chatbot simples

---

# Pós-MVP

- IA avançada
- omnichannel
- billing
- voicebot
- automações complexas
- analytics avançado

---

# Objetivo Técnico

O projeto deve ser:

- modular
- escalável
- desacoplado
- multiempresa
- fácil de manter
- pronto para crescer
- preparado para microsserviços futuramente

