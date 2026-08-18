using System.Collections.Generic;
using System.Text.Json;

namespace PhpNitroDesktop.Protocol;

// Decodes the same JSON Engine\Native\Canvas::toJson() already produces
// for Android (NativeCanvasView.kt), iOS (DrawCommand.swift), and Linux
// (draw_command.py) — a fourth independent consumer of the identical
// wire protocol. Modeled as a plain record hierarchy + a manual parser
// (DrawCommandParser.Parse below) rather than a System.Text.Json custom
// JsonConverter<T> — deliberately: this whole file was written with no
// .NET SDK available to compile or run it against (see windows/README.md),
// and hand-parsing a JsonElement field by field, mirroring
// draw_command.py's own decode_command(dict) shape almost line for line,
// is far less likely to hide a subtle mistake than a converter's more
// indirect API surface would be. Every call site below uses NAMED
// arguments specifically so a swapped pair of doubles (X/Y, Width/Height)
// would be a compile error, not a silent mix-up — the one class of bug
// this file has no other way to catch before a human (or CI) runs it.
//
// An unrecognized "type" decodes to UnknownCommand rather than throwing
// — the same "PHP decides, an unhandled command is a silent no-op, not
// a crash" contract every other platform's own decoder already follows.

public abstract record DrawCommand;

public sealed record RectCommand(
    double X, double Y, double Width, double Height,
    string? Color, double? Radius, string? BorderColor, double? BorderWidth
) : DrawCommand;

public sealed record TextCommand(
    double X, double Y, string Text,
    string? Color, double? Size, bool? Bold, double? LetterSpacing, string? FontFamily
) : DrawCommand;

public sealed record IconCommand(
    double X, double Y, double Size, int Codepoint, string? Color, string? Font
) : DrawCommand;

public sealed record CircleCommand(
    double Cx, double Cy, double Radius, string? Color, string? BorderColor, double? BorderWidth
) : DrawCommand;

public sealed record LineCommand(
    double X1, double Y1, double X2, double Y2, string Color, double? Width
) : DrawCommand;

public sealed record ArcCommand(
    double Cx, double Cy, double Radius, double StartDegrees, double SweepDegrees, string Color, double StrokeWidth
) : DrawCommand;

public sealed record ImageCommand(
    double X, double Y, double Width, double Height, string Url, double? Radius
) : DrawCommand;

public sealed record SpinnerCommand(
    double X, double Y, double Size, string Color, string TrackColor, double StrokeWidth
) : DrawCommand;

public sealed record SkeletonCommand(
    double X, double Y, double Width, double Height, string Color, double Radius
) : DrawCommand;

public sealed record HitRegion(double X, double Y, double Width, double Height, string Action);

public sealed record ClientPanelCommand(
    string Key, int Index, bool InitiallyActive, double X, double Y,
    IReadOnlyList<DrawCommand> Commands, IReadOnlyList<HitRegion> HitRegions
) : DrawCommand;

public sealed record HScrollCommand(
    string Key, double X, double Y, double Width, double Height, double ContentWidth,
    IReadOnlyList<DrawCommand> Commands, IReadOnlyList<HitRegion> HitRegions
) : DrawCommand;

public sealed record VScrollCommand(
    string Key, double X, double Y, double Width, double Height, double ContentHeight,
    IReadOnlyList<DrawCommand> Commands, IReadOnlyList<HitRegion> HitRegions
) : DrawCommand;

public sealed record SliderCommand(
    string Key, double X, double Y, double Width, double Height,
    double TrackHeight, double ThumbSize, double Value,
    string TrackColor, string ActiveColor, string ThumbColor
) : DrawCommand;

public sealed record UnknownCommand(string Type) : DrawCommand;

// The envelope Canvas::toJson() wraps every render in. HitRegions is
// always present (possibly empty), never omitted.
public sealed record DrawCommandPayload(
    IReadOnlyList<DrawCommand> Commands, IReadOnlyList<HitRegion> HitRegions, double ContentHeight
)
{
    // Which hitRegion (if any) a click at (x, y) should fire — checked
    // in REVERSE declaration order, since a later region was painted
    // later (visually on top). Mirrors DrawCommandPayload.action(at:)
    // on iOS and DrawCommandPayload.action_at(...) on Linux exactly.
    public string? ActionAt(double x, double y)
    {
        for (int i = HitRegions.Count - 1; i >= 0; i--)
        {
            var region = HitRegions[i];
            if (x >= region.X && x <= region.X + region.Width && y >= region.Y && y <= region.Y + region.Height)
            {
                return region.Action;
            }
        }
        return null;
    }
}

public static class DrawCommandParser
{
    public static DrawCommand ParseCommand(JsonElement element)
    {
        var type = element.GetProperty("type").GetString();

        return type switch
        {
            "rect" => new RectCommand(
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Width: element.GetProperty("width").GetDouble(),
                Height: element.GetProperty("height").GetDouble(),
                Color: GetStringOrNull(element, "color"),
                Radius: GetDoubleOrNull(element, "radius"),
                BorderColor: GetStringOrNull(element, "borderColor"),
                BorderWidth: GetDoubleOrNull(element, "borderWidth")
            ),
            "text" => new TextCommand(
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Text: element.GetProperty("text").GetString()!,
                Color: GetStringOrNull(element, "color"),
                Size: GetDoubleOrNull(element, "size"),
                Bold: GetBoolOrNull(element, "bold"),
                LetterSpacing: GetDoubleOrNull(element, "letterSpacing"),
                FontFamily: GetStringOrNull(element, "fontFamily")
            ),
            "icon" => new IconCommand(
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Size: element.GetProperty("size").GetDouble(),
                Codepoint: element.GetProperty("codepoint").GetInt32(),
                Color: GetStringOrNull(element, "color"),
                Font: GetStringOrNull(element, "font")
            ),
            "circle" => new CircleCommand(
                Cx: element.GetProperty("cx").GetDouble(),
                Cy: element.GetProperty("cy").GetDouble(),
                Radius: element.GetProperty("radius").GetDouble(),
                Color: GetStringOrNull(element, "color"),
                BorderColor: GetStringOrNull(element, "borderColor"),
                BorderWidth: GetDoubleOrNull(element, "borderWidth")
            ),
            "line" => new LineCommand(
                X1: element.GetProperty("x1").GetDouble(),
                Y1: element.GetProperty("y1").GetDouble(),
                X2: element.GetProperty("x2").GetDouble(),
                Y2: element.GetProperty("y2").GetDouble(),
                Color: element.GetProperty("color").GetString()!,
                Width: GetDoubleOrNull(element, "width")
            ),
            "arc" => new ArcCommand(
                Cx: element.GetProperty("cx").GetDouble(),
                Cy: element.GetProperty("cy").GetDouble(),
                Radius: element.GetProperty("radius").GetDouble(),
                StartDegrees: element.GetProperty("startDegrees").GetDouble(),
                SweepDegrees: element.GetProperty("sweepDegrees").GetDouble(),
                Color: element.GetProperty("color").GetString()!,
                StrokeWidth: element.GetProperty("strokeWidth").GetDouble()
            ),
            "image" => new ImageCommand(
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Width: element.GetProperty("width").GetDouble(),
                Height: element.GetProperty("height").GetDouble(),
                Url: element.GetProperty("url").GetString()!,
                Radius: GetDoubleOrNull(element, "radius")
            ),
            "spinner" => new SpinnerCommand(
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Size: element.GetProperty("size").GetDouble(),
                Color: element.GetProperty("color").GetString()!,
                TrackColor: element.GetProperty("trackColor").GetString()!,
                StrokeWidth: element.GetProperty("strokeWidth").GetDouble()
            ),
            "skeleton" => new SkeletonCommand(
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Width: element.GetProperty("width").GetDouble(),
                Height: element.GetProperty("height").GetDouble(),
                Color: element.GetProperty("color").GetString()!,
                Radius: element.GetProperty("radius").GetDouble()
            ),
            "clientPanel" => new ClientPanelCommand(
                Key: element.GetProperty("key").GetString()!,
                Index: element.GetProperty("index").GetInt32(),
                InitiallyActive: element.GetProperty("initiallyActive").GetBoolean(),
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Commands: ParseCommandList(element.GetProperty("commands")),
                HitRegions: ParseHitRegionList(element.GetProperty("hitRegions"))
            ),
            "hScroll" => new HScrollCommand(
                Key: element.GetProperty("key").GetString()!,
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Width: element.GetProperty("width").GetDouble(),
                Height: element.GetProperty("height").GetDouble(),
                ContentWidth: element.GetProperty("contentWidth").GetDouble(),
                Commands: ParseCommandList(element.GetProperty("commands")),
                HitRegions: ParseHitRegionList(element.GetProperty("hitRegions"))
            ),
            "vScroll" => new VScrollCommand(
                Key: element.GetProperty("key").GetString()!,
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Width: element.GetProperty("width").GetDouble(),
                Height: element.GetProperty("height").GetDouble(),
                ContentHeight: element.GetProperty("contentHeight").GetDouble(),
                Commands: ParseCommandList(element.GetProperty("commands")),
                HitRegions: ParseHitRegionList(element.GetProperty("hitRegions"))
            ),
            "slider" => new SliderCommand(
                Key: element.GetProperty("key").GetString()!,
                X: element.GetProperty("x").GetDouble(),
                Y: element.GetProperty("y").GetDouble(),
                Width: element.GetProperty("width").GetDouble(),
                Height: element.GetProperty("height").GetDouble(),
                TrackHeight: element.GetProperty("trackHeight").GetDouble(),
                ThumbSize: element.GetProperty("thumbSize").GetDouble(),
                Value: element.GetProperty("value").GetDouble(),
                TrackColor: element.GetProperty("trackColor").GetString()!,
                ActiveColor: element.GetProperty("activeColor").GetString()!,
                ThumbColor: element.GetProperty("thumbColor").GetString()!
            ),
            _ => new UnknownCommand(type ?? "null"),
        };
    }

    public static HitRegion ParseHitRegion(JsonElement element) => new(
        X: element.GetProperty("x").GetDouble(),
        Y: element.GetProperty("y").GetDouble(),
        Width: element.GetProperty("width").GetDouble(),
        Height: element.GetProperty("height").GetDouble(),
        Action: element.GetProperty("action").GetString()!
    );

    public static DrawCommandPayload ParsePayload(JsonElement element) => new(
        Commands: ParseCommandList(element.GetProperty("commands")),
        HitRegions: ParseHitRegionList(element.GetProperty("hitRegions")),
        ContentHeight: element.GetProperty("contentHeight").GetDouble()
    );

    public static DrawCommandPayload ParsePayload(string json)
    {
        using var document = JsonDocument.Parse(json);
        return ParsePayload(document.RootElement);
    }

    private static List<DrawCommand> ParseCommandList(JsonElement arrayElement)
    {
        var list = new List<DrawCommand>();
        foreach (var item in arrayElement.EnumerateArray())
        {
            list.Add(ParseCommand(item));
        }
        return list;
    }

    private static List<HitRegion> ParseHitRegionList(JsonElement arrayElement)
    {
        var list = new List<HitRegion>();
        foreach (var item in arrayElement.EnumerateArray())
        {
            list.Add(ParseHitRegion(item));
        }
        return list;
    }

    private static string? GetStringOrNull(JsonElement element, string name) =>
        element.TryGetProperty(name, out var prop) && prop.ValueKind != JsonValueKind.Null ? prop.GetString() : null;

    private static double? GetDoubleOrNull(JsonElement element, string name) =>
        element.TryGetProperty(name, out var prop) && prop.ValueKind != JsonValueKind.Null ? prop.GetDouble() : null;

    private static bool? GetBoolOrNull(JsonElement element, string name) =>
        element.TryGetProperty(name, out var prop) && prop.ValueKind != JsonValueKind.Null ? prop.GetBoolean() : null;
}
