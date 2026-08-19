using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace PhpNitroDesktop.Protocol;

// The Windows counterpart of screen_client.py (Linux) / ScreenClient.swift
// (iOS) — deliberately the MINIMAL slice of fetchDrawCommands()'s
// (NativeRenderPocActivity.kt) contract: fetch one screen, optionally
// with a tap action, get back a DrawCommandPayload. Everything else
// fetchDrawCommands() also does (screen-stack push/pop lives in
// ScreenNavigation, not here; lastHash short-circuiting, dark/locale/
// online params, scroll-position prefetch hints, polling, confetti/
// snackbar/redirect side-channels) is real, separate follow-up work —
// same scoping documented on every other platform's own client.

public abstract record ScreenFetchResult;

// RawJson alongside the decoded Payload — the Rust renderer takes the raw
// envelope JSON directly (RustRenderer.RenderFrame/RustHitTester.HitTest),
// not this class's own decoded object graph. Same addition
// linux/phpnitro_desktop/screen_client.py's own FetchSuccess already
// needed for the identical reason.
public sealed record FetchSuccess(DrawCommandPayload Payload, string RawJson) : ScreenFetchResult;

public sealed record FetchError(string Kind, string Message, int? Status = null) : ScreenFetchResult;

public sealed class ScreenClient
{
    private readonly string _host;
    private readonly int _port;
    private readonly HttpClient _httpClient;

    public ScreenClient(string host, int port, HttpClient? httpClient = null)
    {
        _host = host;
        _port = port;
        _httpClient = httpClient ?? new HttpClient();
    }

    // Mirrors fetchDrawCommands()'s own "/native/layout-demo?width=...
    // &height=...&screen=...&action=...&<field>=<value>..." URL — every
    // param this minimal client doesn't send yet has a server-side
    // default (see public/index.php's own `$_GET[...] ?? ...`
    // fallbacks), so omitting them is a real degradation (no dark mode,
    // no i18n) rather than a broken request. fieldValues is always sent
    // when non-empty, sorted by name for a stable, comparable URL —
    // same convention every other platform's own client already uses.
    public static string BuildUrl(
        string host, int port, string screen, string? action,
        double width, double height, IReadOnlyDictionary<string, string>? fieldValues = null)
    {
        var builder = new StringBuilder();
        builder.Append("http://").Append(host).Append(':').Append(port).Append("/native/layout-demo?");
        builder.Append("screen=").Append(Uri.EscapeDataString(screen));
        builder.Append("&width=").Append(width.ToString(System.Globalization.CultureInfo.InvariantCulture));
        builder.Append("&height=").Append(height.ToString(System.Globalization.CultureInfo.InvariantCulture));

        if (action is not null)
        {
            builder.Append("&action=").Append(Uri.EscapeDataString(action));
        }

        if (fieldValues is not null)
        {
            foreach (var name in fieldValues.Keys.OrderBy(k => k, StringComparer.Ordinal))
            {
                builder.Append('&').Append(Uri.EscapeDataString(name)).Append('=').Append(Uri.EscapeDataString(fieldValues[name]));
            }
        }

        return builder.ToString();
    }

    public async Task<ScreenFetchResult> FetchScreenAsync(
        string screen, string? action, double width, double height,
        IReadOnlyDictionary<string, string>? fieldValues = null)
    {
        var url = BuildUrl(_host, _port, screen, action, width, height, fieldValues);

        HttpResponseMessage response;
        string body;
        try
        {
            response = await _httpClient.GetAsync(url).ConfigureAwait(false);
            body = await response.Content.ReadAsStringAsync().ConfigureAwait(false);
        }
        catch (Exception ex)
        {
            return new FetchError("network", ex.Message);
        }

        if (!response.IsSuccessStatusCode)
        {
            var message = TryExtractServerErrorMessage(body) ?? $"HTTP {(int)response.StatusCode}";
            return new FetchError("server", message, (int)response.StatusCode);
        }

        try
        {
            var payload = DrawCommandParser.ParsePayload(body);
            return new FetchSuccess(payload, body);
        }
        catch (Exception ex)
        {
            return new FetchError("decoding", ex.Message);
        }
    }

    // Body shape of public/index.php's own `{"error":{"class":...,
    // "message":...}}` — set_exception_handler()'s payload for the
    // /native/layout-demo route.
    private static string? TryExtractServerErrorMessage(string body)
    {
        try
        {
            using var document = JsonDocument.Parse(body);
            if (document.RootElement.TryGetProperty("error", out var error) &&
                error.TryGetProperty("message", out var message))
            {
                return message.GetString();
            }
        }
        catch (JsonException)
        {
            // Not JSON at all (a plain-text 503 from a proxy, etc.) —
            // fall through to the generic "HTTP <code>" message instead.
        }
        return null;
    }
}
