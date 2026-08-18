using System.Runtime.CompilerServices;
using System.Text.Json;
using PhpNitroDesktop.Protocol;
using Xunit;

namespace PhpNitroDesktop.Protocol.Tests;

// Decodes real JSON shapes Engine\Native\Canvas::toJson() actually
// produces — the same cases DrawCommandTests.swift (iOS) and
// test_draw_command.py (Linux) already cover, ported case for case, plus
// the same shared golden-file fixture
// (packages/ui/tests/Golden/__fixtures__/button_with_icon.json) every
// platform's own decoder test reads verbatim.
public class DrawCommandTests
{
    // [CallerFilePath] captures THIS source file's real path at compile
    // time — walking up from it (DrawCommandTests.cs ->
    // PhpNitroDesktop.Protocol.Tests -> windows -> repo root) reaches the
    // monorepo root the same way Linux's own test_draw_command.py
    // (Path(__file__)...) and macOS's own MacPhpProcessTests.swift
    // (#filePath) both do, without hardcoding an absolute path.
    private static string RepoRoot([CallerFilePath] string sourceFilePath = "") =>
        Path.GetDirectoryName(Path.GetDirectoryName(Path.GetDirectoryName(sourceFilePath)!)!)!;

    [Fact]
    public void DecodesRectCommand()
    {
        var json = """{"type":"rect","x":0,"y":0,"width":200,"height":54,"color":"#111827","radius":999,"borderWidth":0}""";
        var command = DrawCommandParser.ParseCommand(JsonDocument.Parse(json).RootElement);

        var rect = Assert.IsType<RectCommand>(command);
        Assert.Equal(200, rect.Width);
        Assert.Equal(54, rect.Height);
        Assert.Equal("#111827", rect.Color);
        Assert.Equal(999, rect.Radius);
    }

    [Fact]
    public void DecodesTextCommand()
    {
        var json = """{"type":"text","x":89.1,"y":29.6,"text":"Valider","color":"#FFFFFF","size":15,"bold":true}""";
        var command = DrawCommandParser.ParseCommand(JsonDocument.Parse(json).RootElement);

        var text = Assert.IsType<TextCommand>(command);
        Assert.Equal("Valider", text.Text);
        Assert.True(text.Bold);
        Assert.Null(text.FontFamily);
    }

    [Fact]
    public void DecodesIconCommandWithFontAwesomeFont()
    {
        var json = """{"type":"icon","x":63.1,"y":18,"size":18,"codepoint":58826,"color":"#FFFFFF","font":"fontawesome"}""";
        var command = DrawCommandParser.ParseCommand(JsonDocument.Parse(json).RootElement);

        var icon = Assert.IsType<IconCommand>(command);
        Assert.Equal(58826, icon.Codepoint);
        Assert.Equal("fontawesome", icon.Font);
    }

    [Fact]
    public void UnrecognizedTypeDecodesToUnknownInsteadOfThrowing()
    {
        var json = """{"type":"custom:sparkline","values":[1,2,3]}""";
        var command = DrawCommandParser.ParseCommand(JsonDocument.Parse(json).RootElement);

        var unknown = Assert.IsType<UnknownCommand>(command);
        Assert.Equal("custom:sparkline", unknown.Type);
    }

    [Fact]
    public void DecodesAFullRealButtonPayloadFromTheSharedGoldenFixture()
    {
        var fixturePath = Path.Combine(RepoRoot(), "packages", "ui", "tests", "Golden", "__fixtures__", "button_with_icon.json");
        var json = File.ReadAllText(fixturePath);

        var payload = DrawCommandParser.ParsePayload(json);

        Assert.Equal(3, payload.Commands.Count);
        Assert.Equal(0, payload.ContentHeight);
        Assert.Single(payload.HitRegions);
        Assert.Equal("submit:demo", payload.HitRegions[0].Action);
    }

    [Fact]
    public void EmptyHitRegionsArrayDecodesWithoutThrowing()
    {
        var json = """{"commands":[],"hitRegions":[],"contentHeight":0}""";
        var payload = DrawCommandParser.ParsePayload(json);

        Assert.Empty(payload.HitRegions);
    }

    [Fact]
    public void ActionAtHitsTheContainingRegion()
    {
        var json = """
        {
            "commands": [],
            "hitRegions": [
                {"x":0,"y":0,"width":100,"height":50,"action":"navigate:home"},
                {"x":100,"y":0,"width":100,"height":50,"action":"navigate:settings"}
            ],
            "contentHeight": 0
        }
        """;
        var payload = DrawCommandParser.ParsePayload(json);

        Assert.Equal("navigate:home", payload.ActionAt(50, 25));
        Assert.Equal("navigate:settings", payload.ActionAt(150, 25));
        Assert.Null(payload.ActionAt(500, 500));
    }

    [Fact]
    public void ActionAtPrefersTheLastRegionWhenOverlapping()
    {
        var json = """
        {
            "commands": [],
            "hitRegions": [
                {"x":0,"y":0,"width":200,"height":200,"action":"background"},
                {"x":50,"y":50,"width":50,"height":50,"action":"foreground_button"}
            ],
            "contentHeight": 0
        }
        """;
        var payload = DrawCommandParser.ParsePayload(json);

        Assert.Equal("foreground_button", payload.ActionAt(60, 60));
        Assert.Equal("background", payload.ActionAt(10, 10));
    }

    [Fact]
    public void DecodesClientPanelCommandWithNestedCommands()
    {
        var json = """
        {
            "type": "clientPanel", "key": "tabs1", "index": 1, "initiallyActive": false, "x": 0, "y": 40,
            "commands": [{"type":"text","x":0,"y":0,"text":"Onglet 2","color":"#111827"}],
            "hitRegions": []
        }
        """;
        var command = DrawCommandParser.ParseCommand(JsonDocument.Parse(json).RootElement);

        var panel = Assert.IsType<ClientPanelCommand>(command);
        Assert.Equal("tabs1", panel.Key);
        Assert.Equal(1, panel.Index);
        Assert.False(panel.InitiallyActive);
        Assert.Single(panel.Commands);
    }

    [Fact]
    public void DecodesHScrollAndVScrollCommands()
    {
        var hScrollJson = """{"type":"hScroll","key":"carousel","x":0,"y":0,"width":300,"height":120,"contentWidth":900,"commands":[],"hitRegions":[]}""";
        var vScrollJson = """{"type":"vScroll","key":"comments","x":0,"y":0,"width":300,"height":200,"contentHeight":600,"commands":[],"hitRegions":[]}""";

        var hScroll = Assert.IsType<HScrollCommand>(DrawCommandParser.ParseCommand(JsonDocument.Parse(hScrollJson).RootElement));
        var vScroll = Assert.IsType<VScrollCommand>(DrawCommandParser.ParseCommand(JsonDocument.Parse(vScrollJson).RootElement));

        Assert.Equal(900, hScroll.ContentWidth);
        Assert.Equal(600, vScroll.ContentHeight);
    }

    [Fact]
    public void DecodesSliderCommand()
    {
        var json = """{"type":"slider","key":"volume","x":0,"y":0,"width":260,"height":32,"trackHeight":4,"thumbSize":20,"value":0.4,"trackColor":"#E5E7EB","activeColor":"#111827","thumbColor":"#FFFFFF"}""";
        var command = DrawCommandParser.ParseCommand(JsonDocument.Parse(json).RootElement);

        var slider = Assert.IsType<SliderCommand>(command);
        Assert.Equal("volume", slider.Key);
        Assert.Equal(0.4, slider.Value);
    }
}
