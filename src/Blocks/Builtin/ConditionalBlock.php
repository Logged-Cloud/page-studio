<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ConditionalBlock extends BlockType
{
    public static function key(): string   { return 'conditional'; }
    public static function label(): string { return 'If / show when'; }
    public static function icon(): string  { return '⊕'; }
    public static function group(): string { return 'layout'; }

    public static function slots(): array
    {
        return ['body' => ['label' => 'When true']];
    }

    public static function settings(): array
    {
        return [
            'variable' => [
                'kind'    => 'text',
                'label'   => 'Variable name',
                'default' => 'isAdmin',
            ],
            'mode' => [
                'kind'    => 'select',
                'label'   => 'Show when',
                'default' => 'truthy',
                'options' => [
                    'truthy' => 'value is truthy',
                    'falsy'  => 'value is falsy / empty',
                    'equals' => 'value equals "compare"',
                ],
            ],
            'compare' => [
                'kind'    => 'text',
                'label'   => 'Compare against',
                'default' => '',
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $name = trim((string) ($settings['variable'] ?? ''));
        $value = $context[$name] ?? null;

        $satisfied = match ($settings['mode'] ?? 'truthy') {
            'falsy'  => empty($value),
            'equals' => (string) $value === (string) ($settings['compare'] ?? ''),
            default  => (bool) $value,
        };

        $body = PageRenderer::renderChildren($children, 'body', $context, $decorate);

        if ($satisfied) return $body;
        if (! $decorate) return '';
        return '<div style="opacity:.4;border:1px dashed #94a3b8;padding:.4em .6em;border-radius:.3em" '
            .'title="Hidden because the condition is false right now">'
            .'<small style="color:#64748b">Hidden when <code>'.htmlspecialchars($name, ENT_QUOTES).'</code> is not '
            .htmlspecialchars((string) ($settings['mode'] ?? 'truthy'), ENT_QUOTES).'</small>'.$body.'</div>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $name  = trim((string) ($settings['variable'] ?? ''));
        $value = $context[$name] ?? data_get($context, $name);

        $satisfied = match ($settings['mode'] ?? 'truthy') {
            'falsy'  => empty($value),
            'equals' => (string) $value === (string) ($settings['compare'] ?? ''),
            default  => (bool) $value,
        };

        return $satisfied ? PageRenderer::renderChildrenForText($children, 'body', $context) : '';
    }

    public function renderMarkdown(array $settings, array $children, array $context): ?string
    {
        $name  = trim((string) ($settings['variable'] ?? ''));
        $value = $context[$name] ?? data_get($context, $name);

        $satisfied = match ($settings['mode'] ?? 'truthy') {
            'falsy'  => empty($value),
            'equals' => (string) $value === (string) ($settings['compare'] ?? ''),
            default  => (bool) $value,
        };

        return $satisfied ? PageRenderer::renderChildrenForMarkdown($children, 'body', $context) : '';
    }
}
