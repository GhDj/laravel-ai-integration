<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Exceptions\PromptException;
use Ghdj\AIIntegration\Prompts\PromptTemplate;
use Ghdj\AIIntegration\Tests\TestCase;

class PromptTemplateTest extends TestCase
{
    public function test_it_renders_simple_template(): void
    {
        $template = new PromptTemplate('Hello, {{ name }}!');

        $result = $template->render(['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_it_renders_multiple_variables(): void
    {
        $template = new PromptTemplate('{{ greeting }}, {{ name }}! Welcome to {{ place }}.');

        $result = $template->render([
            'greeting' => 'Hello',
            'name' => 'John',
            'place' => 'Paris',
        ]);

        $this->assertEquals('Hello, John! Welcome to Paris.', $result);
    }

    public function test_it_handles_whitespace_in_variable_syntax(): void
    {
        $template = new PromptTemplate('Hello, {{name}} and {{ name2 }} and {{  name3  }}!');

        $result = $template->render([
            'name' => 'Alice',
            'name2' => 'Bob',
            'name3' => 'Charlie',
        ]);

        $this->assertEquals('Hello, Alice and Bob and Charlie!', $result);
    }

    public function test_it_applies_upper_filter(): void
    {
        $template = new PromptTemplate('Hello, {{ name | upper }}!');

        $result = $template->render(['name' => 'world']);

        $this->assertEquals('Hello, WORLD!', $result);
    }

    public function test_it_applies_lower_filter(): void
    {
        $template = new PromptTemplate('Hello, {{ name | lower }}!');

        $result = $template->render(['name' => 'WORLD']);

        $this->assertEquals('Hello, world!', $result);
    }

    public function test_it_applies_ucfirst_filter(): void
    {
        $template = new PromptTemplate('Hello, {{ name | ucfirst }}!');

        $result = $template->render(['name' => 'world']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_it_supports_default_values(): void
    {
        $template = new PromptTemplate(
            'Hello, {{ name }}!',
            null,
            ['name' => 'World']
        );

        $result = $template->render();

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_it_overrides_defaults_with_provided_values(): void
    {
        $template = new PromptTemplate(
            'Hello, {{ name }}!',
            null,
            ['name' => 'World']
        );

        $result = $template->render(['name' => 'Universe']);

        $this->assertEquals('Hello, Universe!', $result);
    }

    public function test_it_throws_exception_for_missing_required_variables(): void
    {
        $template = new PromptTemplate('Hello, {{ name }}! Welcome to {{ place }}.');

        $this->expectException(PromptException::class);
        $this->expectExceptionMessage('Missing required variables: name, place');

        $template->render();
    }

    public function test_it_extracts_required_variables(): void
    {
        $template = new PromptTemplate(
            '{{ greeting }}, {{ name }}!',
            null,
            ['greeting' => 'Hello']
        );

        $required = $template->getRequiredVariables();

        $this->assertEquals(['name'], $required);
    }

    public function test_it_extracts_all_variables(): void
    {
        $template = new PromptTemplate('{{ greeting }}, {{ name }}! Welcome to {{ place }}.');

        $variables = $template->getAllVariables();

        $this->assertContains('greeting', $variables);
        $this->assertContains('name', $variables);
        $this->assertContains('place', $variables);
    }

    public function test_it_validates_variables(): void
    {
        $template = new PromptTemplate('Hello, {{ name }}!');

        $this->assertTrue($template->validate(['name' => 'World']));
        $this->assertFalse($template->validate());
    }

    public function test_it_returns_missing_variables(): void
    {
        $template = new PromptTemplate('{{ a }}, {{ b }}, {{ c }}!');

        $missing = $template->getMissingVariables(['a' => 'x']);

        $this->assertEquals(['b', 'c'], $missing);
    }

    public function test_it_renders_system_template(): void
    {
        $template = new PromptTemplate(
            'Summarize this: {{ text }}',
            'You are a {{ role }} assistant.'
        );

        $system = $template->renderSystem(['role' => 'helpful']);
        $user = $template->render(['text' => 'Some content', 'role' => 'helpful']);

        $this->assertEquals('You are a helpful assistant.', $system);
        $this->assertEquals('Summarize this: Some content', $user);
    }

    public function test_it_converts_to_messages(): void
    {
        $template = new PromptTemplate(
            'Hello, {{ name }}!',
            'You are a friendly bot.'
        );

        $messages = $template->toMessages(['name' => 'World']);

        $this->assertCount(2, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('You are a friendly bot.', $messages[0]['content']);
        $this->assertEquals('user', $messages[1]['role']);
        $this->assertEquals('Hello, World!', $messages[1]['content']);
    }

    public function test_to_messages_without_system(): void
    {
        $template = new PromptTemplate('Hello, {{ name }}!');

        $messages = $template->toMessages(['name' => 'World']);

        $this->assertCount(1, $messages);
        $this->assertEquals('user', $messages[0]['role']);
    }

    public function test_with_returns_new_instance(): void
    {
        $template = new PromptTemplate('Hello, {{ name }}!');
        $newTemplate = $template->with(['name' => 'World']);

        $this->assertNotSame($template, $newTemplate);
        $this->assertEmpty($template->getVariables());
        $this->assertEquals(['name' => 'World'], $newTemplate->getVariables());
    }

    public function test_with_system_returns_new_instance(): void
    {
        $template = new PromptTemplate('Hello!');
        $newTemplate = $template->withSystem('Be helpful.');

        $this->assertNotSame($template, $newTemplate);
        $this->assertNull($template->getSystemTemplate());
        $this->assertEquals('Be helpful.', $newTemplate->getSystemTemplate());
    }

    public function test_it_creates_from_array(): void
    {
        $template = PromptTemplate::fromArray([
            'template' => 'Hello, {{ name }}!',
            'system' => 'Be friendly.',
            'defaults' => ['name' => 'World'],
        ]);

        $this->assertEquals('Hello, World!', $template->render());
        $this->assertEquals('Be friendly.', $template->renderSystem());
    }

    public function test_it_handles_array_values(): void
    {
        $template = new PromptTemplate('Items: {{ items }}');

        $result = $template->render(['items' => ['apple', 'banana', 'cherry']]);

        $this->assertEquals('Items: apple, banana, cherry', $result);
    }

    public function test_static_create_method(): void
    {
        $template = PromptTemplate::create('Hello, {{ name }}!', 'System prompt');

        $this->assertEquals('Hello, {{ name }}!', $template->getTemplate());
        $this->assertEquals('System prompt', $template->getSystemTemplate());
    }
}
