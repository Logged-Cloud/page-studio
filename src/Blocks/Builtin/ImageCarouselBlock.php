<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Image carousel · two motion modes:
 *   - coverflow · slides fan around a centred hero with pagination dots
 *                 and pointer-drag to swipe. Honours prefers-reduced-motion
 *                 (autoplay disabled, drag still works).
 *   - marquee   · continuous side-scroll for logo strips. Grab to scrub,
 *                 fling to coast. The slide set renders twice and offsets
 *                 wrap at half-width for a seamless loop.
 *
 * Ported from the MBR home-page hero · stripped of Tailwind so the block
 * works in any host app theme.
 */
class ImageCarouselBlock extends BlockType
{
    public static function key(): string   { return 'image_carousel'; }
    public static function label(): string { return 'Image carousel'; }
    public static function icon(): string  { return '⇄'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'images'   => ['kind' => 'textarea', 'label' => 'Images', 'default' => "https://picsum.photos/seed/1/1200/400\nhttps://picsum.photos/seed/2/1200/400\nhttps://picsum.photos/seed/3/1200/400",
                           'help' => 'One URL per line. Optionally suffix with " | alt text".'],
            'mode'     => ['kind' => 'select', 'label' => 'Mode', 'default' => 'coverflow',
                           'options' => ['coverflow' => 'Coverflow', 'marquee' => 'Marquee (logo strip)']],
            'autoplay' => ['kind' => 'bool',   'label' => 'Autoplay (coverflow)', 'default' => true],
            'interval' => ['kind' => 'number', 'label' => 'Autoplay interval (ms)', 'default' => 5000],
            'height'   => ['kind' => 'text',   'label' => 'Height (marquee)', 'default' => '3.5rem',
                           'help' => 'Image height for marquee mode · e.g. 3rem, 56px.'],
            'ratio'    => ['kind' => 'text',   'label' => 'Aspect ratio (coverflow)', 'default' => '3 / 1',
                           'help' => 'CSS aspect-ratio · e.g. 16 / 9, 3 / 1, 1 / 1.'],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $slides = self::parseSlides($settings, $context);
        if (empty($slides)) return '';
        $mode = (string) ($settings['mode'] ?? 'coverflow');
        return $mode === 'marquee'
            ? $this->renderMarquee($slides, $settings)
            : $this->renderCoverflow($slides, $settings);
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        // Email · no JS, no autoplay. Lay slides out in a single column.
        $slides = self::parseSlides($settings, $context);
        if (empty($slides)) return '';
        $rows = '';
        foreach ($slides as $s) {
            $src = htmlspecialchars($s['src'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $alt = htmlspecialchars($s['alt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rows .= '<tr><td style="padding:6px 0"><img src="'.$src.'" alt="'.$alt.'" style="display:block;width:100%;max-width:600px;height:auto;border-radius:8px"></td></tr>';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0;border-collapse:collapse">'.$rows.'</table>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $slides = self::parseSlides($settings, $context);
        if (empty($slides)) return '';
        $lines = array_map(fn ($s) => '- '.($s['alt'] !== '' ? $s['alt'].' ('.$s['src'].')' : $s['src']), $slides);
        return implode("\n", $lines)."\n\n";
    }

    public function renderMarkdown(array $settings, array $children, array $context): ?string
    {
        $slides = self::parseSlides($settings, $context);
        if (empty($slides)) return '';
        $lines = array_map(fn ($s) => '![' . $s['alt'] . '](' . $s['src'] . ')', $slides);
        return implode("\n\n", $lines)."\n\n";
    }

    /** @return array<int, array{src:string,alt:string}> */
    protected static function parseSlides(array $settings, array $context): array
    {
        $raw  = (string) ($settings['images'] ?? '');
        $rows = preg_split('/\r?\n/', trim($raw)) ?: [];
        $out  = [];
        foreach ($rows as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $src = $line;
            $alt = '';
            if (str_contains($line, '|')) {
                [$src, $alt] = array_map('trim', explode('|', $line, 2));
            }
            $out[] = [
                'src' => PageRenderer::substitute($src, $context, false),
                'alt' => PageRenderer::substitute($alt, $context, false),
            ];
        }
        return $out;
    }

    protected function renderCoverflow(array $slides, array $settings): string
    {
        $autoplay = (bool) ($settings['autoplay'] ?? true) ? 'true' : 'false';
        $interval = (int)  ($settings['interval'] ?? 5000);
        $ratio    = htmlspecialchars((string) ($settings['ratio'] ?? '3 / 1'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $total    = count($slides);
        $slidesJson = htmlspecialchars(json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $slideMarkup = '';
        $dotMarkup   = '';
        foreach ($slides as $i => $s) {
            $src = htmlspecialchars($s['src'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $alt = htmlspecialchars($s['alt'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slideMarkup .= sprintf(
                '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;transition:all 500ms ease-out;will-change:transform" '
                .':style="slideStyle(%d)" :aria-hidden="relOffset(%d) !== 0">'
                .'<img src="%s" alt="%s" draggable="false" style="width:100%%;height:100%%;border-radius:1rem;object-fit:cover;background:#fff;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);pointer-events:none">'
                .'</div>',
                $i, $i, $src, $alt,
            );
            $dotMarkup .= sprintf(
                '<button type="button" @click="go(%1$d)" :aria-current="current === %1$d ? \'true\' : \'false\'" aria-label="Go to slide %2$d" '
                .'style="background:transparent;border:0;cursor:pointer;padding:6px"><span :style="current === %1$d ? \'background:#111D3D;width:24px\' : \'background:rgba(17,29,61,.3);width:10px\'" style="display:block;height:10px;border-radius:9999px;transition:all 300ms"></span></button>',
                $i, $i + 1,
            );
        }

        return <<<HTML
<div style="width:100%;display:flex;flex-direction:column;align-items:center;gap:1.5rem;user-select:none;isolation:isolate" x-data='{
    current: 0, total: {$total}, autoplay: {$autoplay}, interval: {$interval},
    timer: null, dragging: false, dragStartX: 0, dragDx: 0,
    next() { this.current = (this.current + 1) % this.total; },
    prev() { this.current = (this.current - 1 + this.total) % this.total; },
    go(i)  { this.current = ((i % this.total) + this.total) % this.total; },
    relOffset(i) {
        let d = i - this.current;
        const half = this.total / 2;
        if (d > half) d -= this.total;
        if (d < -half) d += this.total;
        return d;
    },
    slideStyle(i) {
        const o = this.relOffset(i);
        const abs = Math.abs(o);
        if (abs > 2) return "opacity:0;pointer-events:none;transform:translateX(0) scale(0.6);";
        const rubber = (dx) => { const max = 18; const sign = dx < 0 ? -1 : 1; const a = Math.abs(dx) / 12; return sign * max * (a / (a + max)); };
        const dragShift = this.dragging ? rubber(this.dragDx) : 0;
        const tx = o * 28 + dragShift;
        const scale = Math.max(0.7, 1 - abs * 0.12);
        const opacity = abs === 0 ? 1 : 0;
        const z = 30 - abs;
        const rot = o * 2;
        return "transform: translateX(" + tx + "%) scale(" + scale + ") rotate(" + rot + "deg); opacity:" + opacity + "; z-index:" + z + ";";
    },
    start() {
        if (!this.autoplay || this.total < 2) return;
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        this.stop();
        this.timer = setInterval(() => this.next(), this.interval);
    },
    stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
    pointerX(e) { return e.touches ? e.touches[0].clientX : e.clientX; },
    onDown(e) { this.dragging = true; this.dragStartX = this.pointerX(e); this.dragDx = 0; this.stop(); },
    onMove(e) { if (!this.dragging) return; this.dragDx = this.pointerX(e) - this.dragStartX; },
    onUp() { if (!this.dragging) return; const t = 60; if (this.dragDx < -t) this.next(); else if (this.dragDx > t) this.prev(); this.dragDx = 0; this.dragging = false; this.start(); },
}' x-init="start()" @mouseenter="stop()" @mouseleave="start()">
    <div style="position:relative;width:100%;aspect-ratio:{$ratio}" :style="dragging ? 'cursor:grabbing' : 'cursor:grab'"
         @mousedown.prevent="onDown(\$event)" @mousemove.prevent="onMove(\$event)" @mouseup="onUp()" @mouseleave="onUp()"
         @touchstart="onDown(\$event)" @touchmove="onMove(\$event)" @touchend="onUp()">
        {$slideMarkup}
    </div>
    <div style="display:flex;align-items:center;justify-content:center;gap:.5rem" role="group" aria-label="Carousel pagination">
        {$dotMarkup}
    </div>
</div>
HTML;
    }

    protected function renderMarquee(array $slides, array $settings): string
    {
        $height = htmlspecialchars((string) ($settings['height'] ?? '3.5rem'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Render the slide set twice so the offset can wrap at half-width.
        $imgs = '';
        foreach (array_merge($slides, $slides) as $i => $s) {
            $src = htmlspecialchars($s['src'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $alt = $i < count($slides) ? htmlspecialchars($s['alt'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
            $aria = $i < count($slides) ? '' : 'aria-hidden="true"';
            $imgs .= '<img src="'.$src.'" alt="'.$alt.'" '.$aria.' draggable="false" loading="lazy" decoding="async" style="height:'.$height.';width:auto;object-fit:contain;flex-shrink:0;pointer-events:none">';
        }

        return <<<HTML
<div style="width:100%;overflow:hidden;user-select:none" x-data='{
    offset: 0, base: -0.6, velocity: -0.6, dragging: false, lastX: 0, frameDx: 0, dragVel: 0, raf: null,
    reduced: window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    pointerX(e) { return e.touches ? e.touches[0].clientX : e.clientX; },
    loop() {
        const half = this.\$refs.track.scrollWidth / 2;
        if (half) {
            if (this.dragging) {
                this.dragVel = this.dragVel * 0.7 + this.frameDx * 0.3;
                this.frameDx = 0;
            } else {
                this.velocity += (this.base - this.velocity) * 0.04;
                this.offset += this.velocity;
            }
            while (this.offset <= -half) this.offset += half;
            while (this.offset > 0)       this.offset -= half;
            this.\$refs.track.style.transform = "translateX(" + this.offset + "px)";
        }
        this.raf = requestAnimationFrame(() => this.loop());
    },
    start() {
        if (this.reduced) { this.base = 0; this.velocity = 0; }
        this.raf = requestAnimationFrame(() => this.loop());
    },
    stop() { if (this.raf) cancelAnimationFrame(this.raf); },
    onDown(e) { this.dragging = true; this.lastX = this.pointerX(e); this.dragVel = 0; this.frameDx = 0; },
    onMove(e) { if (!this.dragging) return; const x = this.pointerX(e); const dx = x - this.lastX; this.offset += dx; this.frameDx += dx; this.lastX = x; },
    onUp() { if (!this.dragging) return; this.dragging = false; this.velocity = this.reduced ? 0 : this.dragVel; },
}' x-init="start()" x-on:destroy="stop()"
   @mousedown.prevent="onDown(\$event)" @mousemove="onMove(\$event)" @mouseup.window="onUp()" @mouseleave="onUp()"
   @touchstart.passive="onDown(\$event)" @touchmove.passive="onMove(\$event)" @touchend.window="onUp()"
   :style="dragging ? 'cursor:grabbing' : 'cursor:grab'">
    <div x-ref="track" style="display:flex;align-items:center;gap:5rem;width:max-content;will-change:transform">
        {$imgs}
    </div>
</div>
HTML;
    }
}
