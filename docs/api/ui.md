# Package `ui`

## `Engine\AutoRouter` (class)

Discovers routable classes straight from a directory's own PSR-4 files — no hand-edited route table. A file `lib/pages/AboutPage.php` becomes reachable at ?screen=about the moment it exists, the same way Next.js's pages/ or Flutter's generated go_router routes work off file presence and naming convention instead of a registry a codegen step has to keep in sync. `phpx make:page`/`make:entity` only need to create the file; there is nothing left to wire up.

### `static discover(string $directory, string $namespace, array $stripSuffixes, string $requiredMethod): array`

the first match is removed before kebab-casing (e.g. "AboutPage" with ["Page"] -> "about"). A class left with an empty base after stripping (e.g. "HomePage" alone) maps to the 'home' key. declares this method are registered — a stray non-page/controller class dropped in the same directory is silently skipped rather than crashing dispatch.

### `static routeKey(string $className, array $stripSuffixes): string`

## `Engine\Color` (class)

Typed Tailwind color (name + shade), e.g. Color::blue(600). An escape hatch for anything this doesn't cover: pass a raw Tailwind class string via the widget's $classes parameter instead.

### `static of(string $name, int $shade): self`

### `static gray(int $shade): self`

### `static blue(int $shade): self`

### `static red(int $shade): self`

### `static green(int $shade): self`

### `static slate(int $shade): self`

### `static indigo(int $shade): self`

### `static amber(int $shade): self`

### `static white(): self`

### `static black(): self`

### `textClass(): string`

### `backgroundClass(): string`

### `toHex(): string`

Standard Tailwind v3 palette values, needed by the native render engine (packages/ui/src/Native) which draws on a real Canvas and has no CSS to hand this off to — draw commands need an actual hex color, not a class name. Only covers the shades reachable through this class's named factories; extend this table if Color::of() grows more named colors.

## `Engine\NativeDrawCommand` (class)

Phase 0/1 of docs/proposals/moteur-rendu-natif.md — a flat list of primitive draw operations in absolute pixel coordinates, serialized to JSON and replayed by NativeCanvasView.kt against a real Android Canvas (Skia at the OS level, no WebView). No layout engine yet (phase 2): every position here is explicit, not computed from a widget tree.

### `static make(): self`

### `rect(float $x, float $y, float $width, float $height, string $color, float $radius = 0.0): self`

### `text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0): self`

### `toJson(): string`

## `Engine\Native\AlertButton` (class)

The native-tree equivalent of Engine\Dialogs\AlertButton — a real android.app.AlertDialog instead of phpxDialogs.alert()'s JS confirm() shim, which is what a native app should show in the first place (no WebView chrome awkwardly hosting what looks like a browser dialog). Message/title travel in the hit region's meta; the dialog needs no server round-trip at all, so there's no PHP-side handling to match ConfirmButton's action.

### `__construct(string $message, string $label = 'Afficher un message', string $title = '')`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Align` (class)

Center generalized to any Alignment — Center stays as its own class (it's the overwhelmingly common case and reads clearer at call sites) rather than becoming `Align(..., Alignment::CENTER)` everywhere.

### `__construct(Engine\Native\Widget $child, Engine\Native\Alignment $alignment)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Alignment` (enum)

Align's positions — the native-tree equivalent of Engine\Alignment, which is a set of Tailwind flex classes and can't be reused here (there's no DOM/flexbox under a Canvas, Align computes the offset itself).

### `static cases(): array`

## `Engine\Native\Animated` (class)

The general-purpose implicit-animation wrapper — Flutter's AnimatedContainer/AnimatedOpacity/AnimatedPositioned family unified into one primitive, since they all reduce to the same question: "did the subtree under this $key look different last render? if so, ease into the new state instead of snapping." Same underlying mechanism as Hero (Canvas::beginHero()/endHero(), heroRegions, the Matrix-based flight in NativeCanvasView.kt's drawHeroTransition()) — a Hero flight across a navigation and a color/size change on the same screen are the same primitive at the Kotlin level, just used in different contexts. drawHeroTransition() additionally interpolates per-command color/geometry fields (not just the subtree's outer rect), so a background color or radius change eases too, not just position.

### `__construct(Engine\Native\Widget $child, string $key, ?Engine\Native\Curve $curve = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\AppBar` (class)

The native-tree equivalent of Engine\AppBar — meant to be handed to Scaffold, not painted directly: Scaffold is what pins it to the viewport top via Fixed while the body scrolls underneath (an AppBar painted on its own, mid-tree, would just scroll away like everything else).

### `__construct(float $width, string $title, ?string $backAction = NULL, ?Engine\Native\Widget $leading = NULL, ?Engine\Color $background = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Async` (class)

Flutter's FutureBuilder, backed by AsyncTask's real background process instead of a Dart Future. On every render this asks AsyncTask::poll() for the current state:

### `__construct(string $taskKey, string $handlerClass, string $handlerMethod, array $args, Engine\Native\Widget $loading, callable $builder, int $pollIntervalMs = 400)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\AsyncTask` (class)

The closest thing this stack has to a Dart isolate: a genuinely separate OS process (proc_open(), confirmed to work on the cross- compiled Android PHP build via a throwaway diagnostic route — a plain `sleep 2 &&` background command returned control to the request in ~1ms, not 2000ms, and its result file only existed after the fact), with no shared memory with the request that queued it. There's no closure-serialization trick here — the work is a plain class::method reference plus JSON-safe args (the same "point at a named handler, don't try to ship a live closure" shape a queue job or a cron entry uses), run by public/async-runner.php as its own process.

### `static resultPathFor(string $taskKey): string`

### `static poll(string $taskKey, string $handlerClass, string $handlerMethod, array $args = array (
)): array`

background process as a JSON string, so nothing that can't survive json_encode()/json_decode() works.

### `static reset(string $taskKey): void`

Clears a task's cached result/lock so the next poll() runs it again from scratch.

## `Engine\Native\Axis` (enum)

### `static cases(): array`

## `Engine\Native\Badge` (class)

A small count/status marker, meant to overlay a corner of whatever it's paired with (an icon, an avatar) via Stack + Positioned — see NativeWidgetsFormsScreen.php or wherever this gets used for the pairing, this class only draws the badge itself. $count === null (or 0) draws a plain dot instead of a number — the "read/unread" indicator shape, not "there are 0 of something".

### `__construct(?int $count = NULL, ?Engine\Color $background = NULL, int $max = 99)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Banner` (class)

The native-tree equivalent of Engine\ErrorBanner — stays in the normal layout flow (unlike Engine\FlashMessage, which is fixed-position and auto-dismisses via CSS; there's no client-side timer/overlay mechanism for that on this pipeline yet), so screens keep using it the same way: pass the current validation error straight through, render nothing when it's null/empty.

### `__construct(?string $message, string $icon = 'warning', ?Engine\Color $background = NULL, ?Engine\Color $foreground = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\BottomNavigation` (class)

The native-tree equivalent of Engine\BottomNavigation — meant to be handed to Scaffold, which pins it to the viewport bottom via Fixed. Each tab fires "tab:screen" rather than plain "navigate:screen" — NativeRenderPocActivity resets the whole screen stack to that single entry instead of pushing, so switching tabs repeatedly doesn't grow an ever-longer back stack the way drilling into a detail screen should.

### `__construct(float $width, array $items, string $currentScreen, ?Engine\Color $activeColor = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\BottomSheet` (class)

A modal panel anchored to the bottom edge, with a tap-outside-to-dismiss scrim — built entirely on TWO existing primitives, no new NativeCanvasView.kt code at all: Canvas::clientTabPanel() (ClientTabs' own "open"/"closed" state lives on the client, zero network round-trip to toggle — see that class's docblock) for the show/hide state itself, and Fixed (beginFixed()/endFixed()) so the scrim+sheet paint relative to the VIEWPORT rather than the scrollable body underneath, covering the whole screen regardless of how far the user has scrolled.

### `__construct(string $key, Engine\Native\Widget $content)`

### `static openAction(string $key): string`

### `static closeAction(string $key): string`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Button` (class)

A pill-radius tappable button — NativeDocumentsScreen's "Continuer" and NativeOtpScreen's "Vérifier" were both this shape hand-built from Tappable+Container+Center+Text/Flex. Pass $width explicitly for a full-width CTA (there's no "stretch to parent" shortcut without a real width in this constraint system — same reason Flutter's own ElevatedButton needs a SizedBox/Expanded wrapper to go full-width).

### `__construct(string $label, string $action, ?string $icon = NULL, ?float $width = NULL, float $height = 54.0, ?Engine\Color $background = NULL, ?Engine\Color $foreground = NULL, ?array $meta = NULL)`

see Tappable's docblock.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Canvas` (class)

The layout engine's paint target: Widget::paint() calls append flat draw commands here in absolute pixel coordinates (layout has already resolved every position by the time paint() runs), then toJson() hands the array to NativeCanvasView.kt for replay against a real Canvas.

### `setPullToRefresh(string $action): self`

Opts this screen into pull-to-refresh — NativeCanvasView.kt tracks the overscroll-at-top drag entirely client-side (a circular indicator grows under the finger, no server round-trip per frame, same "PHP never sees the gesture, only its outcome" split as Dismissible/BottomSheet's own drag handle), and only fires $action once released past threshold — indistinguishable from any other action string on the PHP side, this screen just gets rebuilt with whatever fresh data $action's handling put in $_SESSION/the database. The indicator itself keeps spinning until the refetch that follows actually lands (setCommands()), so a slow refresh still reads as "in progress", not "stuck".

### `setScrollFollow(bool $follow = true): self`

LazyList only builds/paints the items within its current scroll window, but reports the FULL virtual list height as its Size — this flag tells NativeCanvasView.kt "re-send scrollY and re-fetch as the user scrolls near the edge of what's actually loaded" instead of only ever building the first screenful once. Screens with no lazy list have nothing to prefetch and leave this false, so a normal scroll stays purely client-side with zero extra network chatter.

### `beginFixed(): self`

Everything painted between beginFixed()/endFixed() is tagged "fixed": true — NativeCanvasView.kt draws those commands a second time with no scroll translate applied, so they stay pinned to the viewport (an AppBar/BottomNavigationBar) instead of scrolling with the body. See Fixed, which is what actually calls this rather than call sites reaching for it directly.

### `endFixed(): self`

### `beginHero(string $tag, float $x, float $y, float $width, float $height, ?Engine\Native\Curve $curve = NULL): self`

The native equivalent of Engine\Hero: everything painted between beginHero($tag)/endHero() is tagged "hero": $tag, and the wrapper's own bounding box is recorded in heroRegions. When the SAME tag shows up in two consecutive renders at two different rects, NativeCanvasView.kt flies that tagged subtree from its old rect to its new one (a real FLIP transition — translate+scale via a Matrix, see drawHeroTransition()) instead of just crossfading in place like everything else. See Hero, which is what actually calls this.

### `endHero(): self`

### `dismissible(string $key, float $x, float $y, float $width, float $height, string $action): self`

The one genuinely continuous gesture in this pipeline: swiping an item registers its rect + $action here (dismissRegions), and everything painted between beginDismiss($key)/endDismiss() is tagged "dismiss": $key. NativeCanvasView.kt tracks the drag entirely client-side — translating the tagged commands live under the finger, no round-trip per frame — and only calls back to PHP with $action once the swipe commits past threshold on release (see Dismissible, drawDismissOverlay(), and NativeRenderPocActivity's onTap() handling "dismiss:" actions the same as any other). PHP never sees the gesture itself, only its outcome — the "sync only on release" split this whole primitive exists for.

### `beginDismiss(string $key): self`

### `endDismiss(): self`

### `reorderItem(string $group, string $key, float $x, float $y, float $width, float $height, string $action): self`

Drag-to-reorder — the same "PHP never sees the gesture, only its outcome" split as dismissible(), applied to reordering a whole group instead of removing one item. Each item in a Reorderable registers its own rect + stable $key under a shared $group here (reorderRegions); NativeCanvasView.kt tracks a long-press-then-drag entirely client-side — following the finger, swapping slot assignments as the dragged item crosses a neighbor's midpoint, animating the displaced items into their new slots — and only calls back once the finger lifts, with the group's action and the final key order. See Reorderable.

### `beginReorder(string $key): self`

### `endReorder(): self`

### `sheetHandle(string $key, float $x, float $y, float $width, float $height, float $sheetHeight, string $closeAction): self`

The grab strip at the top of a BottomSheet's card — same "PHP never sees the gesture, only its outcome" split as dismissible() reorderItem(), applied to a vertical drag-to-close instead of a horizontal swipe or a reorder. Registered separately from the card's own tappable content (a Fermer button, form fields...) so NativeCanvasView.kt can tell "drag the handle" apart from "tap something inside the sheet" by rect alone. $sheetHeight is the card's own full height — how far there is to drag before it counts as fully closed. $closeAction is always exactly BottomSheet::closeAction($key)'s "clientTab:{key}:0" string, carried explicitly rather than reconstructed client-side so this primitive doesn't have to know that convention itself. Fixed-tagged automatically when called inside beginFixed()/endFixed() (see tagFixed()) — BottomSheet always is, since the sheet is screen-relative like an AppBar, not scroll-relative.

### `lottieRegion(string $key, float $x, float $y, float $width, float $height, string $url, bool $loop, bool $autoplay): self`

Registers a rect for a real com.airbnb.android.lottie. LottieAnimationView overlay — Lottie's whole point is a continuous frame-by-frame animation loop, which has no equivalent in a "PHP computes one frame, Kotlin replays it" draw-command pipeline. NativeCanvasView.kt reconciles a live overlay View per registered $key against this list on every render (added when new, repositioned when it moves, removed when it disappears) — the same "overlay a real Android View, there's no Canvas concept for this" idiom VideoPlayer/MapView already use, just synced on every render instead of only on tap, since a Lottie animation is expected to autoplay rather than wait for one. See Lottie.

### `spinner(float $x, float $y, float $size, string $color, string $trackColor, float $strokeWidth): self`

An indeterminate spinner — Flutter's CircularProgressIndicator() with no `value`. Unlike CircularProgress (a determinate percent, fully described by one PHP-computed frame), a spinner has to keep rotating between renders with nobody re-fetching anything, which this request/response pipeline has no way to express as a static command. So this command carries no rotation angle at all — NativeCanvasView.kt's drawSpinnerCommand() computes it from its own clock every frame, and keeps invalidating on its own (a small continuously-repeating ValueAnimator, started/stopped based on whether any "spinner" command is present) for as long as one is on screen. See Spinner.

### `clientTabPanel(string $key, int $index, bool $initiallyActive, float $x, float $y, array $panelCommands, array $panelHitRegions): self`

A pre-rendered ClientTabs panel — the actual client-side state primitive. $panel already ran layout()/paint() into its own nested Canvas by the time this is called (see ClientTabs), so this just embeds that panel's own commands/hitRegions as one "clientPanel" command in $this->commands. NativeCanvasView.kt keeps a local `key -> selected index` map (seeded once from whichever panel has $initiallyActive, never overwritten by a later render for the same key) and draws/hit-tests only the panel matching the current selection — switching tabs is a local redraw, never a server round trip, the same way Flutter's TabBarView holds its selected index in local State rather than asking the backend which tab is open.

### `horizontalScroll(string $key, float $x, float $y, float $viewportWidth, float $viewportHeight, float $contentWidth, array $regionCommands, array $regionHitRegions): self`

A "carousel inside a list" — see HorizontalScroll's own docblock for why this needs its own command type instead of reusing clientPanel: the content here scrolls continuously along a local drag axis (clamped to [0, contentWidth - viewportWidth]) rather than switching between discrete panels on a tap. NativeCanvasView.kt keeps a local `key -> horizontal offset` map (seeded to 0), clips painting to ($x, $y, $viewportWidth, $viewportHeight), and disambiguates the drag against the outer vertical scroll the same way it already does for Dismissible's horizontal swipe.

### `verticalScroll(string $key, float $x, float $y, float $viewportWidth, float $viewportHeight, float $contentHeight, array $regionCommands, array $regionHitRegions): self`

A scrollable region nested inside the screen's own vertical scroll — the vertical counterpart to horizontalScroll() (a capped-height "recent activity" panel, a scrollable comment list embedded inside a longer page, anything that needs its OWN scroll bounded to less than the full screen). Same tradeoffs as horizontalScroll(): no virtualization (every child laid out/painted up front — for a bounded amount of content, not a long list; LazyList still owns that case), the drag itself 100% client-side. Unlike horizontalScroll()'s axis-based disambiguation against the outer page scroll (same touch, different axis — an easy split), NativeCanvasView.kt claims this region for the WHOLE gesture the moment a drag starts inside its rect (both this and the outer scroll are vertical, so there's no axis to arbitrate on) — see its own comment for why that's an intentional, real scope boundary, not full nested-scroll bubble semantics. See NestedScroll, the only real caller.

### `slider(string $name, float $x, float $y, float $width, float $height, float $trackHeight, float $thumbSize, float $value, string $trackColor, string $activeColor, string $thumbColor): self`

A draggable 0.0-1.0 value picker — self-contained draw (track, fill, thumb, all computed from $value) plus one entry in $sliderRegions so NativeCanvasView.kt can hit-test and drag-track it, the same "register a rect once, own the whole gesture client-side" split as dismissible()/reorderItem()/horizontalScroll(). Unlike those three, a slider has no arbitrary child content to wrap (beginX()/endX()) — it's always exactly a track + a thumb — so this is one call, not a begin/end pair. See Slider for the widget that calls this.

### `rawCommands(): array`

Raw command/hitRegion arrays, no envelope — clientTabPanel() embeds a whole nested Canvas's output as one command, which needs the bare arrays toJson() would otherwise wrap in {commands, hitRegions, ...}.

### `rawHitRegions(): array`

### `setContentHeight(float $height): self`

The full laid-out content height (which can exceed the viewport) — NativeCanvasView needs this to know how far there is to scroll. Called once with the root Widget::layout()'s returned Size.

### `setRenderTimeMs(float $ms): self`

How long layout()+paint() actually took, in milliseconds — the one real, measured number in the "is this fast?" question instead of an intuition. Excludes HTTP transport and Kotlin-side parse/draw on purpose (see docs/proposals/moteur-rendu-natif.md's definition of done): this isolates the PHP-side cost specifically, since that's the part this architecture is gambling on staying cheap.

### `setRedirect(string $screen): self`

Server-driven navigation — a Button's "submit:" action can change what screen the client should be on (login succeeding, most obviously) the same way LoginPage.php's onLogin() returning a path redirects the HTML pipeline's router. There's no router to re-resolve here, so this just tells NativeRenderPocActivity which screen name to swap the top of its stack to before it re-fetches — see its handling of the "redirect" field.

### `setTransition(string $type): self`

Which animation NativeCanvasView.kt's crossfade uses for THIS screen's entrance — only meaningful on a real navigation (a same-screen refetch never crossfades at all, see setCommands()'s own isNavigation branch). 'fade' (the default if never called) is the plain opacity blend this pipeline always did; 'slideLeft' 'slideRight' add a horizontal translate on top of it (a push/pop feel — call slideLeft when navigating deeper, slideRight when navigating back, though nothing enforces that convention, it's just what reads correctly to a user), 'slideUp' for a modal-style entrance. An unrecognized value falls back to 'fade' client-side rather than drawing nothing.

### `rect(float $x, float $y, float $width, float $height, ?string $color = NULL, float $radius = 0.0, ?string $borderColor = NULL, float $borderWidth = 0.0, float $elevation = 0.0, ?string $gradientFrom = NULL, ?string $gradientTo = NULL): self`

### `custom(string $type, array $data): self`

The extension point for a genuinely new native drawing this engine module has zero built-in knowledge of — a third-party package (or an app-specific widget not worth upstreaming) emits {"type": "custom:$type", ...$data}, and whoever owns the consuming Kotlin Activity registers a handler for that exact $type via NativeCanvasView.registerCustomCommandHandler() — see Sparkline, the real wired example (a tiny inline line chart NativeCanvasView.kt itself has no drawSparklineCommand() for; NativeRenderPocActivity registers the handler that actually draws it). Every OTHER Canvas method (rect(), text(), skeleton()...) is a FRAMEWORK primitive with a handler built into the engine directly; this is only for what isn't — the same "PHP decides the data, Kotlin owns the pixels" split as everything else here, just with the pixels living outside this engine module instead of inside it.

### `skeleton(float $x, float $y, float $width, float $height, string $color, float $radius = 0.0): self`

A loading placeholder that SWEEPS — same reasoning as spinner()'s own docblock: a continuously-repainting gradient has no honest way to travel as one static JSON response, so this is its own command type NativeCanvasView.kt drives with a dedicated ValueAnimator (started/stopped on demand, same idea as updateSpinnerAnimator()), not a plain "rect". See Skeleton, which is the only real caller.

### `text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0, bool $bold = false, float $letterSpacing = 0.0): self`

### `icon(float $x, float $y, float $size, int $codepoint, string $color = '#111827'): self`

A Material Icons glyph — NativeCanvasView draws it with Canvas.drawText() against the bundled MaterialIcons-Regular.ttf, exactly the technique Flutter's own Icons class uses internally (an icon is a character, not a bitmap or a hand-drawn path). $x/$y are the icon's top-left corner, same convention as rect()/text(); $codepoint comes from MaterialIcons::codepoint($name).

### `image(float $x, float $y, float $width, float $height, string $url, float $radius = 0.0): self`

A bitmap loaded asynchronously by NativeCanvasView (HTTP fetch + decode off the main thread, in-memory LRU cache keyed by URL) — the layout engine reserves the box eagerly since it can't know the image's intrinsic size ahead of a network round-trip, same constraint Image.network() has in Flutter. Nothing is drawn for this box until the bitmap finishes loading; the view just invalidates itself when it does.

### `circle(float $cx, float $cy, float $radius, ?string $color = NULL, ?string $borderColor = NULL, float $borderWidth = 0.0): self`

A raw filled/stroked circle — the primitive Container's rect()+radius can't express (a rect radius rounds corners, it doesn't produce a circle sized independently of a bounding box), and what Engine\Canvas's ->circle() needs a native equivalent for.

### `line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 1.0): self`

A raw straight line — what Engine\Canvas's ->line() needs a native equivalent for.

### `arc(float $cx, float $cy, float $radius, float $startDegrees, float $sweepDegrees, string $color, float $strokeWidth): self`

A stroked arc — what CircularProgress needs (a track ring plus a partial ring for the filled portion) since a plain circle() can't express "only part of the ring". $startDegrees/$sweepDegrees follow Android's Canvas.drawArc() convention (0° = 3 o'clock, clockwise).

### `hitRegion(float $x, float $y, float $width, float $height, string $action, ?array $meta = NULL): self`

without a second round-trip — a SelectBox's options, a dialog's message/title/confirm action.

### `autoNavigate(string $screen, int $afterMs): self`

Queues a client-side, timer-driven navigation — the same navigate:/screenStack push NativeRenderPocActivity.kt already does for a tapped Tappable, just fired by a Handler.postDelayed() instead of a touch. Used by Splash so a splash screen can send itself to its real home screen once its animation has had time to play, with no user interaction required. Only the last call in a paint pass wins — a screen only ever wants to schedule one jump.

### `pollAgain(int $afterMs): self`

Async's polling primitive: "refetch this SAME screen again in $afterMs, nothing navigates." NativeRenderPocActivity.kt deliberately never sends ?lastHash= on a poll-triggered refetch (see its own isPoll flag) even though it would otherwise qualify for the "unchanged" short-circuit — a poll's entire purpose is checking whether AsyncTask::poll() moved from pending to done, so skipping the real payload the one time it might have changed would silently stop the polling loop dead.

### `triggerConfetti(): self`

A one-shot celebratory particle burst, fired automatically the moment this screen renders — same "server-decided, client just plays it out" idiom as autoNavigate() above, just a full-screen overlay instead of a navigation. See Confetti (the widget that calls this from its own paint()) and NativeCanvasView.kt's ConfettiView/showConfettiOverlay() for the actual particle simulation, which owns its own animation clock entirely client-side — there's no per-frame server round-trip, matching spinner()'s exact reasoning for the same "continuous animation this request/response pipeline can't express as one static frame" problem.

### `showSnackbar(string $message, int $durationMs = 3000): self`

A transient bottom-anchored message, auto-dismissing after $durationMs — same "server decides it should show, client owns the actual fade-in/wait/fade-out animation with no per-frame round-trip" idiom as triggerConfetti() just above. See Snackbar (the widget that calls this from its own paint()) and NativeRenderPocActivity.kt's showSnackbarOverlay(). Only the LAST call in a paint pass wins — same "a screen only ever wants to schedule one of these" reasoning autoNavigate()'s own docblock already gives, there is no queue of multiple snackbars.

### `stableHash(): string`

A hash of everything that decides what's actually on screen — deliberately excluding renderTimeMs (differs on literally every request, real content or not) and the hash itself. index.php compares this against the client's own last-applied hash (NativeRenderPocActivity's lastAppliedHash, sent back as ?lastHash=) and skips sending the full payload at all when nothing actually changed — the same "don't do the work if the output would be identical" instinct behind React/Flutter's own diffing, just applied at the transport layer instead of a widget tree, since this architecture re-renders the whole screen server-side on every request rather than keeping a persistent tree to diff.

### `toJson(): string`

## `Engine\Native\Card` (class)

The one visual unit every native screen so far is built from: a surface-colored box, rounded corners, a thin border by default (the captures/ reference screens are flat and high-contrast, not shadow-driven — pass $elevation explicitly for the earlier gradient/shadow style instead). Formalizes what used to be an inline closure duplicated across NativeDocumentsScreen/NativeSettingsScreen.

### `__construct(Engine\Native\Widget $child, ?Engine\Native\EdgeInsets $padding = NULL, ?Engine\Color $background = NULL, ?Engine\Color $borderColor = NULL, float $borderWidth = 1.0, float $radius = 18.0, float $elevation = 0.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Center` (class)

### `__construct(Engine\Native\Widget $child)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Checkbox` (class)

The native-tree equivalent of Engine\Checkbox — the toggled value is decided server-side (the opposite of $checked) and travels in the hit region's meta as "next", so a tap can flip it with no client-side boolean state of its own; NativeRenderPocActivity's generic "toggle:" handler (shared with Toggle) just writes meta.next into fieldValues and refetches.

### `__construct(string $name, string $label, bool $checked = false, ?Engine\Color $accentColor = NULL, float $size = 22.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Chip` (class)

A small pill-shaped label — a filter tag, a category marker, a removable selection. Pure composition (Container + Text, optionally an Icon and a Tappable "x"), no engine changes needed: the same reason ProgressBar/RadioGroup never needed one either. Pass $onTap for a selectable/toggleable chip (the whole chip becomes tappable, wired to an ordinary action string, no special dispatch); pass $onDismiss for a removable one (a small "x" glyph gets its own tap target instead of eating the whole chip's).

### `__construct(string $label, bool $selected = false, ?string $onTap = NULL, ?string $onDismiss = NULL, ?Engine\Color $accentColor = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\CircularProgress` (class)

The native-tree equivalent of Engine\CircularProgress — a full-sweep track arc plus a partial-sweep filled arc on top, both via Canvas::arc(). Starting at -90° (12 o'clock) matches the HTML widget's `-rotate-90` SVG trick without needing a canvas-level rotation.

### `__construct(float $percent, float $size = 64.0, ?Engine\Color $trackColor = NULL, ?Engine\Color $color = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\ClientTabs` (class)

The framework's first genuine client-side state primitive — everything else in the render pipeline treats every interaction as "refetch the whole screen from PHP" (see NativeRenderPocActivity.kt's refetch()), which is correct for anything that touches real app/business state, but wrong for a tab switch: nothing about which of these panels is visible needs PHP at all, since every panel's content already travelled to the device in this same response. Switching tabs should feel instant and work offline, the same way Flutter's TabBarView holds its selected index in local State rather than round-tripping to a backend.

### `__construct(string $key, array $panels, int $initialIndex = 0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Confetti` (class)

A one-shot celebratory particle burst — drop this ANYWHERE in a screen's widget tree (typically behind a condition, e.g. "$orderJust completed") and it fires automatically the moment that render happens, no tap required. Renders as literally nothing itself (Size::zero()) — its only job is calling Canvas::triggerConfetti() from paint(), which just sets a flag toJson() includes as `"confetti": true`. NativeCanvasView.kt's setCommands() checks for that flag on every render and, when present, plays the actual burst — see NativeRenderPocActivity.kt's showConfettiOverlay()/ConfettiView.kt for the client-owned particle simulation, same "continuous animation this request/response pipeline can't express as one static frame, so the client owns the clock" idiom as Spinner.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

### `static triggerAction(): string`

For a manual "replay" button — e.g. new Button('🎉 Encore', Confetti::triggerAction()).

## `Engine\Native\ConfirmButton` (class)

The native-tree equivalent of Engine\Dialogs\ConfirmButton — a real android.app.AlertDialog with Confirmer/Annuler buttons; $action only reaches PHP (via the same fieldValues+refetch round-trip every other submit:-style action uses) if the user actually taps Confirmer, same "don't call the server until confirmed" guarantee the HTML pipeline's phpxDialogs.confirm() callback gives.

### `__construct(string $message, string $action, string $label = 'Confirmer', string $title = '')`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Constraints` (class)

Flutter's BoxConstraints, ported as-is: a node never picks its own size in a vacuum, its parent hands it a min/max box and the node must return a Size that fits inside it. This one rule is what makes layout a single top-down pass instead of the HTML pipeline's browser-owned reflow.

### `__construct(float $minWidth = 0.0, float $maxWidth = INF, float $minHeight = 0.0, float $maxHeight = INF)`

### `static tight(float $width, float $height): self`

### `static loose(float $maxWidth, float $maxHeight): self`

### `constrainWidth(float $width): float`

### `constrainHeight(float $height): float`

### `constrain(Engine\Native\Size $size): Engine\Native\Size`

### `loosen(): self`

Same max bounds, but minimums dropped to 0 — "you may be smaller".

### `tightenWidth(float $width): self`

### `tightenHeight(float $height): self`

### `hasBoundedWidth(): bool`

### `hasBoundedHeight(): bool`

## `Engine\Native\Container` (class)

The native-engine analogue of Container.php's HTML widget: an optionally colored/rounded/bordered box with padding, wrapping a single child. Fixed width/height (if given) win outright — a tight constraint on that axis, same as Flutter's Container. Otherwise the box hugs its (padded) child, clamped to whatever the parent allows.

### `__construct(?Engine\Native\Widget $child = NULL, ?float $width = NULL, ?float $height = NULL, ?Engine\Color $background = NULL, float $radius = 0.0, ?Engine\Color $borderColor = NULL, float $borderWidth = 0.0, float $elevation = 0.0, ?Engine\Color $gradientFrom = NULL, ?Engine\Color $gradientTo = NULL, Engine\Native\EdgeInsets $padding = \Engine\Native\EdgeInsets::__set_state(array(
   'left' => 0.0,
   'top' => 0.0,
   'right' => 0.0,
   'bottom' => 0.0,
)))`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\CrossAxisAlignment` (enum)

### `static cases(): array`

## `Engine\Native\Curve` (enum)

Flutter's Curves class, the small subset with a direct built-in Android Interpolator equivalent (see NativeCanvasView.kt's curveInterpolator()). Threaded through Hero/Animated into Canvas::beginHero()'s $curve — every hero flight already runs on one shared, linear-time ValueAnimator (NativeCanvasView.kt's startHeroTransition()), so a per-tag curve is applied by reshaping that same 0..1 progress value at draw time (drawHeroTransition()), not by running a separate animator per tag.

### `static cases(): array`

## `Engine\Native\CustomPaint` (class)

The native-tree equivalent of Engine\Canvas — a fixed-size box you paint into with absolute (box-relative) coordinates, replayed as flat Canvas commands offset by wherever layout placed this box. Single draw at paint time, same "no diffing, one-shot" contract Engine\Canvas has (it draws once at mount, not on every state change either).

### `static make(float $width, float $height): self`

### `rect(float $x, float $y, float $width, float $height, string $color, float $radius = 0.0): self`

### `circle(float $cx, float $cy, float $radius, string $color): self`

### `line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 1.0): self`

### `text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0): self`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\DatePicker` (class)

The native-tree equivalent of Engine\DatePicker — that HTML widget was already a thin wrapper (Android's WebView delegates input[type=date] to the OS date-picker dialog on its own), so this is the more direct path: tapping the field tells NativeRenderPocActivity to show a real android.app.DatePickerDialog, same dialog either pipeline ends up at. The picked value comes back as an ISO "YYYY-MM-DD" string, same shape DatePicker.php's HTML input already produced.

### `__construct(string $name, string $value = '', string $placeholder = 'jj/mm/aaaa', float $height = 52.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Dismissible` (class)

The native equivalent of Flutter's Dismissible — swipe an item off to either side to remove it. Unlike every other interaction in this pipeline, the drag itself never round-trips to PHP: NativeCanvasView.kt tracks the finger and translates this subtree's own commands live (tagged via Canvas::beginDismiss()/endDismiss()), and only calls back with $action once the swipe commits past threshold on release — PHP sees the outcome ("item 42 dismissed"), never the gesture.

### `__construct(Engine\Native\Widget $child, string $key, string $action)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Divider` (class)

A 1px-tall filled bar spanning whatever width its parent gives it — the native-tree equivalent of Engine\Divider (an <hr>-like Tailwind rule).

### `__construct(float $thickness = 1.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Drawer` (class)

The native-tree equivalent of Engine\Drawer — a full-screen scrim plus a left-anchored panel, meant to be handed to Scaffold's $drawer param (which paints it via Fixed, screen-relative, on top of everything else including the AppBar/BottomNavigation). There's no client-side open/close animation state on this pipeline (see Fixed's docblock — every paint is one-shot): the caller decides whether the drawer exists in the tree at all based on a server-known "is it open" flag (see NativeHomeScreen's $_GET['drawer_open']), same "$_GET drives what's on screen" idiom every other stateful native widget already uses.

### `__construct(float $screenWidth, float $viewportHeight, array $items, string $title = 'Menu')`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\EdgeInsets` (class)

### `__construct(float $left, float $top, float $right, float $bottom)`

### `static all(float $value): self`

### `static symmetric(float $horizontal = 0.0, float $vertical = 0.0): self`

### `static only(float $left = 0.0, float $top = 0.0, float $right = 0.0, float $bottom = 0.0): self`

### `horizontal(): float`

### `vertical(): float`

## `Engine\Native\Fab` (class)

The native-tree equivalent of Engine\FloatingActionButton — meant to be handed to Scaffold, which pins it above the bottom-right corner (above BottomNavigation if one is present) via Fixed.

### `__construct(string $icon, string $action, ?Engine\Color $background = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Fixed` (class)

Marks its subtree's draw commands/hit regions as screen-relative instead of content-relative — NativeCanvasView.kt draws the scrollable stream translated by -scrollY, then draws everything painted while Canvas::beginFixed()/endFixed() was active a second time with no translate, so it stays pinned while the body scrolls underneath. What Flutter's Scaffold gets from AppBar/BottomNavigationBar living outside the scrollable body — this is the primitive Scaffold builds that on top of, not something call sites should normally reach for directly.

### `__construct(Engine\Native\Widget $child)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Flex` (class)

Flutter's Flex algorithm (Row/Column are both this with a fixed Axis): two-pass sizing along the main axis — inflexible children first (they get as much main-axis space as they want), then whatever's left over is divided among Flexible children by flex factor. Cross axis is a single pass: STRETCH gives every child a tight constraint at the container's cross size, everything else gives a loose one and aligns after the fact.

### `__construct(Engine\Native\Axis $direction, array $children, Engine\Native\MainAxisAlignment $mainAxisAlignment = \Engine\Native\MainAxisAlignment::START, Engine\Native\CrossAxisAlignment $crossAxisAlignment = \Engine\Native\CrossAxisAlignment::START)`

### `static row(array $children, Engine\Native\MainAxisAlignment $mainAxisAlignment = \Engine\Native\MainAxisAlignment::START, Engine\Native\CrossAxisAlignment $crossAxisAlignment = \Engine\Native\CrossAxisAlignment::START): self`

### `static column(array $children, Engine\Native\MainAxisAlignment $mainAxisAlignment = \Engine\Native\MainAxisAlignment::START, Engine\Native\CrossAxisAlignment $crossAxisAlignment = \Engine\Native\CrossAxisAlignment::START): self`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Flexible` (class)

Wraps a child with a flex factor for use inside Flex, mirroring Flutter's Expanded/Flexible — a plain child (not wrapped in this) keeps its intrinsic size and flex 0, exactly like an un-Expanded child in a Flutter Row/Column.

### `__construct(Engine\Native\Widget $child, int $flex = 1)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\GestureDetector` (class)

The native-tree equivalent of Engine\GestureDetector — a real android.view.GestureDetector wired into NativeCanvasView.kt's onTouchEvent (onDoubleTap()/onFling()), not a JS dblclick/touch-delta listener. The three actions travel in the hit region's meta under fixed keys ("onDoubleClick"/"onSwipeLeft"/"onSwipeRight") that NativeCanvasView.kt's dispatchGestureAction() looks for specifically — a plain single tap inside the region does nothing, same as the HTML widget's bare `<div class="gesture-area">` with no onclick.

### `__construct(Engine\Native\Widget $child, ?string $onDoubleClick = NULL, ?string $onSwipeLeft = NULL, ?string $onSwipeRight = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Hero` (class)

The native equivalent of Engine\Hero — same "$tag" idea, but there's no DOM/CSS transition to hand this off to. The child's own bounding box (this node's layout() Size, at the x/y paint() is called with) is recorded as a heroRegion under $tag; if the SAME tag appears in the next render at a different rect, NativeCanvasView.kt flies the tagged subtree there instead of just crossfading in place (see Canvas::beginHero()/endHero() and drawHeroTransition()).

### `__construct(Engine\Native\Widget $child, string $tag, ?Engine\Native\Curve $curve = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\HorizontalScroll` (class)

A scrollable row nested inside the screen's own vertical scroll — the "carousel inside a list" case `LazyList` alone can't cover, since it only virtualizes ONE full-screen scroll axis per screen. This is the opposite trade-off: no virtualization at all (every child is laid out and painted up front, so it's for a bounded number of children — a category rail, a card carousel — not a long list), in exchange for the drag itself being 100% client-side, the same "no PHP round-trip mid-gesture" pattern `Dismissible`/`Reorderable` already use. NativeCanvasView.kt keeps a local `key -> horizontal offset` map (seeded to 0, clamped to [0, contentWidth - viewportWidth]) and disambiguates the drag direction against the outer vertical scroll the same way it already does for Dismissible's horizontal swipe.

### `__construct(string $key, array $children, float $gap = 0.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Icon` (class)

A fixed-size square icon, drawn from the real Material Icons font (2235 names — anything at https://fonts.google.com/icons, e.g. 'arrow_back', 'shopping_cart', 'notifications', 'settings'). See MaterialIcons::codepoint() for the name -> glyph lookup and NativeCanvasView.kt for how the glyph actually gets painted.

### `__construct(string $name, float $size = 24.0, string $color = '#111827')`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\IconCircle` (class)

An icon centered in a colored circle — the single most repeated shape across every native screen so far (back buttons, avatar badges, list row leading/trailing icons). Optionally tappable: pass $action and the whole circle becomes a hit region, same as wrapping it in Tappable by hand.

### `__construct(string $icon, float $diameter = 40.0, ?Engine\Color $background = NULL, ?Engine\Color $iconColor = NULL, ?string $action = NULL, ?array $meta = NULL)`

see Tappable's docblock.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Image` (class)

A network image, loaded and decoded off the main thread by NativeCanvasView (see ImageLoader.kt) and cached in memory by URL. Needs an explicit width/height — there's no synchronous way to know an image's intrinsic size at PHP layout time without fetching it, so this behaves like Flutter's Image.network() used with an explicit size: the layout engine reserves the box immediately, the bitmap fills it once the async load finishes.

### `__construct(string $url, float $width, float $height, float $radius = 0.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\ImageCircle` (class)

Flutter's CircleAvatar for a network image — Image already draws a true circle whenever it's given a square box and a radius of half that box (NativeCanvasView.kt's drawImageCommand() clips via canvas.drawRoundRect(rect, radius, radius, ...), which degenerates to a circle exactly when radius == width/2 == height/2), so this widget adds no new native capability — it's the same convenience IconCircle already is for an icon-in-a-circle: a friendly, discoverable name for a shape callers would otherwise have to remember to compose by hand.

### `__construct(string $url, float $diameter = 40.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\LazyList` (class)

Flutter's ListView.builder, adapted to a request/response pipeline that has no persistent connection to lazily build items over: PHP can't push a new item as the user's finger crosses into view mid-scroll, so instead of building all $itemCount items every request, this builds only the ones within [scrollY - buffer, scrollY + viewportHeight + buffer] — a windowed prefetch, not true per-frame laziness — and reports the FULL virtual height ($itemCount * $itemHeight) as this node's Size so NativeCanvasView.kt's scrollbar/scroll range covers the whole list even though most items were never built or painted.

### `__construct(int $itemCount, Closure $itemBuilder, float $itemHeight, float $scrollY, float $viewportHeight, float $bufferViewports = 2.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\ListTile` (class)

A leading-icon-circle + title/subtitle + trailing row, as a Card. Covers both shapes that showed up duplicated across screens: a plain trailing value (NativeSettingsScreen's "Couleur d'accent — blue") via $trailingText, or a second icon-circle (NativeDocumentsScreen's checkmark/add-button) via $trailingIcon. Pass $action to make the whole row tappable.

### `__construct(string $title, ?string $subtitle, string $leadingIcon, ?Engine\Color $leadingBackground = NULL, ?Engine\Color $leadingColor = NULL, ?string $trailingIcon = NULL, ?Engine\Color $trailingBackground = NULL, ?Engine\Color $trailingColor = NULL, ?string $trailingText = NULL, ?Engine\Color $borderColor = NULL, float $borderWidth = 1.0, ?string $action = NULL, ?Engine\Native\Widget $subtitleNode = NULL, ?array $meta = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Lottie` (class)

The native equivalent of Engine\LottieView — a real com.airbnb.android.lottie.LottieAnimationView overlaid at this widget's rect, not a hand-rolled frame-by-frame Canvas replay (Lottie's whole point — a continuous animation loop — has no equivalent in a pipeline where PHP computes one still frame per request). NativeCanvasView.kt reconciles the overlay on every render (see Canvas::lottieRegion()), so it autoplays and keeps looping across taps/scrolls the same way it would in any other native app, not just once per screen load.

### `__construct(string $url, float $width, float $height, bool $loop = true, bool $autoplay = true, ?string $key = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\MainAxisAlignment` (enum)

### `static cases(): array`

## `Engine\Native\MapView` (class)

The native-tree equivalent of Engine\Maps\MapView — a real, pannable zoomable org.osmdroid.views.MapView overlaid at the tapped rect (same "no DOM element to attach to" idiom TextField's EditText and VideoPlayer's VideoView already use), not the single static OpenStreetMap tile image NativeWidgetsMapsScreen.php showed before this. osmdroid needs no API key (unlike Mapbox/Google Maps), so this is always available regardless of what's configured in .env.

### `__construct(float $latitude, float $longitude, int $zoom, float $width, float $height = 240.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\MaterialIcons` (class)

Name -> Unicode codepoint for every glyph in Google's Material Icons font (Apache 2.0), bundled at android/app/src/main/assets/fonts/MaterialIcons-Regular.ttf. This is the same technique Flutter's own Icons class uses internally — an icon is just a single glyph drawn with Canvas.drawText() against a special font, not a bitmap or a hand-drawn path. 2235 icons, generated from https://raw.githubusercontent.com/google/material-design-icons/master/font/MaterialIcons-Regular.codepoints — regenerate this file wholesale if that upstream list changes, don't hand-edit entries.

### `static codepoint(string $name): int`

### `static has(string $name): bool`

## `Engine\Native\MediaQuery` (class)

Flutter's MediaQuery.of(context).size, minus the context — every screen's build() already receives $screenWidth/$screenHeight as explicit parameters (see any Native*Screen::build() signature), which is fine for a screen's own top-level layout decisions but means a widget several constructors deep (a reusable component that isn't a whole screen) has no way to know the viewport size unless every caller in between remembers to thread it through — exactly the pain MediaQuery.of(context) exists to avoid in Flutter.

### `static init(float $width, float $height): void`

### `static width(): float`

### `static height(): float`

### `static size(): Engine\Native\Size`

### `static isLandscape(): bool`

Matches Flutter's own convenience check — most navigation/list layouts only care about this.

## `Engine\Native\NestedScroll` (class)

A vertically-scrollable region with its OWN bounded viewport height, nested inside the screen's own page scroll — see Canvas::verticalScroll()'s docblock for the exact mechanism and its documented scope boundary (claims the whole gesture on first drag, not full nested-scroll bubble semantics). $viewportHeight is required (not inferred): unlike a normal Column, which naturally hugs its content inside a screen laid out against Constraints::INFINITY (see docs/architecture.md), a nested scroll region needs an explicit cap or there would be nothing to scroll past.

### `__construct(string $key, Engine\Native\Widget $child, float $viewportHeight)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Padding` (class)

### `__construct(Engine\Native\EdgeInsets $insets, Engine\Native\Widget $child)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\PageView` (class)

The native-tree equivalent of Engine\PageView — pages switch via tap (dot indicators + prev/next chevrons) instead of a horizontal swipe gesture. NativeCanvasView.kt's touch handling is built around one whole-screen vertical scroll region; a true nested horizontal swipe-to-page region is real, separate gesture-routing work this doesn't attempt. $onPageAction receives the target page index appended (e.g. "toggle:layout_page" with meta next carrying the index), same "$_GET drives what's on screen" idiom every other stateful native widget already uses — the caller decides the field name.

### `__construct(array $pages, int $currentPage, string $fieldName, float $height = 96.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\PasswordField` (class)

TextField(obscure: true) plus a real reveal/hide eye toggle — the one thing plain TextField couldn't do on its own, since "obscure" there is a static render-time flag with nothing to flip. The toggle reuses Checkbox/Toggle/RadioGroup's exact "toggle:" dispatch (meta.next becomes $_GET["{$name}_reveal"]) rather than anything new — a real, ordinary server round-trip, same as every other stateful widget here.

### `__construct(string $name, string $value = '', string $placeholder = '', ?float $width = NULL, float $height = 52.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Positioned` (class)

Only meaningful as a direct child of Stack — paintIn() is called by Stack once the stack's own box size is known, offsetting this child from whichever edges were given (unset edges default to 0, same as Engine\Positioned's HTML equivalent).

### `__construct(Engine\Native\Widget $child, ?float $top = NULL, ?float $right = NULL, ?float $bottom = NULL, ?float $left = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

### `paintIn(Engine\Native\Canvas $canvas, float $stackX, float $stackY, float $stackWidth, float $stackHeight, Engine\Native\Size $childSize): void`

## `Engine\Native\ProgressBar` (class)

A track + a proportionally-sized fill, both pill-rounded — takes an explicit pixel width rather than stretching to its parent, since a Stack (used here to overlay the fill on the track) doesn't resolve percentage widths on its own; call sites already know their available width the same way Button's do.

### `__construct(float $width, float $percent, float $height = 8.0, ?Engine\Color $trackColor = NULL, ?Engine\Color $fillColor = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\RadioGroup` (class)

A column of mutually-exclusive options — reuses Checkbox/Toggle's exact "toggle:" dispatch (NativeRenderPocActivity's generic `action.startsWith("toggle:")` handler already does `fieldValues[name] = meta.next; refetch()`) rather than inventing a new action/handler pair: a radio pick is really just "set this field to a specific string" the same shape as a checkbox flip, just with the value coming from $meta instead of always being the boolean opposite.

### `__construct(string $name, array $options, string $selected, ?Engine\Color $accentColor = NULL, float $size = 22.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Reorderable` (class)

The native equivalent of Flutter's ReorderableListView — a vertical stack of items the user can long-press and drag to reorder. Same split as Dismissible: NativeCanvasView.kt tracks the whole drag (long-press detection, live follow, slot-swapping as the dragged item crosses a neighbor, settle animation) entirely client-side, and only calls back with $action once the finger lifts — carrying the final key order as a comma-separated suffix (`"{$action}:key3,key1,key2"`), not a per-frame round-trip.

### `__construct(string $group, array $items, string $action)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\RichText` (class)

Flutter's Text.rich(TextSpan(...)) — mixed styles (bold, color, size, even a tappable link) flowing as ONE wrapped paragraph, not one Text per run stacked vertically. Text's word-wrap only ever had a single style for the whole string; this tokenizes every span into words tagged with their own style, then greedy-wraps across the WHOLE token stream — a span boundary is where the style changes, not where a line is allowed to break, so "bold **word** and a link" wraps exactly like a plain sentence would.

### `__construct(array $spans, float $fontSize = 16.0, string $color = '#000000')`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Router` (class)

A declarative alternative to growing public/index.php's own `match ($screen) { ... }` block one more arm at a time — register() a screen name against a closure that builds its Widget tree, and public/index.php checks has()/build() before falling through to the legacy match(). Both can coexist indefinitely: this was introduced to give NEW screens a cleaner place to register from (see 'product' in public/index.php for the real, wired example) without forcing a risky one-shot migration of the ~40 screens already dispatched the old way.

### `static register(string $screen, callable $builder): void`

### `static has(string $screen): bool`

### `static build(string $screen): ?Engine\Native\Widget`

### `static reset(): void`

Test-only — clears every registration so one test's routes can't leak into the next.

## `Engine\Native\Scaffold` (class)

The native-tree equivalent of Engine\Scaffold — reserves top/bottom padding in the scrollable body so content doesn't render underneath the AppBar/BottomNavigation, then paints those (plus an optional Fab) via Fixed so they stay pinned to the viewport while the body scrolls. $viewportHeight (not the body's own, possibly-taller, laid-out content height) is what pins the bottom bar/Fab to the true screen bottom — the same ?height= NativeRenderPocActivity already sends with every request.

### `__construct(Engine\Native\Widget $body, float $screenWidth, float $viewportHeight, ?Engine\Native\AppBar $appBar = NULL, ?Engine\Native\BottomNavigation $bottomNav = NULL, ?Engine\Native\Fab $fab = NULL, ?Engine\Native\Drawer $drawer = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\SelectBox` (class)

The native-tree equivalent of Engine\SelectBox — there's no HTML <select> to fall back on, so tapping this field tells NativeRenderPocActivity to show a real android.app.AlertDialog single-choice list (the options travel in the hit region's meta, so no second round-trip is needed to know what to offer). A pick is tracked client-side exactly like TextField's typed value — read by name from $_GET on the next request, not pushed back synchronously.

### `__construct(string $name, array $options, string $selected = '', string $placeholder = 'Choisir...', float $height = 52.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Size` (class)

### `__construct(float $width, float $height)`

### `static zero(): self`

## `Engine\Native\SizedBox` (class)

Forces a fixed size (used bare as fixed-size spacing, or wrapping a child to override the size it would otherwise have picked).

### `__construct(float $width, float $height, ?Engine\Native\Widget $child = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Skeleton` (class)

A loading placeholder with a real shimmer sweep — Canvas::skeleton() emits its own "skeleton" command type (not a plain "rect") precisely because the sweep is a continuous repaint with no honest single-frame JSON representation, the same "needs real NativeCanvasView.kt support" category Spinner/Confetti were in before they got it — see that method's own docblock for the exact mechanism (a dedicated ValueAnimator, started/stopped on demand, driving a moving gradient shader client-side).

### `__construct(float $width, float $height, float $radius = 10.0)`

### `static circle(float $diameter): self`

A circular skeleton (avatar placeholder) — same shape ImageCircle's real content would eventually take.

### `static lines(int $count, float $width, float $lineHeight = 14.0, float $gap = 8.0): Engine\Native\Widget`

Convenience for the common "N lines of skeleton text" case.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Slider` (class)

A draggable 0.0-1.0 value picker — the same "NativeCanvasView.kt tracks the whole drag client-side, PHP only sees the final value on release" split as Dismissible/Reorderable/HorizontalScroll (see Canvas::slider()). The commit on release reuses Checkbox/Toggle's existing "toggle:" action (NativeRenderPocActivity's generic handler already does `fieldValues[name] = meta.next; refetch()`) with the dragged value — formatted to 3 decimals — standing in for "next", so no new action dispatch had to be added to the Kotlin side at all; only the drag mechanics (track hit-testing, live thumb follow, gesture-priority disambiguation against vertical scroll) are new.

### `__construct(string $name, float $value, ?float $width = NULL, ?Engine\Color $trackColor = NULL, ?Engine\Color $activeColor = NULL, ?Engine\Color $thumbColor = NULL, float $trackHeight = 6.0, float $thumbSize = 22.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Snackbar` (class)

A transient bottom-anchored message — drop this ANYWHERE in a screen's widget tree (typically behind a condition, e.g. "$justSaved") and it fires automatically the moment that render happens, no tap required — same "renders as nothing itself, its only job is flagging the Canvas" shape as Confetti. See Canvas::showSnackbar() and NativeRenderPocActivity.kt's showSnackbarOverlay() for the actual fade-in/wait/fade-out animation, owned entirely client-side.

### `__construct(string $message, int $durationMs = 3000)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Sparkline` (class)

A tiny inline line chart — chosen as the REFERENCE example for Canvas::custom() precisely because it's a real, useful widget that still has no business being a built-in engine primitive (unlike ProgressBar/Slider, which every app needs). NativeCanvasView.kt has no drawSparklineCommand() of its own; NativeRenderPocActivity registers the actual drawing via registerCustomCommandHandler("sparkline", ...) — proof the extension point works end to end without touching the engine module itself, not a special case baked into it.

### `__construct(array $values, float $width, float $height, string $color = '#F97316')`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Spinner` (class)

Flutter's CircularProgressIndicator() with no `value` — an indeterminate spinner that keeps rotating on its own, unlike CircularProgress which needs a real percent recomputed every render. See Canvas::spinner()'s docblock for how a request/response pipeline expresses "keep animating with nobody asking again."

### `__construct(float $diameter = 32.0, ?Engine\Color $color = NULL, ?Engine\Color $trackColor = NULL)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Splash` (class)

A splash screen the developer composes from real widgets — a logo wrapped in Animated for a scale/fade-in, a Lottie loop, whatever the brand needs — instead of a fixed built-in layout. Wrapping that content in Splash is what turns it into an actual splash: paint() queues Canvas::autoNavigate($nextScreen, $durationMs), so NativeRenderPocActivity.kt pushes $nextScreen on its own once the timer elapses, no tap required.

### `__construct(Engine\Native\Widget $content, string $nextScreen, int $durationMs = 1800)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Stack` (class)

Children overlaid in paint order (later = on top), box sized to the largest non-positioned child. Plain children stay top-left aligned; Positioned children are offset from whichever edges they specify once the stack's own size is known (needed for e.g. a corner badge).

### `__construct(array $children)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Table` (class)

The native-tree equivalent of Engine\Table — equal-width columns (a Canvas has no intrinsic-column-width algorithm to fall back on, so this doesn't attempt one), an optional bold header row, and a divider between every row. String cells only: a Widget-cell isn't meaningful here since there's no shared Widget/Widget interface to accept one of either.

### `__construct(array $rows, array $headers = array (
))`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Tappable` (class)

Phase 3 of docs/proposals/moteur-rendu-natif.md: the layout engine has no live DOM to attach an onclick to, so a tappable region has to be registered explicitly at paint time, in the same absolute-pixel space the draw commands already use. NativeCanvasView.kt hit-tests raw touch coordinates against these rects; a hit fires this node's $action string back to PHP over HTTP, same round-trip shape as nav.js's phpxNav.submitAction() in the HTML pipeline, just without a DOM to re-render into — the whole draw-command list comes back instead.

### `__construct(Engine\Native\Widget $child, string $action, ?array $meta = NULL)`

e.g. a SelectBox's options, a dialog's message/title. Not needed for plain navigate:/submit:/device: actions. A `'label'` entry is also the escape hatch for NativeCanvasView.kt's accessibility tree (rebuildAccessibilityNodes()): it infers a TalkBack content description from nearby text commands automatically for most widgets, but an icon-only region with no visible text (a raw Tappable around an icon, say) has nothing to infer from without one.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Text` (class)

### `__construct(string $text, float $fontSize = 16.0, string $color = '#000000', bool $bold = false, float $letterSpacing = 0.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\TextField` (class)

A tappable field that opens a real native keyboard — there's no DOM `<input>` for the OS's IME to attach to on a Canvas, so tapping this box tells NativeRenderPocActivity to overlay a real android.widget. EditText at this exact rect (see its showTextInput()), positioned via the same dp coordinates every draw command already uses. The value shown here (before focus) is whatever PHP was last told about — typed input is tracked client-side and only reaches PHP when a Button with a "submit:" action collects every field's current value and sends them along with that request.

### `__construct(string $name, string $value = '', string $placeholder = '', bool $obscure = false, bool $multiline = false, float $height = 52.0, ?float $width = NULL)`

a STRETCH-aligned Flex ancestor — needed by PasswordField, which wraps this in a Stack (Stack always loosens the constraint it hands non-Positioned children, so an ordinary unconstrained TextField would shrink to its own content width instead of filling the row). Every existing call site omits this and keeps relying on STRETCH exactly as before.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\TextMetrics` (class)

Real per-character advance widths (fraction of em) for Roboto Regular and Bold — parsed directly from the font's own hmtx/cmap tables (see the generator note below), not a hand-picked bucket heuristic. This is as precise as Android's Canvas.measureText() itself for plain Latin French text: the same numbers the actual renderer uses, just computed ahead of time in PHP instead of round-tripping to native at layout time. Ligatures and complex script shaping aren't modeled (Roboto's Latin set barely uses either), and a character outside this table falls back to a reasonable average-width estimate.

### `static lineHeight(float $fontSize): float`

### `static width(string $text, float $fontSize, float $letterSpacing = 0.0, bool $bold = false): float`

### `static wrap(string $text, float $fontSize, float $maxWidth, float $letterSpacing = 0.0, bool $bold = false): array`

Greedy word-wrap: keeps adding words to the current line while it fits within $maxWidth, otherwise starts a new one. A single word longer than $maxWidth is left to overflow rather than broken mid-word (matches Flutter's default Text behavior without an explicit overflow strategy).

## `Engine\Native\TextSpan` (class)

One styled run inside a RichText — Flutter's TextSpan, minimally. Any field left null inherits RichText's own base style, the same "spans override, don't replace" idea Flutter's TextSpan.style has. An $action makes just this run tappable (RichText registers a per-word hit region for it) — a "click here" link inline in a sentence, without wrapping the whole paragraph in a single Tappable.

### `__construct(string $text, ?string $color = NULL, ?bool $bold = NULL, ?float $size = NULL, ?float $letterSpacing = NULL, ?string $action = NULL)`

## `Engine\Native\TimePicker` (class)

The native-tree equivalent of Engine\TimePicker — same reasoning as DatePicker: tapping the field opens a real android.app.TimePickerDialog. The picked value comes back as "HH:mm", same shape TimePicker.php's HTML input[type=time] already produced.

### `__construct(string $name, string $value = '', string $placeholder = '--:--', float $height = 52.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Toggle` (class)

The native-tree equivalent of Engine\SwitchToggle — same "next" meta + shared "toggle:" handler as Checkbox, just a pill track with an offset knob instead of a check mark. The knob's position is drawn directly from the current $on value (no animation — a fresh render always reflects the true state, same one-shot-paint contract every other native widget has).

### `__construct(string $name, string $label, bool $on = false, ?Engine\Color $activeColor = NULL, float $trackWidth = 44.0, float $trackHeight = 26.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Tokens` (class)

A small fixed design-token set (spacing/radius/type scale/color roles) instead of hand-picked numbers scattered across every screen — the gap between "a layout engine that works" and "an app that looks designed" is mostly this: every card, button and label pulling from the same scale instead of an ad-hoc "18px here, 20px there".

### `static init(bool $isDark): void`

### `static isDark(): bool`

### `static ink(): Engine\Color`

### `static inkSecondary(): Engine\Color`

### `static inkMuted(): Engine\Color`

### `static surface(): Engine\Color`

### `static surfaceMuted(): Engine\Color`

### `static border(): Engine\Color`

### `static success(): Engine\Color`

### `static successMuted(): Engine\Color`

### `static danger(): Engine\Color`

### `static dangerMuted(): Engine\Color`

## `Engine\Native\VideoPlayer` (class)

The native-tree equivalent of Engine\VideoPlayer — there's no DOM <video> element for a Canvas, so tapping this box tells NativeRenderPocActivity to overlay a real android.widget.VideoView (with its built-in MediaController transport bar) at this exact rect, the same "no DOM element to attach to, overlay a real Android View instead" idiom TextField's EditText already uses.

### `__construct(string $url, float $width, float $height = 200.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Widget` (interface)

One node in the layout tree. Two passes, same contract Flutter's RenderObject uses: layout() is a top-down negotiation (parent proposes a Constraints box, child returns the Size it settled on — never the other way around, which is what keeps this a single pass instead of a browser-style reflow loop), paint() is a second top-down pass that turns whatever layout() decided into absolute-pixel draw commands.

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`

## `Engine\Native\Wrap` (class)

Children flow left-to-right, wrapping to a new line once a child would overflow the available width — the native-tree equivalent of Engine\Wrap (a flex-wrap Tailwind class), needed because a Canvas has no flexbox to fall back on.

### `__construct(array $children, float $spacing = 8.0, float $runSpacing = 8.0)`

### `layout(Engine\Native\Constraints $constraints): Engine\Native\Size`

### `paint(Engine\Native\Canvas $canvas, float $x, float $y): void`
