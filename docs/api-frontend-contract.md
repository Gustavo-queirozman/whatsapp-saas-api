# Contrato API x Frontend

Documento de contrato entre backend Laravel e frontend React, baseado no codigo atual da API em `2026-05-13`.

## Escopo

- Base versionada: `/api/v1`
- Autenticacao: Sanctum com bearer token
- Multi-tenancy: empresa atual resolvida no backend via `X-Company-Id`
- Webhook Evolution: `/api/webhooks/evolution` nao faz parte do consumo do frontend

## Regras gerais do contrato

### Base URL

Exemplo local:

```txt
http://127.0.0.1:8000/api/v1
```

### Headers padrao

Para endpoints autenticados:

```http
Authorization: Bearer {access_token}
Accept: application/json
X-Company-Id: {company_id}
```

### Envelope padrao de sucesso

A maior parte da API responde no formato:

```json
{
  "success": true,
  "data": {}
}
```

Excecao atual:

- `GET /health` retorna payload simples de healthcheck, sem `success` e sem `data`

### Autenticacao

#### Login

`POST /auth/login`

Request:

```json
{
  "email": "admin@local.test",
  "password": "password",
  "device_name": "web"
}
```

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "token_type": "Bearer",
    "access_token": "1|sanctum_token_exemplo",
    "user": {
      "id": 1,
      "name": "Administrador",
      "email": "admin@local.test",
      "status": "active",
      "email_verified_at": null,
      "current_company": {
        "id": 1,
        "name": "Acme Support",
        "slug": "acme-support",
        "status": "active",
        "is_current": true,
        "created_at": "2026-05-13T10:00:00+00:00"
      },
      "companies": [
        {
          "id": 1,
          "name": "Acme Support",
          "slug": "acme-support",
          "status": "active",
          "is_current": true,
          "created_at": "2026-05-13T10:00:00+00:00"
        }
      ],
      "created_at": "2026-05-13T10:00:00+00:00"
    }
  }
}
```

#### Perfil autenticado

`GET /auth/me`

- exige `Authorization`
- usa `X-Company-Id` para carregar `data.current_company`

#### Logout

`POST /auth/logout`

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "message": "Sessao encerrada com sucesso."
  }
}
```

### Empresa atual

O frontend nunca envia `company_id` como fonte de verdade. A empresa atual vem do contexto autenticado.

Regras atuais:

- o middleware `current.company` le o header `X-Company-Id`
- se o header nao for enviado, a API usa a primeira empresa ativa do usuario
- se o header apontar para uma empresa fora do vinculo do usuario, a API retorna `403`
- o backend ignora `company_id` enviado no body quando a entidade depende de tenant

Exemplo:

```http
X-Company-Id: 2
```

Erro quando a empresa nao pertence ao usuario:

```json
{
  "success": false,
  "message": "Acesso negado para a empresa informada."
}
```

### Paginacao

Estado atual do backend:

- nenhum endpoint de listagem em `/api/v1` usa `paginate()`, `simplePaginate()` ou `cursorPaginate()`
- todas as listagens atuais retornam colecoes completas em `data`
- nao existem chaves `meta`, `links`, `current_page`, `per_page` ou `total` no contrato atual

Implicacao para o frontend:

- tratar listagens como arrays simples
- nao assumir paginacao ate que a API introduza isso explicitamente em uma nova revisao do contrato

### Padrao de erros

#### 401 nao autenticado

Endpoints protegidos por `auth:sanctum` seguem o padrao default do Laravel para requisicoes JSON:

```json
{
  "message": "Unauthenticated."
}
```

#### 401 webhook invalido

Uso interno do webhook Evolution:

```json
{
  "success": false,
  "message": "Token de webhook invalido."
}
```

#### 403 empresa invalida

```json
{
  "success": false,
  "message": "Acesso negado para a empresa informada."
}
```

#### 403 sem permissao de policy

Quando a policy bloqueia o acesso, a API usa o padrao JSON do Laravel para autorizacao.

Exemplo esperado:

```json
{
  "message": "This action is unauthorized."
}
```

#### 404 recurso fora da empresa atual

Em recursos com binding multi-tenant, o comportamento observado e `404 Not Found`.

Exemplo:

```json
{
  "message": "No query results for model [App\\\\Domain\\\\Tags\\\\Models\\\\Tag] 999"
}
```

#### 422 validacao

Validacoes de `FormRequest` seguem o padrao default do Laravel:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "sector_id": [
      "The selected sector id is invalid."
    ]
  }
}
```

#### 422 regra de negocio controlada

Algumas acoes bloqueiam operacoes com resposta de validacao `422`.

Exemplo esperado:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "pipeline_stage": [
      "Nao e possivel remover um estagio com deals vinculados."
    ]
  }
}
```

## Lista de endpoints

Observacao:

- a tabela abaixo documenta o contrato principal para o frontend em `/api/v1`
- existe um alias legado em `GET /api/dashboard/overview`, mas o frontend deve consumir `GET /api/v1/dashboard/overview`

### System

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/health` | Nao | Healthcheck da API |

### Auth

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| POST | `/auth/login` | Nao | Login e emissao de token |
| GET | `/auth/me` | Sim | Perfil autenticado e empresa atual |
| POST | `/auth/logout` | Sim | Revoga token atual |

### Companies

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/companies` | Sim | Lista empresas ativas do usuario |

### Dashboard

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/dashboard/overview` | Sim | Resumo operacional da empresa atual |

### WhatsApp

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/whatsapp-instances` | Sim | Lista instancias |
| POST | `/whatsapp-instances` | Sim | Cria instancia |
| GET | `/whatsapp-instances/{id}` | Sim | Detalha instancia |
| GET | `/whatsapp-instances/{id}/qrcode` | Sim | Busca QR code e sincroniza status |
| POST | `/whatsapp-instances/{id}/disconnect` | Sim | Desconecta instancia |
| DELETE | `/whatsapp-instances/{id}` | Sim | Remove instancia |

### Conversations

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/attendants` | Sim | Lista atendentes da empresa |
| GET | `/queue` | Sim | Fila geral de conversas em espera |
| GET | `/conversations` | Sim | Lista conversas |
| GET | `/conversations/{id}` | Sim | Detalha conversa |
| GET | `/conversations/{id}/messages` | Sim | Lista mensagens da conversa |
| POST | `/conversations/{id}/assign-me` | Sim | Atribui conversa ao usuario logado |
| POST | `/conversations/{id}/assign-user` | Sim | Atribui conversa a outro usuario |
| POST | `/conversations/{id}/auto-assign` | Sim | Autoatribuicao por carga |
| POST | `/conversations/{id}/transfer-sector` | Sim | Transfere conversa de setor |
| POST | `/conversations/{id}/send-message` | Sim | Envia mensagem outbound |
| POST | `/conversations/{id}/close` | Sim | Fecha conversa |
| POST | `/conversations/{id}/reopen` | Sim | Reabre conversa |

### Tags

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/tags` | Sim | Lista tags |
| POST | `/tags` | Sim | Cria tag |
| GET | `/tags/{id}` | Sim | Detalha tag |
| PUT/PATCH | `/tags/{id}` | Sim | Atualiza tag |
| DELETE | `/tags/{id}` | Sim | Remove tag |
| POST | `/contacts/{contact}/tags` | Sim | Vincula tag ao contato |
| DELETE | `/contacts/{contact}/tags/{tag}` | Sim | Remove tag do contato |
| POST | `/conversations/{conversation}/tags` | Sim | Vincula tag a conversa |
| DELETE | `/conversations/{conversation}/tags/{tag}` | Sim | Remove tag da conversa |

### Sectors

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/sectors` | Sim | Lista setores |
| POST | `/sectors` | Sim | Cria setor |
| GET | `/sectors/{id}` | Sim | Detalha setor |
| PUT/PATCH | `/sectors/{id}` | Sim | Atualiza setor |
| DELETE | `/sectors/{id}` | Sim | Remove setor |
| POST | `/sectors/{id}/users` | Sim | Vincula usuario ao setor |
| DELETE | `/sectors/{id}/users/{userId}` | Sim | Remove usuario do setor |
| GET | `/sectors/{id}/queue` | Sim | Fila de espera do setor |

### Chatbot

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/bot-flows` | Sim | Lista fluxos |
| POST | `/bot-flows` | Sim | Cria fluxo |
| GET | `/bot-flows/{id}` | Sim | Detalha fluxo |
| PUT | `/bot-flows/{id}` | Sim | Atualiza fluxo |
| DELETE | `/bot-flows/{id}` | Sim | Remove fluxo |

### AI

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| POST | `/conversations/{id}/ai/summary` | Sim | Gera resumo da conversa |
| POST | `/conversations/{id}/ai/suggest-reply` | Sim | Sugere resposta |
| POST | `/messages/{id}/ai/classify-intent` | Sim | Classifica intencao da mensagem |

### Campaigns

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/campaigns` | Sim | Lista campanhas |
| POST | `/campaigns` | Sim | Cria campanha |
| GET | `/campaigns/{id}` | Sim | Detalha campanha |
| PUT | `/campaigns/{id}` | Sim | Atualiza campanha |
| DELETE | `/campaigns/{id}` | Sim | Remove campanha |
| POST | `/campaigns/{id}/contacts` | Sim | Importa contatos da campanha |
| POST | `/campaigns/{id}/schedule` | Sim | Agenda ou inicia disparo |
| POST | `/campaigns/{id}/pause` | Sim | Pausa campanha |
| POST | `/campaigns/{id}/resume` | Sim | Retoma campanha |

### CRM

| Metodo | Endpoint | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/pipelines` | Sim | Lista pipelines |
| POST | `/pipelines` | Sim | Cria pipeline |
| GET | `/pipelines/{id}` | Sim | Detalha pipeline |
| PUT/PATCH | `/pipelines/{id}` | Sim | Atualiza pipeline |
| DELETE | `/pipelines/{id}` | Sim | Remove pipeline |
| GET | `/pipeline-stages` | Sim | Lista estagios |
| POST | `/pipeline-stages` | Sim | Cria estagio |
| GET | `/pipeline-stages/{id}` | Sim | Detalha estagio |
| PUT/PATCH | `/pipeline-stages/{id}` | Sim | Atualiza estagio |
| DELETE | `/pipeline-stages/{id}` | Sim | Remove estagio |
| GET | `/deals` | Sim | Lista deals |
| POST | `/deals` | Sim | Cria deal |
| GET | `/deals/{id}` | Sim | Detalha deal |
| PUT/PATCH | `/deals/{id}` | Sim | Atualiza deal |
| DELETE | `/deals/{id}` | Sim | Remove deal |
| POST | `/deals/{id}/move-stage` | Sim | Move deal para outro estagio |

## Exemplos de request e response por modulo

### System

#### `GET /health`

Response `200 OK`:

```json
{
  "status": "ok",
  "service": "Laravel",
  "environment": "local",
  "timestamp": "2026-05-13T13:30:00+00:00"
}
```

### Companies

#### `GET /companies`

Response `200 OK`:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Acme Support",
      "slug": "acme-support",
      "status": "active",
      "is_current": true,
      "created_at": "2026-05-13T10:00:00+00:00"
    }
  ]
}
```

### WhatsApp

#### `POST /whatsapp-instances`

Request:

```json
{
  "sector_id": 5,
  "instance_name": "acme_suporte",
  "phone_number": "5511999999999",
  "metadata": {
    "label": "Canal principal"
  }
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 10,
    "company_id": 1,
    "sector_id": 5,
    "instance_name": "acme_suporte",
    "phone_number": "5511999999999",
    "status": "connecting",
    "last_connection_at": null,
    "metadata": {
      "label": "Canal principal"
    },
    "sector": {
      "id": 5,
      "name": "Suporte",
      "slug": "suporte"
    },
    "created_at": "2026-05-13T13:30:00+00:00",
    "updated_at": "2026-05-13T13:30:00+00:00"
  }
}
```

#### `GET /whatsapp-instances/{id}/qrcode`

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "instance": {
      "id": 10,
      "company_id": 1,
      "sector_id": 5,
      "instance_name": "acme_suporte",
      "phone_number": "5511999999999",
      "status": "connected",
      "last_connection_at": "2026-05-13T13:32:00+00:00",
      "metadata": {},
      "sector": {
        "id": 5,
        "name": "Suporte",
        "slug": "suporte"
      },
      "created_at": "2026-05-13T13:30:00+00:00",
      "updated_at": "2026-05-13T13:32:00+00:00"
    },
    "qrcode": {
      "pairingCode": "ABC123",
      "code": "base64-qr-code"
    },
    "status": "connected"
  }
}
```

### Conversations

#### Filtros de `GET /conversations`

Query params suportados:

- `status`
- `sector_id`
- `whatsapp_instance_id`
- `contact_id`
- `assigned_user_id`
- `search`

Exemplo:

```http
GET /api/v1/conversations?status=waiting&search=Maria
```

Response `200 OK`:

```json
{
  "success": true,
  "data": [
    {
      "id": 200,
      "company_id": 1,
      "sector_id": 5,
      "whatsapp_instance_id": 10,
      "contact_id": 80,
      "assigned_user_id": null,
      "status": "waiting",
      "assigned_at": null,
      "closed_at": null,
      "last_message_at": "2026-05-13T13:00:00+00:00",
      "messages_count": 1,
      "contact": {
        "id": 80,
        "name": "Maria Silva",
        "phone": "5511999990001",
        "avatar_url": null,
        "metadata": {}
      },
      "sector": {
        "id": 5,
        "name": "Suporte",
        "slug": "suporte",
        "color": "#2563EB"
      },
      "whatsapp_instance": {
        "id": 10,
        "instance_name": "acme_suporte",
        "phone_number": "5511999999999",
        "status": "connected"
      },
      "assigned_user": null,
      "tags": [],
      "created_at": "2026-05-13T12:59:00+00:00",
      "updated_at": "2026-05-13T13:00:00+00:00"
    }
  ]
}
```

#### `POST /conversations/{id}/send-message`

Request:

```json
{
  "body": "Retornando seu atendimento.",
  "options": {
    "delay": 250
  }
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "conversation": {
      "id": 200,
      "company_id": 1,
      "sector_id": 5,
      "whatsapp_instance_id": 10,
      "contact_id": 80,
      "assigned_user_id": 7,
      "status": "open",
      "assigned_at": "2026-05-13T13:35:00+00:00",
      "closed_at": null,
      "last_message_at": "2026-05-13T13:40:00+00:00",
      "messages_count": 5,
      "contact": {
        "id": 80,
        "name": "Carlos Souza",
        "phone": "5511999991111",
        "avatar_url": null,
        "metadata": {}
      },
      "sector": {
        "id": 5,
        "name": "Suporte",
        "slug": "suporte",
        "color": "#2563EB"
      },
      "whatsapp_instance": {
        "id": 10,
        "instance_name": "canal_suporte",
        "phone_number": "5511888881111",
        "status": "connected"
      },
      "assigned_user": {
        "id": 7,
        "name": "Agente 01",
        "email": "agente01@local.test"
      },
      "tags": [],
      "created_at": "2026-05-13T12:00:00+00:00",
      "updated_at": "2026-05-13T13:40:00+00:00"
    },
    "message": {
      "id": 999,
      "company_id": 1,
      "conversation_id": 200,
      "direction": "outbound",
      "type": "text",
      "external_id": "outbound-msg-001",
      "body": "Retornando seu atendimento.",
      "payload": {},
      "sent_at": "2026-05-13T13:40:00+00:00",
      "delivered_at": null,
      "read_at": null,
      "created_at": "2026-05-13T13:40:00+00:00",
      "updated_at": "2026-05-13T13:40:00+00:00"
    }
  }
}
```

#### `GET /queue`

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "summary": {
      "waiting": 3,
      "open": 7,
      "closed": 1
    },
    "conversations": []
  }
}
```

### Tags

#### `POST /tags`

Request:

```json
{
  "name": "Urgente",
  "color": "#F97316"
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 30,
    "company_id": 1,
    "name": "Urgente",
    "color": "#F97316",
    "created_at": "2026-05-13T13:45:00+00:00",
    "updated_at": "2026-05-13T13:45:00+00:00"
  }
}
```

#### `POST /conversations/{conversation}/tags`

Request:

```json
{
  "tag_id": 30
}
```

Response `200 OK`:

```json
{
  "success": true,
  "data": [
    {
      "id": 30,
      "company_id": 1,
      "name": "Urgente",
      "color": "#F97316",
      "created_at": "2026-05-13T13:45:00+00:00",
      "updated_at": "2026-05-13T13:45:00+00:00"
    }
  ]
}
```

### Sectors

#### `POST /sectors`

Request:

```json
{
  "name": "Financeiro",
  "slug": "financeiro",
  "color": "#F97316",
  "settings": {
    "auto_assign": true
  }
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 5,
    "company_id": 1,
    "name": "Financeiro",
    "slug": "financeiro",
    "color": "#F97316",
    "settings": {
      "auto_assign": true
    },
    "users_count": 0,
    "created_at": "2026-05-13T13:50:00+00:00",
    "updated_at": "2026-05-13T13:50:00+00:00"
  }
}
```

#### `POST /sectors/{id}/users`

Request:

```json
{
  "user_id": 7
}
```

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "id": 5,
    "company_id": 1,
    "name": "Financeiro",
    "slug": "financeiro",
    "color": "#F97316",
    "settings": {},
    "users_count": 1,
    "users": [
      {
        "id": 7,
        "name": "Agente 01",
        "email": "agente01@local.test"
      }
    ],
    "created_at": "2026-05-13T13:50:00+00:00",
    "updated_at": "2026-05-13T13:55:00+00:00"
  }
}
```

### Chatbot

#### `POST /bot-flows`

Request:

```json
{
  "sector_id": 5,
  "name": "Fluxo principal",
  "is_active": true,
  "welcome_message": "Ola! Escolha uma opcao.",
  "menu_message": "1. Suporte\n2. Financeiro",
  "invalid_option_message": "Opcao invalida.",
  "office_hours_enabled": false,
  "settings": {},
  "options": [
    {
      "label": "Atendimento",
      "number": "1",
      "keywords": [
        "atendimento",
        "suporte"
      ],
      "action": "reply",
      "response_message": "Vamos continuar por aqui."
    },
    {
      "label": "Financeiro",
      "number": "2",
      "keywords": [
        "financeiro"
      ],
      "action": "transfer_sector",
      "target_sector_id": 9,
      "response_message": "Encaminhando para o financeiro."
    }
  ]
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 40,
    "company_id": 1,
    "sector_id": 5,
    "name": "Fluxo principal",
    "is_active": true,
    "welcome_message": "Ola! Escolha uma opcao.",
    "menu_message": "1. Suporte\n2. Financeiro",
    "invalid_option_message": "Opcao invalida.",
    "out_of_hours_message": null,
    "office_hours_enabled": false,
    "office_hours_timezone": null,
    "office_hours": {},
    "settings": {},
    "sector": {
      "id": 5,
      "name": "Suporte",
      "slug": "suporte",
      "color": "#2563EB"
    },
    "options": [
      {
        "id": 100,
        "company_id": 1,
        "bot_flow_id": 40,
        "target_sector_id": null,
        "label": "Atendimento",
        "number": "1",
        "keywords": [
          "atendimento",
          "suporte"
        ],
        "action": "reply",
        "response_message": "Vamos continuar por aqui.",
        "sort_order": 0,
        "is_active": true,
        "settings": {},
        "target_sector": null,
        "created_at": "2026-05-13T14:00:00+00:00",
        "updated_at": "2026-05-13T14:00:00+00:00"
      }
    ],
    "created_at": "2026-05-13T14:00:00+00:00",
    "updated_at": "2026-05-13T14:00:00+00:00"
  }
}
```

#### Regras importantes de validacao em chatbot

- cada opcao precisa de `number` ou ao menos uma `keyword`
- `number` nao pode repetir dentro do mesmo fluxo
- `keywords` nao podem repetir dentro do mesmo fluxo
- `action=transfer_sector` exige `target_sector_id`
- `office_hours_enabled=true` exige horarios validos por dia ativo

### AI

#### `POST /conversations/{id}/ai/summary`

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "summary": "Cliente relata problema de acesso apos troca de senha.",
    "usage": {
      "id": 15,
      "provider": "fake",
      "model": "fake-summary-v1",
      "prompt_tokens": 120,
      "completion_tokens": 40,
      "total_tokens": 160
    }
  }
}
```

#### `POST /messages/{id}/ai/classify-intent`

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "intent": "financeiro",
    "usage": {
      "id": 16,
      "provider": "fake",
      "model": "fake-classifier-v1",
      "prompt_tokens": 45,
      "completion_tokens": 12,
      "total_tokens": 57
    }
  }
}
```

### Campaigns

#### `POST /campaigns`

Request:

```json
{
  "whatsapp_instance_id": 10,
  "name": "Campanha Nova",
  "message": "Oferta ativa",
  "send_limit_per_minute": 12
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 60,
    "company_id": 1,
    "whatsapp_instance_id": 10,
    "name": "Campanha Nova",
    "message": "Oferta ativa",
    "send_limit_per_minute": 12,
    "status": "draft",
    "scheduled_at": null,
    "started_at": null,
    "paused_at": null,
    "finished_at": null,
    "total_contacts": 0,
    "pending_contacts": 0,
    "processing_contacts": 0,
    "success_contacts": 0,
    "failed_contacts": 0,
    "processed_contacts": 0,
    "whatsapp_instance": {
      "id": 10,
      "instance_name": "campaign_instance",
      "status": "connected",
      "phone_number": "5511888888888"
    },
    "created_at": "2026-05-13T14:10:00+00:00",
    "updated_at": "2026-05-13T14:10:00+00:00"
  }
}
```

#### `POST /campaigns/{id}/contacts`

Request:

```json
{
  "contacts": [
    {
      "name": "Maria",
      "phone": "+55 (11) 99999-0001"
    },
    {
      "name": "Joao",
      "phone": "5511999990002"
    }
  ]
}
```

Response `200 OK`:

```json
{
  "success": true,
  "data": [
    {
      "id": 501,
      "campaign_id": 60,
      "company_id": 1,
      "name": "Maria",
      "phone": "5511999990001",
      "status": "pending",
      "error_message": null,
      "last_attempt_at": null,
      "sent_at": null,
      "failed_at": null,
      "created_at": "2026-05-13T14:11:00+00:00",
      "updated_at": "2026-05-13T14:11:00+00:00"
    }
  ]
}
```

#### `POST /campaigns/{id}/schedule`

Request para agendar:

```json
{
  "scheduled_at": "2026-05-13T15:00:00-03:00"
}
```

Request para iniciar imediatamente:

```json
{}
```

### CRM

#### `POST /pipelines`

Request:

```json
{
  "name": "Comercial",
  "description": "Pipeline principal"
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 70,
    "company_id": 1,
    "name": "Comercial",
    "description": "Pipeline principal",
    "stages_count": 0,
    "deals_count": 0,
    "stages": [],
    "created_at": "2026-05-13T14:20:00+00:00",
    "updated_at": "2026-05-13T14:20:00+00:00"
  }
}
```

#### `POST /pipeline-stages`

Request:

```json
{
  "pipeline_id": 70,
  "name": "Entrada",
  "color": "#2563EB",
  "position": 1
}
```

#### `POST /deals`

Request:

```json
{
  "pipeline_id": 70,
  "pipeline_stage_id": 71,
  "contact_id": 80,
  "assigned_user_id": 7,
  "title": "Cliente CRM - fechamento",
  "value": 2450.75,
  "notes": "Originado do contato CRM"
}
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "id": 90,
    "company_id": 1,
    "pipeline_id": 70,
    "pipeline_stage_id": 71,
    "contact_id": 80,
    "assigned_user_id": 7,
    "title": "Cliente CRM - fechamento",
    "value": "2450.75",
    "notes": "Originado do contato CRM",
    "pipeline": {
      "id": 70,
      "name": "Comercial"
    },
    "stage": {
      "id": 71,
      "pipeline_id": 70,
      "name": "Entrada",
      "color": "#2563EB",
      "position": 1
    },
    "contact": {
      "id": 80,
      "name": "Cliente CRM",
      "phone": "5511999997001"
    },
    "assigned_user": {
      "id": 7,
      "name": "Responsavel CRM",
      "email": "crm@local.test"
    },
    "created_at": "2026-05-13T14:25:00+00:00",
    "updated_at": "2026-05-13T14:25:00+00:00"
  }
}
```

#### `POST /deals/{id}/move-stage`

Request:

```json
{
  "pipeline_stage_id": 72
}
```

### Dashboard

#### `GET /dashboard/overview`

Response `200 OK`:

```json
{
  "success": true,
  "data": {
    "conversations_today": 2,
    "messages_today": 3,
    "open_conversations": 2,
    "waiting_conversations": 1,
    "closed_conversations": 1,
    "average_first_response_time": {
      "seconds": 300,
      "formatted": "00:05:00",
      "conversations_count": 1
    },
    "conversations_by_sector": [
      {
        "sector_id": 5,
        "sector_name": "Suporte",
        "sector_slug": "support-dashboard",
        "total_conversations": 2,
        "open_conversations": 1,
        "waiting_conversations": 0,
        "closed_conversations": 1
      }
    ],
    "conversations_by_attendant": [
      {
        "user_id": 7,
        "user_name": "Atendente Um",
        "total_conversations": 2,
        "open_conversations": 2,
        "waiting_conversations": 0,
        "closed_conversations": 0
      }
    ],
    "connected_numbers": 1
  }
}
```

## Filtros suportados por listagens

### `/whatsapp-instances`

- `sector_id`
- `status`

### `/conversations`

- `status`
- `sector_id`
- `whatsapp_instance_id`
- `contact_id`
- `assigned_user_id`
- `search`

### `/queue`

- `sector_id`

### `/tags`

- `search`

### `/sectors`

- `search`

### `/bot-flows`

- `sector_id`
- `is_active`

### `/campaigns`

- `status`
- `search`

### `/pipelines`

- `search`

### `/pipeline-stages`

- `pipeline_id`
- `search`

### `/deals`

- `pipeline_id`
- `pipeline_stage_id`
- `contact_id`
- `assigned_user_id`
- `search`

## Valores de referencia usados no frontend

### Conversation status

- `waiting`
- `open`
- `closed`

### Message direction

- `inbound`
- `outbound`

### Message type

- `text`

### Campaign status

- `draft`
- `scheduled`
- `running`
- `paused`
- `finished`

### Campaign contact status

- `pending`
- `processing`
- `success`
- `failed`

### Bot option action

- `reply`
- `transfer_sector`
- `open_queue`

## Observacoes finais para o frontend

- enviar sempre `Accept: application/json`
- enviar sempre `X-Company-Id` apos o usuario selecionar a empresa ativa
- nao montar regras de tenant no frontend; o backend decide a empresa atual
- nao assumir paginacao nem campo `meta` nas listagens atuais
- tratar `404` como recurso inexistente ou inacessivel dentro da empresa atual
- tratar `422` com leitura direta de `errors`
- consumir a rota versionada `/api/v1/dashboard/overview`, nao o alias legado `/api/dashboard/overview`
