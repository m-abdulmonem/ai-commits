
# AI Commits for Laravel

[![Latest Version](https://img.shields.io/packagist/v/mabdulmonem/ai-commits.svg?style=flat-square)](https://packagist.org/packages/mabdulmonem/ai-commits)
[![Tests](https://github.com/mabdulmonem/ai-commits/actions/workflows/tests.yml/badge.svg)](https://github.com/mabdulmonem/ai-commits/actions)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/mabdulmonem/ai-commits.svg?style=flat-square)](https://packagist.org/packages/mabdulmonem/ai-commits)

An intelligent Git commit assistant that automatically generates meaningful, conventional commit messages using AI based on your code changes.

## Features

- 🧠 **Multi-AI Support** - Works with OpenAI, OpenRouter, and Anthropic Claude
- 📝 **Conventional Commits** - Generates properly formatted commit messages
- 🔍 **Smart Diff Analysis** - Understands code changes at hunk level
- 🚀 **VCS Integration** - GitHub, GitLab, and Bitbucket support
- ⚡ **Auto-Repo Setup** - Initializes Git repos and configures remotes
- 🔧 **Extensible Architecture** - Easy to add new AI/VCS providers
- 🛡️ **Error Resilient** - Graceful fallbacks and validations

## Installation

Install via Composer:

```bash
composer require --dev mabdulmonem/ai-commits:dev-main
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ai-commits-config
```

## Configuration

Add your API keys to .env:

```env
# AI Providers
OPENAI_API_KEY=your-openai-key
OPENROUTER_API_KEY=your-openrouter-key
ANTHROPIC_API_KEY=your-anthropic-key

# VCS Providers
GITHUB_TOKEN=your-github-token
GITLAB_TOKEN=your-gitlab-token
BITBUCKET_USERNAME=your-bitbucket-username
BITBUCKET_APP_PASSWORD=your-bitbucket-app-password
```

## Usage

### Basic Command

```bash
php artisan commit
```

### Command Options

| Option       | Description               | Example                |
|--------------|---------------------------|------------------------|
| --push       | Push after committing      | --push                 |
| --model=     | Specify AI model           | --model=gpt-4          |
| --provider=  | Specify AI provider        | --provider=openai      |
| --vcs=       | Specify VCS provider       | --vcs=github           |
| --dry-run    | Show what would happen     | --dry-run              |
| --no-ai      | Skip AI and use simple messages | --no-ai          |
| --auto       | Non-interactive mode       | --auto                 |

### Advanced Examples

Create and push with specific model:

```bash
php artisan commit --push --model=claude-2
```

Dry run with OpenRouter:

```bash
php artisan commit --dry-run --provider=openrouter
```

Initialize new repo and connect to GitHub:

```bash
php artisan commit --auto --vcs=github
```

## Workflow Integration

### As a Git Hook

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/sh
php artisan commit --auto
```

### In CI/CD Pipelines

Example GitHub Action step:

```yaml
- name: AI Commit
  run: php artisan commit --auto --no-ai
  env:
    GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

## Supported AI Models

| Provider     | Supported Models                     |
|--------------|--------------------------------------|
| OpenAI       | gpt-3.5-turbo, gpt-4, etc.           |
| OpenRouter   | 100+ models across providers         |
| Anthropic    | claude-2, claude-instant             |

## Supported VCS Features

| Platform   | Repository | Pull Requests | Auth Methods   |
|------------|------------|----------------|----------------|
| GitHub     | ✅          | ✅              | Token          |
| GitLab     | ✅          | ✅              | Token          |
| Bitbucket  | ✅          | ✅              | App Password   |

## Testing

Run the test suite:

```bash
composer test
```

Generate test coverage:

```bash
composer test-coverage
```

## Security

If you discover any security issues, please email security@example.com instead of using the issue tracker.

## Contributing

1. Fork the project  
2. Create your feature branch (`git checkout -b feature/amazing-feature`)  
3. Commit your changes (`git commit -m 'Add some amazing feature'`)  
4. Push to the branch (`git push origin feature/amazing-feature`)  
5. Open a Pull Request  

## Changelog

See `CHANGELOG.md` for recent changes.

## License

The MIT License (MIT). See `LICENSE.md` for more information.
