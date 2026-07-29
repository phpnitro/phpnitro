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
 * The native-tree equivalent of Engine\Scaffold — reserves top/bottom
 * padding in the scrollable body so content doesn't render underneath the
 * AppBar/BottomNavigation, then paints those (plus an optional Fab) via
 * RenderFixed so they stay pinned to the viewport while the body scrolls.
 * $viewportHeight (not the body's own, possibly-taller, laid-out content
 * height) is what pins the bottom bar/Fab to the true screen bottom — the
 * same ?height= NativeRenderPocActivity already sends with every request.
 */
final class NativeScaffold implements RenderNode
{
    private readonly RenderNode $paddedBody;

    public function __construct(
        RenderNode $body,
        private readonly float $screenWidth,
        private readonly float $viewportHeight,
        private readonly ?NativeAppBar $appBar = null,
        private readonly ?NativeBottomNavigation $bottomNav = null,
        private readonly ?NativeFab $fab = null,
        private readonly ?NativeDrawer $drawer = null,
    ) {
        $topInset = $this->appBar !== null ? NativeAppBar::HEIGHT : 0.0;
        $bottomInset = $this->bottomNav !== null ? NativeBottomNavigation::HEIGHT : 0.0;

        $this->paddedBody = new RenderPadding(EdgeInsets::only(top: $topInset, bottom: $bottomInset), $body);
    }

    public function layout(Constraints $constraints): Size
    {
        $bodySize = $this->paddedBody->layout($constraints);

        $fixedConstraints = new Constraints($this->screenWidth, $this->screenWidth, 0.0, Constraints::INFINITY);
        $this->appBar?->layout($fixedConstraints);
        $this->bottomNav?->layout($fixedConstraints);
        $this->fab?->layout(new Constraints(0.0, Constraints::INFINITY, 0.0, Constraints::INFINITY));
        $this->drawer?->layout($fixedConstraints);

        return $bodySize;
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->paddedBody->paint($canvas, $x, $y);

        if ($this->appBar !== null) {
            $canvas->beginFixed();
            $this->appBar->paint($canvas, $x, 0.0);
            $canvas->endFixed();
        }

        if ($this->bottomNav !== null) {
            $canvas->beginFixed();
            $this->bottomNav->paint($canvas, $x, $this->viewportHeight - NativeBottomNavigation::HEIGHT);
            $canvas->endFixed();
        }

        if ($this->fab !== null) {
            $margin = Tokens::SPACE_LG;
            $bottomBarHeight = $this->bottomNav !== null ? NativeBottomNavigation::HEIGHT : 0.0;
            $canvas->beginFixed();
            $this->fab->paint(
                $canvas,
                $x + $this->screenWidth - NativeFab::SIZE - $margin,
                $this->viewportHeight - $bottomBarHeight - NativeFab::SIZE - $margin,
            );
            $canvas->endFixed();
        }

        // Painted last (on top of the AppBar/BottomNavigation/Fab too),
        // matching Flutter's Drawer covering the whole Scaffold — a real
        // side menu should sit above everything else, not just the body.
        if ($this->drawer !== null) {
            $canvas->beginFixed();
            $this->drawer->paint($canvas, $x, 0.0);
            $canvas->endFixed();
        }
    }
}
