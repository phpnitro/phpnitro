using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Imaging;
using System.Globalization;
using System.Runtime.InteropServices;
using System.Text.Json;
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

    // Mirrors NativeCanvasView.kt's own touchSlop (ViewConfiguration's
    // scaledTouchSlop, ~8dp) — a plain constant here since WinForms has
    // no per-device touch-slop concept of its own to query.
    private const double TouchSlop = 4.0;

    private readonly ScreenClient _client;
    private readonly RustRenderer _renderer;
    private List<string> _stack;
    private string? _rawJson;

    // clientPanel.key -> currently active index — a clientTab: action is
    // "entirely local, no fetch at all" (ScreenNavigation.cs's own
    // comment), so this is the only state that tracks it: updated on tap,
    // fed into RenderFrame/HitTest as interactionStateJson (same shape
    // rust/phpnitro-render/src/hittest.rs's InteractionState decodes),
    // never cleared — a key absent here just falls back to whichever
    // panel PHP marked initiallyActive, exactly like Android's own
    // seedClientTabState() never clearing clientTabState either.
    private readonly Dictionary<string, int> _activePanel = new();

    // hScroll/vScroll.key -> local drag offset, mirrors NativeCanvasView.kt's
    // own hScrollOffsets/vScrollOffsets — accumulated raw (rust/phpnitro-
    // render/src/raster.rs's own draw_hscroll/draw_vscroll clamp to
    // [0, contentExtent - viewportExtent] on every render, so this side
    // never needs to know contentWidth/contentHeight to stay in range).
    private readonly Dictionary<string, float> _axisOffset = new();

    // slider.key -> live drag value (0..1), mirrors NativeCanvasView.kt's
    // own sliderValues — overrides SliderCommand.value while dragging;
    // committed into _fieldValues (below) only on mouse-up.
    private readonly Dictionary<string, float> _sliderValue = new();

    // Checkbox/Toggle/Slider's shared "toggle:" commit destination —
    // mirrors NativeRenderPocActivity.kt's own fieldValues: sent as extra
    // query params on every fetch (see ScreenClient.FetchScreenAsync),
    // never cleared, same "server round-trips it back via the next
    // render" contract every platform's fieldValues already has.
    private readonly Dictionary<string, string> _fieldValues = new();

    private ScrollTarget? _pendingScroll;
    private ScrollTarget? _activeScroll;
    private SliderDrag? _activeSlider;
    private Point _mouseDownPoint;
    private Point _lastDragPoint;

    // The size the CURRENT _rawJson was actually fetched for — a real
    // window resize (dragging the border) means PHP laid out for the
    // wrong size until a fresh fetch catches up. Marked BEFORE the async
    // result lands (see FetchAsync), not after, so ClientSizeChanged
    // firing again mid-flight (the size settling a few pixels further
    // while the previous request is still in the air) doesn't launch a
    // second redundant fetch for what's effectively the same resize.
    private Size _lastFetchedSize;

    // A real TextField/PasswordField's own commit destination —
    // Checkbox.php/Slider.php's "toggle:" and TextField.php's "focus:"
    // both fill _fieldValues, just via different UI (see ShowTextInput's
    // own doc comment for the exact wire convention this ports from
    // NativeRenderPocActivity.kt's showTextInput()/TextWatcher).
    private TextBox? _activeTextBox;

    private sealed record ScrollTarget(string Key, bool IsHorizontal, RectangleF Rect, double ContentExtent);

    private sealed record SliderDrag(string Key, string Action, RectangleF Rect, double ThumbSize);

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
        ClientSizeChanged += OnClientSizeChanged;
        MouseDown += OnMouseDown;
        MouseMove += OnMouseMove;
        MouseUp += OnMouseUp;
        FormClosed += (_, _) => _renderer.Dispose();
    }

    private string CurrentScreen => _stack.Count > 0 ? _stack[^1] : "home";

    // {"activePanel":{...},"axisOffset":{...},"sliderValue":{...}} — the
    // same shape rust/phpnitro-render/src/hittest.rs's InteractionState
    // decodes. Rebuilt fresh each call rather than cached — this state
    // changes far less often than frames render, but this stays correct
    // without a dirty flag to forget to clear.
    private string BuildInteractionStateJson() =>
        JsonSerializer.Serialize(new { activePanel = _activePanel, axisOffset = _axisOffset, sliderValue = _sliderValue });

    private void OnClientSizeChanged(object? sender, EventArgs e)
    {
        if (ClientSize == _lastFetchedSize || ClientSize.Width <= 0 || ClientSize.Height <= 0)
        {
            return;
        }
        _ = FetchAsync(action: null);
    }

    private async System.Threading.Tasks.Task FetchAsync(string? action)
    {
        _lastFetchedSize = ClientSize;
        var result = await _client.FetchScreenAsync(CurrentScreen, action, ClientSize.Width, ClientSize.Height, _fieldValues)
            .ConfigureAwait(true);

        switch (result)
        {
            case FetchSuccess success:
                _rawJson = success.RawJson;
                // A new payload just replaced whatever ClearTextInput's
                // caller last saw — NativeRenderPocActivity.kt only tears
                // its own overlay down on navigate:/tab:/back/submit:,
                // leaving it alone across other same-screen refetches
                // (toggle:, etc); this port simplifies to "any new
                // payload ends the current editing session", safer than
                // trying to reposition a stale overlay against content it
                // was never laid out for.
                ClearTextInput();
                Invalidate();
                break;
            case FetchError error:
                MessageBox.Show(this, $"{error.Kind}: {error.Message}", "PhpNitro", MessageBoxButtons.OK, MessageBoxIcon.Error);
                break;
        }
    }

    /// `focus:[multiline:][secure:]name` — TextField.php/PasswordField.php's
    /// own hitRegion action convention (see Checkbox.php's docblock for
    /// the sibling "toggle:" convention this mirrors). Ports
    /// NativeRenderPocActivity.kt's showTextInput(): one real
    /// System.Windows.Forms.TextBox at a time, positioned over the
    /// static rect+text TextField.php already painted underneath (which
    /// stays in the command list, just visually covered while focused),
    /// styled by hand from Tokens.php's own constants since none of this
    /// is sent over the wire (RADIUS_MD=14 has no direct WinForms
    /// equivalent — BorderStyle.FixedSingle is the closest available
    /// primitive, not a rounded rect).
    private void ShowTextInput(string action, RectangleF rect)
    {
        var rest = action.Substring("focus:".Length);
        var multiline = rest.StartsWith("multiline:", StringComparison.Ordinal);
        if (multiline)
        {
            rest = rest.Substring("multiline:".Length);
        }
        var secure = rest.StartsWith("secure:", StringComparison.Ordinal);
        if (secure)
        {
            rest = rest.Substring("secure:".Length);
        }
        var fieldName = rest;

        ClearTextInput();

        var textBox = new TextBox
        {
            Location = new Point((int)rect.X, (int)rect.Y),
            Size = new Size((int)rect.Width, (int)rect.Height),
            Text = _fieldValues.TryGetValue(fieldName, out var existing) ? existing : "",
            Multiline = multiline,
            UseSystemPasswordChar = secure,
            BorderStyle = BorderStyle.FixedSingle,
            Font = new Font(DefaultFont.FontFamily, 15f, GraphicsUnit.Pixel),
            ForeColor = ColorTranslator.FromHtml("#111827"),
        };
        // Every keystroke, not just on blur/submit — mirrors
        // NativeRenderPocActivity.kt's TextWatcher.afterTextChanged()
        // exactly; every platform here already sends _fieldValues on
        // EVERY fetch regardless of what triggered it (unlike Android's
        // own selective includeFields flag), so there's no separate
        // "commit" step to wire beyond keeping this dictionary current.
        textBox.TextChanged += (_, _) => _fieldValues[fieldName] = textBox.Text;

        Controls.Add(textBox);
        textBox.Select(textBox.Text.Length, 0);
        textBox.Focus();
        _activeTextBox = textBox;
    }

    private void ClearTextInput()
    {
        if (_activeTextBox is null)
        {
            return;
        }
        Controls.Remove(_activeTextBox);
        _activeTextBox.Dispose();
        _activeTextBox = null;
    }

    private void OnMouseDown(object? sender, MouseEventArgs e)
    {
        _pendingScroll = null;
        _activeScroll = null;
        _activeSlider = null;

        if (_rawJson is null || e.Button != MouseButtons.Left)
        {
            return;
        }

        _mouseDownPoint = e.Location;
        _lastDragPoint = e.Location;

        // Read-only geometry lookup against the ALREADY-DECODED command
        // tree — never re-serialized back to Rust (DrawCommandPayload has
        // no Encodable-equivalent for that), just used to find which
        // slider/scroll region a raw mouse point falls within, exactly
        // like NativeCanvasView.kt's own hitTestSlider()/hitTestHScroll()/
        // hitTestVScroll() do against its own locally-parsed regions.
        var payload = DrawCommandParser.ParsePayload(_rawJson);

        // Slider commits immediately on down, not after a decisive-move
        // threshold like hScroll/vScroll — a slider's whole touch box IS
        // the gesture, there's no "was this actually meant as a page
        // scroll" ambiguity to resolve (see NativeCanvasView.kt's own
        // comment on this exact point).
        foreach (var region in payload.SliderRegions)
        {
            var rect = new RectangleF((float)region.X, (float)region.Y, (float)region.Width, (float)region.Height);
            if (rect.Contains(e.X, e.Y))
            {
                _activeSlider = new SliderDrag(region.Key, region.Action, rect, region.ThumbSize);
                _sliderValue[region.Key] = SliderValueForTouch(rect, region.ThumbSize, e.X);
                Invalidate();
                return;
            }
        }

        // Only TOP-LEVEL hScroll/vScroll commands are considered — same
        // limitation NativeCanvasView.kt's own parseHScrollRegions()/
        // parseVScrollRegions() have (a flat scan over `commands`, no
        // recursion into a nested clientPanel), not a gap introduced here.
        foreach (var command in payload.Commands)
        {
            if (command is HScrollCommand hScroll)
            {
                var rect = new RectangleF((float)hScroll.X, (float)hScroll.Y, (float)hScroll.Width, (float)hScroll.Height);
                if (rect.Contains(e.X, e.Y))
                {
                    _pendingScroll = new ScrollTarget(hScroll.Key, IsHorizontal: true, rect, hScroll.ContentWidth);
                    return;
                }
            }
            else if (command is VScrollCommand vScroll)
            {
                var rect = new RectangleF((float)vScroll.X, (float)vScroll.Y, (float)vScroll.Width, (float)vScroll.Height);
                if (rect.Contains(e.X, e.Y))
                {
                    _pendingScroll = new ScrollTarget(vScroll.Key, IsHorizontal: false, rect, vScroll.ContentHeight);
                    return;
                }
            }
        }
    }

    private void OnMouseMove(object? sender, MouseEventArgs e)
    {
        if (_rawJson is null || e.Button != MouseButtons.Left)
        {
            return;
        }

        if (_activeSlider is not null)
        {
            _sliderValue[_activeSlider.Key] = SliderValueForTouch(_activeSlider.Rect, _activeSlider.ThumbSize, e.X);
            Invalidate();
            return;
        }

        var totalDeltaX = e.X - _mouseDownPoint.X;
        var totalDeltaY = e.Y - _mouseDownPoint.Y;

        if (_pendingScroll is not null && _activeScroll is null)
        {
            if (_pendingScroll.IsHorizontal && Math.Abs(totalDeltaX) > TouchSlop && Math.Abs(totalDeltaX) > Math.Abs(totalDeltaY))
            {
                _activeScroll = _pendingScroll;
                _pendingScroll = null;
            }
            else if (!_pendingScroll.IsHorizontal && Math.Abs(totalDeltaY) > TouchSlop)
            {
                _activeScroll = _pendingScroll;
                _pendingScroll = null;
            }
            else if (_pendingScroll.IsHorizontal && Math.Abs(totalDeltaY) > TouchSlop && Math.Abs(totalDeltaY) > Math.Abs(totalDeltaX))
            {
                // Decisive move was vertical over a pending HORIZONTAL
                // target — this was never an hScroll gesture.
                _pendingScroll = null;
            }
        }

        if (_activeScroll is not null)
        {
            var viewportExtent = _activeScroll.IsHorizontal ? _activeScroll.Rect.Width : _activeScroll.Rect.Height;
            var maxOffset = (float)Math.Max(_activeScroll.ContentExtent - viewportExtent, 0.0);
            var key = _activeScroll.Key;
            var current = _axisOffset.TryGetValue(key, out var existing) ? existing : 0f;
            var delta = _activeScroll.IsHorizontal ? _lastDragPoint.X - e.X : _lastDragPoint.Y - e.Y;
            _axisOffset[key] = Math.Clamp(current + delta, 0f, maxOffset);
            _lastDragPoint = e.Location;
            Invalidate();
        }
    }

    private void OnMouseUp(object? sender, MouseEventArgs e)
    {
        if (_rawJson is null)
        {
            return;
        }

        if (_activeSlider is not null)
        {
            var slider = _activeSlider;
            _activeSlider = null;
            var value = _sliderValue.TryGetValue(slider.Key, out var v) ? v : 0f;
            // Invariant culture, not the current culture — a French/
            // Belgian/etc. locale's decimal COMMA sent as a literal
            // query-string value would have PHP's (float) cast stop
            // parsing at the first non-digit character, silently
            // truncating every dragged value to its integer part (same
            // bug NativeCanvasView.kt's own Locale.US comment warns
            // about).
            var metaJson = $"{{\"next\":\"{value.ToString("F3", CultureInfo.InvariantCulture)}\"}}";
            Commit(ScreenNavigation.Reduce(slider.Action, _stack, metaJson));
            return;
        }

        if (_activeScroll is not null)
        {
            // A real scroll drag happened this gesture — no tap fires,
            // matching NativeCanvasView.kt's own ACTION_UP handling
            // (activeHScroll/activeVScroll just get cleared, nothing else).
            _activeScroll = null;
            _pendingScroll = null;
            return;
        }
        _pendingScroll = null;

        // Never became a drag — a plain tap, hit-tested at the RELEASE
        // position (mirrors NativeCanvasView.kt's own handleTap(event),
        // called with the ACTION_UP event's coordinates).
        var hit = RustHitTester.HitTest(_rawJson, e.X, e.Y, BuildInteractionStateJson());
        if (hit is null || string.IsNullOrEmpty(hit.Action))
        {
            return;
        }

        // focus: never reaches ScreenNavigation.Reduce (no fetch at all,
        // entirely client-side — same "not funneled through the generic
        // reducer" treatment clientTab: gets) — matches
        // NativeRenderPocActivity.kt's own onTap(), which branches on
        // "focus:" before any of the actions that DO end in a refetch.
        if (hit.Action.StartsWith("focus:", StringComparison.Ordinal))
        {
            var rect = new RectangleF(hit.Left, hit.Top, hit.Right - hit.Left, hit.Bottom - hit.Top);
            ShowTextInput(hit.Action, rect);
            return;
        }

        Commit(ScreenNavigation.Reduce(hit.Action, _stack, hit.MetaJson));
    }

    private void Commit(ScreenNavigationResult navigation)
    {
        switch (navigation)
        {
            case ClientTabOnly clientTab:
                _activePanel[clientTab.Key] = clientTab.Index;
                Invalidate();
                break;
            case FieldUpdate fieldUpdate:
                _fieldValues[fieldUpdate.Key] = fieldUpdate.Value;
                _ = FetchAsync(action: null);
                break;
            case Fetch fetch:
                _stack = new List<string>(fetch.Stack);
                _ = FetchAsync(fetch.Action);
                break;
        }
    }

    /// Inverse of drawSliderCommand()'s own thumbCx formula (see
    /// rust/phpnitro-render/src/raster.rs's draw_slider) — mirrors
    /// NativeCanvasView.kt's own sliderValueForTouch() exactly.
    private static float SliderValueForTouch(RectangleF rect, double thumbSize, float touchX)
    {
        var trackWidth = MathF.Max(rect.Width - (float)thumbSize, 1f);
        var value = (touchX - rect.Left - (float)thumbSize / 2f) / trackWidth;
        return Math.Clamp(value, 0f, 1f);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        base.OnPaint(e);
        e.Graphics.Clear(Color.White);

        if (_rawJson is null)
        {
            return;
        }

        var frame = _renderer.RenderFrame(_rawJson, (uint)ClientSize.Width, (uint)ClientSize.Height, interactionStateJson: BuildInteractionStateJson());
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
