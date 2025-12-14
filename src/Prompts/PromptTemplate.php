<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Prompts;

use Ghdj\AIIntegration\Contracts\PromptTemplateInterface;
use Ghdj\AIIntegration\Exceptions\PromptException;

class PromptTemplate implements PromptTemplateInterface
{
    protected array $variables = [];
    protected array $defaults = [];

    public function __construct(
        protected string $template,
        protected ?string $systemTemplate = null,
        array $defaults = []
    ) {
        $this->defaults = $defaults;
    }

    public static function create(string $template, ?string $systemTemplate = null): static
    {
        return new static($template, $systemTemplate);
    }

    public static function fromArray(array $config): static
    {
        return new static(
            $config['template'] ?? $config['user'] ?? '',
            $config['system'] ?? null,
            $config['defaults'] ?? []
        );
    }

    public function render(array $variables = []): string
    {
        $merged = array_merge($this->defaults, $this->variables, $variables);
        $this->validateRequired($merged);

        return $this->substitute($this->template, $merged);
    }

    public function renderSystem(array $variables = []): ?string
    {
        if ($this->systemTemplate === null) {
            return null;
        }

        $merged = array_merge($this->defaults, $this->variables, $variables);

        return $this->substitute($this->systemTemplate, $merged);
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getSystemTemplate(): ?string
    {
        return $this->systemTemplate;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getRequiredVariables(): array
    {
        $templateVars = $this->extractVariables($this->template);
        $systemVars = $this->systemTemplate ? $this->extractVariables($this->systemTemplate) : [];

        $allVars = array_unique(array_merge($templateVars, $systemVars));

        return array_values(array_diff($allVars, array_keys($this->defaults)));
    }

    public function getAllVariables(): array
    {
        $templateVars = $this->extractVariables($this->template);
        $systemVars = $this->systemTemplate ? $this->extractVariables($this->systemTemplate) : [];

        return array_unique(array_merge($templateVars, $systemVars));
    }

    public function with(array $variables): static
    {
        $clone = clone $this;
        $clone->variables = array_merge($this->variables, $variables);

        return $clone;
    }

    public function withDefaults(array $defaults): static
    {
        $clone = clone $this;
        $clone->defaults = array_merge($this->defaults, $defaults);

        return $clone;
    }

    public function withSystem(string $systemTemplate): static
    {
        $clone = clone $this;
        $clone->systemTemplate = $systemTemplate;

        return $clone;
    }

    public function validate(array $variables = []): bool
    {
        $merged = array_merge($this->defaults, $this->variables, $variables);
        $required = $this->getRequiredVariables();

        foreach ($required as $var) {
            if (!array_key_exists($var, $merged)) {
                return false;
            }
        }

        return true;
    }

    public function getMissingVariables(array $variables = []): array
    {
        $merged = array_merge($this->defaults, $this->variables, $variables);
        $required = $this->getRequiredVariables();

        return array_values(array_filter(
            $required,
            fn(string $var) => !array_key_exists($var, $merged)
        ));
    }

    public function toMessages(array $variables = []): array
    {
        $messages = [];

        $system = $this->renderSystem($variables);
        if ($system !== null) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $this->render($variables)];

        return $messages;
    }

    protected function substitute(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)(?:\s*\|\s*(\w+))?\s*\}\}/',
            function (array $matches) use ($variables) {
                $key = $matches[1];
                $filter = $matches[2] ?? null;

                $value = $variables[$key] ?? '';

                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                return $this->applyFilter((string) $value, $filter);
            },
            $template
        );
    }

    protected function applyFilter(string $value, ?string $filter): string
    {
        if ($filter === null) {
            return $value;
        }

        return match ($filter) {
            'upper' => strtoupper($value),
            'lower' => strtolower($value),
            'ucfirst' => ucfirst($value),
            'ucwords' => ucwords($value),
            'trim' => trim($value),
            'json' => json_encode($value),
            'escape' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            default => $value,
        };
    }

    protected function extractVariables(string $template): array
    {
        preg_match_all('/\{\{\s*(\w+)(?:\s*\|\s*\w+)?\s*\}\}/', $template, $matches);

        return array_unique($matches[1]);
    }

    protected function validateRequired(array $variables): void
    {
        $missing = $this->getMissingVariables($variables);

        if (!empty($missing)) {
            throw new PromptException(
                'Missing required variables: ' . implode(', ', $missing)
            );
        }
    }
}
