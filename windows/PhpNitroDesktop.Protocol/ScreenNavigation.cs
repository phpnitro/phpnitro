using System.Linq;
using System.Text.Json;

namespace PhpNitroDesktop.Protocol;

// The Windows counterpart of navigation.py (Linux) / ScreenNavigation.swift
// (iOS) — deliberately the MINIMAL slice of the action-dispatch table
// every platform's own tap handler has: navigate:/tab:/back/clientTab:/
// toggle:, and the plain fallback (any other action refetches the
// current screen with it). A pure static method of (action, stack,
// metaJson) -> result, same reasoning as the other two platforms: the
// decision itself needs nothing Windows-specific to be fully testable.

public abstract record ScreenNavigationResult;

// clientTab:key:index — a ClientTabs tab switch, entirely local, no
// fetch at all.
public sealed record ClientTabOnly(string Key, int Index) : ScreenNavigationResult;

// toggle:name (Checkbox/Toggle/Slider's shared commit action, see
// packages/ui/src/Native/Checkbox.php/Slider.php) — a local
// fieldValues[name] = value update followed by a same-screen refetch
// with no action param, mirroring NativeRenderPocActivity.kt's own
// generic "toggle:" handler exactly (fieldValues[name] = meta.next;
// refetch(action = null, includeFields = true)). Only ever produced when
// the caller passes a real metaJson to Reduce(...) containing a "next"
// key — a caller that never passes one (no meta source available) keeps
// falling through to the generic Fetch case below, unchanged.
public sealed record FieldUpdate(string Key, string Value) : ScreenNavigationResult;

// Everything else ends in a fetch — Stack is what the screen stack
// should become BEFORE fetching, Action is what to pass to
// ScreenClient.FetchScreenAsync (null for navigate:/tab:/back, the
// original action string for the plain fallback case).
public sealed record Fetch(IReadOnlyList<string> Stack, string? Action) : ScreenNavigationResult;

public static class ScreenNavigation
{
    // metaJson is the tapped hit region's own meta object as a JSON
    // string (or, for a slider, a caller-synthesized {"next":"<value
    // formatted to 3 decimals>"}) — needed only to resolve a toggle:
    // action's value. Omit it (the default) for a caller with no meta
    // source at all; toggle: then falls through to the generic Fetch
    // case exactly as it always has, unchanged.
    public static ScreenNavigationResult Reduce(string action, IReadOnlyList<string> stack, string? metaJson = null)
    {
        if (action.StartsWith("clientTab:", StringComparison.Ordinal))
        {
            var rest = action.Substring("clientTab:".Length);
            var separatorIndex = rest.IndexOf(':');
            if (separatorIndex > 0)
            {
                var key = rest.Substring(0, separatorIndex);
                var indexString = rest.Substring(separatorIndex + 1);
                if (int.TryParse(indexString, out var index))
                {
                    return new ClientTabOnly(key, index);
                }
            }
            return new Fetch(stack, null);
        }

        if (action.StartsWith("toggle:", StringComparison.Ordinal) && metaJson is not null)
        {
            var next = NextValue(metaJson);
            if (next is not null)
            {
                return new FieldUpdate(action.Substring("toggle:".Length), next);
            }
        }

        if (action.StartsWith("navigate:", StringComparison.Ordinal))
        {
            var target = action.Substring("navigate:".Length);
            var newStack = new List<string>(stack) { target };
            return new Fetch(newStack, null);
        }

        // A BottomNavigation tab switch — resets the whole stack to
        // that one screen instead of pushing, so hopping between tabs
        // repeatedly doesn't grow an ever-longer back stack the way
        // drilling into a real detail screen should.
        if (action.StartsWith("tab:", StringComparison.Ordinal))
        {
            var target = action.Substring("tab:".Length);
            return new Fetch(new List<string> { target }, null);
        }

        if (action == "back")
        {
            var newStack = stack.Count > 1 ? stack.Take(stack.Count - 1).ToList() : stack.ToList();
            return new Fetch(newStack, null);
        }

        return new Fetch(stack, action);
    }

    // Extracts meta.next from a hit region's meta JSON (e.g. {"next":"1"}
    // — see Checkbox.php's own docblock) as a string, same loose
    // optString("next", "")-style tolerance NativeRenderPocActivity.kt's
    // own reader has: a present-but-empty next still counts (an
    // unchecked Checkbox's own next IS ""), only a missing/malformed meta
    // blob returns null.
    private static string? NextValue(string metaJson)
    {
        try
        {
            using var document = JsonDocument.Parse(metaJson);
            if (document.RootElement.ValueKind != JsonValueKind.Object)
            {
                return null;
            }
            if (!document.RootElement.TryGetProperty("next", out var nextElement))
            {
                return null;
            }
            return nextElement.ValueKind == JsonValueKind.String ? nextElement.GetString() : nextElement.GetRawText();
        }
        catch (JsonException)
        {
            return null;
        }
    }
}
