using System.Collections.Generic;
using System.IO;
using System.Reflection;
using System.Runtime.CompilerServices;
using System.Runtime.InteropServices;
using System.Text;

namespace PhpNitroDesktop.Render;

// P/Invoke bindings for rust/phpnitro-render's C ABI
// (include/phpnitro_render.h) — the Windows counterpart of
// linux/phpnitro_desktop/rust_render.py's ctypes bindings. Every
// function name below matches phpnitro_render.h one-for-one, same
// "compare side by side, don't trust this file alone" discipline
// rust_render.py's own docstring asks for.
//
// P/Invoke's DllImport is fully cross-platform in .NET Core: resolving
// "phpnitro_render" tries libphpnitro_render.so (Linux),
// libphpnitro_render.dylib (macOS), or phpnitro_render.dll (Windows)
// depending on the OS this actually runs on — the same "one C ABI, N
// consumers" idea DrawCommand.cs's own header comment already states
// for JSON decoding, just for the compiled binary instead of source.
// A custom NativeLibrary.SetDllImportResolver locates the exact build
// output path (rust/phpnitro-render/target/<profile>/...) the same way
// rust_render.py's own _candidate_library_paths() does, rather than
// relying on the OS's default library search path ever containing it.

public sealed class RustRenderUnavailableException : Exception
{
    public RustRenderUnavailableException(string message) : base(message)
    {
    }
}

internal static class NativeMethods
{
    private const string LibraryName = "phpnitro_render";
    private static readonly object RegisterLock = new();
    private static bool _resolverRegistered;

    internal static void EnsureResolverRegistered()
    {
        lock (RegisterLock)
        {
            if (_resolverRegistered)
            {
                return;
            }
            NativeLibrary.SetDllImportResolver(typeof(NativeMethods).Assembly, Resolve);
            _resolverRegistered = true;
        }
    }

    private static IntPtr Resolve(string libraryName, Assembly assembly, DllImportSearchPath? searchPath)
    {
        if (libraryName != LibraryName)
        {
            return IntPtr.Zero;
        }
        foreach (var candidate in CandidatePaths())
        {
            if (File.Exists(candidate) && NativeLibrary.TryLoad(candidate, out var handle))
            {
                return handle;
            }
        }
        // Falls through to the default resolver, which will raise a
        // clear DllNotFoundException naming "phpnitro_render" if this
        // also fails — never silently do nothing.
        return IntPtr.Zero;
    }

    // windows/PhpNitroDesktop.Render/RustRenderer.cs -> .../PhpNitroDesktop.Render/
    // -> .../windows/ -> repo root (3 levels) — same [CallerFilePath]-based
    // resolution ScreenClientTests.cs already uses to find the repo root
    // from a compile-time-known source location, not a runtime-fragile guess.
    private static string RepoRoot([CallerFilePath] string sourceFilePath = "") =>
        Path.GetDirectoryName(Path.GetDirectoryName(Path.GetDirectoryName(sourceFilePath)!)!)!;

    private static IEnumerable<string> CandidatePaths()
    {
        var overridePath = Environment.GetEnvironmentVariable("PHPNITRO_RUST_RENDER_LIB");
        if (!string.IsNullOrEmpty(overridePath))
        {
            yield return overridePath;
        }

        var crateTargetDir = Path.Combine(RepoRoot(), "rust", "phpnitro-render", "target");
        foreach (var profile in new[] { "release", "debug" })
        {
            var profileDir = Path.Combine(crateTargetDir, profile);
            yield return Path.Combine(profileDir, "libphpnitro_render.so");
            yield return Path.Combine(profileDir, "libphpnitro_render.dylib");
            yield return Path.Combine(profileDir, "phpnitro_render.dll");
        }
    }

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_version();

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_last_error();

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_new();

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern void phpnitro_render_free(IntPtr renderer);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_frame(
        IntPtr renderer, byte[] envelopeJsonUtf8, byte[]? previousEnvelopeJsonUtf8, ulong transitionElapsedMs,
        uint widthPx, uint heightPx, ulong elapsedMs, byte[]? interactionStateJsonUtf8);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_frame_pixels(IntPtr frame);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern uint phpnitro_render_frame_stride(IntPtr frame);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern uint phpnitro_render_frame_width(IntPtr frame);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern uint phpnitro_render_frame_height(IntPtr frame);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern void phpnitro_render_free_frame(IntPtr frame);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_hit_test(
        byte[] envelopeJsonUtf8, float tapX, float tapY, byte[]? interactionStateJsonUtf8);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_hit_action(IntPtr hit);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern IntPtr phpnitro_render_hit_meta_json(IntPtr hit);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern void phpnitro_render_hit_rect(
        IntPtr hit, out float left, out float top, out float right, out float bottom);

    [DllImport(LibraryName, CallingConvention = CallingConvention.Cdecl)]
    internal static extern void phpnitro_render_free_hit(IntPtr hit);

    internal static byte[] Utf8WithNul(string value)
    {
        var byteCount = Encoding.UTF8.GetByteCount(value);
        var buffer = new byte[byteCount + 1];
        Encoding.UTF8.GetBytes(value, 0, value.Length, buffer, 0);
        buffer[byteCount] = 0;
        return buffer;
    }
}

/// RGBA8, premultiplied alpha — tiny-skia's native Pixmap layout, NOT
/// GDI+/Direct2D's usual BGRA layout. A caller painting this through
/// System.Drawing or WPF needs to account for that, not assume the same
/// byte order.
public sealed record RenderedFrame(uint Width, uint Height, uint Stride, byte[] Data);

public sealed record HitResult(string Action, string MetaJson, float Left, float Top, float Right, float Bottom);

/// Owns the loaded fonts (rust/phpnitro-render's FontSystem) — create
/// ONE of these per app lifetime (or per screen at most), never one per
/// frame, same guidance phpnitro_render.h gives every other consumer.
public sealed class RustRenderer : IDisposable
{
    private IntPtr _handle;

    public RustRenderer()
    {
        NativeMethods.EnsureResolverRegistered();
        _handle = NativeMethods.phpnitro_render_new();
        if (_handle == IntPtr.Zero)
        {
            throw new RustRenderUnavailableException("phpnitro_render_new() returned NULL");
        }
    }

    public static string Version()
    {
        NativeMethods.EnsureResolverRegistered();
        return Marshal.PtrToStringUTF8(NativeMethods.phpnitro_render_version()) ?? "";
    }

    public static string? LastError()
    {
        NativeMethods.EnsureResolverRegistered();
        return Marshal.PtrToStringUTF8(NativeMethods.phpnitro_render_last_error());
    }

    /// Returns null on failure (malformed JSON, zero width/height) —
    /// check LastError() for why. previousEnvelopeJson/transitionElapsedMs
    /// drive a crossfade/hero transition between it and envelopeJson (see
    /// rust/phpnitro-render/src/transition.rs) — omit both (the defaults)
    /// for a plain, untransitioned render. interactionStateJson is the same
    /// shape HitTest() already takes (activePanel/axisOffset/sliderValue) —
    /// omit it (the default) to paint every clientPanel/hScroll/vScroll/
    /// slider at its server-authored resting state.
    public RenderedFrame? RenderFrame(
        string envelopeJson, uint widthPx, uint heightPx, ulong elapsedMs = 0,
        string? previousEnvelopeJson = null, ulong transitionElapsedMs = 0,
        string? interactionStateJson = null)
    {
        var previousBytes = previousEnvelopeJson is null ? null : NativeMethods.Utf8WithNul(previousEnvelopeJson);
        var stateBytes = interactionStateJson is null ? null : NativeMethods.Utf8WithNul(interactionStateJson);
        var frame = NativeMethods.phpnitro_render_frame(
            _handle, NativeMethods.Utf8WithNul(envelopeJson), previousBytes, transitionElapsedMs,
            widthPx, heightPx, elapsedMs, stateBytes);
        if (frame == IntPtr.Zero)
        {
            return null;
        }
        try
        {
            var stride = NativeMethods.phpnitro_render_frame_stride(frame);
            var actualWidth = NativeMethods.phpnitro_render_frame_width(frame);
            var actualHeight = NativeMethods.phpnitro_render_frame_height(frame);
            var pixelsPtr = NativeMethods.phpnitro_render_frame_pixels(frame);
            var byteCount = checked((int)(stride * actualHeight));
            var data = new byte[byteCount];
            if (pixelsPtr != IntPtr.Zero && byteCount > 0)
            {
                Marshal.Copy(pixelsPtr, data, 0, byteCount);
            }
            return new RenderedFrame(actualWidth, actualHeight, stride, data);
        }
        finally
        {
            NativeMethods.phpnitro_render_free_frame(frame);
        }
    }

    public void Dispose()
    {
        if (_handle != IntPtr.Zero)
        {
            NativeMethods.phpnitro_render_free(_handle);
            _handle = IntPtr.Zero;
        }
    }
}

/// Module-level (not a RustRenderer method) since hit-testing needs no
/// loaded fonts at all — mirrors phpnitro_render_hit_test not taking a
/// renderer handle either, same as rust_render.py's own module-level
/// hit_test() function.
public static class RustHitTester
{
    public static HitResult? HitTest(string envelopeJson, float tapX, float tapY, string? interactionStateJson = null)
    {
        NativeMethods.EnsureResolverRegistered();
        var stateBytes = interactionStateJson is null ? null : NativeMethods.Utf8WithNul(interactionStateJson);
        var hit = NativeMethods.phpnitro_render_hit_test(NativeMethods.Utf8WithNul(envelopeJson), tapX, tapY, stateBytes);
        if (hit == IntPtr.Zero)
        {
            return null;
        }
        try
        {
            var action = Marshal.PtrToStringUTF8(NativeMethods.phpnitro_render_hit_action(hit)) ?? "";
            var metaJson = Marshal.PtrToStringUTF8(NativeMethods.phpnitro_render_hit_meta_json(hit)) ?? "null";
            NativeMethods.phpnitro_render_hit_rect(hit, out var left, out var top, out var right, out var bottom);
            return new HitResult(action, metaJson, left, top, right, bottom);
        }
        finally
        {
            NativeMethods.phpnitro_render_free_hit(hit);
        }
    }
}
