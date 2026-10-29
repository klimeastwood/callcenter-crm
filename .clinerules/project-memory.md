# Krayin CRM Agent Instructions & Project Memory

## 1. Framework Context & Core Rules (From Krayin Team)
- **Stack:** Laravel ^12.0, PHP ^8.3, Pest ^3.0.
- **NEVER modify core Krayin files** unless absolutely necessary. Extend behavior through a custom package under `packages/Webkul/`. 
- **All schema changes go through migrations.** Never edit the database directly.
- **Follow existing module layout:** Providers, Models, Contracts, Repositories, Http/Controllers, Routes, Database/Migrations, Resources/views.
- **Repository pattern:** Models are accessed through repositories bound via contracts in the service provider.
- **Translations:** Follow the rule: a package gets its own `Resources/lang/` only if it has `Resources/views/`. Otherwise, keep strings in Admin package.
- **Every feature updates `CHANGELOG.md`**.

## 2. Our Project Specifics (Crypto Call Center)
- **Goal:** Build a CRM for a crypto-trading call center. 
- **Custom Fields:** Use Krayin's native Custom Attributes system for crypto-specific data (e.g., Wallet Address, Trading Experience) instead of hardcoding DB columns.
- **Interface:** We will heavily modify the UI/UX. Prefer overriding Blade templates and Vue components over core changes.

## 3. AI Agent Memory & Workflow (CRITICAL)
> **Rule #1: Read Context First.**
> Before answering ANY prompt, you MUST read the file `PROJECT_CONTEXT.md` in the project root. It contains the current state of the project, completed tasks, and architecture decisions.

> **Rule #2: Act, Don't Overthink.**
> Minimize internal thinking. Your goal is to output ready-to-use code and use tools (file edits, terminal commands).

> **Rule #3: Update Context After Action.**
> After successfully completing a task or modifying code, you MUST update `PROJECT_CONTEXT.md`. Add a short entry to the "Completed Tasks" section describing what was done.

> **Rule #4: Proactive Improvement.**
> If you know a better, more secure, or more modern way to solve a problem than what the user asked for, DO IT. At the end of your response, add a section **"My Adjustments"** (Мои коррективы) explaining what you changed and why, or why you skipped something as unnecessary.