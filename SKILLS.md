# SKILLS.md

# Projeto

SaaS multiempresa de atendimento WhatsApp utilizando Evolution Go como gateway WhatsApp, com API Laravel e frontend React separados.

---

# Objetivo deste arquivo

Este arquivo define as habilidades esperadas dos agentes, assistentes de código e desenvolvedores que atuarão no projeto.

Ele deve orientar:

- implementação de funcionalidades
- tomada de decisão técnica
- divisão de tarefas
- revisão de código
- padronização entre backend e frontend
- manutenção do sistema

---

# Visão Geral das Skills

Os agentes devem ser capazes de trabalhar nas seguintes áreas:

- Laravel API
- React Frontend
- Multi-tenancy
- Integração Evolution Go
- Webhooks
- WebSocket/tempo real
- PostgreSQL
- Redis
- Filas/jobs
- Chatbot
- IA leve
- Campanhas WhatsApp
- CRM leve
- Docker
- Segurança
- Testes

---

# Skill 1 — Backend Laravel API

## Objetivo

Desenvolver a API principal do SaaS.

## Conhecimentos necessários

- Laravel moderno
- Sanctum
- Eloquent
- Form Requests
- Resources
- Policies
- Middleware
- Jobs
- Events
- Queues
- Migrations
- Seeders
- Tests

## Responsabilidades

- criar endpoints REST
- implementar regras de negócio
- manter controllers finos
- usar Services e Actions
- validar dados de entrada
- proteger rotas autenticadas
- aplicar isolamento por empresa

## Padrões esperados

```txt
Controller → Request → Action/Service → Model/Repository → Resource
```

---

# Skill 2 — Multi-tenancy

## Objetivo

Garantir separação total dos dados entre empresas.

## Conhecimentos necessários

- escopo por company_id
- middleware de empresa atual
- policies
- autorização
- queries seguras

## Responsabilidades

- nunca confiar em company_id enviado pelo frontend
- sempre usar empresa do usuário autenticado
- impedir vazamento de dados entre empresas
- criar testes de isolamento

## Regra principal

Toda entidade de negócio deve pertencer a uma empresa.

```txt
company_id obrigatório
```

Exceto entidades globais do sistema.

---

# Skill 3 — Integração Evolution Go

## Objetivo

Integrar a API Laravel com o Evolution Go.

## Conhecimentos necessários

- APIs REST
- autenticação via API key
- webhooks
- QR Code
- envio de mensagens
- status de conexão

## Responsabilidades

- criar EvolutionClient
- criar instância WhatsApp
- buscar QR Code
- enviar mensagem
- consultar status
- desconectar instância
- tratar falhas externas
- criar retries quando necessário

## Regra

Evolution Go é gateway. Não colocar regra de negócio nele.

---

# Skill 4 — Webhooks

## Objetivo

Processar eventos enviados pelo Evolution Go.

## Conhecimentos necessários

- endpoints públicos seguros
- validação de assinatura/token
- idempotência
- logs
- normalização de payload

## Responsabilidades

- receber mensagens
- identificar instância
- identificar empresa
- identificar setor
- criar contato
- criar ou atualizar conversa
- salvar mensagem
- evitar duplicidade
- disparar eventos em tempo real

## Regra

Webhook deve responder rápido.

Processamentos pesados devem ir para fila/job.

---

# Skill 5 — Banco de Dados PostgreSQL

## Objetivo

Modelar e otimizar o banco de dados.

## Conhecimentos necessários

- migrations
- relacionamentos
- índices
- constraints
- JSONB
- paginação
- consultas otimizadas

## Responsabilidades

- criar tabelas consistentes
- usar índices em chaves importantes
- evitar N+1
- garantir integridade referencial
- projetar dados multi-tenant

## Tabelas principais

```txt
companies
users
company_users
sectors
sector_users
whatsapp_instances
contacts
conversations
messages
tags
bot_flows
campaigns
pipelines
deals
```

---

# Skill 6 — Redis, Filas e Jobs

## Objetivo

Processar tarefas assíncronas e melhorar performance.

## Conhecimentos necessários

- Redis
- Laravel Queues
- Jobs
- retries
- backoff
- rate limit
- locks

## Responsabilidades

- processar mensagens recebidas
- disparar campanhas
- executar tarefas de IA
- evitar duplicidade
- controlar volume de envio

## Usos principais

```txt
ProcessIncomingMessageJob
SendCampaignMessageJob
GenerateConversationSummaryJob
SyncWhatsappStatusJob
```

---

# Skill 7 — Tempo Real

## Objetivo

Atualizar o atendimento em tempo real.

## Conhecimentos necessários

- Laravel Reverb
- WebSocket
- broadcasting
- eventos frontend
- canais privados

## Responsabilidades

- emitir eventos de mensagem recebida
- atualizar conversas
- atualizar filas
- atualizar status de instância
- proteger canais por empresa

## Eventos

```txt
MessageReceived
ConversationUpdated
ConversationAssigned
InstanceStatusChanged
```

---

# Skill 8 — Frontend React

## Objetivo

Criar interface web do SaaS.

## Conhecimentos necessários

- React
- TypeScript
- Vite
- Tailwind
- React Router
- Axios
- Zustand
- componentes reutilizáveis

## Responsabilidades

- criar telas
- consumir API
- gerenciar autenticação
- gerenciar empresa atual
- renderizar mensagens em tempo real
- criar experiência parecida com WhatsApp Web

## Regra

Não chamar Axios diretamente nas páginas.

Usar services centralizados.

---

# Skill 9 — UI/UX de Atendimento

## Objetivo

Construir uma experiência eficiente para atendentes.

## Conhecimentos necessários

- layout de chat
- listas em tempo real
- estados de carregamento
- filtros
- responsividade

## Responsabilidades

- tela de atendimento
- lista de conversas
- painel de mensagens
- painel do contato
- filtros por setor/status/tag
- notificações
- UX rápida e simples

## Referência visual

```txt
WhatsApp Web + CRM leve
```

---

# Skill 10 — Chatbot Simples

## Objetivo

Criar automações simples de atendimento.

## Conhecimentos necessários

- fluxos simples
- menus numéricos
- palavras-chave
- roteamento por setor
- horário de atendimento

## Responsabilidades

- mensagem de boas-vindas
- opções numéricas
- respostas automáticas
- transferir conversa para setor
- abrir atendimento humano

## Limite do MVP

Não criar construtor visual complexo no início.

---

# Skill 11 — IA Leve

## Objetivo

Adicionar recursos simples de inteligência artificial.

## Conhecimentos necessários

- OpenAI API ou provider similar
- prompts
- classificação de intenção
- resumo de conversa
- sugestão de resposta
- controle de custo

## Responsabilidades

- criar AiProviderInterface
- criar provider OpenAI
- criar provider fake para testes
- registrar uso
- limitar consumo por empresa

## Funcionalidades

```txt
Resumo da conversa
Sugestão de resposta
Classificação de intenção
```

---

# Skill 12 — Campanhas WhatsApp

## Objetivo

Criar envio controlado de campanhas.

## Conhecimentos necessários

- filas
- agendamento
- rate limit
- status de envio
- controle de falhas

## Responsabilidades

- criar campanha
- importar contatos
- agendar envio
- pausar campanha
- retomar campanha
- registrar sucesso/falha

## Atenção

Campanhas devem ter controle de velocidade para reduzir risco de bloqueio.

---

# Skill 13 — CRM Leve

## Objetivo

Criar CRM simples integrado ao atendimento.

## Conhecimentos necessários

- pipelines
- estágios
- deals
- kanban
- relacionamento com contatos

## Responsabilidades

- criar pipelines
- criar estágios
- criar negócios
- mover negócios
- vincular contato
- vincular responsável

## Limite do MVP

CRM deve ser simples.

Evitar automações complexas inicialmente.

---

# Skill 14 — Dashboard e Métricas

## Objetivo

Exibir indicadores operacionais.

## Conhecimentos necessários

- consultas agregadas
- métricas por período
- filtros
- gráficos
- performance

## Responsabilidades

- conversas do dia
- mensagens do dia
- conversas abertas
- conversas aguardando
- tempo médio de resposta
- produtividade por atendente
- volume por setor
- números conectados

---

# Skill 15 — Segurança

## Objetivo

Proteger o sistema e os dados dos clientes.

## Conhecimentos necessários

- autenticação
- autorização
- policies
- rate limit
- validação de webhook
- proteção de tokens
- logs

## Responsabilidades

- proteger rotas
- validar permissões
- impedir acesso cruzado entre empresas
- proteger API keys
- nunca expor credenciais
- registrar eventos críticos

---

# Skill 16 — Testes

## Objetivo

Garantir estabilidade do MVP.

## Conhecimentos necessários

- PHPUnit/Pest
- Feature tests
- Unit tests
- testes com HTTP fake
- testes de autorização

## Responsabilidades

- testar multi-tenancy
- testar endpoints críticos
- testar webhook
- testar envio de mensagem mockado
- testar chatbot
- testar campanhas

## Prioridade de testes

```txt
1. Multi-tenant
2. Webhook
3. Conversas
4. Envio de mensagem
5. Campanhas
```

---

# Skill 17 — Docker e DevOps

## Objetivo

Criar ambiente local e base de deploy.

## Conhecimentos necessários

- Docker
- Docker Compose
- Nginx
- PostgreSQL
- Redis
- filas
- variáveis de ambiente

## Responsabilidades

- criar docker-compose
- configurar API
- configurar frontend
- configurar banco
- configurar Redis
- configurar Evolution Go
- documentar setup local

---

# Skill 18 — Documentação Técnica

## Objetivo

Manter documentação clara para desenvolvimento.

## Responsabilidades

- atualizar README
- documentar endpoints
- documentar variáveis de ambiente
- documentar fluxos
- documentar comandos Docker
- documentar padrões de código

Documentação deve ser simples e objetiva.

---

# Skill 19 — Revisão de Código

## Objetivo

Garantir qualidade dos PRs.

## Checklist

- código segue padrão do projeto
- não quebra multi-tenancy
- não expõe dados sensíveis
- tem validações
- tem testes quando necessário
- não cria acoplamento desnecessário
- não duplica lógica
- mantém controllers finos

---

# Skill 20 — Produto e MVP

## Objetivo

Evitar excesso de complexidade no início.

## Regra

Sempre priorizar o MVP.

## O que evitar inicialmente

```txt
omnichannel
voicebot
billing complexo
IA avançada
automações visuais complexas
relatórios enterprise
SLA avançado
```

---

# Ordem de Prioridade das Skills

```txt
1. Backend Laravel API
2. Multi-tenancy
3. Evolution Go
4. Webhooks
5. Conversas
6. Frontend React
7. Tempo Real
8. Multiatendimento
9. Chatbot simples
10. Dashboard
11. Campanhas
12. CRM
13. IA leve
```

---

# Definição de Pronto

Uma tarefa só deve ser considerada pronta quando:

- código foi implementado
- erros óbvios foram tratados
- dados respeitam company_id
- endpoints foram testados
- frontend consome API corretamente
- não há credenciais expostas
- README foi atualizado quando necessário

---

# Objetivo Final

Construir um SaaS:

- modular
- seguro
- multiempresa
- separado em API e frontend
- integrado ao Evolution Go
- pronto para crescer
- simples o suficiente para lançar rápido

