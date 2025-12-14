<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Feature;

use Ghdj\AIIntegration\Facades\AI;
use Ghdj\AIIntegration\Prompts\PromptTemplate;
use Ghdj\AIIntegration\Tests\TestCase;

class PromptIntegrationTest extends TestCase
{
    public function test_register_and_use_inline_template(): void
    {
        AI::prompts()->register('summarize', [
            'system' => 'You are a helpful summarizer.',
            'template' => 'Summarize this text: {{ text }}',
        ]);

        $template = AI::prompt('summarize', ['text' => 'Long content here']);
        $messages = $template->toMessages();

        $this->assertCount(2, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('You are a helpful summarizer.', $messages[0]['content']);
        $this->assertEquals('user', $messages[1]['role']);
        $this->assertStringContainsString('Long content here', $messages[1]['content']);
    }

    public function test_template_with_defaults(): void
    {
        AI::prompts()->register('translate', [
            'template' => 'Translate to {{ language }}: {{ text }}',
            'defaults' => ['language' => 'French'],
        ]);

        $result = AI::prompts()->render('translate', ['text' => 'Hello']);

        $this->assertEquals('Translate to French: Hello', $result);
    }

    public function test_template_override_defaults(): void
    {
        AI::prompts()->register('translate', [
            'template' => 'Translate to {{ language }}: {{ text }}',
            'defaults' => ['language' => 'French'],
        ]);

        $result = AI::prompts()->render('translate', [
            'text' => 'Hello',
            'language' => 'Spanish',
        ]);

        $this->assertEquals('Translate to Spanish: Hello', $result);
    }

    public function test_extend_existing_template(): void
    {
        AI::prompts()->register('base', [
            'template' => '{{ instruction }}: {{ content }}',
            'system' => 'You are helpful.',
        ]);

        AI::prompts()->extend('formal', 'base', [
            'system' => 'You are a formal assistant. Use professional language.',
        ]);

        $template = AI::prompt('formal');
        $messages = $template->toMessages([
            'instruction' => 'Rewrite',
            'content' => 'hey whats up',
        ]);

        $this->assertEquals('You are a formal assistant. Use professional language.', $messages[0]['content']);
        $this->assertEquals('Rewrite: hey whats up', $messages[1]['content']);
    }

    public function test_template_filters(): void
    {
        AI::prompts()->register('format', [
            'template' => 'Name: {{ name | upper }}, Title: {{ title | ucfirst }}',
        ]);

        $result = AI::prompts()->render('format', [
            'name' => 'john doe',
            'title' => 'developer',
        ]);

        $this->assertEquals('Name: JOHN DOE, Title: Developer', $result);
    }

    public function test_chained_template_operations(): void
    {
        $template = PromptTemplate::create('Hello, {{ name }}!')
            ->withSystem('Be friendly.')
            ->withDefaults(['name' => 'Guest'])
            ->with(['greeting' => 'Hi']);

        $this->assertEquals('Hello, Guest!', $template->render());
        $this->assertEquals('Be friendly.', $template->renderSystem());
    }

    public function test_multiple_templates_isolation(): void
    {
        AI::prompts()->register('template1', 'First: {{ value }}');
        AI::prompts()->register('template2', 'Second: {{ value }}');

        $t1 = AI::prompt('template1', ['value' => 'A']);
        $t2 = AI::prompt('template2', ['value' => 'B']);

        $this->assertEquals('First: A', $t1->render());
        $this->assertEquals('Second: B', $t2->render());
    }

    public function test_template_validation(): void
    {
        AI::prompts()->register('required', 'Hello, {{ name }} from {{ place }}!');

        $template = AI::prompt('required');

        $this->assertFalse($template->validate());
        $this->assertFalse($template->validate(['name' => 'John']));
        $this->assertTrue($template->validate(['name' => 'John', 'place' => 'Paris']));
    }

    public function test_get_missing_variables(): void
    {
        AI::prompts()->register('multi', '{{ a }} {{ b }} {{ c }}');

        $template = AI::prompt('multi', ['a' => 'x']);
        $missing = $template->getMissingVariables();

        $this->assertContains('b', $missing);
        $this->assertContains('c', $missing);
        $this->assertNotContains('a', $missing);
    }
}
