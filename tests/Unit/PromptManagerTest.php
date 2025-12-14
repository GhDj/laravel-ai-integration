<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Contracts\PromptTemplateInterface;
use Ghdj\AIIntegration\Exceptions\PromptException;
use Ghdj\AIIntegration\Prompts\PromptManager;
use Ghdj\AIIntegration\Prompts\PromptTemplate;
use Ghdj\AIIntegration\Tests\TestCase;

class PromptManagerTest extends TestCase
{
    private PromptManager $manager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/ai-prompts-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->manager = new PromptManager([
            'path' => $this->tempDir,
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_it_registers_template_instance(): void
    {
        $template = new PromptTemplate('Hello, {{ name }}!');

        $this->manager->register('greeting', $template);

        $this->assertTrue($this->manager->has('greeting'));
        $retrieved = $this->manager->get('greeting');
        $this->assertInstanceOf(PromptTemplateInterface::class, $retrieved);
    }

    public function test_it_registers_template_from_string(): void
    {
        $this->manager->register('greeting', 'Hello, {{ name }}!');

        $result = $this->manager->render('greeting', ['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_it_registers_template_from_array(): void
    {
        $this->manager->register('greeting', [
            'template' => 'Hello, {{ name }}!',
            'system' => 'Be friendly.',
        ]);

        $template = $this->manager->get('greeting');
        $messages = $template->toMessages(['name' => 'World']);

        $this->assertCount(2, $messages);
        $this->assertEquals('Be friendly.', $messages[0]['content']);
    }

    public function test_it_makes_template_with_variables(): void
    {
        $this->manager->register('greeting', 'Hello, {{ name }}!');

        $template = $this->manager->make('greeting', ['name' => 'World']);

        $this->assertEquals('Hello, World!', $template->render());
    }

    public function test_it_renders_template_directly(): void
    {
        $this->manager->register('greeting', 'Hello, {{ name }}!');

        $result = $this->manager->render('greeting', ['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_it_converts_to_messages(): void
    {
        $this->manager->register('greeting', [
            'template' => 'Hello!',
            'system' => 'Be helpful.',
        ]);

        $messages = $this->manager->toMessages('greeting');

        $this->assertCount(2, $messages);
    }

    public function test_it_throws_exception_for_missing_template(): void
    {
        $this->expectException(PromptException::class);
        $this->expectExceptionMessage('Prompt template [nonexistent] not found.');

        $this->manager->get('nonexistent');
    }

    public function test_it_forgets_template(): void
    {
        $this->manager->register('greeting', 'Hello!');

        $this->assertTrue($this->manager->has('greeting'));

        $this->manager->forget('greeting');

        $this->assertFalse($this->manager->has('greeting'));
    }

    public function test_it_returns_all_templates(): void
    {
        $this->manager->register('greeting', 'Hello!');
        $this->manager->register('farewell', 'Goodbye!');

        $all = $this->manager->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('greeting', $all);
        $this->assertArrayHasKey('farewell', $all);
    }

    public function test_it_extends_existing_template(): void
    {
        $this->manager->register('base', [
            'template' => 'Hello, {{ name }}!',
            'system' => 'Be helpful.',
        ]);

        $this->manager->extend('formal', 'base', [
            'system' => 'Be very formal and professional.',
        ]);

        $template = $this->manager->get('formal');

        $this->assertEquals('Hello, {{ name }}!', $template->getTemplate());
        $this->assertEquals('Be very formal and professional.', $template->getSystemTemplate());
    }

    public function test_it_loads_php_template_from_file(): void
    {
        $content = <<<'PHP'
<?php
return [
    'template' => 'Hello from file, {{ name }}!',
    'system' => 'You are helpful.',
];
PHP;
        file_put_contents($this->tempDir . '/file-template.php', $content);

        $template = $this->manager->get('file-template');

        $this->assertEquals('Hello from file, World!', $template->render(['name' => 'World']));
    }

    public function test_it_loads_json_template_from_file(): void
    {
        $content = json_encode([
            'template' => 'Hello from JSON, {{ name }}!',
            'system' => 'Be nice.',
        ]);
        file_put_contents($this->tempDir . '/json-template.json', $content);

        $template = $this->manager->get('json-template');

        $this->assertEquals('Hello from JSON, World!', $template->render(['name' => 'World']));
    }

    public function test_it_loads_text_template_from_file(): void
    {
        file_put_contents($this->tempDir . '/text-template.txt', 'Hello from text, {{ name }}!');

        $template = $this->manager->get('text-template');

        $this->assertEquals('Hello from text, World!', $template->render(['name' => 'World']));
    }

    public function test_it_loads_prompt_file_extension(): void
    {
        file_put_contents($this->tempDir . '/my-prompt.prompt', 'Custom prompt: {{ content }}');

        $template = $this->manager->get('my-prompt');

        $this->assertEquals('Custom prompt: test', $template->render(['content' => 'test']));
    }

    public function test_it_supports_nested_directories(): void
    {
        mkdir($this->tempDir . '/admin', 0755);
        file_put_contents($this->tempDir . '/admin/dashboard.txt', 'Admin: {{ message }}');

        $template = $this->manager->get('admin.dashboard');

        $this->assertEquals('Admin: Hello', $template->render(['message' => 'Hello']));
    }

    public function test_it_supports_namespaced_paths(): void
    {
        $customDir = $this->tempDir . '/custom';
        mkdir($customDir, 0755);
        file_put_contents($customDir . '/special.txt', 'Special: {{ text }}');

        $this->manager->addPath('myns', $customDir);

        $template = $this->manager->get('myns::special');

        $this->assertEquals('Special: test', $template->render(['text' => 'test']));
    }

    public function test_it_parses_front_matter(): void
    {
        $content = <<<'TXT'
---
system: You are a translator.
---
Translate this: {{ text }}
TXT;
        file_put_contents($this->tempDir . '/translate.txt', $content);

        $template = $this->manager->get('translate');
        $messages = $template->toMessages(['text' => 'Hello']);

        $this->assertCount(2, $messages);
        $this->assertEquals('You are a translator.', $messages[0]['content']);
        $this->assertEquals('Translate this: Hello', $messages[1]['content']);
    }

    public function test_get_returns_cloned_instance(): void
    {
        $this->manager->register('greeting', 'Hello!');

        $template1 = $this->manager->get('greeting');
        $template2 = $this->manager->get('greeting');

        $this->assertNotSame($template1, $template2);
    }

    public function test_it_initializes_from_config(): void
    {
        $manager = new PromptManager([
            'templates' => [
                'test' => 'Test template: {{ var }}',
            ],
        ]);

        $result = $manager->render('test', ['var' => 'value']);

        $this->assertEquals('Test template: value', $result);
    }
}
