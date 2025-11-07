# Clean Architecture MVC Skeleton

Layers:
- `src/Framework/` — mini framework (Router, Request, Response, DI, Events).
- `src/App/Domain/` — Entities and Repository interfaces.
- `src/App/Application/` — Use cases (commands/handlers).
- `src/App/Infrastructure/` — Implementations (DB, Auth, etc.).
- `src/App/Presentation/` — HTTP controllers, middleware, views.
- `routes/` — HTTP routes.
- `public/` — Front controller.

## Run (dev)
- `composer dump-autoload`
- `php -S localhost:8000 -t public`
