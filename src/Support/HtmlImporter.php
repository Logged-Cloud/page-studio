<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Convert a raw HTML blob (the kind CKEditor / TinyMCE / Trix saves into a
 * single column) into a page-studio block tree.
 *
 * Lossy by design · the importer maps the common semantic tags onto the
 * package's built-in blocks and falls back to a paragraph for anything it
 * doesn't recognise. Tables are kept as `table` blocks holding the raw
 * markup so authors don't lose data during the migration window.
 */
class HtmlImporter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function toBlocks(string $html): array
    {
        $html = trim($html);
        if ($html === '') return [];

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // The UTF-8 declaration + wrapper div protect against entity drops
        // and let us start the walk from a known root.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__ps_root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__ps_root__')
            ?? $dom->getElementsByTagName('div')->item(0);
        if (! $root) return [];

        $blocks = [];
        foreach ($root->childNodes as $node) {
            $block = self::nodeToBlock($node);
            if ($block !== null) $blocks[] = $block;
        }
        return $blocks;
    }

    protected static function nodeToBlock(\DOMNode $node): ?array
    {
        // Bare text between block-level elements becomes a paragraph.
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->nodeValue ?? '');
            return $text === '' ? null : self::block('paragraph', ['text' => $text]);
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) return null;

        $tag = strtolower($node->nodeName);
        return match ($tag) {
            'h1', 'h2', 'h3', 'h4' => self::block('heading', [
                'text'  => self::innerText($node),
                'level' => $tag,
                'align' => 'left',
            ]),
            'p' => self::block('paragraph', ['text' => self::innerText($node)]),
            'img' => self::block('image', [
                'src' => $node->getAttribute('src'),
                'alt' => $node->getAttribute('alt'),
            ]),
            'ul', 'ol' => self::block('list', [
                'items' => implode("\n", self::extractListItems($node)),
                'style' => $tag === 'ol' ? 'number' : 'bullet',
            ]),
            'hr' => self::block('divider'),
            'blockquote' => self::block('quote', [
                'text' => self::innerText($node),
                'cite' => '',
            ]),
            'pre' => self::block('code', [
                'code'     => $node->textContent ?? '',
                'language' => '',
            ]),
            'table' => self::block('table', [
                // Preserve the original markup so authors don't lose data;
                // they can hand-edit the textarea or replace the block.
                'html' => self::outerHtml($node),
            ]),
            'br' => null,
            default => self::fallbackParagraph($node),
        };
    }

    protected static function fallbackParagraph(\DOMNode $node): ?array
    {
        $text = trim(self::innerText($node));
        return $text === '' ? null : self::block('paragraph', ['text' => $text]);
    }

    /**
     * Trim + collapse whitespace from a node's text content · keeps line
     * breaks inside <pre>/<code> intact via the dedicated case above.
     */
    protected static function innerText(\DOMNode $node): string
    {
        $text = (string) ($node->textContent ?? '');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * @return array<int, string>
     */
    protected static function extractListItems(\DOMNode $list): array
    {
        $items = [];
        foreach ($list->childNodes as $li) {
            if (strtolower($li->nodeName ?? '') !== 'li') continue;
            $text = self::innerText($li);
            if ($text !== '') $items[] = $text;
        }
        return $items;
    }

    protected static function outerHtml(\DOMNode $node): string
    {
        $owner = $node->ownerDocument;
        return $owner ? (string) $owner->saveHTML($node) : '';
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected static function block(string $type, array $settings = []): array
    {
        return [
            'id'       => 'b_'.bin2hex(random_bytes(4)),
            'type'     => $type,
            'settings' => $settings,
        ];
    }
}
