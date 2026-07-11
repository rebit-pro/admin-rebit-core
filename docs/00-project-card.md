# Project Card: ReBit Admin Core

## Confirmed facts

- Product: universal admin panel.
- Working product name: ReBit Admin Core.
- Project slug: `rebit-admin-core`.
- Backend framework: Slim 4.
- Runtime: PHP 8.5.
- Environment: Docker Compose for local development.
- Production compose: not created at this stage.

## Assumptions

- The first deliverable is a backend/API foundation, not a finished admin UI.
- PostgreSQL is the default database for the project.
- Architecture will follow ReBit Slim standards: thin HTTP actions, business logic outside controllers, strict typing, CQRS-ready module structure.

## Discovery questions for the next step

- Which universal admin modules are MVP: users, roles, permissions, audit log, settings, files, notifications?
- Will the admin panel be standalone SaaS, reusable starter, or embedded per-client boilerplate?
- What frontend stack should be paired with this backend: Nuxt/Vue, Vuetify, or another UI kit?
- Which auth model is required: password login, OAuth2/OIDC, Telegram, SSO?
## Frontend transfer

- Source project: `/home/user/rebit-p2p/frontend`.
- Target path: `/home/user/projects/rebit-admin-core/frontend`.
- Stack: Vue 3, Vite, Vuetify, Pinia, Vue Router, TypeScript.
- Transferred capabilities: auth shell, route guards, token storage, GeeTest CAPTCHA integration, mock API mode, first protected dashboard screen.
- Local frontend URL: `http://localhost:5174`.
- Local API URL: `http://localhost:8085`; frontend uses `/api` with Vite proxy.
- Current local mode: `VITE_API_MOCKS_ENABLED=true` until Slim auth endpoints are implemented.
