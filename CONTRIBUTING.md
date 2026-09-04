# Contributing to EasySQL PHP & Laravel SDK

Thank you for your interest in contributing to the **EasySQL PHP & Laravel SDK** (`clearsoft/easysql-sdk`)! We appreciate your help in making this library better for everyone.

This document outlines the guidelines for reporting issues, suggesting features, and submitting code contributions.

---

## Code of Conduct

We are committed to providing a welcoming, inclusive, and harassment-free experience for everyone. Please be respectful, constructive, and considerate when interacting with the community and maintainers.

---

## How to Contribute

### 1. Reporting Bugs

If you find a bug:
1. Check the [Issues tracker](https://github.com/Clearsoft-net/easysql-php/issues) to ensure the bug hasn't already been reported.
2. If not reported, open a new issue with:
   - A clear and descriptive title.
   - Steps to reproduce the problem.
   - Expected behavior vs. actual behavior.
   - Your environment (PHP version, Laravel/framework version, OS, package version).
   - Code snippets or minimal reproduction examples when applicable.

### 2. Suggesting Enhancements

Feature requests are always welcome!
- Open an issue describing the proposed feature and why it would be beneficial.
- Provide examples of how the new API or feature would be used in practice.

---

## Development Workflow

### Prerequisites

- [PHP](https://www.php.net/) (version 8.2 or newer)
- [Composer](https://getcomposer.org/) (version 2.x)
- Git

### Setup

1. **Fork the repository** on GitHub: [Clearsoft-net/easysql-php](https://github.com/Clearsoft-net/easysql-php).
2. **Clone your fork**:
   ```bash
   git clone https://github.com/<your-username>/easysql-php.git
   cd easysql-php
   ```
3. **Install dependencies**:
   ```bash
   make install
   # or
   composer install
   ```

### Local Scripts

You can use `make` or run Composer/PHP commands directly:

| Command | Description |
|---|---|
| `make lint` | Check PHP syntax across all source files |
| `make test` | Run the PHPUnit test suite (`./vendor/bin/phpunit`) |
| `make build` | Run generation, linting, and tests |
| `make generate` | Regenerate client and models from the OpenAPI specification |
| `make clean` | Remove auto-generated files |

Before submitting any code, ensure that all tests and lint checks pass:
```bash
make lint
make test
```

---

## Commit Message Guidelines

We use **[Semantic Release](https://github.com/semantic-release/semantic-release)** to automate package versioning and publishing. Because of this, commit messages **must follow the [Conventional Commits](https://www.conventionalcommits.org/) specification**.

Format:
```text
<type>(<scope>): <subject>
```

### Supported Types:

| Type | Release Type | Description |
|---|---|---|
| `feat` | **Minor** (`1.x.0`) | A new feature or capability |
| `fix` | **Patch** (`1.0.x`) | A bug fix |
| `perf` | **Patch** (`1.0.x`) | Performance improvement |
| `docs` | None | Documentation changes |
| `refactor` | None | Code change that neither fixes a bug nor adds a feature |
| `test` | None | Adding or updating tests |
| `chore` | None | Maintenance tasks, tooling updates, dependencies |
| `ci` | None | Changes to CI/CD workflows and configuration |

### Breaking Changes:
If a commit introduces a breaking change, include `BREAKING CHANGE:` in the commit footer or add `!` after the type (e.g. `feat!: change client constructor arguments`), which triggers a **Major** release (`2.0.0`).

### Examples:
- `feat(laravel): add automatic query caching helper`
- `fix(client): handle connection timeouts gracefully`
- `docs(readme): add Laravel configuration instructions`
- `test(laravel): add integration test for EasySQL facade`

---

## Pull Request Process

1. Create a new branch for your change:
   ```bash
   git checkout -b feat/my-new-feature
   ```
2. Make your changes and write unit tests where appropriate.
3. Ensure the test suite and lint checks pass:
   ```bash
   make lint
   make test
   ```
4. Commit your changes using Conventional Commits.
5. Push your branch to your fork:
   ```bash
   git push origin feat/my-new-feature
   ```
6. Open a **Pull Request** targeting the `main` branch of `Clearsoft-net/easysql-php`.
7. Fill in the PR description with details about the changes made and link any related issues.

---

## License

By contributing to EasySQL PHP & Laravel SDK, you agree that your contributions will be licensed under the project's [MIT License](./LICENSE).
