# Package `ui`

## `Engine\Align` (class)

### `__construct(Engine\Widget $child, string $alignment = 'items-center justify-center')`

### `static make(Engine\Widget $child, string $alignment = 'items-center justify-center'): self`

### `render(): string`

## `Engine\Alignment` (class)

Flex alignment presets — the DOM/Tailwind equivalent of Flutter's Alignment/AxisAlignment enums (there is no separate rendering-engine concept to model here, just a set of flexbox utility combinations).

## `Engine\AnimatedContainer` (class)

Flutter's AnimatedContainer tweens between two states of a rebuilt widget — impossible to replicate literally here (nav.js replaces #phpx-content's innerHTML wholesale on every action/navigation, there is no reactivity/diffing layer to detect "this is the same widget, some of its properties changed", see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md item #4 and FadeIn's docblock). `assets/js/animated-container.js` approximates it with the FLIP technique instead: `$key` is this container's stable identity across two independent server renders. On `phpx:beforeSwap` (fired with the OLD DOM still in place — see nav.js) it snapshots the computed style of every element sharing that key; once the new HTML lands, it freezes the new element at the OLD computed values, forces a reflow, then releases to the new element's real (already-rendered) values with a CSS transition — the browser interpolates background-color/width/height/border-radius/padding/opacity automatically, without this class needing to know what actually changed.

### `__construct(Engine\Widget $child, string $key, string $classes = 'p-4', ?Engine\Color $background = NULL, ?Engine\Rounded $rounded = NULL, int $durationMs = 300, string $curve = 'ease-in-out')`

### `static make(Engine\Widget $child, string $key, string $classes = 'p-4', ?Engine\Color $background = NULL, ?Engine\Rounded $rounded = NULL, int $durationMs = 300, string $curve = 'ease-in-out'): self`

### `render(): string`

## `Engine\AnimatedText` (class)

animated_text_kit equivalent — cycles a typewriter effect through a list of strings (assets/js/animated-text.js).

### `__construct(array $texts, int $typeSpeedMs = 60, int $pauseMs = 1200, int $deleteSpeedMs = 30, string $classes = 'text-lg font-medium')`

### `static make(array $texts, int $typeSpeedMs = 60, int $pauseMs = 1200, int $deleteSpeedMs = 30, string $classes = 'text-lg font-medium'): self`

### `render(): string`

## `Engine\AppBar` (class)

### `__construct(string $title, ?string $backHref = NULL, string $classes = 'gpu-layer fixed top-0 left-0 right-0 z-10 flex items-center gap-3 px-4 h-14 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700', ?Engine\Widget $leading = NULL)`

### `static make(string $title, ?string $backHref = NULL, string $classes = 'gpu-layer fixed top-0 left-0 right-0 z-10 flex items-center gap-3 px-4 h-14 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700', ?Engine\Widget $leading = NULL): self`

### `render(): string`

## `Engine\AudioPlayer` (class)

WebView's Chromium engine supports <audio> with native transport controls directly — no JS bridge needed, unlike Engine\Device\Sound (which fires a one-shot sound effect via the native MediaPlayer bridge).

### `__construct(string $src, bool $controls = true, bool $autoplay = false, bool $loop = false, string $classes = 'w-full')`

### `static make(string $src, bool $controls = true, bool $autoplay = false, bool $loop = false, string $classes = 'w-full'): self`

### `render(): string`

## `Engine\AutoSizeText` (class)

auto_size_text equivalent — shrinks font-size until the text fits its container (assets/js/autosize-text.js), down to $minSize.

### `__construct(string $content, int $minSize = 10, int $maxSize = 32, string $classes = 'whitespace-nowrap overflow-hidden')`

### `static make(string $content, int $minSize = 10, int $maxSize = 32, string $classes = 'whitespace-nowrap overflow-hidden'): self`

### `render(): string`

## `Engine\BottomNavigation` (class)

### `__construct(array $items, string $variant = 'default', ?Engine\Color $activeColor = NULL)`

### `static make(array $items, string $variant = 'default', ?Engine\Color $activeColor = NULL): self`

### `render(): string`

## `Engine\Button` (class)

### `__construct(string $label, ?string $action = NULL, string $classes = 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors', ?string $onClick = NULL, ?Engine\Color $background = NULL, ?Engine\Color $foreground = NULL)`

### `static make(string $label, ?string $action = NULL, string $classes = 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors', ?string $onClick = NULL, ?Engine\Color $background = NULL, ?Engine\Color $foreground = NULL): self`

### `render(): string`

## `Engine\Canvas` (class)

CustomPaint equivalent — a plain HTML5 <canvas> (hardware-accelerated, mature in every WebView), with a small fluent PHP builder for the common shapes instead of making every consumer hand-write canvas JS. Not a general-purpose drawing DSL: it maps a short list of ops (rect/circle/line/text) to a JSON array assets/js/canvas.js replays against CanvasRenderingContext2D once, at mount — there's no live update/animation loop here, just a one-shot drawing (see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md item #5 for what a real CustomPaint-equivalent with per-frame redraw would still need).

### `__construct(int $width = 300, int $height = 150, string $classes = '')`

### `static make(int $width = 300, int $height = 150, string $classes = ''): self`

### `rect(int $x, int $y, int $width, int $height, string $color = '#000'): self`

### `circle(int $x, int $y, int $radius, string $color = '#000'): self`

### `line(int $x1, int $y1, int $x2, int $y2, string $color = '#000', int $width = 1): self`

### `text(int $x, int $y, string $content, string $color = '#000', string $font = '14px sans-serif'): self`

### `render(): string`

## `Engine\Center` (class)

### `__construct(Engine\Widget $child)`

### `static make(Engine\Widget $child): self`

### `render(): string`

## `Engine\Checkbox` (class)

### `__construct(string $name, string $label, bool $checked = false, ?Engine\Color $accentColor = NULL)`

### `static make(string $name, string $label, bool $checked = false, ?Engine\Color $accentColor = NULL): self`

### `render(): string`

## `Engine\CircularProgress` (class)

Circular progress indicator (plain SVG + stroke-dasharray, no JS/canvas). $value (0-100) is computed server-side, same live-update pattern as ProgressBar.

### `__construct(float $value, int $size = 64, string $trackColor = 'text-gray-200 dark:text-gray-700', string $color = '#2563EB')`

### `static make(float $value, int $size = 64, string $trackColor = 'text-gray-200 dark:text-gray-700', string $color = '#2563EB'): self`

### `render(): string`

## `Engine\Color` (class)

Typed Tailwind color (name + shade), e.g. Color::blue(600). An escape hatch for anything this doesn't cover: pass a raw Tailwind class string via the widget's $classes parameter instead.

### `static of(string $name, int $shade): self`

### `static gray(int $shade): self`

### `static blue(int $shade): self`

### `static red(int $shade): self`

### `static green(int $shade): self`

### `textClass(): string`

### `backgroundClass(): string`

## `Engine\Column` (class)

### `__construct(array $children, string $classes = 'flex flex-col gap-3 p-4')`

### `static make(array $children, string $classes = 'flex flex-col gap-3 p-4'): self`

### `render(): string`

## `Engine\Container` (class)

### `__construct(Engine\Widget $child, string $classes = 'p-4', ?Engine\Color $background = NULL, ?Engine\Rounded $rounded = NULL)`

### `static make(Engine\Widget $child, string $classes = 'p-4', ?Engine\Color $background = NULL, ?Engine\Rounded $rounded = NULL): self`

### `render(): string`

## `Engine\Csrf` (class)

### `static token(): string`

### `static field(): string`

### `static verify(?string $token): bool`

## `Engine\Curves` (class)

Named easing curves, passed as the `curve` argument to animated widgets (see FadeIn) — CSS timing-function strings under Flutter-familiar names, not a real animatable-value curve system (there's no property tweening here, only CSS keyframes/transitions).

## `Engine\DatePicker` (class)

input[type=date] — Android's WebView delegates this to the OS native date-picker dialog, so no custom widget or JS bridge is needed here.

### `__construct(string $name, string $label = '', string $value = '', string $min = '', string $max = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500')`

### `static make(string $name, string $label = '', string $value = '', string $min = '', string $max = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500'): self`

### `render(): string`

## `Engine\Divider` (class)

### `__construct(string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2')`

### `static make(string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2'): self`

### `render(): string`

## `Engine\Drawer` (class)

Off-canvas side navigation. Zero JavaScript: a hidden checkbox + Tailwind `peer` variants drive the open/close animation and the overlay, the same trick as a "CSS-only" hamburger menu. Pair with DrawerToggle (usually passed as AppBar's $leading) to open it.

### `__construct(array $items, string $title = 'Menu')`

### `static make(array $items, string $title = 'Menu'): self`

### `render(): string`

## `Engine\DrawerToggle` (class)

Hamburger button that opens a Drawer — pass as AppBar's $leading. Purely a <label for="phpx-drawer">, no JS: toggling the drawer's hidden checkbox is what the browser already does natively for form controls.

### `static make(): self`

### `render(): string`

## `Engine\Dropdown` (class)

Click-to-open menu using the native <details>/<summary> element — no JavaScript, no state to manage, works everywhere (accessible by default, closes on outside click/tap in every real browser and WebView).

### `__construct(string $label, array $items, string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg')`

### `static make(string $label, array $items, string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg'): self`

### `render(): string`

## `Engine\ErrorBanner` (class)

A distinct concern from Flash/FlashMessage: Flash is one-shot (set before a redirect, shown once on the next page, then gone) and fixed-position — wrong semantics for a validation error, which must stay visible in the normal page flow across every failed submit until the user actually fixes it. Screens keep this in their own $state (the same way CheckoutPage already tracks $state['error']) and pass it straight through: ErrorBanner::make($this->state['error']).

### `__construct(?string $message, string $classes = 'flex items-start gap-2 bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 rounded-lg p-3 text-sm')`

### `static make(?string $message, string $classes = 'flex items-start gap-2 bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 rounded-lg p-3 text-sm'): self`

### `render(): string`

## `Engine\FadeIn` (class)

Fades (and slides slightly upward) a child in on mount, via a CSS keyframe animation (see .phpx-animate in assets/css/input.css) — no JS. A keyframe animation always plays when its element is inserted into the DOM, which is what makes this work through nav.js's innerHTML swaps and StreamBuilder/FutureBuilder's fragment replacements without any mutation-observer or restart logic.

### `__construct(Engine\Widget $child, int $durationMs = 400, int $delayMs = 0, string $curve = 'ease-out', int $distancePx = 12)`

### `static make(Engine\Widget $child, int $durationMs = 400, int $delayMs = 0, string $curve = 'ease-out', int $distancePx = 12): self`

### `render(): string`

## `Engine\Flash` (class)

One-shot session message ("Ajouté au panier", "Erreur de connexion"...) set from an onXxx() handler before a redirect, displayed once by FlashMessage on the next page and cleared immediately after being read.

### `static set(string $message, string $type = 'success'): void`

### `static consume(): ?array`

## `Engine\FlashMessage` (class)

Renders (and consumes) the pending Flash message, if any, as a toast that fades in then auto-dismisses via a pure CSS animation — no JS timer. Place it once per screen, anywhere in the tree (it's fixed-position).

### `__construct()`

### `static make(): self`

### `render(): string`

## `Engine\FloatingActionButton` (class)

### `__construct(string $label, ?string $action = NULL, ?string $classes = NULL, string $ariaLabel = '', ?Engine\Color $background = NULL)`

### `static make(string $label, ?string $action = NULL, ?string $classes = NULL, string $ariaLabel = '', ?Engine\Color $background = NULL): self`

### `render(): string`

## `Engine\FontWeight` (enum)

### `static cases(): array`

### `static from(string|int $value): static`

### `static tryFrom(string|int $value): ?static`

## `Engine\Form` (class)

Groups input widgets and a submit button into one <form> posting a named action. The screen receives every input value (by input name) as the $data array of its onXxx(array $data) handler.

### `__construct(array $children, string $action, string $classes = 'flex flex-col gap-3')`

### `static make(array $children, string $action, string $classes = 'flex flex-col gap-3'): self`

### `render(): string`

## `Engine\FutureBuilder` (class)

One-shot async: fetches $endpoint once on page load and swaps the result in — unlike StreamBuilder, it never re-polls. $endpoint is a route returning a pre-rendered HTML fragment, same convention as StreamBuilder (PHP stays the single source of truth for rendering).

### `__construct(string $endpoint, Engine\Widget $loading, string $classes = '')`

### `static make(string $endpoint, Engine\Widget $loading, string $classes = ''): self`

### `render(): string`

## `Engine\GestureDetector` (class)

### `__construct(Engine\Widget $child, ?string $onDoubleClick = NULL, ?string $onSwipeLeft = NULL, ?string $onSwipeRight = NULL, ?string $onPinch = NULL, ?string $onRotate = NULL, string $classes = '')`

### `static make(Engine\Widget $child, ?string $onDoubleClick = NULL, ?string $onSwipeLeft = NULL, ?string $onSwipeRight = NULL, ?string $onPinch = NULL, ?string $onRotate = NULL, string $classes = ''): self`

### `render(): string`

## `Engine\GoogleTranslate` (class)

Embeds Google's own Website Translator widget (the standard translate.google.com/translate_a/element.js embed) — a dropdown that translates the rendered page client-side. Requires network access, which the WebView already has (INTERNET permission).

### `__construct(string $pageLanguage = 'fr', string $includedLanguages = 'fr,en,es,pt,ar')`

### `static make(string $pageLanguage = 'fr', string $includedLanguages = 'fr,en,es,pt,ar'): self`

### `render(): string`

## `Engine\Hero` (class)

Flutter's Hero flies a shared element from its position/size on one screen to its position/size on the next. `$tag` identifies the SAME conceptual element across two independent server renders (e.g. a product thumbnail on a list page and the same product's photo on its detail page) — `assets/js/hero.js` uses the FLIP technique (see AnimatedContainer's docblock for the general approach) on `getBoundingClientRect()` instead of computed style: it records the element's on-screen rect before the swap, then applies a CSS `transform` (translate + scale) that makes the newly-inserted element with the same tag instantly LOOK like it's still at the old rect, forces a reflow, and releases to `transform: none` with a transition — the browser animates the element visually flying from its old position/size to its new one.

### `__construct(Engine\Widget $child, string $tag, int $durationMs = 300, string $curve = 'ease-in-out')`

### `static make(Engine\Widget $child, string $tag, int $durationMs = 300, string $curve = 'ease-in-out'): self`

### `render(): string`

## `Engine\Html` (class)

Passthrough for pre-built HTML/JS fragments (script tags, output elements) that don't belong to a specific button — the escape hatch a service class needs to compose script/element snippets into a widget tree without being a "widget" that renders its own opinionated markup. Escaping is the caller's responsibility, same as any service class that already builds its own escaped HTML/JS (see Engine\Device\, Engine\Payments\).

### `__construct(string $html)`

### `static raw(string $html): self`

### `render(): string`

## `Engine\Icon` (class)

Minimal inline-SVG icon set — no external font/CDN request, no reproduced third-party path data (avoids silently-wrong bezier curves misremembered from a icon library). Each icon is a small, precise geometric construction we fully control and can verify by rendering it.

### `static home(string $classes = 'w-5 h-5'): string`

### `static settings(string $classes = 'w-5 h-5'): string`

### `static camera(string $classes = 'w-5 h-5'): string`

### `static bolt(string $classes = 'w-5 h-5'): string`

### `static rocket(string $classes = 'w-5 h-5'): string`

### `static link(string $classes = 'w-5 h-5'): string`

### `static hamburger(string $classes = 'w-6 h-6'): string`

### `static chevronDown(string $classes = 'w-4 h-4'): string`

### `static cart(string $classes = 'w-5 h-5'): string`

### `static user(string $classes = 'w-5 h-5'): string`

### `static warning(string $classes = 'w-5 h-5'): string`

### `static check(string $classes = 'w-5 h-5'): string`

### `static close(string $classes = 'w-5 h-5'): string`

### `static search(string $classes = 'w-5 h-5'): string`

### `static heart(string $classes = 'w-5 h-5'): string`

### `static star(string $classes = 'w-5 h-5'): string`

### `static trash(string $classes = 'w-5 h-5'): string`

### `static edit(string $classes = 'w-5 h-5'): string`

### `static download(string $classes = 'w-5 h-5'): string`

### `static upload(string $classes = 'w-5 h-5'): string`

### `static share(string $classes = 'w-5 h-5'): string`

### `static calendar(string $classes = 'w-5 h-5'): string`

### `static clock(string $classes = 'w-5 h-5'): string`

### `static mail(string $classes = 'w-5 h-5'): string`

### `static phone(string $classes = 'w-5 h-5'): string`

### `static lock(string $classes = 'w-5 h-5'): string`

### `static bell(string $classes = 'w-5 h-5'): string`

### `static plus(string $classes = 'w-5 h-5'): string`

### `static minus(string $classes = 'w-5 h-5'): string`

### `static chevronLeft(string $classes = 'w-4 h-4'): string`

### `static chevronRight(string $classes = 'w-4 h-4'): string`

### `static chevronUp(string $classes = 'w-4 h-4'): string`

### `static arrowLeft(string $classes = 'w-5 h-5'): string`

### `static arrowRight(string $classes = 'w-5 h-5'): string`

### `static info(string $classes = 'w-5 h-5'): string`

### `static eye(string $classes = 'w-5 h-5'): string`

## `Engine\IconButton` (class)

A button showing only an icon (see Icon::* for the built-in set) — same action/no-action/onClick behavior as Button, just icon content instead of a text label.

### `__construct(string $icon, ?string $action = NULL, string $classes = 'p-2 rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700', string $ariaLabel = '', ?string $onClick = NULL)`

### `static make(string $icon, ?string $action = NULL, string $classes = 'p-2 rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700', string $ariaLabel = '', ?string $onClick = NULL): self`

### `render(): string`

## `Engine\Image` (class)

### `__construct(string $src, string $alt = '', string $classes = 'max-w-full h-auto')`

### `static make(string $src, string $alt = '', string $classes = 'max-w-full h-auto'): self`

### `static network(string $url, string $alt = '', string $classes = 'max-w-full h-auto'): self`

Flutter-parity alias for a remote URL (Image.network / NetworkImage) — identical to make(), src is already just a URL or local path either way.

### `render(): string`

## `Engine\InfiniteScrollList` (class)

infinite_scroll_pagination equivalent. $endpoint gets "?page=N" appended by assets/js/infinite-scroll.js as the user scrolls near the bottom (IntersectionObserver on a sentinel element); the endpoint returns raw HTML for that page (empty body = no more pages), same "PHP renders, JS just swaps/appends" idiom as StreamBuilder/FutureBuilder.

### `__construct(string $endpoint, array $initialItems, string $classes = 'flex flex-col gap-2')`

### `static make(string $endpoint, array $initialItems, string $classes = 'flex flex-col gap-2'): self`

### `render(): string`

## `Engine\Link` (class)

### `__construct(string $label, string $href, string $classes = 'text-blue-600 hover:underline')`

### `static make(string $label, string $href, string $classes = 'text-blue-600 hover:underline'): self`

### `render(): string`

## `Engine\LinkWrap` (class)

Like Link, but wraps an arbitrary Widget (a whole card, not just text) in an <a href>. Useful for "the entire product card is clickable" layouts.

### `__construct(Engine\Widget $child, string $href, string $classes = 'block')`

### `static make(Engine\Widget $child, string $href, string $classes = 'block'): self`

### `render(): string`

## `Engine\ListView` (class)

### `__construct(array $children, string $classes = 'flex flex-col divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden', string $itemClasses = 'px-4 py-3')`

### `static make(array $children, string $classes = 'flex flex-col divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden', string $itemClasses = 'px-4 py-3'): self`

### `render(): string`

## `Engine\LocationButton` (class)

### `__construct(string $label = 'Localiser', string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg')`

### `static make(string $label = 'Localiser', string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg'): self`

### `render(): string`

## `Engine\LottieView` (class)

Lottie equivalent — plays a bundled Lottie JSON animation via the vendored lottie-web (assets/js/vendor/lottie.min.js, MIT, see assets/js/vendor/NOTICE.md), not a CDN — works fully offline. $src is a path to a .json animation file served by this app (e.g. "/assets/animations/success.json").

### `__construct(string $src, bool $loop = true, bool $autoplay = true, string $classes = 'w-32 h-32')`

### `static make(string $src, bool $loop = true, bool $autoplay = true, string $classes = 'w-32 h-32'): self`

### `render(): string`

## `Engine\Margin` (class)

### `__construct(Engine\Widget $child, string $classes = 'm-4')`

### `static make(Engine\Widget $child, string $classes = 'm-4'): self`

### `render(): string`

## `Engine\Navigation` (class)

Detects requests coming from nav.js's fetch-based interception of link clicks and form submits, as opposed to a real browser navigation (or a plain HTML form submit with JS disabled/unavailable) — nav.js sets this header on every request it makes so the front controller knows whether to answer with a full HTML document or just the rendered widget tree.

### `static isPartial(): bool`

## `Engine\Navigator` (class)

There is no in-memory route stack here — every "navigation" is a real URL and a real HTTP redirect (see Screen::handle(), which turns an onXxx() return value into a Location header). Navigator is naming sugar over that reality, mirroring Flutter's push/pop vocabulary without pretending to be a route stack.

### `static to(string $path): string`

Return from an onXxx() handler to redirect to $path (Navigator.push equivalent).

### `static back(string $fallback = '/'): string`

Return from an onXxx() handler to redirect back to the previous page (Navigator.pop equivalent).

### `static link(string $label, string $path, string $classes = 'text-blue-600 hover:underline'): Engine\Link`

## `Engine\Padding` (class)

Flutter's Padding takes an EdgeInsets; here $classes is any Tailwind spacing utility ('p-4', 'px-6 py-2', 'pt-8'...) — same idea, DOM-native syntax instead of a dedicated value object.

### `__construct(Engine\Widget $child, string $classes = 'p-4')`

### `static make(Engine\Widget $child, string $classes = 'p-4'): self`

### `render(): string`

## `Engine\PageRenderer` (class)

Emits either a full HTML document, or — for nav.js-intercepted requests — a small JSON envelope carrying just the rendered widget tree. Same "PHP renders, JS only swaps" split StreamBuilder/FutureBuilder already use for their own fragments, just wired to normal navigation/actions instead of polling, which is what lets normal link clicks and form submits update the page without a full reload.

### `static redirectExternally(string $url): never`

A redirect target can be an external URL (e.g. a hosted Stripe Checkout session) — that can't be resolved through the local Router or rendered as a fragment. In partial mode, tell nav.js to do a real top-level navigation there instead of trying to swap it in.

### `static isExternalUrl(string $target): bool`

### `static render(Engine\Widget $widgetTree, string $path, string $appName, array $scripts, bool $debug, ?Engine\Widget $persistentNav = NULL, bool $showBottomNav = true, ?Engine\Screen $screen = NULL): never`

$persistentNav is rendered exactly once per HTTP request and lives OUTSIDE #phpx-content, the region nav.js ever swaps — it is never part of $widgetTree, and never appears in the partial JSON's "html" either. Only its visibility changes per route, via the "showBottomNav" flag (both here and in the partial payload) — nav.js toggles a `hidden` class instead of ever destroying/recreating the nav bar, which is what causes the jump a full node replacement would.

## `Engine\PageView` (class)

Flutter's PageView is a swipeable page carousel with snap-to-page behavior — the DOM equivalent is a horizontally scrollable flex row with CSS scroll-snap, native to WebView, no JS needed for the swipe gesture itself.

### `__construct(array $pages, string $classes = 'flex overflow-x-auto snap-x snap-mandatory w-full', string $pageClasses = 'snap-center shrink-0 w-full')`

### `static make(array $pages, string $classes = 'flex overflow-x-auto snap-x snap-mandatory w-full', string $pageClasses = 'snap-center shrink-0 w-full'): self`

### `render(): string`

## `Engine\Positioned` (class)

Only meaningful as a direct child of Stack — gives that child an explicit offset (top/right/bottom/left, in pixels) instead of stretching to fill the stack. Inline style rather than Tailwind classes: these are arbitrary runtime values Tailwind's build-time class scanner can never see (unlike Container's Color/Rounded, which only ever emit a small fixed set of class names that already exist elsewhere in the compiled stylesheet).

### `__construct(Engine\Widget $child, ?int $top = NULL, ?int $right = NULL, ?int $bottom = NULL, ?int $left = NULL)`

### `static make(Engine\Widget $child, ?int $top = NULL, ?int $right = NULL, ?int $bottom = NULL, ?int $left = NULL): self`

### `render(): string`

## `Engine\ProgressBar` (class)

Linear progress bar. $value (0-100) is computed server-side — combine with StreamBuilder to make it update live without a page reload (see OrderConfirmationPage in examples/ecom for the same pattern applied to a status label).

### `__construct(float $value, string $classes = 'w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden', ?string $barColor = NULL)`

### `static make(float $value, string $classes = 'w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden', ?string $barColor = NULL): self`

### `render(): string`

## `Engine\Rounded` (enum)

### `static cases(): array`

### `static from(string|int $value): static`

### `static tryFrom(string|int $value): ?static`

## `Engine\Router` (class)

### `__construct(array $routes)`

### `resolve(string $path): array`

## `Engine\Row` (class)

### `__construct(array $children, string $classes = 'flex flex-row gap-3 items-center')`

### `static make(array $children, string $classes = 'flex flex-row gap-3 items-center'): self`

### `render(): string`

## `Engine\Scaffold` (class)

Standard screen structure: optional fixed AppBar on top, scrollable body with the right paddings, FAB and Drawer. The bottom nav itself is no longer rendered here — it's a single persistent widget PageRenderer places once outside every screen's own tree (see Screen::showsBottomNav()) — $hasBottomNav only tells this Scaffold whether to reserve room for it (pb-24 vs pb-4) so content doesn't render underneath the fixed bar.

### `__construct(Engine\Widget $body, ?Engine\Widget $appBar = NULL, bool $hasBottomNav = false, ?Engine\Widget $floatingActionButton = NULL, ?Engine\Widget $drawer = NULL)`

### `static make(Engine\Widget $body, ?Engine\Widget $appBar = NULL, bool $hasBottomNav = false, ?Engine\Widget $floatingActionButton = NULL, ?Engine\Widget $drawer = NULL): self`

### `render(): string`

## `Engine\Screen` (class)

### `__construct(array $params = array (
))`

### `build(): Engine\Widget`

### `state(): array`

Read-only view of this screen's current state — used by PageRenderer's DevTools panel to show live state instead of just route/timing. Not for app logic (that's what $this->state is for inside the screen itself).

### `showsBottomNav(): bool`

Whether the persistent bottom nav (rendered once by PageRenderer, see index.php) should be visible on this screen — override to false for screens like login/checkout that don't want it at all.

### `handle(string $action, array $data = array (
)): ?string`

Runs the onXxx handler for $action, passing it the submitted form values. The handler may return a path (string) to redirect to; returning null redirects back to the current page.

## `Engine\SelectBox` (class)

### `__construct(string $name, array $options, string $selected = '', string $label = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100', string $error = '')`

### `static make(string $name, array $options, string $selected = '', string $label = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100', string $error = ''): self`

### `render(): string`

## `Engine\SingleScrollView` (class)

### `__construct(Engine\Widget $child, string $classes = 'overflow-y-auto max-h-screen')`

### `static make(Engine\Widget $child, string $classes = 'overflow-y-auto max-h-screen'): self`

### `render(): string`

## `Engine\Stack` (class)

Overlays children on top of each other. A plain (non-Positioned) child stretches to fill the stack (absolute inset-0); a Positioned child renders at its own explicit offset instead — the same Stack/Positioned pairing Flutter uses, built here on plain CSS absolute positioning, not a dedicated layout/paint engine (see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md item #5).

### `__construct(array $children, string $classes = 'relative')`

### `static make(array $children, string $classes = 'relative'): self`

### `render(): string`

## `Engine\Stepper` (class)

Stateless — just draws the step header (done/current/upcoming), the body for the current step, and Back/Next buttons. The actual state (which step is active, data collected per step) belongs to the calling Screen's $state, exactly like CheckoutPage accumulates validation state across several POSTs — this widget has no session/state of its own.

### `__construct(int $currentStep, int $totalSteps, array $stepLabels, Engine\Widget $body, ?string $backAction = NULL, ?string $nextAction = NULL, string $backLabel = 'Retour', string $nextLabel = 'Suivant')`

### `static make(int $currentStep, int $totalSteps, array $stepLabels, Engine\Widget $body, ?string $backAction = NULL, ?string $nextAction = NULL, string $backLabel = 'Retour', string $nextLabel = 'Suivant'): self`

### `render(): string`

## `Engine\StreamBuilder` (class)

Polls $endpoint (a route that returns a pre-rendered HTML fragment — plain PHP output, not JSON) every $intervalMs and swaps it into the DOM. Keeps PHP as the single source of truth for rendering: the client never reimplements widget logic in JS, it just displays whatever HTML the server sends on each poll.

### `__construct(string $endpoint, Engine\Widget $initial, int $intervalMs = 2000, string $classes = '')`

### `static make(string $endpoint, Engine\Widget $initial, int $intervalMs = 2000, string $classes = ''): self`

### `render(): string`

## `Engine\SwitchToggle` (class)

### `__construct(string $name, string $label, bool $on = false, ?Engine\Color $activeColor = NULL)`

### `static make(string $name, string $label, bool $on = false, ?Engine\Color $activeColor = NULL): self`

### `render(): string`

## `Engine\Table` (class)

### `__construct(array $rows, array $headers = array (
), string $border = 'border border-collapse divide-y divide-x divide-gray-300 dark:divide-gray-700 dark:border-gray-700', string $classes = 'w-full text-left text-sm text-gray-700 dark:text-gray-300')`

### `static make(array $rows, array $headers = array (
), string $border = 'border border-collapse divide-y divide-x divide-gray-300 dark:divide-gray-700 dark:border-gray-700', string $classes = 'w-full text-left text-sm text-gray-700 dark:text-gray-300'): self`

### `render(): string`

## `Engine\TableBorder` (class)

Flutter's TableBorder describes per-edge border painting on a table; here it's a set of Tailwind divide-x/divide-y presets applied to the <table> element (divide-* draws borders between children natively, no arbitrary per-cell selectors needed).

## `Engine\Text` (class)

### `__construct(string $content, string $classes = 'text-base text-gray-900 dark:text-gray-100', ?Engine\TextSize $size = NULL, ?Engine\FontWeight $weight = NULL, ?Engine\Color $color = NULL)`

### `static make(string $content, string $classes = 'text-base text-gray-900 dark:text-gray-100', ?Engine\TextSize $size = NULL, ?Engine\FontWeight $weight = NULL, ?Engine\Color $color = NULL): self`

### `render(): string`

## `Engine\TextField` (class)

### `__construct(string $name, string $label = '', string $value = '', string $type = 'text', string $placeholder = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500', string $error = '')`

### `static make(string $name, string $label = '', string $value = '', string $type = 'text', string $placeholder = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500', string $error = ''): self`

### `render(): string`

## `Engine\TextSize` (enum)

### `static cases(): array`

### `static from(string|int $value): static`

### `static tryFrom(string|int $value): ?static`

## `Engine\Textarea` (class)

### `__construct(string $name, string $label = '', string $value = '', string $placeholder = '', int $rows = 4, string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500', string $error = '')`

### `static make(string $name, string $label = '', string $value = '', string $placeholder = '', int $rows = 4, string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500', string $error = ''): self`

### `render(): string`

## `Engine\Theme` (class)

ThemeData equivalent — a process-wide, settable default palette. Defaults match what every widget already hardcoded (blue-600 primary, gray-600 secondary) so leaving it untouched changes nothing; call Theme::setPrimary() etc. once (e.g. at app bootstrap) to recolor every widget that reads it (FloatingActionButton, ProgressBar, CircularProgress, BottomNavigation's active tab, Checkbox, SwitchToggle) without touching each call site.

### `static setPrimary(Engine\Color $color): void`

### `static setSecondary(Engine\Color $color): void`

### `static primary(): Engine\Color`

### `static secondary(): Engine\Color`

### `static reset(): void`

Test isolation only — a real app sets its theme once at bootstrap and never needs to unset it.

## `Engine\ThemeToggle` (class)

### `__construct(string $classes = 'text-sm text-gray-500 dark:text-gray-400 hover:underline')`

### `static make(string $classes = 'text-sm text-gray-500 dark:text-gray-400 hover:underline'): self`

### `render(): string`

## `Engine\TimePicker` (class)

input[type=time] — same reasoning as DatePicker: WebView delegates to the OS native time-picker dialog.

### `__construct(string $name, string $label = '', string $value = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500')`

### `static make(string $name, string $label = '', string $value = '', string $classes = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500'): self`

### `render(): string`

## `Engine\Translator` (class)

Key-based i18n helper (server-side, no network needed) — distinct from GoogleTranslate, which machine-translates the rendered page client-side. Use Translator for strings you control the wording of.

### `static load(string $locale, array $translations): void`

### `static setLocale(string $locale): void`

### `static locale(): string`

### `static t(string $key, array $params = array (
)): string`

## `Engine\VideoPlayer` (class)

### `__construct(string $src, bool $controls = true, bool $autoplay = false, bool $loop = false, string $poster = '', string $classes = 'w-full rounded-lg')`

### `static make(string $src, bool $controls = true, bool $autoplay = false, bool $loop = false, string $poster = '', string $classes = 'w-full rounded-lg'): self`

### `render(): string`

## `Engine\Widget` (class)

### `render(): string`

## `Engine\Wrap` (class)

Like Row, but children wrap onto a new line instead of overflowing — Tailwind's flex-wrap, the same idea as Flutter's Wrap.

### `__construct(array $children, string $classes = 'flex flex-wrap gap-3')`

### `static make(array $children, string $classes = 'flex flex-wrap gap-3'): self`

### `render(): string`
