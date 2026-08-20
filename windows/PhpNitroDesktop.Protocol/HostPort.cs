namespace PhpNitroDesktop.Protocol;

// The Windows counterpart of ios/Sources/PhpNitroGo/HostPort.swift and
// android/go/src/main/java/com/phpnitro/go/HostPort.kt — a fresh port
// rather than a shared reference, since neither of those lives in a
// platform-agnostic package this project could depend on. Used by
// PhpNitroDesktop.App's own `--connect HOST:PORT` flag, the same
// "IP:PORT, optionally prefixed with a scheme" string `phpx serve`
// prints on every platform.
public static class HostPort
{
    public static (string Host, int Port)? Parse(string input)
    {
        var withoutScheme = input.Trim();
        foreach (var prefix in new[] { "http://", "https://" })
        {
            if (withoutScheme.StartsWith(prefix, System.StringComparison.Ordinal))
            {
                withoutScheme = withoutScheme[prefix.Length..];
                break;
            }
        }
        withoutScheme = withoutScheme.TrimEnd('/');

        var colonIndex = withoutScheme.LastIndexOf(':');
        if (colonIndex <= 0 || colonIndex == withoutScheme.Length - 1)
        {
            return null;
        }

        var host = withoutScheme[..colonIndex];
        var portString = withoutScheme[(colonIndex + 1)..];

        if (!int.TryParse(portString, out var port) || port < 1 || port > 65535)
        {
            return null;
        }

        return (host, port);
    }
}
