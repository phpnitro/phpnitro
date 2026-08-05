<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * The layout engine's paint target: Widget::paint() calls append flat
 * draw commands here in absolute pixel coordinates (layout has already
 * resolved every position by the time paint() runs), then toJson() hands
 * the array to NativeCanvasView.kt for replay against a real Canvas.
 *
 * Superset of the Phase 0 NativeDrawCommand protocol (rect/text) — adds
 * optional border fields on rect and lets text carry an explicit baseline
 * so Text's line-wrapping can emit one command per line. Kept as a
 * separate class rather than extending NativeDrawCommand because Phase 0's
 * demo route is intentionally frozen (docs/proposals/moteur-rendu-natif.md)
 * and shouldn't shift under a change meant for the layout engine.
 *
 * toJson()'s shape changed from a flat array to {commands, hitRegions} in
 * phase 3 (hit-testing/actions) — Tappable needs somewhere to record
 * "this absolute rect fires this action string" alongside the draw
 * commands, so NativeCanvasView.kt has something to hit-test touches
 * against. Only /native/layout-demo consumes this; Phase 0's /native/demo
 * still uses the frozen flat-array NativeDrawCommand protocol.
 */
final class Canvas
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $commands = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $hitRegions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $heroRegions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $dismissRegions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $reorderRegions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $lottieRegions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $sliderRegions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $sheetRegions = [];

    private float $contentHeight = 0.0;
    private ?float $renderTimeMs = null;
    private ?string $redirect = null;
    private ?string $transition = null;

    /** @var array{screen: string, afterMs: int}|null */
    private ?array $autoNavigate = null;
    private ?int $pollAgainMs = null;
    private bool $fixedMode = false;
    private ?string $heroTag = null;
    private ?string $dismissKey = null;
    private ?string $reorderKey = null;
    private bool $scrollFollow = false;
    private bool $confetti = false;
    private ?string $pullToRefreshAction = null;

    /** @var array{message: string, durationMs: int}|null */
    private ?array $snackbar = null;

    /**
     * Opts this screen into pull-to-refresh — NativeCanvasView.kt tracks
     * the overscroll-at-top drag entirely client-side (a circular
     * indicator grows under the finger, no server round-trip per frame,
     * same "PHP never sees the gesture, only its outcome" split as
     * Dismissible/BottomSheet's own drag handle), and only fires $action
     * once released past threshold — indistinguishable from any other
     * action string on the PHP side, this screen just gets rebuilt with
     * whatever fresh data $action's handling put in $_SESSION/the
     * database. The indicator itself keeps spinning until the refetch
     * that follows actually lands (setCommands()), so a slow refresh
     * still reads as "in progress", not "stuck".
     */
    public function setPullToRefresh(string $action): self
    {
        $this->pullToRefreshAction = $action;

        return $this;
    }

    /**
     * LazyList only builds/paints the items within its current
     * scroll window, but reports the FULL virtual list height as its
     * Size — this flag tells NativeCanvasView.kt "re-send scrollY and
     * re-fetch as the user scrolls near the edge of what's actually
     * loaded" instead of only ever building the first screenful once.
     * Screens with no lazy list have nothing to prefetch and leave this
     * false, so a normal scroll stays purely client-side with zero extra
     * network chatter.
     */
    public function setScrollFollow(bool $follow = true): self
    {
        $this->scrollFollow = $follow;

        return $this;
    }

    /**
     * Everything painted between beginFixed()/endFixed() is tagged
     * "fixed": true — NativeCanvasView.kt draws those commands a second
     * time with no scroll translate applied, so they stay pinned to the
     * viewport (an AppBar/BottomNavigationBar) instead of scrolling with
     * the body. See Fixed, which is what actually calls this rather
     * than call sites reaching for it directly.
     */
    public function beginFixed(): self
    {
        $this->fixedMode = true;

        return $this;
    }

    public function endFixed(): self
    {
        $this->fixedMode = false;

        return $this;
    }

    /**
     * The native equivalent of Engine\Hero: everything painted between
     * beginHero($tag)/endHero() is tagged "hero": $tag, and the wrapper's
     * own bounding box is recorded in heroRegions. When the SAME tag shows
     * up in two consecutive renders at two different rects,
     * NativeCanvasView.kt flies that tagged subtree from its old rect to
     * its new one (a real FLIP transition — translate+scale via a Matrix,
     * see drawHeroTransition()) instead of just crossfading in place like
     * everything else. See Hero, which is what actually calls this.
     */
    public function beginHero(string $tag, float $x, float $y, float $width, float $height, ?Curve $curve = null): self
    {
        $this->heroTag = $tag;
        $this->heroRegions[] = array_filter([
            'tag' => $tag,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'curve' => $curve?->name,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    public function endHero(): self
    {
        $this->heroTag = null;

        return $this;
    }

    /**
     * The one genuinely continuous gesture in this pipeline: swiping an
     * item registers its rect + $action here (dismissRegions), and
     * everything painted between beginDismiss($key)/endDismiss() is
     * tagged "dismiss": $key. NativeCanvasView.kt tracks the drag entirely
     * client-side — translating the tagged commands live under the
     * finger, no round-trip per frame — and only calls back to PHP with
     * $action once the swipe commits past threshold on release (see
     * Dismissible, drawDismissOverlay(), and
     * NativeRenderPocActivity's onTap() handling "dismiss:" actions the
     * same as any other). PHP never sees the gesture itself, only its
     * outcome — the "sync only on release" split this whole primitive
     * exists for.
     */
    public function dismissible(string $key, float $x, float $y, float $width, float $height, string $action): self
    {
        $this->dismissRegions[] = ['key' => $key, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'action' => $action];

        return $this;
    }

    public function beginDismiss(string $key): self
    {
        $this->dismissKey = $key;

        return $this;
    }

    public function endDismiss(): self
    {
        $this->dismissKey = null;

        return $this;
    }

    /**
     * Drag-to-reorder — the same "PHP never sees the gesture, only its
     * outcome" split as dismissible(), applied to reordering a whole
     * group instead of removing one item. Each item in a
     * Reorderable registers its own rect + stable $key under a
     * shared $group here (reorderRegions); NativeCanvasView.kt tracks a
     * long-press-then-drag entirely client-side — following the finger,
     * swapping slot assignments as the dragged item crosses a neighbor's
     * midpoint, animating the displaced items into their new slots — and
     * only calls back once the finger lifts, with the group's action and
     * the final key order. See Reorderable.
     */
    public function reorderItem(string $group, string $key, float $x, float $y, float $width, float $height, string $action): self
    {
        $this->reorderRegions[] = ['group' => $group, 'key' => $key, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'action' => $action];

        return $this;
    }

    public function beginReorder(string $key): self
    {
        $this->reorderKey = $key;

        return $this;
    }

    public function endReorder(): self
    {
        $this->reorderKey = null;

        return $this;
    }

    /**
     * The grab strip at the top of a BottomSheet's card — same "PHP
     * never sees the gesture, only its outcome" split as dismissible()/
     * reorderItem(), applied to a vertical drag-to-close instead of a
     * horizontal swipe or a reorder. Registered separately from the
     * card's own tappable content (a Fermer button, form fields...) so
     * NativeCanvasView.kt can tell "drag the handle" apart from "tap
     * something inside the sheet" by rect alone. $sheetHeight is the
     * card's own full height — how far there is to drag before it counts
     * as fully closed. $closeAction is always exactly
     * BottomSheet::closeAction($key)'s "clientTab:{key}:0" string,
     * carried explicitly rather than reconstructed client-side so this
     * primitive doesn't have to know that convention itself. Fixed-tagged
     * automatically when called inside beginFixed()/endFixed() (see
     * tagFixed()) — BottomSheet always is, since the sheet is
     * screen-relative like an AppBar, not scroll-relative.
     */
    public function sheetHandle(string $key, float $x, float $y, float $width, float $height, float $sheetHeight, string $closeAction): self
    {
        $this->sheetRegions[] = $this->tagFixed([
            'key' => $key,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'sheetHeight' => $sheetHeight,
            'closeAction' => $closeAction,
        ]);

        return $this;
    }

    /**
     * Registers a rect for a real com.airbnb.android.lottie.
     * LottieAnimationView overlay — Lottie's whole point is a continuous
     * frame-by-frame animation loop, which has no equivalent in a
     * "PHP computes one frame, Kotlin replays it" draw-command pipeline.
     * NativeCanvasView.kt reconciles a live overlay View per registered
     * $key against this list on every render (added when new, repositioned
     * when it moves, removed when it disappears) — the same "overlay a
     * real Android View, there's no Canvas concept for this" idiom
     * VideoPlayer/MapView already use, just synced on every
     * render instead of only on tap, since a Lottie animation is expected
     * to autoplay rather than wait for one. See Lottie.
     */
    public function lottieRegion(string $key, float $x, float $y, float $width, float $height, string $url, bool $loop, bool $autoplay): self
    {
        $this->lottieRegions[] = [
            'key' => $key,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'url' => $url,
            'loop' => $loop,
            'autoplay' => $autoplay,
        ];

        return $this;
    }

    /**
     * An indeterminate spinner — Flutter's CircularProgressIndicator()
     * with no `value`. Unlike CircularProgress (a determinate
     * percent, fully described by one PHP-computed frame), a spinner has
     * to keep rotating between renders with nobody re-fetching anything,
     * which this request/response pipeline has no way to express as a
     * static command. So this command carries no rotation angle at all —
     * NativeCanvasView.kt's drawSpinnerCommand() computes it from its own
     * clock every frame, and keeps invalidating on its own (a small
     * continuously-repeating ValueAnimator, started/stopped based on
     * whether any "spinner" command is present) for as long as one is on
     * screen. See Spinner.
     */
    public function spinner(float $x, float $y, float $size, string $color, string $trackColor, float $strokeWidth): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'spinner',
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'color' => $color,
            'trackColor' => $trackColor,
            'strokeWidth' => $strokeWidth,
        ]);

        return $this;
    }

    /**
     * A pre-rendered ClientTabs panel — the actual client-side state
     * primitive. $panel already ran layout()/paint() into its own nested
     * Canvas by the time this is called (see ClientTabs), so
     * this just embeds that panel's own commands/hitRegions as one
     * "clientPanel" command in $this->commands. NativeCanvasView.kt keeps a
     * local `key -> selected index` map (seeded once from whichever panel
     * has $initiallyActive, never overwritten by a later render for the
     * same key) and draws/hit-tests only the panel matching the current
     * selection — switching tabs is a local redraw, never a server round
     * trip, the same way Flutter's TabBarView holds its selected index in
     * local State rather than asking the backend which tab is open.
     *
     * @param array<int, array<string, mixed>> $panelCommands
     * @param array<int, array<string, mixed>> $panelHitRegions
     */
    public function clientTabPanel(string $key, int $index, bool $initiallyActive, float $x, float $y, array $panelCommands, array $panelHitRegions): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'clientPanel',
            'key' => $key,
            'index' => $index,
            'initiallyActive' => $initiallyActive,
            'x' => $x,
            'y' => $y,
            'commands' => $panelCommands,
            'hitRegions' => $panelHitRegions,
        ]);

        return $this;
    }

    /**
     * A "carousel inside a list" — see HorizontalScroll's own docblock for
     * why this needs its own command type instead of reusing clientPanel:
     * the content here scrolls continuously along a local drag axis
     * (clamped to [0, contentWidth - viewportWidth]) rather than switching
     * between discrete panels on a tap. NativeCanvasView.kt keeps a local
     * `key -> horizontal offset` map (seeded to 0), clips painting to
     * ($x, $y, $viewportWidth, $viewportHeight), and disambiguates the
     * drag against the outer vertical scroll the same way it already does
     * for Dismissible's horizontal swipe.
     *
     * @param array<int, array<string, mixed>> $regionCommands
     * @param array<int, array<string, mixed>> $regionHitRegions
     */
    public function horizontalScroll(string $key, float $x, float $y, float $viewportWidth, float $viewportHeight, float $contentWidth, array $regionCommands, array $regionHitRegions): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'hScroll',
            'key' => $key,
            'x' => $x,
            'y' => $y,
            'width' => $viewportWidth,
            'height' => $viewportHeight,
            'contentWidth' => $contentWidth,
            'commands' => $regionCommands,
            'hitRegions' => $regionHitRegions,
        ]);

        return $this;
    }

    /**
     * A scrollable region nested inside the screen's own vertical scroll —
     * the vertical counterpart to horizontalScroll() (a capped-height
     * "recent activity" panel, a scrollable comment list embedded inside
     * a longer page, anything that needs its OWN scroll bounded to less
     * than the full screen). Same tradeoffs as horizontalScroll(): no
     * virtualization (every child laid out/painted up front — for a
     * bounded amount of content, not a long list; LazyList still owns
     * that case), the drag itself 100% client-side. Unlike
     * horizontalScroll()'s axis-based disambiguation against the outer
     * page scroll (same touch, different axis — an easy split), both this
     * and the outer scroll are vertical, so there's no axis to arbitrate
     * on: NativeCanvasView.kt claims a drag starting inside this region's
     * rect for it, but once the drag pushes past this region's own
     * top/bottom edge, the excess bubbles to the outer page scroll for
     * the rest of that same gesture — see its own ACTION_MOVE handling.
     * Still only one level (a region nested inside another isn't
     * arbitrated). See NestedScroll, the only real caller.
     */
    public function verticalScroll(string $key, float $x, float $y, float $viewportWidth, float $viewportHeight, float $contentHeight, array $regionCommands, array $regionHitRegions): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'vScroll',
            'key' => $key,
            'x' => $x,
            'y' => $y,
            'width' => $viewportWidth,
            'height' => $viewportHeight,
            'contentHeight' => $contentHeight,
            'commands' => $regionCommands,
            'hitRegions' => $regionHitRegions,
        ]);

        return $this;
    }

    /**
     * A draggable 0.0-1.0 value picker — self-contained draw (track, fill,
     * thumb, all computed from $value) plus one entry in $sliderRegions so
     * NativeCanvasView.kt can hit-test and drag-track it, the same
     * "register a rect once, own the whole gesture client-side" split as
     * dismissible()/reorderItem()/horizontalScroll(). Unlike those three,
     * a slider has no arbitrary child content to wrap (beginX()/endX()) —
     * it's always exactly a track + a thumb — so this is one call, not a
     * begin/end pair. See Slider for the widget that calls this.
     */
    public function slider(string $name, float $x, float $y, float $width, float $height, float $trackHeight, float $thumbSize, float $value, string $trackColor, string $activeColor, string $thumbColor): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'slider',
            'key' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'trackHeight' => $trackHeight,
            'thumbSize' => $thumbSize,
            'value' => $value,
            'trackColor' => $trackColor,
            'activeColor' => $activeColor,
            'thumbColor' => $thumbColor,
        ]);

        $this->sliderRegions[] = [
            'key' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'trackHeight' => $trackHeight,
            'thumbSize' => $thumbSize,
            'value' => $value,
            // Reuses Checkbox/Toggle's existing generic handler — see
            // this method's own docblock.
            'action' => "toggle:{$name}",
        ];

        return $this;
    }

    /**
     * Raw command/hitRegion arrays, no envelope — clientTabPanel() embeds
     * a whole nested Canvas's output as one command, which needs the
     * bare arrays toJson() would otherwise wrap in {commands, hitRegions,
     * ...}.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rawCommands(): array
    {
        return $this->commands;
    }

    /** @return array<int, array<string, mixed>> */
    public function rawHitRegions(): array
    {
        return $this->hitRegions;
    }

    /**
     * @param array<string, mixed> $command
     * @return array<string, mixed>
     */
    private function tagFixed(array $command): array
    {
        if ($this->fixedMode) {
            $command['fixed'] = true;
        }
        if ($this->heroTag !== null) {
            $command['hero'] = $this->heroTag;
        }
        if ($this->dismissKey !== null) {
            $command['dismiss'] = $this->dismissKey;
        }
        if ($this->reorderKey !== null) {
            $command['reorder'] = $this->reorderKey;
        }

        return $command;
    }

    /**
     * The full laid-out content height (which can exceed the viewport) —
     * NativeCanvasView needs this to know how far there is to scroll.
     * Called once with the root Widget::layout()'s returned Size.
     */
    public function setContentHeight(float $height): self
    {
        $this->contentHeight = $height;

        return $this;
    }

    /**
     * How long layout()+paint() actually took, in milliseconds — the one
     * real, measured number in the "is this fast?" question instead of an
     * intuition. Excludes HTTP transport and Kotlin-side parse/draw on
     * purpose (see docs/proposals/moteur-rendu-natif.md's definition of
     * done): this isolates the PHP-side cost specifically, since that's
     * the part this architecture is gambling on staying cheap.
     */
    public function setRenderTimeMs(float $ms): self
    {
        $this->renderTimeMs = $ms;

        return $this;
    }

    /**
     * Server-driven navigation — a Button's "submit:" action can
     * change what screen the client should be on (login succeeding, most
     * obviously) the same way LoginPage.php's onLogin() returning a path
     * redirects the HTML pipeline's router. There's no router to
     * re-resolve here, so this just tells NativeRenderPocActivity which
     * screen name to swap the top of its stack to before it re-fetches —
     * see its handling of the "redirect" field.
     */
    public function setRedirect(string $screen): self
    {
        $this->redirect = $screen;

        return $this;
    }

    /**
     * Which animation NativeCanvasView.kt's crossfade uses for THIS
     * screen's entrance — only meaningful on a real navigation (a
     * same-screen refetch never crossfades at all, see setCommands()'s
     * own isNavigation branch). 'fade' (the default if never called) is
     * the plain opacity blend this pipeline always did; 'slideLeft'/
     * 'slideRight' add a horizontal translate on top of it (a
     * push/pop feel — call slideLeft when navigating deeper,
     * slideRight when navigating back, though nothing enforces that
     * convention, it's just what reads correctly to a user), 'slideUp'
     * for a modal-style entrance. An unrecognized value falls back to
     * 'fade' client-side rather than drawing nothing.
     */
    public function setTransition(string $type): self
    {
        $this->transition = $type;

        return $this;
    }

    public function rect(
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $color = null,
        float $radius = 0.0,
        ?string $borderColor = null,
        float $borderWidth = 0.0,
        float $elevation = 0.0,
        ?string $gradientFrom = null,
        ?string $gradientTo = null,
    ): self {
        $this->commands[] = $this->tagFixed(array_filter([
            'type' => 'rect',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'color' => $color,
            'radius' => $radius,
            'borderColor' => $borderColor,
            'borderWidth' => $borderWidth,
            'elevation' => $elevation > 0.0 ? $elevation : null,
            'gradientFrom' => $gradientFrom,
            'gradientTo' => $gradientTo,
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /**
     * The extension point for a genuinely new native drawing this engine
     * module has zero built-in knowledge of — a third-party package (or
     * an app-specific widget not worth upstreaming) emits
     * {"type": "custom:$type", ...$data}, and whoever owns the consuming
     * Kotlin Activity registers a handler for that exact $type via
     * NativeCanvasView.registerCustomCommandHandler() — see Sparkline,
     * the real wired example (a tiny inline line chart NativeCanvasView.kt
     * itself has no drawSparklineCommand() for; NativeRenderPocActivity
     * registers the handler that actually draws it). Every OTHER
     * Canvas method (rect(), text(), skeleton()...) is a FRAMEWORK
     * primitive with a handler built into the engine directly; this is
     * only for what isn't — the same "PHP decides the data, Kotlin owns
     * the pixels" split as everything else here, just with the pixels
     * living outside this engine module instead of inside it.
     *
     * @param array<string, mixed> $data Whatever the registered Kotlin
     *   handler expects to find on the JSONObject it receives — this
     *   class has no way to validate that shape, same as any other
     *   loosely-typed wire format.
     */
    public function custom(string $type, array $data): self
    {
        $this->commands[] = $this->tagFixed(array_merge(['type' => "custom:{$type}"], $data));

        return $this;
    }

    /**
     * A loading placeholder that SWEEPS — same reasoning as spinner()'s
     * own docblock: a continuously-repainting gradient has no honest way
     * to travel as one static JSON response, so this is its own command
     * type NativeCanvasView.kt drives with a dedicated ValueAnimator
     * (started/stopped on demand, same idea as updateSpinnerAnimator()),
     * not a plain "rect". See Skeleton, which is the only real caller.
     */
    public function skeleton(float $x, float $y, float $width, float $height, string $color, float $radius = 0.0): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'skeleton',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'color' => $color,
            'radius' => $radius,
        ]);

        return $this;
    }

    /**
     * $fontFamily (GoogleFontText's own use) names a Google Fonts family
     * ("Roboto Slab", "Playfair Display"...) resolved on-device via
     * Android's Downloadable Fonts API — omitted/null keeps the default
     * bundled Roboto every other Text/Button/etc. already uses.
     */
    public function text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0, bool $bold = false, float $letterSpacing = 0.0, ?string $fontFamily = null): self
    {
        $this->commands[] = $this->tagFixed(array_filter([
            'type' => 'text',
            'x' => $x,
            'y' => $y,
            'text' => $text,
            'color' => $color,
            'size' => $size,
            'bold' => $bold ?: null,
            'letterSpacing' => $letterSpacing > 0.0 ? $letterSpacing : null,
            'fontFamily' => $fontFamily,
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /**
     * A Material Icons (or, with $font: 'fontawesome', Font Awesome
     * Solid) glyph — NativeCanvasView draws it with Canvas.drawText()
     * against the matching bundled font, exactly the technique Flutter's
     * own Icons class uses internally (an icon is a character, not a
     * bitmap or a hand-drawn path). $x/$y are the icon's top-left
     * corner, same convention as rect()/text(); $codepoint comes from
     * MaterialIcons::codepoint($name) or FontAwesomeIcons::codepoint($name)
     * depending on $font.
     */
    public function icon(float $x, float $y, float $size, int $codepoint, string $color = '#111827', string $font = 'material'): self
    {
        $this->commands[] = $this->tagFixed(array_filter([
            'type' => 'icon',
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'codepoint' => $codepoint,
            'color' => $color,
            'font' => $font !== 'material' ? $font : null,
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /**
     * A bitmap loaded asynchronously by NativeCanvasView (HTTP fetch +
     * decode off the main thread, in-memory LRU cache keyed by URL) — the
     * layout engine reserves the box eagerly since it can't know the
     * image's intrinsic size ahead of a network round-trip, same
     * constraint Image.network() has in Flutter. Nothing is drawn for
     * this box until the bitmap finishes loading; the view just
     * invalidates itself when it does.
     */
    public function image(float $x, float $y, float $width, float $height, string $url, float $radius = 0.0): self
    {
        $this->commands[] = $this->tagFixed(array_filter([
            'type' => 'image',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'url' => $url,
            'radius' => $radius,
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /**
     * A raw filled/stroked circle — the primitive Container's
     * rect()+radius can't express (a rect radius rounds corners, it
     * doesn't produce a circle sized independently of a bounding box), and
     * what Engine\Canvas's ->circle() needs a native equivalent for.
     */
    public function circle(float $cx, float $cy, float $radius, ?string $color = null, ?string $borderColor = null, float $borderWidth = 0.0): self
    {
        $this->commands[] = $this->tagFixed(array_filter([
            'type' => 'circle',
            'cx' => $cx,
            'cy' => $cy,
            'radius' => $radius,
            'color' => $color,
            'borderColor' => $borderColor,
            'borderWidth' => $borderWidth,
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /** A raw straight line — what Engine\Canvas's ->line() needs a native equivalent for. */
    public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 1.0): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'color' => $color,
            'width' => $width,
        ]);

        return $this;
    }

    /**
     * A stroked arc — what CircularProgress needs (a track ring plus
     * a partial ring for the filled portion) since a plain circle() can't
     * express "only part of the ring". $startDegrees/$sweepDegrees follow
     * Android's Canvas.drawArc() convention (0° = 3 o'clock, clockwise).
     */
    public function arc(float $cx, float $cy, float $radius, float $startDegrees, float $sweepDegrees, string $color, float $strokeWidth): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'arc',
            'cx' => $cx,
            'cy' => $cy,
            'radius' => $radius,
            'startDegrees' => $startDegrees,
            'sweepDegrees' => $sweepDegrees,
            'color' => $color,
            'strokeWidth' => $strokeWidth,
        ]);

        return $this;
    }

    /**
     * @param ?array<string, mixed> $meta Extra data the client needs to handle this action
     *                                    without a second round-trip — a SelectBox's
     *                                    options, a dialog's message/title/confirm action.
     */
    public function hitRegion(float $x, float $y, float $width, float $height, string $action, ?array $meta = null): self
    {
        $this->hitRegions[] = $this->tagFixed(array_filter([
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'action' => $action,
            'meta' => $meta,
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /**
     * Queues a client-side, timer-driven navigation — the same
     * navigate:/screenStack push NativeRenderPocActivity.kt already does
     * for a tapped Tappable, just fired by a Handler.postDelayed()
     * instead of a touch. Used by Splash so a splash screen can send
     * itself to its real home screen once its animation has had time to
     * play, with no user interaction required. Only the last call in a
     * paint pass wins — a screen only ever wants to schedule one jump.
     */
    public function autoNavigate(string $screen, int $afterMs): self
    {
        $this->autoNavigate = ['screen' => $screen, 'afterMs' => $afterMs];

        return $this;
    }

    /**
     * Async's polling primitive: "refetch this SAME screen again in
     * $afterMs, nothing navigates." NativeRenderPocActivity.kt deliberately
     * never sends ?lastHash= on a poll-triggered refetch (see its own
     * isPoll flag) even though it would otherwise qualify for the
     * "unchanged" short-circuit — a poll's entire purpose is checking
     * whether AsyncTask::poll() moved from pending to done, so skipping
     * the real payload the one time it might have changed would silently
     * stop the polling loop dead.
     */
    public function pollAgain(int $afterMs): self
    {
        $this->pollAgainMs = $afterMs;

        return $this;
    }

    /**
     * A one-shot celebratory particle burst, fired automatically the
     * moment this screen renders — same "server-decided, client just
     * plays it out" idiom as autoNavigate() above, just a full-screen
     * overlay instead of a navigation. See Confetti (the widget that
     * calls this from its own paint()) and NativeCanvasView.kt's
     * ConfettiView/showConfettiOverlay() for the actual particle
     * simulation, which owns its own animation clock entirely
     * client-side — there's no per-frame server round-trip, matching
     * spinner()'s exact reasoning for the same "continuous animation
     * this request/response pipeline can't express as one static frame"
     * problem.
     */
    public function triggerConfetti(): self
    {
        $this->confetti = true;

        return $this;
    }

    /**
     * A transient bottom-anchored message, auto-dismissing after
     * $durationMs — same "server decides it should show, client owns
     * the actual fade-in/wait/fade-out animation with no per-frame
     * round-trip" idiom as triggerConfetti() just above. See Snackbar
     * (the widget that calls this from its own paint()) and
     * NativeRenderPocActivity.kt's showSnackbarOverlay(). Only the
     * LAST call in a paint pass wins — same "a screen only ever wants
     * to schedule one of these" reasoning autoNavigate()'s own docblock
     * already gives, there is no queue of multiple snackbars.
     */
    public function showSnackbar(string $message, int $durationMs = 3000): self
    {
        $this->snackbar = ['message' => $message, 'durationMs' => $durationMs];

        return $this;
    }

    /**
     * A hash of everything that decides what's actually on screen —
     * deliberately excluding renderTimeMs (differs on literally every
     * request, real content or not) and the hash itself. index.php
     * compares this against the client's own last-applied hash
     * (NativeRenderPocActivity's lastAppliedHash, sent back as
     * ?lastHash=) and skips sending the full payload at all when nothing
     * actually changed — the same "don't do the work if the output would
     * be identical" instinct behind React/Flutter's own diffing, just
     * applied at the transport layer instead of a widget tree, since this
     * architecture re-renders the whole screen server-side on every
     * request rather than keeping a persistent tree to diff.
     */
    public function stableHash(): string
    {
        return hash('xxh128', json_encode(array_filter([
            'commands' => $this->commands,
            'hitRegions' => $this->hitRegions,
            'heroRegions' => $this->heroRegions !== [] ? $this->heroRegions : null,
            'dismissRegions' => $this->dismissRegions !== [] ? $this->dismissRegions : null,
            'reorderRegions' => $this->reorderRegions !== [] ? $this->reorderRegions : null,
            'lottieRegions' => $this->lottieRegions !== [] ? $this->lottieRegions : null,
            'sliderRegions' => $this->sliderRegions !== [] ? $this->sliderRegions : null,
            'sheetRegions' => $this->sheetRegions !== [] ? $this->sheetRegions : null,
            'autoNavigate' => $this->autoNavigate,
            'pollAgain' => $this->pollAgainMs,
            'contentHeight' => $this->contentHeight,
            'redirect' => $this->redirect,
            'scrollFollow' => $this->scrollFollow ? true : null,
            'pullToRefresh' => $this->pullToRefreshAction,
            'confetti' => $this->confetti ? true : null,
            'snackbar' => $this->snackbar,
        ], static fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR));
    }

    public function toJson(): string
    {
        return json_encode(array_filter([
            'commands' => $this->commands,
            'hitRegions' => $this->hitRegions,
            'heroRegions' => $this->heroRegions !== [] ? $this->heroRegions : null,
            'dismissRegions' => $this->dismissRegions !== [] ? $this->dismissRegions : null,
            'reorderRegions' => $this->reorderRegions !== [] ? $this->reorderRegions : null,
            'lottieRegions' => $this->lottieRegions !== [] ? $this->lottieRegions : null,
            'sliderRegions' => $this->sliderRegions !== [] ? $this->sliderRegions : null,
            'sheetRegions' => $this->sheetRegions !== [] ? $this->sheetRegions : null,
            'autoNavigate' => $this->autoNavigate,
            'pollAgain' => $this->pollAgainMs,
            'contentHeight' => $this->contentHeight,
            'renderTimeMs' => $this->renderTimeMs,
            'redirect' => $this->redirect,
            'scrollFollow' => $this->scrollFollow ? true : null,
            'pullToRefresh' => $this->pullToRefreshAction,
            'confetti' => $this->confetti ? true : null,
            'snackbar' => $this->snackbar,
            'transition' => $this->transition,
            'hash' => $this->stableHash(),
        ], static fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR);
    }
}
