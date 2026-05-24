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

        // Dots are allowed in token names so authors can drop deep references
        // like `{{ user.email }}` or `{{ booking.customer.name }}` · the
        // matching value is resolved via data_get so both flat-key contexts
        // (`['user.email' => 'foo']`) and nested ones (`['user' => [...]]`)
        // both work.
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/',
            function ($m) use ($context, $decorate) {
                $name  = $m[1];
                $value = self::lookup($context, $name);
                if ($value === self::MISSING) return $m[0];

                $rendered = htmlspecialchars(self::stringify($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! $decorate) return $rendered;
                return '<mark class="ps-var" data-var="'.$name.'" '
                    .'title="'.$name.'" '
                    .'style="background:color-mix(in srgb, #2C66E8 18%, transparent);color:#2C66E8;border-radius:.2rem;padding:0 .3rem;font-weight:600">'
                    .$rendered.'</mark>';
            },
            $escaped,
        );
    }

    /** Sentinel for `lookup()` so callers can distinguish "missing" from "value is empty". */
    private const MISSING = "\0__ps_missing__\0";

    /**
     * Look up a dotted name in the context. Checks both the flat key and a
     * walked nested path. Returns MISSING if neither exists · used by the
     * renderer to leave unsubstituted tokens visible to the author.
     */
    protected static function lookup(array $context, string $name): mixed
    {
        if (array_key_exists($name, $context)) {
            return $context[$name];
        }
        $walked = data_get($context, $name, self::MISSING);
        return $walked;
    }

    /**
     * Render a value to a string for substitution · DateTimeInterface gets
     * ATOM, arrays/objects get JSON, anything else falls through to (string).
     */
    protected static function stringify(mixed $value): string
    {
        if ($value === null)                      return '';
        if (is_string($value))                    return $value;
        if (is_bool($value))                      return $value ? 'true' : 'false';
        if (is_scalar($value))                    return (string) $value;
        if ($value instanceof \DateTimeInterface) return $value->format(DATE_ATOM);
        if (is_object($value) && method_exists($value, '__toString')) return (string) $value;
        return (string) json_encode($value);
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
            '/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/',
            function ($m) use ($context) {
                $value = self::lookup($context, $m[1]);
                return $value === self::MISSING ? $m[0] : self::stringify($value);
            },
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

    /**
     * Email-safe variant of `render()` · prefers each block's
     * `renderEmail()` override (typically nested `<table>` markup) and
     * falls back to the regular `render()` when no override exists.
     */
    public static function renderForEmail(array $blocks, array $context = [], bool $decorate = false): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $out .= self::renderBlockForEmail($block, $context, $decorate);
        }
        return $out;
    }

    public static function renderBlockForEmail(array $block, array $context = [], bool $decorate = false): string
    {
        $type = $block['type'] ?? null;
        if (! $type) return '';
        $class = \LoggedCloud\PageStudio\Blocks\BlockRegistry::find($type);
        if (! $class) return '';

        try {
            /** @var \LoggedCloud\PageStudio\Blocks\BlockType $instance */
            $instance = new $class();
            $settings = $block['settings']        ?? [];
            $children = is_array($block['children'] ?? null) ? $block['children'] : [];

            $email = $instance->renderEmail($settings, $children, $context, $decorate);
            if ($email !== null) return $email;

            return $instance->render($settings, $children, $context, $decorate);
        } catch (\Throwable) {
            return '';
        }
    }

    /** Email-aware sibling of `renderChildren()` for layout blocks' `renderEmail()`. */
    public static function renderChildrenForEmail(array $children, string $slot, array $context, bool $decorate): string
    {
        $kids = $children[$slot] ?? [];
        return is_array($kids) ? self::renderForEmail($kids, $context, $decorate) : '';
    }

    /**
     * Plain-text counterpart to `render()` · produces the text/plain half
     * of a multipart email. Each block's `renderText()` is preferred;
     * blocks without an override fall back to stripping HTML from their
     * regular render output.
     */
    public static function renderForText(array $blocks, array $context = []): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $out .= self::renderBlockForText($block, $context);
        }
        // Collapse runaway blank lines + ensure the body ends with one
        // trailing newline so the output drops cleanly into a Mail::raw.
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;
        return rtrim($out, "\n")."\n";
    }

    public static function renderBlockForText(array $block, array $context = []): string
    {
        $type = $block['type'] ?? null;
        if (! $type) return '';
        $class = \LoggedCloud\PageStudio\Blocks\BlockRegistry::find($type);
        if (! $class) return '';

        try {
            /** @var \LoggedCloud\PageStudio\Blocks\BlockType $instance */
            $instance = new $class();
            $settings = $block['settings']        ?? [];
            $children = is_array($block['children'] ?? null) ? $block['children'] : [];

            $text = $instance->renderText($settings, $children, $context);
            if ($text !== null) return $text;

            // Fallback · take the regular HTML render, drop tags, decode
            // entities, collapse whitespace. Good enough for plain content
            // blocks that don't bother with an override.
            $html = $instance->render($settings, $children, $context, false);
            return self::stripToText($html);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function renderChildrenForText(array $children, string $slot, array $context): string
    {
        $kids = $children[$slot] ?? [];
        return is_array($kids) ? self::renderForText($kids, $context) : '';
    }

    /**
     * Markdown counterpart to `render()` · prefers each block's
     * `renderMarkdown()` override, falls back to `renderText()` (markdown
     * is a superset of plain text), and finally to stripping HTML from
     * the regular render. Collapses runaway blank lines the same way
     * `renderForText` does so the output is safe to drop into a `.md` file.
     */
    public static function renderForMarkdown(array $blocks, array $context = []): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $out .= self::renderBlockForMarkdown($block, $context);
        }
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;
        return rtrim($out, "\n")."\n";
    }

    public static function renderBlockForMarkdown(array $block, array $context = []): string
    {
        $type = $block['type'] ?? null;
        if (! $type) return '';
        $class = \LoggedCloud\PageStudio\Blocks\BlockRegistry::find($type);
        if (! $class) return '';

        try {
            /** @var \LoggedCloud\PageStudio\Blocks\BlockType $instance */
            $instance = new $class();
            $settings = $block['settings']        ?? [];
            $children = is_array($block['children'] ?? null) ? $block['children'] : [];

            $md = $instance->renderMarkdown($settings, $children, $context);
            if ($md !== null) return $md;

            // Markdown is a superset of plain text · existing renderText
            // overrides already emit valid markdown for headings, lists,
            // code fences, dividers, quotes, etc.
            $text = $instance->renderText($settings, $children, $context);
            if ($text !== null) return $text;

            $html = $instance->render($settings, $children, $context, false);
            return self::stripToText($html);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function renderChildrenForMarkdown(array $children, string $slot, array $context): string
    {
        $kids = $children[$slot] ?? [];
        return is_array($kids) ? self::renderForMarkdown($kids, $context) : '';
    }

    /**
     * Strip HTML to readable plain text · used as the fallback shape when a
     * block doesn't ship its own `renderText()`.
     */
    public static function stripToText(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text)."\n\n";
    }
}
