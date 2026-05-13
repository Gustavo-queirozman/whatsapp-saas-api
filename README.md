# WhatsApp SaaS API

Base inicial de uma API Laravel para um SaaS multi-tenant de atendimento WhatsApp.

## Stack

- Laravel 8 API-only
- PostgreSQL
- Laravel Sanctum
- Estrutura por dominios em `app/Domain`
- Rotas versionadas em `routes/domains`

## Estrutura inicial

- `Auth`: login, logout e perfil autenticado
- `Companies`: companies, memberships e workspaces
- `WhatsApp`: instancias de conexao por workspace
- `Conversations`: contatos, conversas e mensagens
- `System`: health check da API

## Endpoints base

- `GET /api/v1/health`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `GET /api/v1/companies`

## Documentacao

- Contrato API x Frontend: [docs/api-frontend-contract.md](docs/api-frontend-contract.md)

## Instalacao

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## Configuracao do banco

Use PostgreSQL e ajuste o `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=whatsapp_saas_api
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

## Configuracao de frontend separado

Configure as origens do frontend no `.env`:

```env
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000,localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000
```

## Migrations e seed

```bash
php artisan migrate --seed
```

Seed inicial:

- Tenant: `Acme Support`
- Workspace: `Operacao Principal`
- Usuario admin: `admin@local.test`
- Senha: `password`

## Subir a API localmente

```bash
php artisan serve
```

API local:

- `http://127.0.0.1:8000/api/v1/health`

## Observacao de compatibilidade

O projeto foi estruturado em Laravel 8 porque o ambiente local disponivel nesta maquina usa `PHP 7.3.2`. Para migrar para Laravel 10+ ou 11+, primeiro atualize o PHP.
