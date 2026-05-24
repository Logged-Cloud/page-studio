<?php

use LoggedCloud\PageStudio\Blocks\BlockRegistry;
use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Blocks\Builtin\HeadingBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ParagraphBlock;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

class CalloutTestBlock extends BlockType
{
    public static function key(): string   { return 'callout'; }
    public static function label(): string { return 'Callout'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'tone' => ['kind' => 'select', 'label' => 'Tone', 'default' => 'info',
                'options' => ['info' => 'Info', 'warning' => 'Warning']],
            'text' => ['kind' => 'text', 'label' => 'Text', 'default' => ''],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $tone = $settings['tone'] ?? 'info';
        $text = PageRenderer::renderText((string) ($settings['text'] ?? ''), $context, $decorate);
        return "<div data-callout=\"{$tone}\">{$text}</div>";
    }
}

it('registers a BlockType subclass under its declared key', function () {
    BlockRegistry::register(CalloutTestBlock::class);
    expect(BlockRegistry::all())->toHaveKey('callout')
        ->and(BlockRegistry::find('callout'))->toBe(CalloutTestBlock::class);
});

it('refuses non-BlockType classes', function () {
    BlockRegistry::register(\stdClass::class);
})->throws(InvalidArgumentException::class);

it('PageRenderer dispatches blocks through the registry', function () {
    BlockRegistry::register(CalloutTestBlock::class);
    $html = PageRenderer::renderBlock(
        ['type' => 'callout', 'settings' => ['tone' => 'warning', 'text' => 'Heads up']],
        [],
    );
    expect($html)->toContain('data-callout="warning"')
        ->and($html)->toContain('Heads up');
});

it('built-in heading + paragraph blocks render via their classes', function () {
    $heading = PageRenderer::renderBlock([
        'type' => 'heading',
        'settings' => ['text' => 'Hello', 'level' => 'h1', 'align' => 'center'],
    ], []);
    expect($heading)->toContain('<h1')->toContain('Hello')->toContain('text-align:center');

    $paragraph = PageRenderer::renderBlock([
        'type' => 'paragraph',
        'settings' => ['text' => 'World'],
    ], []);
    expect($paragraph)->toContain('<p')->toContain('World');
});

it('built-in blocks toLibraryEntry shape is config-compatible', function () {
    $entry = HeadingBlock::toLibraryEntry();
    expect($entry)
        ->toHaveKeys(['group', 'label', 'icon', 'settings', 'slots', 'custom', 'class'])
        ->and($entry['class'])->toBe(HeadingBlock::class)
        ->and($entry['settings']['text']['default'])->toBe('Section heading');
});

it('paragraph block converts newlines to <br>', function () {
    $html = ParagraphBlock::toLibraryEntry();
    expect($html['settings']['text']['kind'])->toBe('textarea');

    $rendered = PageRenderer::renderBlock([
        'type' => 'paragraph',
        'settings' => ['text' => "line one\nline two"],
    ], []);
    expect($rendered)->toContain('<br');
});
