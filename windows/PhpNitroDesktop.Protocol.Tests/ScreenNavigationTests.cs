using PhpNitroDesktop.Protocol;
using Xunit;

namespace PhpNitroDesktop.Protocol.Tests;

// Ported case for case from ScreenNavigationTests.swift (iOS) /
// test_navigation.py (Linux).
public class ScreenNavigationTests
{
    [Fact]
    public void NavigatePushesOntoTheStack()
    {
        var result = ScreenNavigation.Reduce("navigate:product?id=42", new[] { "home" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal(new[] { "home", "product?id=42" }, fetch.Stack);
        Assert.Null(fetch.Action);
    }

    [Fact]
    public void TabResetsTheWholeStack()
    {
        var result = ScreenNavigation.Reduce("tab:profile", new[] { "home", "product?id=42", "reviews" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal(new[] { "profile" }, fetch.Stack);
    }

    [Fact]
    public void BackPopsTheStackWhenMoreThanOneScreen()
    {
        var result = ScreenNavigation.Reduce("back", new[] { "home", "product?id=42" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal(new[] { "home" }, fetch.Stack);
    }

    [Fact]
    public void BackIsANoOpOnTheRootScreen()
    {
        var result = ScreenNavigation.Reduce("back", new[] { "home" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal(new[] { "home" }, fetch.Stack);
    }

    [Fact]
    public void ClientTabIsFullyLocalWithNoFetch()
    {
        var result = ScreenNavigation.Reduce("clientTab:tabs1:2", new[] { "home" });

        var clientTabOnly = Assert.IsType<ClientTabOnly>(result);
        Assert.Equal("tabs1", clientTabOnly.Key);
        Assert.Equal(2, clientTabOnly.Index);
    }

    [Fact]
    public void MalformedClientTabFallsBackToAPlainFetch()
    {
        var result = ScreenNavigation.Reduce("clientTab:tabs1", new[] { "home" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal(new[] { "home" }, fetch.Stack);
        Assert.Null(fetch.Action);
    }

    [Fact]
    public void APlainActionRefetchesTheCurrentScreenWithIt()
    {
        var result = ScreenNavigation.Reduce("counter:increment", new[] { "home" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal(new[] { "home" }, fetch.Stack);
        Assert.Equal("counter:increment", fetch.Action);
    }

    [Fact]
    public void ToggleWithMetaExtractsTheNextValueAsALocalFieldUpdate()
    {
        var result = ScreenNavigation.Reduce("toggle:agree", new[] { "home" }, """{"next":"1"}""");

        var fieldUpdate = Assert.IsType<FieldUpdate>(result);
        Assert.Equal("agree", fieldUpdate.Key);
        Assert.Equal("1", fieldUpdate.Value);
    }

    [Fact]
    public void ToggleWithAnEmptyNextIsStillAFieldUpdate()
    {
        // An unchecked Checkbox's own "next" is the empty string, not
        // absent — Checkbox.php's own docblock: 'next' => $checked ? ''
        // : '1'.
        var result = ScreenNavigation.Reduce("toggle:agree", new[] { "home" }, """{"next":""}""");

        var fieldUpdate = Assert.IsType<FieldUpdate>(result);
        Assert.Equal("agree", fieldUpdate.Key);
        Assert.Equal("", fieldUpdate.Value);
    }

    [Fact]
    public void ToggleWithNoMetaFallsBackToAPlainFetch()
    {
        var result = ScreenNavigation.Reduce("toggle:agree", new[] { "home" });

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal("toggle:agree", fetch.Action);
    }

    [Fact]
    public void ToggleWithMalformedMetaFallsBackToAPlainFetch()
    {
        var result = ScreenNavigation.Reduce("toggle:agree", new[] { "home" }, "not json");

        var fetch = Assert.IsType<Fetch>(result);
        Assert.Equal("toggle:agree", fetch.Action);
    }
}
