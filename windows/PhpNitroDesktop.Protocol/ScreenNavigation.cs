using System.Linq;

namespace PhpNitroDesktop.Protocol;

// The Windows counterpart of navigation.py (Linux) / ScreenNavigation.swift
// (iOS) — deliberately the MINIMAL slice of the action-dispatch table
// every platform's own tap handler has: navigate:/tab:/back/clientTab:,
// and the plain fallback (any other action refetches the current screen
// with it). A pure static method of (action, stack) -> result, same
// reasoning as the other two platforms: the decision itself needs
// nothing Windows-specific to be fully testable.

public abstract record ScreenNavigationResult;

// clientTab:key:index — a ClientTabs tab switch, entirely local, no
// fetch at all.
public sealed record ClientTabOnly(string Key, int Index) : ScreenNavigationResult;

// Everything else ends in a fetch — Stack is what the screen stack
// should become BEFORE fetching, Action is what to pass to
// ScreenClient.FetchScreenAsync (null for navigate:/tab:/back, the
// original action string for the plain fallback case).
public sealed record Fetch(IReadOnlyList<string> Stack, string? Action) : ScreenNavigationResult;

public static class ScreenNavigation
{
    public static ScreenNavigationResult Reduce(string action, IReadOnlyList<string> stack)
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
}
