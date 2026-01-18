# CLAUDE.md — Project Context for Cursor Agents

This file defines **hard constraints**, not suggestions.

## Project Overview

**Project Name:** Siteground DVD Profiler DB  
**Purpose:**  
A full-stack web application that ingests, stores, and presents DVD Profiler data in a structured database, enabling querying, browsing, and future extensions beyond the original DVD Profiler tooling.

This project is both:
- A **functional application**
- An **experimentation / learning environment**, so clarity, maintainability, and explicitness are preferred over cleverness.

When in doubt, choose best practices and readability over cleverness.

---

## Tech Stack

### Backend
- **Language**: PHP 8.5
- **Framework**: Slim 4
- **Database**: PostgreSQL 16
- **Dependency Injection**: PHP-DI
- **Environment Config**: vlucas/phpdotenv
- **Migrations**: Flyway
- **Containerization**: Docker, Docker Compose
- **Web Server**: Nginx + PHP-FPM
- **Build Automation**: Makefile

### Frontend
- **Planned**: React (not yet implemented)
- When implemented, will use modern functional components with hooks

### Infrastructure
- **Containerization**: Docker, Docker Compose
- Each service runs in its own container (php, nginx, db, flyway)
- Local development works with `docker-compose up` or `make build`

## High-Level Architecture

This is a **containerized, multi-service application**:

### Backend (Current)
- **PHP 8.5 with Slim 4**
- RESTful API
- Clear layering:
  - Controllers (HTTP boundary only)
  - Services (business logic)
  - Repositories (data access via PDO)
  - Domain interfaces (abstraction layer)
  - DTOs (data transfer objects)
- Dependency injection via PHP-DI container
- Prefer explicit DTOs over leaking entities

### Database
- **PostgreSQL 16**
- Schema is intentional and modeled (not "just dump JSON")
- Migrations managed by Flyway. Named according to the following pattern: `V{yyyymmdd_hhmm}__{description}.sql`
- Migrations should be explicit and reversible when possible

### Frontend (Planned)
- React will be implemented later
- Will consume the REST API
- Clear separation between:
  - UI components
  - Data-fetching / API logic
  - Domain models (when applicable)

---

## Project Structure

```
API/
├── public/              # Document root (web server entry point)
│   └── index.php        # Slim 4 app bootstrap
├── src/
│   ├── Bootstrap/       # DI container factory
│   ├── Config/          # Environment/config loader
│   ├── Controller/      # HTTP controllers
│   ├── Service/         # Business logic
│   ├── Domain/          # Repository interfaces
│   ├── Infrastructure/  # PDO factory, repository implementations
│   ├── DTO/             # Data Transfer Objects
│   ├── Mapper/          # Entity to DTO mappers
│   └── Routes/          # Route registration
├── db/migration/        # Flyway migration files
├── docker/              # Docker configuration files
├── bin/                 # Utility scripts (e.g., import_dvdprofiler.php)
├── composer.json        # PHP dependencies
├── docker-compose.yml   # Service definitions
├── Dockerfile           # PHP-FPM container image
├── Makefile             # Development commands
└── phinx.php            # Phinx configuration (if used)
```

## Domain Context

- Source data originates from **DVD Profiler** XML exports
- Data is imported via `bin/import_dvdprofiler.php` script

### Domain Models

#### Core Entities
- **Film**: Movie/title information (title, production_year, original_title, running_time)
- **Edition**: Physical release of a film (UPC, release_date, media_type: DVD/BLURAY/UHD, distributor)
- **EditionDisc**: Disc within an edition (disc_number, role, label, notes)
- **Disc**: Physical disc record (fingerprint, dual_layered, dual_sided)
- **EditionRegion**: Region codes for an edition ('1', '2', 'A', 'B', 'C')
- **AudioTrack**: Audio track details (language, channel_layout, format, is_descriptive)
- **VideoFormat**: Video format details (aspect_ratio, video_standard: NTSC/PAL, color/B&W, 2D/3D)

#### Supporting Entities
- **User**: User management (for future authentication/authorization)

### Database Schema Notes

- Films are identified by `(normalized_title, production_year)` unique constraint
- Editions reference films via `film_id` foreign key
- Editions have unique `external_id` (from DVD Profiler)
- Cascading deletes: deleting a film cascades to editions and related data
- Foreign keys use `ON DELETE CASCADE` or `ON DELETE RESTRICT` as appropriate

Agents should:
- Preserve domain meaning (don't flatten or oversimplify prematurely)
- Prefer readable, explicit naming over abbreviations
- Avoid introducing "generic" models that erase DVD Profiler semantics
- Maintain referential integrity when modifying schema

---

## Coding Principles

### General
- Favor **clarity over brevity**
- Prefer **explicit code paths** over magic or heavy abstraction
- Avoid speculative features unless explicitly requested
- If something is ambiguous, choose the most conservative, maintainable option

### Backend (PHP/Slim)
- Use constructor injection (via PHP-DI)
- Keep controllers thin (HTTP boundary only)
- Business logic lives in services
- Repositories should not contain business logic
- Use PDO for database access (via repository implementations)
- Prefer explicit type hints and strict types (`declare(strict_types=1)`)
- Follow PSR-4 autoloading conventions

### Frontend (React)
- Functional components only
- Hooks over class patterns
- Avoid deeply nested component trees when possible
- Prefer small, composable components
- Avoid global state unless clearly justified

### Database
- Avoid premature denormalization
- Prefer correct relational modeling first
- Indices should be intentional, not automatic
- Do not assume small data forever

---

## API Design Expectations

- RESTful conventions
- Predictable, consistent routes
- Proper HTTP status codes
- JSON responses with stable shapes
- Avoid leaking internal entity structures directly

---

## Development Setup

### Prerequisites
- Docker and Docker Compose
- Make (Windows: `choco install make` or `scoop install make`)

**Development Environment**: Windows with Git Bash. Ensure commands work in this environment.

### Environment Configuration
Create `.env` file in `API/` directory:
```env
APP_ENV=development
PG_DB=dvd_collection
PG_USER=postgres
PG_PASSWORD=postgres
```

### Common Make Commands
- `make build` - Build and start all containers
- `make up` / `make down` - Start/stop containers
- `make logs` - Follow logs
- `make psql` - Open PostgreSQL CLI
- `make flyway` - Run Flyway migrations
- `make reset-db` - Recreate database and run migrations

### Local Database Access
- Host: localhost
- Port: 5432
- Database: `dvd_collection` (or value from `PG_DB` env var)
- Username: `postgres` (or value from `PG_USER` env var)

### Access Points
- API: http://localhost:8080/
- Health check: http://localhost:8080/health
- Database health: http://localhost:8080/health/db

### Running Locally (without Docker)
```bash
cd API
composer install
php -S localhost:8000 -t public
```

## Database Migrations

- Managed by Flyway
- Migration files in `db/migration/` follow pattern: `V{timestamp}__{description}.sql`
- Use `make flyway` to run migrations
- Use `make reset-db` to recreate database and run all migrations
- Migrations are numbered sequentially and should be idempotent

## API Endpoints

Current endpoints:
- `GET /` - Redirects to main site
- `GET /health` - API status check
- `GET /health/db` - Database connectivity check
- `GET /users` - List users
- `POST /users` - Create user
- `GET /users/{id}` - Get user by ID
- `PUT /users/{id}` - Update user
- `DELETE /users/{id}` - Delete user
- `GET /films` - List films (with search, pagination)
- `GET /films/{id}` - Get film by ID with editions
- `GET /editions/{id}` - Get edition by ID

## Deployment

- Deployment via GitHub Actions to SiteGround
- Deploys on push to `main` branch
- Uses rsync to transfer files to `~/www/api.mathieulalonde.com/`
- Requires SSH keys configured in GitHub secrets
- See `DEPLOY.md` for detailed deployment instructions

## Testing Philosophy

- Tests are valuable, but not mandatory everywhere
- Focus testing on:
  - Business logic
  - Data transformations
  - Non-trivial edge cases
- Avoid brittle tests tied to implementation details
- Testing infrastructure not yet set up (PHPUnit can be added when needed)

---

## What Agents Should NOT Do

- Do not introduce new frameworks without justification
- Do not refactor large areas unless explicitly asked
- Do not assume production constraints unless stated
- Do not optimize prematurely
- Do not remove “redundant-looking” code without understanding intent

---

## Collaboration Notes for Agents

When suggesting changes:
- Explain *why*, not just *what*
- Prefer incremental improvements
- Call out trade-offs explicitly
- If multiple valid approaches exist, present options

When unsure:
- Ask for clarification **only if necessary**
- Otherwise, choose the safest, most maintainable path and state assumptions

---

## Tone & Interaction Style

- Be direct and technical
- No marketing language
- No hand-waving
- Assume the user is a competent developer
- Optimize for long-term maintainability and understanding

---

## North Star

> This project values **understandability, correctness, and evolution over time** more than speed or novelty.

All suggestions and code should move the project closer to that goal.

---

## When Editing Files

Before making any change, agents must:

1. **Read the entire file first**
   - Do not assume intent from a partial snippet
   - Look for existing patterns, comments, and conventions

2. **Minimize the scope of changes**
   - Change only what is necessary to satisfy the request
   - Avoid opportunistic refactors
   - Do not reformat unrelated code

3. **Preserve existing structure and style**
   - Match existing naming, formatting, and patterns
   - Do not introduce new architectural patterns into an existing file
   - If a file uses a specific approach, follow it unless explicitly told otherwise

4. **Avoid cross-file changes unless required**
   - Do not modify multiple files unless the change *cannot* be done safely in one
   - If multiple files must change, explain why before doing so

5. **Do not silently change behavior**
   - Any behavioral change must be intentional and visible
   - Call out behavior changes explicitly in explanations

6. **Prefer additive changes over destructive ones**
   - Add new methods or fields instead of rewriting existing ones
   - Do not delete code unless explicitly requested or clearly dead

7. **Respect public interfaces**
   - Assume exported methods, controllers, and API responses may be in use
   - Breaking changes require explicit acknowledgment

8. **Keep diffs reviewable**
   - Avoid large rewrites
   - Prefer multiple small, obvious changes over one large change

9. **Explain non-obvious decisions**
   - If a change is not self-evident, include a brief explanation
   - Call out trade-offs where relevant

10. **Leave the codebase clearer than you found it**
    - Improve naming or comments *only* where directly relevant
    - Do not “clean up” unrelated areas

11. **Apply DRY deliberately, not aggressively**
    - Prefer small, local duplication over premature abstraction
    - Extract shared code **only when duplication is intentional and stable**
    - Reuse components/services when behavior and intent truly align
    - Avoid “generic” abstractions created solely to remove duplication
    - When in doubt, leave duplication and note the opportunity instead

Duplication is cheaper than the wrong abstraction.

### DRY Guidance (Frontend)

- Reuse presentational components freely (buttons, tables, layout)
- Be cautious reusing page-level or container components
- Do not extract shared hooks or components unless:
  - The data shape is the same
  - The behavior is the same
  - The future evolution is likely the same
- Duplication across pages is acceptable during early development

### DRY Guidance (Backend)

- Shared business rules should live in services or domain helpers
- Validation logic should not be duplicated across controllers
- Repository logic should not be copy-pasted across entities
- Do not create utility classes that hide business meaning

### Cursor-Specific Rules

- Do not re-run or regenerate entire files unless explicitly instructed
- Do not replace working code with alternative implementations for stylistic reasons
- If a change would affect more than ~30 lines, consider whether the task should be split
- When unsure, prefer a smaller, safer change
