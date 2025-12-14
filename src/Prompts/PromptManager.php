<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Prompts;

use Ghdj\AIIntegration\Contracts\PromptTemplateInterface;
use Ghdj\AIIntegration\Exceptions\PromptException;

class PromptManager
{
    protected array $templates = [];
    protected array $paths = [];
    protected ?string $defaultPath = null;

    public function __construct(array $config = [])
    {
        $this->defaultPath = $config['path'] ?? null;

        if (isset($config['paths'])) {
            foreach ($config['paths'] as $namespace => $path) {
                $this->addPath($namespace, $path);
            }
        }

        if (isset($config['templates'])) {
            foreach ($config['templates'] as $name => $template) {
                $this->register($name, $template);
            }
        }
    }

    public function register(string $name, PromptTemplateInterface|array|string $template): static
    {
        if (is_string($template)) {
            $template = new PromptTemplate($template);
        } elseif (is_array($template)) {
            $template = PromptTemplate::fromArray($template);
        }

        $this->templates[$name] = $template;

        return $this;
    }

    public function get(string $name): PromptTemplateInterface
    {
        if (isset($this->templates[$name])) {
            return clone $this->templates[$name];
        }

        $template = $this->loadFromFile($name);

        if ($template !== null) {
            $this->templates[$name] = $template;

            return clone $template;
        }

        throw new PromptException("Prompt template [{$name}] not found.");
    }

    public function has(string $name): bool
    {
        if (isset($this->templates[$name])) {
            return true;
        }

        return $this->findTemplateFile($name) !== null;
    }

    public function make(string $name, array $variables = []): PromptTemplateInterface
    {
        return $this->get($name)->with($variables);
    }

    public function render(string $name, array $variables = []): string
    {
        return $this->get($name)->render($variables);
    }

    public function toMessages(string $name, array $variables = []): array
    {
        return $this->get($name)->toMessages($variables);
    }

    public function addPath(string $namespace, string $path): static
    {
        $this->paths[$namespace] = rtrim($path, '/');

        return $this;
    }

    public function setDefaultPath(string $path): static
    {
        $this->defaultPath = rtrim($path, '/');

        return $this;
    }

    public function all(): array
    {
        return $this->templates;
    }

    public function forget(string $name): static
    {
        unset($this->templates[$name]);

        return $this;
    }

    public function extend(string $name, string $baseName, array $overrides = []): static
    {
        $base = $this->get($baseName);

        $template = new PromptTemplate(
            $overrides['template'] ?? $base->getTemplate(),
            $overrides['system'] ?? $base->getSystemTemplate(),
            array_merge($base->getVariables(), $overrides['defaults'] ?? [])
        );

        return $this->register($name, $template);
    }

    protected function loadFromFile(string $name): ?PromptTemplateInterface
    {
        $file = $this->findTemplateFile($name);

        if ($file === null) {
            return null;
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => $this->loadPhpTemplate($file),
            'json' => $this->loadJsonTemplate($file),
            'txt', 'prompt' => $this->loadTextTemplate($file),
            default => null,
        };
    }

    protected function findTemplateFile(string $name): ?string
    {
        if (str_contains($name, '::')) {
            [$namespace, $template] = explode('::', $name, 2);

            if (isset($this->paths[$namespace])) {
                return $this->findInPath($this->paths[$namespace], $template);
            }

            return null;
        }

        if ($this->defaultPath !== null) {
            $file = $this->findInPath($this->defaultPath, $name);
            if ($file !== null) {
                return $file;
            }
        }

        foreach ($this->paths as $path) {
            $file = $this->findInPath($path, $name);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    protected function findInPath(string $basePath, string $name): ?string
    {
        $name = str_replace('.', '/', $name);
        $extensions = ['php', 'json', 'txt', 'prompt'];

        foreach ($extensions as $ext) {
            $file = "{$basePath}/{$name}.{$ext}";
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    protected function loadPhpTemplate(string $file): PromptTemplateInterface
    {
        $config = require $file;

        if ($config instanceof PromptTemplateInterface) {
            return $config;
        }

        if (is_array($config)) {
            return PromptTemplate::fromArray($config);
        }

        if (is_string($config)) {
            return new PromptTemplate($config);
        }

        throw new PromptException("Invalid prompt template format in [{$file}].");
    }

    protected function loadJsonTemplate(string $file): PromptTemplateInterface
    {
        $content = file_get_contents($file);
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PromptException("Invalid JSON in prompt template [{$file}].");
        }

        return PromptTemplate::fromArray($config);
    }

    protected function loadTextTemplate(string $file): PromptTemplateInterface
    {
        $content = file_get_contents($file);

        if (str_starts_with($content, '---')) {
            return $this->parseTemplateWithFrontMatter($content);
        }

        return new PromptTemplate($content);
    }

    protected function parseTemplateWithFrontMatter(string $content): PromptTemplateInterface
    {
        $parts = preg_split('/^---\s*$/m', $content, 3);

        if (count($parts) < 3) {
            return new PromptTemplate($content);
        }

        $frontMatter = trim($parts[1]);
        $template = trim($parts[2]);

        $config = [];
        foreach (explode("\n", $frontMatter) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        return new PromptTemplate(
            $template,
            $config['system'] ?? null,
            []
        );
    }
}
