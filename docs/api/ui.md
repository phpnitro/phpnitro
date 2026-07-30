# Package `ui`

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
