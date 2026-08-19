using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Imaging;
using System.Runtime.InteropServices;
using System.Windows.Forms;
using PhpNitroDesktop.Protocol;
using PhpNitroDesktop.Render;

namespace PhpNitroDesktop.App;

/// The Windows counterpart of linux/phpnitro_desktop/app.py's ScreenWindow —
/// a single Form that fetches one screen at a time from a real `php -S`
/// (via ScreenClient), renders it through the shared Rust engine
/// (RustRenderer — there is no separate native GDI+/Direct2D renderer to
/// fall back to on this platform, unlike Linux/macOS/iOS/Android, which
/// each also have a fully native Canvas port; Rust is Windows' ONLY
/// rendering path), and dispatches clicks through ScreenNavigation.Reduce
/// exactly like every other platform's own shell.
public sealed class ScreenForm : Form
{
    private const int DefaultWidth = 390;
    private const int DefaultHeight = 844;

    private readonly ScreenClient _client;
    private readonly RustRenderer _renderer;
    private List<string> _stack;
    private string? _rawJson;

    public ScreenForm(string host, int port, string initialScreen)
    {
        _client = new ScreenClient(host, port);
        _renderer = new RustRenderer();
        _stack = new List<string> { initialScreen };

        Text = "PhpNitro";
        ClientSize = new Size(DefaultWidth, DefaultHeight);
        BackColor = Color.White;
        DoubleBuffered = true;

        Load += (_, _) => _ = FetchAsync(action: null);
        MouseDown += OnMouseDown;
        FormClosed += (_, _) => _renderer.Dispose();
    }

    private string CurrentScreen => _stack.Count > 0 ? _stack[^1] : "home";

    private async System.Threading.Tasks.Task FetchAsync(string? action)
    {
        var result = await _client.FetchScreenAsync(CurrentScreen, action, ClientSize.Width, ClientSize.Height)
            .ConfigureAwait(true);

        switch (result)
        {
            case FetchSuccess success:
                _rawJson = success.RawJson;
                Invalidate();
                break;
            case FetchError error:
                MessageBox.Show(this, $"{error.Kind}: {error.Message}", "PhpNitro", MessageBoxButtons.OK, MessageBoxIcon.Error);
                break;
        }
    }

    private void OnMouseDown(object? sender, MouseEventArgs e)
    {
        if (_rawJson is null)
        {
            return;
        }

        var hit = RustHitTester.HitTest(_rawJson, e.X, e.Y);
        if (hit is null || string.IsNullOrEmpty(hit.Action))
        {
            return;
        }

        var navigation = ScreenNavigation.Reduce(hit.Action, _stack);
        switch (navigation)
        {
            case ClientTabOnly:
                // No client-side tab-switch state kept in this minimal
                // shell yet — same "not yet interactive" scoping the
                // Rust engine's own clientPanel painting currently has
                // (see rust/phpnitro-render/src/raster.rs).
                break;
            case Fetch fetch:
                _stack = new List<string>(fetch.Stack);
                _ = FetchAsync(fetch.Action);
                break;
        }
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        e.Graphics.Clear(Color.White);

        if (_rawJson is null)
        {
            return;
        }

        var frame = _renderer.RenderFrame(_rawJson, (uint)ClientSize.Width, (uint)ClientSize.Height);
        if (frame is null)
        {
            return;
        }

        using var bitmap = ToBitmap(frame);
        e.Graphics.DrawImageUnscaled(bitmap, 0, 0);
    }

    /// tiny-skia hands back RGBA8, premultiplied alpha; GDI+'s
    /// Format32bppPArgb is BGRA8, premultiplied alpha — same alpha
    /// premultiplication, different channel order, so this only needs a
    /// R/B byte swap, not a full unpremultiply/repremultiply round trip.
    private static Bitmap ToBitmap(RenderedFrame frame)
    {
        var data = (byte[])frame.Data.Clone();
        for (var i = 0; i + 3 < data.Length; i += 4)
        {
            (data[i], data[i + 2]) = (data[i + 2], data[i]);
        }

        var handle = GCHandle.Alloc(data, GCHandleType.Pinned);
        try
        {
            using var bitmap = new Bitmap(
                (int)frame.Width, (int)frame.Height, (int)frame.Stride,
                PixelFormat.Format32bppPArgb, handle.AddrOfPinnedObject());
            // Cloning here so the returned Bitmap owns its own buffer —
            // `data`/`handle` don't outlive this method otherwise.
            return new Bitmap(bitmap);
        }
        finally
        {
            handle.Free();
        }
    }
}
