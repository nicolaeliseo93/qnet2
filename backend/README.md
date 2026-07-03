# Backend

Applicazione **Laravel (PHP)**. Owner: Backend Agent.

Tutto il codice server-side vive esclusivamente qui. Nessun codice frontend in questa cartella.
Il confine con il frontend è il **contratto API**, documentato in [`../docs/api/`](../docs/api/).

## Stato

- ✅ Laravel **13.x** installato (PHP 8.4)
- ✅ **API-only**: livello web rimosso (niente `routes/web.php`, niente Blade/asset pipeline). Solo `routes/api.php` + health check `GET /up`
- ✅ **Laravel Sanctum** 4.x — auth API a token (`HasApiTokens` sul model `User`)
- ✅ **Spatie Permission** 8.x — ruoli e permessi (`HasRoles` sul model `User`)
- ✅ **Spatie Activitylog** 4.x — audit/activity log
- ✅ **Pest** 4.x — framework di test (config + esempi)
- ✅ Database configurato su **MySQL** (`.env.example`)
- ✅ Backend starter allineato al pattern **Laravel Layered Service Architecture**

### Migrazioni incluse (eseguire `php artisan migrate` con DB attivo)

`users` · `cache` · `jobs` · `personal_access_tokens` (Sanctum) · `roles`/`permissions` (Spatie) · `activity_log` (Spatie)

## Stack

Laravel · PHP 8.4 · MySQL · Laravel Sanctum · Spatie Activitylog · Spatie Permission · Queue Jobs · Events & Listeners · Notifications · Policies

> Riferimento: [`../standards/architecture.md`](../standards/architecture.md).

## Layering (vedi `standards/architecture.md`)

```
Request → FormRequest → Controller → Service → Model → Database
```

Pattern di riferimento: **Laravel Layered Service Architecture**.

- **Controller**: sottili (validazione, autorizzazione, chiamata Service, risposta)
- **Service**: tutta la business logic
- **Model**: dominio (relazioni, scope, accessor/mutator)
- **DTO**: contratti espliciti tra i layer applicativi

## Setup locale

Prerequisiti: PHP 8.4+, Composer 2.x, MySQL/MariaDB.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Crea il database `laravel` (o aggiorna DB_* in .env), poi:
php artisan migrate

php artisan serve   # http://127.0.0.1:8000
```

## Testing

Pest · Feature Test · Unit Test

```bash
./vendor/bin/pest          # oppure: php artisan test
```
