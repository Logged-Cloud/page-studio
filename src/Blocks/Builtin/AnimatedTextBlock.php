<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Animated text · cycles through a list of phrases with one of four
 * animation styles. Inspired by the MBR hero · ported off Tailwind so
 * the block works in any host app theme.
 *
 * Five modes:
 *   - typewriter         · types in, pauses, deletes, types next
 *   - roller-up          · whole-word reel rolls upward
 *   - roller-down        · whole-word reel rolls downward
 *   - slot-roller-up     · per-letter reel, cascades left-to-right
 *   - slot-roller-down   · per-letter reel, the other direction
 *
 * Variables substitute into the phrase text · `{{ name }}` works the
 * same as in any text block.
 */
class AnimatedTextBlock extends BlockType
{
    public static function key(): string   { return 'animated_text'; }
    public static function label(): string { return 'Animated text'; }
    public static function icon(): string  { return '⌐'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'items' => ['kind' => 'textarea', 'label' => 'Phrases', 'default' => "Agents\nAirlines\nTour Operators",
                        'help' => 'One phrase per line. Optionally prefix with a CSS colour and a colon, e.g. "#E11D48: Agents".'],
            'mode'  => ['kind' => 'select', 'label' => 'Mode', 'default' => 'typewriter',
                        'options' => [
                            'typewriter'        => 'Typewriter',
                            'roller-up'         => 'Roller (up)',
                            'roller-down'       => 'Roller (down)',
                            'slot-roller-up'    => 'Slot roller (up)',
                            'slot-roller-down'  => 'Slot roller (down)',
                        ]],
            'color' => ['kind' => 'text',   'label' => 'Default colour', 'default' => '#2C66E8',
                        'help' => 'Per-phrase override via the "colour: text" syntax in Phrases.'],
            'size'  => ['kind' => 'select', 'label' => 'Size', 'default' => 'xl',
                        'options' => ['sm' => 'Small', 'base' => 'Base', 'lg' => 'Large', 'xl' => 'XL', '2xl' => '2XL', '3xl' => '3XL', 'display' => 'Display']],
            'speed' => ['kind' => 'number', 'label' => 'Type speed (ms/char)', 'default' => 80],
            'pause' => ['kind' => 'number', 'label' => 'Hold (ms)',            'default' => 1500],
            'loop'  => ['kind' => 'bool',   'label' => 'Loop forever',         'default' => true],
            'caret' => ['kind' => 'bool',   'label' => 'Caret (typewriter)',   'default' => true],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $items = self::parseItems($settings, $context, $decorate);
        if (empty($items)) return '';

        $mode    = (string) ($settings['mode']  ?? 'typewriter');
        $size    = self::sizeStyle((string) ($settings['size'] ?? 'xl'));
        $speed   = (int)    ($settings['speed'] ?? 80);
        $pause   = (int)    ($settings['pause'] ?? 1500);
        $loop    = (bool)   ($settings['loop']  ?? true);
        $caret   = (bool)   ($settings['caret'] ?? true);
        $longest = max(array_map(fn ($i) => mb_strlen($i['text']), $items)) ?: 1;

        $itemsJson = htmlspecialchars(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $outerStyle = "display:inline-flex;align-items:baseline;{$size};font-weight:600";

        if (in_array($mode, ['roller-up', 'roller-down'], true)) {
            return $this->renderRoller($itemsJson, $mode, $pause, $longest, $outerStyle);
        }
        if (in_array($mode, ['slot-roller-up', 'slot-roller-down'], true)) {
            return $this->renderSlotRoller($itemsJson, $mode, $pause, $longest, $outerStyle);
        }
        return $this->renderTypewriter($itemsJson, $speed, $pause, $loop, $caret, $longest, $outerStyle);
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        // Email clients don't run JS. Show the first phrase as static text.
        $items = self::parseItems($settings, $context, false);
        if (empty($items)) return '';
        $first = $items[0];
        $size  = self::sizeStyle((string) ($settings['size'] ?? 'xl'));
        return sprintf(
            '<span style="%s;color:%s;font-weight:600">%s</span>',
            $size,
            htmlspecialchars($first['color'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars($first['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $items = self::parseItems($settings, $context, false);
        return implode(' / ', array_column($items, 'text'))."\n\n";
    }

    public function renderMarkdown(array $settings, array $children, array $context): ?string
    {
        $items = self::parseItems($settings, $context, false);
        return '**'.implode('** / **', array_column($items, 'text'))."**\n\n";
    }

    /**
     * @return array<int, array{text:string,color:string}>
     */
    protected static function parseItems(array $settings, array $context, bool $decorate): array
    {
        $defaultColor = (string) ($settings['color'] ?? '#2C66E8');
        $raw          = (string) ($settings['items'] ?? '');
        $lines        = preg_split('/\r?\n/', trim($raw)) ?: [];

        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // "colour: text" parsing · the colour part is anything up to the
            // first colon that LOOKS like a CSS colour (starts with #, or
            // is one of the obvious named keywords). Otherwise the whole
            // line is the text.
            $color = $defaultColor;
            $text  = $line;
            if (preg_match('/^(#[0-9A-Fa-f]{3,8}|rgb\([^)]+\)|hsl\([^)]+\)|[A-Za-z]+)\s*:\s*(.+)$/', $line, $m)
                && self::looksLikeColor($m[1])) {
                $color = $m[1];
                $text  = $m[2];
            }
            $text = PageRenderer::renderText($text, $context, $decorate);
            // renderText returns HTML-escaped string with optional <mark> wraps · we want plain text for the Alpine x-text binding.
            $text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $out[] = ['text' => $text, 'color' => $color];
        }
        return $out;
    }

    protected static function looksLikeColor(string $candidate): bool
    {
        if ($candidate === '') return false;
        if ($candidate[0] === '#' || str_starts_with($candidate, 'rgb') || str_starts_with($candidate, 'hsl')) return true;
        // Named colour shortlist · keeps the heuristic narrow so "Agents: 5"
        // doesn't get misread as a colour.
        return in_array(strtolower($candidate), [
            'red', 'blue', 'green', 'orange', 'purple', 'pink', 'yellow', 'cyan', 'magenta', 'white', 'black', 'gray', 'grey', 'transparent', 'inherit', 'currentcolor',
        ], true);
    }

    protected static function sizeStyle(string $size): string
    {
        return match ($size) {
            'sm'      => 'font-size:0.875rem;line-height:1.4',
            'base'    => 'font-size:1rem;line-height:1.5',
            'lg'      => 'font-size:1.125rem;line-height:1.4',
            'xl'      => 'font-size:1.25rem;line-height:1.3',
            '2xl'     => 'font-size:1.5rem;line-height:1.3',
            '3xl'     => 'font-size:1.875rem;line-height:1.2',
            'display' => 'font-size:clamp(1.75rem, 4vw + 0.5rem, 3rem);line-height:1.1',
            default   => 'font-size:1.25rem;line-height:1.3',
        };
    }

    protected function renderTypewriter(string $itemsJson, int $speed, int $pause, bool $loop, bool $caret, int $longest, string $outerStyle): string
    {
        $loopJs  = $loop  ? 'true' : 'false';
        $caretHtml      = '';
        $caretKeyframes = '';
        if ($caret) {
            $caretHtml      = '<span class="ps-anim-caret" style="display:inline-block;width:2px;height:1em;vertical-align:-0.15em;margin-left:2px;background:currentColor;animation:ps-anim-caret 1s steps(2) infinite" aria-hidden="true"></span>';
            $caretKeyframes = '<style>@keyframes ps-anim-caret { 50% { opacity: 0; } }</style>';
        }

        return <<<HTML
<span style="{$outerStyle}" x-data='{
    items: {$itemsJson},
    speed: {$speed}, pause: {$pause}, loop: {$loopJs},
    index: 0, chars: 0, deleting: false, timer: null,
    get current() { return this.items[this.index] || { text: "", color: "" }; },
    get visible() { return this.current.text.slice(0, this.chars); },
    start() {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) { this.chars = this.current.text.length; return; }
        this.tick();
    },
    stop() { if (this.timer) clearTimeout(this.timer); },
    tick() {
        const item = this.current;
        if (!this.deleting && this.chars < item.text.length) { this.chars++; this.timer = setTimeout(() => this.tick(), this.speed); }
        else if (!this.deleting && this.chars === item.text.length) {
            if (!this.loop && this.index === this.items.length - 1) return;
            this.timer = setTimeout(() => { this.deleting = true; this.tick(); }, this.pause);
        }
        else if (this.deleting && this.chars > 0) { this.chars--; this.timer = setTimeout(() => this.tick(), this.speed / 2); }
        else { this.deleting = false; this.index = (this.index + 1) % this.items.length; this.timer = setTimeout(() => this.tick(), this.speed); }
    },
}' x-init="start()" x-on:destroy="stop()">
    {$caretKeyframes}
    <span style="position:relative;display:inline-block;min-width:{$longest}ch">
        <span :style="'color:' + current.color" style="white-space:pre" x-text="visible"></span>{$caretHtml}
    </span>
</span>
HTML;
    }

    protected function renderRoller(string $itemsJson, string $mode, int $pause, int $longest, string $outerStyle): string
    {
        $up = $mode === 'roller-up' ? 'true' : 'false';
        return <<<HTML
<span style="{$outerStyle}" x-data='{
    items: {$itemsJson}, up: {$up}, interval: {$pause}, rollMs: 550,
    index: 0, next: 0, rolling: false, timer: null,
    start() {
        if (this.items.length < 2) return;
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        this.next = 1;
        this.timer = setInterval(() => this.roll(), this.interval);
    },
    stop() { if (this.timer) clearInterval(this.timer); },
    roll() {
        this.rolling = true;
        setTimeout(() => { this.index = this.next; this.next = (this.index + 1) % this.items.length; this.rolling = false; }, this.rollMs);
    },
    get cur() { return this.items[this.index] || { text: "", color: "" }; },
    get nxt() { return this.items[this.next]  || { text: "", color: "" }; },
    reelStyle() {
        const y = this.up ? (this.rolling ? "-1.15em" : "0em") : (this.rolling ? "0em" : "-1.15em");
        const t = this.rolling ? "transform " + this.rollMs + "ms cubic-bezier(.6,0,.2,1)" : "none";
        return "transform: translateY(" + y + "); transition: " + t + ";";
    },
}' x-init="start()" x-on:destroy="stop()">
    <span style="position:relative;display:inline-block;overflow:hidden;min-width:{$longest}ch;height:1.15em">
        <span style="visibility:hidden" aria-hidden="true">&nbsp;</span>
        <span style="position:absolute;inset-inline-start:0;inset-inline-end:0;top:0;display:flex;flex-direction:column" :style="reelStyle()">
            <span style="height:1.15em;white-space:pre" :style="'color:' + (up ? cur : nxt).color" x-text="(up ? cur : nxt).text"></span>
            <span style="height:1.15em;white-space:pre" :style="'color:' + (up ? nxt : cur).color" x-text="(up ? nxt : cur).text"></span>
        </span>
    </span>
</span>
HTML;
    }

    protected function renderSlotRoller(string $itemsJson, string $mode, int $pause, int $longest, string $outerStyle): string
    {
        $up = $mode === 'slot-roller-up' ? 'true' : 'false';
        $cells = '';
        for ($i = 0; $i < $longest; $i++) {
            $cells .= <<<HTML
        <span style="position:relative;display:inline-block;overflow:hidden;height:1.15em">
            <span style="visibility:hidden;display:block;height:1.15em;white-space:pre" aria-hidden="true" x-text="char('cur', {$i})"></span>
            <span style="position:absolute;inset-inline-start:0;inset-inline-end:0;top:0;display:flex;flex-direction:column" :style="cellStyle({$i})">
                <span style="display:block;height:1.15em;white-space:pre" :style="'color:' + (up ? curColor : nxtColor)" x-text="char(up ? 'cur' : 'nxt', {$i})"></span>
                <span style="display:block;height:1.15em;white-space:pre" :style="'color:' + (up ? nxtColor : curColor)" x-text="char(up ? 'nxt' : 'cur', {$i})"></span>
            </span>
        </span>
HTML;
        }

        return <<<HTML
<span style="{$outerStyle}" x-data='{
    items: {$itemsJson}, up: {$up}, interval: {$pause}, rollMs: 320, stagger: 60,
    index: 0, next: 0, rolling: false, timer: null,
    start() {
        if (this.items.length < 2) return;
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        this.next = 1;
        this.timer = setInterval(() => this.roll(), this.interval);
    },
    stop() { if (this.timer) clearInterval(this.timer); },
    roll() {
        this.rolling = true;
        setTimeout(() => { this.index = this.next; this.next = (this.index + 1) % this.items.length; this.rolling = false; }, this.rollMs + {$longest} * this.stagger);
    },
    char(which, i) { const item = which === "cur" ? this.items[this.index] : this.items[this.next]; return (item && item.text[i]) || " "; },
    get curColor() { return (this.items[this.index] || {}).color || ""; },
    get nxtColor() { return (this.items[this.next]  || {}).color || ""; },
    cellStyle(i) {
        const y = this.up ? (this.rolling ? "-1.15em" : "0em") : (this.rolling ? "0em" : "-1.15em");
        const t = this.rolling ? "transform " + this.rollMs + "ms cubic-bezier(.2,.7,.2,1) " + (i * this.stagger) + "ms" : "none";
        return "transform: translateY(" + y + "); transition: " + t + ";";
    },
}' x-init="start()" x-on:destroy="stop()">
{$cells}
</span>
HTML;
    }
}
