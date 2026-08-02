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

    private float $contentHeight = 0.0;
    private ?float $renderTimeMs = null;
    private ?string $redirect = null;

    /** @var array{screen: string, afterMs: int}|null */
    private ?array $autoNavigate = null;
    private ?int $pollAgainMs = null;
    private bool $fixedMode = false;
    private ?string $heroTag = null;
    private ?string $dismissKey = null;
    private ?string $reorderKey = null;
    private bool $scrollFollow = false;

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

    public function text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0, bool $bold = false, float $letterSpacing = 0.0): self
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
        ], static fn (mixed $value): bool => $value !== null));

        return $this;
    }

    /**
     * A Material Icons glyph — NativeCanvasView draws it with
     * Canvas.drawText() against the bundled MaterialIcons-Regular.ttf,
     * exactly the technique Flutter's own Icons class uses internally
     * (an icon is a character, not a bitmap or a hand-drawn path). $x/$y
     * are the icon's top-left corner, same convention as rect()/text();
     * $codepoint comes from MaterialIcons::codepoint($name).
     */
    public function icon(float $x, float $y, float $size, int $codepoint, string $color = '#111827'): self
    {
        $this->commands[] = $this->tagFixed([
            'type' => 'icon',
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'codepoint' => $codepoint,
            'color' => $color,
        ]);

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
            'autoNavigate' => $this->autoNavigate,
            'pollAgain' => $this->pollAgainMs,
            'contentHeight' => $this->contentHeight,
            'redirect' => $this->redirect,
            'scrollFollow' => $this->scrollFollow ? true : null,
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
            'autoNavigate' => $this->autoNavigate,
            'pollAgain' => $this->pollAgainMs,
            'contentHeight' => $this->contentHeight,
            'renderTimeMs' => $this->renderTimeMs,
            'redirect' => $this->redirect,
            'scrollFollow' => $this->scrollFollow ? true : null,
            'hash' => $this->stableHash(),
        ], static fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR);
    }
}
