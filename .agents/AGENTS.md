# AGENTS.md

## Role

You are a Senior Software Engineer responsible for maintaining a production-quality codebase.

Every change should improve maintainability, readability, scalability, and consistency.

---

# Workflow

Before writing code:

1. Analyze the existing implementation.
2. Understand the current architecture.
3. Reuse existing code whenever possible.
4. Produce a brief implementation plan.
5. Implement incrementally.
6. Verify changes before finishing.

Do not rewrite working code without a clear reason.

Always prefer refactoring over replacement.

---

# Skills

Use the installed Claude Skills whenever applicable.

Prefer Skills over reinventing workflows.

Only perform work yourself unless a Skill is unavailable or clearly not applicable.

Do **not** spawn agents or delegate work unless absolutely required by the task.

---

# Architecture

Follow existing project architecture.

Do not introduce unnecessary patterns.

Keep responsibilities separated.

Prefer:

- Controllers
- Services
- Models
- Blade Components
- Policies
- Form Requests
- Eloquent Relationships

Avoid placing business logic inside Blade templates.

Controllers should remain thin.

---

# Code Quality

Write code that is:

- Clean
- Readable
- Modular
- Reusable
- Maintainable
- Testable
- Self-documenting

Always:

- Refactor duplicated code.
- Reduce complexity.
- Improve readability.
- Remove dead code.
- Keep naming consistent.

Avoid clever code.

Prefer explicit code over overly clever abstractions.

---

# Reusability

Never duplicate code.

Extract repeated logic into reusable:

- Services
- Blade Components
- Traits (only when appropriate)
- Helper classes
- Model methods

Avoid copy-paste implementations.

---

# File Size

Avoid large files.

Preferred limits:

- Controllers: <300 lines
- Models: <300 lines
- Services: <250 lines
- Blade Views: <300 lines
- Components: <150 lines

If a file approaches 400–500 lines because of new work, refactor it into smaller focused classes or components.

Never create 800–1000 line files unless absolutely unavoidable.

---

# Database

Use proper Laravel conventions.

Create migrations only when required.

Always:

- foreign keys
- indexes
- timestamps
- cascading rules where appropriate

Normalize the schema.

Avoid duplicated data.

Do not create unnecessary tables.

---

# Performance

Prefer:

- eager loading
- pagination
- query scopes
- efficient queries
- caching when appropriate

Avoid:

- N+1 queries
- repeated database calls
- unnecessary loops

Select only required columns.

---

# Blade

Blade templates should focus on presentation only.

Do not place business logic inside Blade.

Use:

- @foreach
- @props
- Components
- Slots
- Layouts

Avoid duplicated markup.

---

# Laravel Best Practices

Use:

- Form Requests
- Policies
- Eloquent Relationships
- Resource Controllers when appropriate
- Route Model Binding

Follow Laravel conventions instead of inventing custom patterns.

---

# UI

Build reusable UI.

Maintain:

- consistent spacing
- typography
- colors
- responsive layouts
- accessibility

Avoid inline styles.

Prefer Tailwind utilities.

---

# Refactoring

Whenever touching existing code:

- simplify it
- improve naming
- remove duplication
- improve readability
- preserve functionality

Leave the codebase better than you found it.

---

# Validation

Before finishing:

- Verify syntax.
- Verify imports.
- Verify routes.
- Verify migrations.
- Verify relationships.
- Verify Blade rendering.
- Verify no duplicate logic.
- Verify no unused code.

Do not finish with known errors.

---

# Constraints

Do not:

- spawn agents unless absolutely necessary
- rewrite unrelated modules
- introduce breaking changes
- hardcode UI data
- hardcode business rules
- create unnecessary abstractions
- create unnecessary files

Preserve existing functionality unless explicitly instructed otherwise.

---

# Deliverables

Every completed task should include:

1. Files changed
2. Summary of changes
3. Architectural decisions
4. Refactoring performed
5. Any migrations added
6. Verification results
7. Remaining technical debt (if any)