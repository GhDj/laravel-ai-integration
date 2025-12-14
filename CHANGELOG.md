# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-12-14

### Added

- **Multi-provider support** for OpenAI, Claude (Anthropic), and Gemini (Google)
- **Chat completions** with full message history support
- **Streaming responses** for real-time output from all providers
- **Embeddings** support for OpenAI and Gemini
- **Tool/function calling** with unified interface across providers
- **JSON mode** for structured outputs (OpenAI)
- **Rate limiting** with configurable limits per provider using Laravel Cache
- **Cost tracking** with per-model pricing and usage metrics
- **Prompt templating** system with:
  - Variable substitution (`{{ variable }}`)
  - Filters (`upper`, `lower`, `ucfirst`)
  - Default values
  - System message support
  - File-based templates (PHP, JSON, TXT)
- **Laravel integration** with:
  - Service provider with auto-discovery
  - Facade for convenient access
  - Publishable configuration
- **Extensibility** via custom provider registration
- **Comprehensive test suite** with 195 tests and 380 assertions
- **GitHub Actions CI** for tests, code style, and static analysis

### Providers

#### OpenAI
- Models: GPT-4o, GPT-4o-mini, GPT-4 Turbo, o1, o1-mini, o3-mini
- Embeddings: text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002
- Features: Chat, streaming, embeddings, tool calling, JSON mode

#### Claude (Anthropic)
- Models: Claude 4 Sonnet, Claude 4 Opus, Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus/Sonnet/Haiku
- Features: Chat, streaming, tool calling, system messages

#### Gemini (Google)
- Models: Gemini 1.5 Pro, Gemini 1.5 Flash, Gemini 2.0 Flash
- Embeddings: text-embedding-004
- Features: Chat, streaming, embeddings, tool calling

[Unreleased]: https://github.com/GhDj/laravel-ai-integration/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/GhDj/laravel-ai-integration/releases/tag/v1.0.0
