using System.Runtime.CompilerServices;
using PhpNitroDesktop.Render;
using Xunit;

namespace PhpNitroDesktop.Render.Tests;

// Real tests against the ACTUAL compiled rust/phpnitro-render library —
// on ubuntu-latest (where this whole test project's own CI job runs,
// see .github/workflows/ci.yml's windows-desktop-protocol job) that
// means libphpnitro_render.so, the exact same artifact
// linux/tests/test_rust_render_parity.py already verifies pixel-by-pixel
// against Cairo. This is deliberately the SAME real proof
// PhpNitroDesktop.Protocol's own tests already use for the JSON layer:
// running .NET's cross-platform P/Invoke against a Linux-built .so here
// is genuine evidence the same C# code would also resolve
// phpnitro_render.dll on real Windows, not just an untested claim — this
// project has no Windows machine to test that half directly (see
// windows/README.md).
//
// Skipped (an early return, not a real xUnit "Skipped" status — same
// convention PhpNitroDesktop.Protocol.Tests.ScreenClientIntegrationTests
// already uses) if the compiled library isn't found, since that depends
// on a separate `cargo build --release` step this test project can't run
// itself.
public class RustRendererTests
{
    private static string RepoRoot([CallerFilePath] string sourceFilePath = "") =>
        Path.GetDirectoryName(Path.GetDirectoryName(Path.GetDirectoryName(sourceFilePath)!)!)!;

    private static string FixturePath(string name) =>
        Path.Combine(RepoRoot(), "packages", "ui", "tests", "Golden", "__fixtures__", name);

    private static bool TryCreateRenderer(out RustRenderer? renderer)
    {
        try
        {
            renderer = new RustRenderer();
            return true;
        }
        catch (Exception ex) when (ex is DllNotFoundException or RustRenderUnavailableException)
        {
            renderer = null;
            return false;
        }
    }

    [Fact]
    public void VersionReturnsANonEmptyString()
    {
        string version;
        try
        {
            version = RustRenderer.Version();
        }
        catch (DllNotFoundException)
        {
            return; // rust/phpnitro-render not built in this checkout
        }
        Assert.False(string.IsNullOrEmpty(version));
    }

    [Fact]
    public void RenderFrameProducesTheExpectedPixelForAPlainRedRect()
    {
        if (!TryCreateRenderer(out var renderer))
        {
            return;
        }
        using (renderer)
        {
            const string envelope = """
                {"commands":[{"type":"rect","x":0,"y":0,"width":10,"height":10,"color":"#FF0000","radius":0}],
                 "hitRegions":[],"contentHeight":10}
                """;
            var frame = renderer!.RenderFrame(envelope, 20, 20);
            Assert.NotNull(frame);
            Assert.Equal(20u, frame!.Width);
            Assert.Equal(20u, frame.Height);
            Assert.Equal(80u, frame.Stride);

            // (5, 5) sits inside the 10x10 red rect — RGBA8 premultiplied,
            // opaque red: [255, 0, 0, 255].
            var offset = (int)(5 * frame.Stride + 5 * 4);
            Assert.Equal(255, frame.Data[offset]);
            Assert.Equal(0, frame.Data[offset + 1]);
            Assert.Equal(0, frame.Data[offset + 2]);
            Assert.Equal(255, frame.Data[offset + 3]);
        }
    }

    [Fact]
    public void RenderFrameReturnsNullAndSetsLastErrorOnMalformedJson()
    {
        if (!TryCreateRenderer(out var renderer))
        {
            return;
        }
        using (renderer)
        {
            var frame = renderer!.RenderFrame("{not valid json", 10, 10);
            Assert.Null(frame);
            Assert.False(string.IsNullOrEmpty(RustRenderer.LastError()));
        }
    }

    [Fact]
    public void RenderFrameMatchesTheRealButtonWithIconGoldenFixture()
    {
        if (!TryCreateRenderer(out var renderer))
        {
            return;
        }
        var fixturePath = FixturePath("button_with_icon.json");
        if (!File.Exists(fixturePath))
        {
            return; // shouldn't happen in a normal checkout, but don't hard-fail on a path assumption
        }
        using (renderer)
        {
            var envelope = File.ReadAllText(fixturePath);
            var frame = renderer!.RenderFrame(envelope, 200, 54);
            Assert.NotNull(frame);

            // The button's dark pill background (#111827) — (10, 27) is
            // well left of both the icon (starts ~x=63) and the text
            // ("Valider", starts ~x=89), so this samples pure background
            // fill, not glyph ink.
            var offset = (int)(27 * frame!.Stride + 10 * 4);
            Assert.Equal(0x11, frame.Data[offset]);
            Assert.Equal(0x18, frame.Data[offset + 1]);
            Assert.Equal(0x27, frame.Data[offset + 2]);
        }
    }

    [Fact]
    public void RenderFrameCrossfadesBetweenTwoEnvelopes()
    {
        // Not a Cairo/Rust-style parity check (Cairo has no crossfade
        // path at all) — exercises RenderFrame's own optional
        // previousEnvelopeJson/transitionElapsedMs parameters directly
        // (see rust/phpnitro-render/src/transition.rs), the real FFI
        // round-trip through this project's own P/Invoke bindings.
        if (!TryCreateRenderer(out var renderer))
        {
            return;
        }
        using (renderer)
        {
            const string oldEnvelope = """
                {"commands":[{"type":"rect","x":0,"y":0,"width":20,"height":20,"color":"#FF0000"}],
                 "hitRegions":[],"contentHeight":20}
                """;
            const string newEnvelope = """
                {"commands":[{"type":"rect","x":0,"y":0,"width":20,"height":20,"color":"#0000FF"}],
                 "hitRegions":[],"contentHeight":20}
                """;

            // transitionElapsedMs = 0 -> eased crossfade progress is still
            // 0, i.e. only the OLD (red) envelope should be visible.
            var atStart = renderer!.RenderFrame(newEnvelope, 20, 20, previousEnvelopeJson: oldEnvelope, transitionElapsedMs: 0);
            Assert.NotNull(atStart);
            var offset = (int)(10 * atStart!.Stride + 10 * 4);
            Assert.Equal(255, atStart.Data[offset]);
            Assert.Equal(0, atStart.Data[offset + 2]);

            // Past the 220ms crossfade duration -> only the NEW (blue) envelope.
            var atEnd = renderer.RenderFrame(newEnvelope, 20, 20, previousEnvelopeJson: oldEnvelope, transitionElapsedMs: 220);
            Assert.NotNull(atEnd);
            Assert.Equal(0, atEnd!.Data[offset]);
            Assert.Equal(255, atEnd.Data[offset + 2]);
        }
    }

    [Fact]
    public void HitTestFindsTheRealButtonActionAtItsCenter()
    {
        try
        {
            RustRenderer.Version();
        }
        catch (DllNotFoundException)
        {
            return;
        }

        var fixturePath = FixturePath("button_with_icon.json");
        if (!File.Exists(fixturePath))
        {
            return;
        }
        var envelope = File.ReadAllText(fixturePath);

        // button_with_icon.json's single hitRegion covers the whole
        // 200x54 button.
        var hit = RustHitTester.HitTest(envelope, 100f, 27f);
        Assert.NotNull(hit);
        Assert.Equal("submit:demo", hit!.Action);
        Assert.Equal("null", hit.MetaJson);
    }

    [Fact]
    public void HitTestOnEmptySpaceReturnsNullWithoutThrowing()
    {
        try
        {
            RustRenderer.Version();
        }
        catch (DllNotFoundException)
        {
            return;
        }

        const string envelope = """{"commands":[],"hitRegions":[],"contentHeight":0}""";
        var hit = RustHitTester.HitTest(envelope, 999f, 999f);
        Assert.Null(hit);
    }
}
