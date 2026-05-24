<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Pure-PHP block-tree → HTML renderer. Resolves `{{ var }}` tokens against
 * the supplied context (typically the route's resolved variable values).
 * Stays HTML-escape-safe by default; only `paragraph` lets line breaks
 * survive via nl2br on the already-escaped text.
 */
class PageRenderer
{
    /**
     * Render a list of blocks. With $decorate=true the editor wraps every
     * substituted variable value in <mark class="ps-var">…</mark> so the
     * author sees which bits came from a variable.
     */
    public static function render(array $blocks, array $context = [], bool $decorate = false): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $out .= self::renderBlock($block, $context, $decorate);
        }
        return $out;
    }

    public static function renderBlock(array $block, array $context = [], bool $decorate = false): string
    {
        $type = $block['type'] ?? null;
        $s    = $block['settings'] ?? [];

        // Class-defined block? Dispatch through the BlockRegistry so
        // built-ins and host-app blocks share one code path.
        if ($type && $class = \LoggedCloud\PageStudio\Blocks\BlockRegistry::find($type)) {
            try {
                /** @var \LoggedCloud\PageStudio\Blocks\BlockType $instance */
                $instance = new $class();
                $children = is_array($block['children'] ?? null) ? $block['children'] : [];
                return $instance->render($s, $children, $context, $decorate);
            } catch (\Throwable) {
                return '';
            }
        }

        // Built-in blocks are all BlockType subclasses registered at boot ·
        // anything that misses the registry above is an unknown type.
        return '';
    }


    /**
     * Escape user text first, then substitute {{ name }} tokens with the
     * escaped context value · optionally wrapping it in a <mark> so the
     * editor canvas can highlight resolved variables.
     */
    public static function renderText(string $text, array $context, bool $decorate): string
    {
        // Escape the raw text, then walk the regex over the escaped string ·
        // the {{ }} braces don't get escaped so the pattern still matches.
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/',
            function ($m) use ($context, $decorate) {
                $name = $m[1];
                if (! array_key_exists($name, $context)) {
                    return $m[0];
                }
                $value = htmlspecialchars(
                    (string) $context[$name],
                    ENT_QUOTES | ENT_HTML5, 'UTF-8',
                );
                if (! $decorate) return $value;
                return '<mark class="ps-var" data-var="'.$name.'" '
                    .'title="'.$name.'" '
                    .'style="background:color-mix(in srgb, #2C66E8 18%, transparent);color:#2C66E8;border-radius:.2rem;padding:0 .3rem;font-weight:600">'
                    .$value.'</mark>';
            },
            $escaped,
        );
    }

    /**
     * Replace `{{ name }}` tokens · used by callers that need plain
     * substitution (e.g. href attributes). NOT html-escaping.
     */
    public static function substitute(string $text, array $context, bool $decorate = false): string
    {
        if ($decorate) {
            return self::renderText($text, $context, true);
        }
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/',
            fn ($m) => array_key_exists($m[1], $context)
                ? (string) $context[$m[1]]
                : $m[0],
            $text,
        );
    }

    /**
     * Convenience for BlockType subclasses · render the children sitting
     * in the named slot of a layout block's flat children array.
     */
    public static function renderChildren(array $children, string $slot, array $context, bool $decorate): string
    {
        $kids = $children[$slot] ?? [];
        return is_array($kids) ? self::render($kids, $context, $decorate) : '';
    }
}
