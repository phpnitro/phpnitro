using System.Diagnostics;
using System.Net.Sockets;
using System.Runtime.CompilerServices;
using PhpNitroDesktop.Protocol;
using Xunit;

namespace PhpNitroDesktop.Protocol.Tests;

public class BuildUrlTests
{
    [Fact]
    public void BuildsTheExpectedUrl()
    {
        var url = ScreenClient.BuildUrl("192.168.1.23", 8090, "home", "counter:increment", 390, 844);

        Assert.StartsWith("http://192.168.1.23:8090/native/layout-demo?", url);
        Assert.Contains("screen=home", url);
        Assert.Contains("action=counter%3Aincrement", url);
    }

    [Fact]
    public void OmitsActionWhenNull()
    {
        var url = ScreenClient.BuildUrl("127.0.0.1", 8090, "home", null, 390, 844);

        Assert.DoesNotContain("action=", url);
    }

    [Fact]
    public void IncludesFieldValuesSortedByName()
    {
        var fieldValues = new Dictionary<string, string> { ["password"] = "hunter2", ["email"] = "a@b.com" };
        var url = ScreenClient.BuildUrl("127.0.0.1", 8090, "login", null, 390, 844, fieldValues);

        Assert.True(url.IndexOf("email", StringComparison.Ordinal) < url.IndexOf("password", StringComparison.Ordinal));
    }
}

// A real integration test — actually spawns `php -S` against this repo,
// not a mocked HTTP layer, the same rigor as
// linux/tests/test_screen_client.py and macos/.../MacPhpProcessTests.swift.
// This whole test project targets plain net8.0 (no Windows-only APIs),
// so it runs on ubuntu-latest in CI — see
// .github/workflows/ci.yml's windows-desktop-protocol job.
public class ScreenClientIntegrationTests : IAsyncLifetime
{
    private static string RepoRoot([CallerFilePath] string sourceFilePath = "") =>
        Path.GetDirectoryName(Path.GetDirectoryName(Path.GetDirectoryName(sourceFilePath)!)!)!;

    private Process? _phpProcess;
    private int _port;

    public async Task InitializeAsync()
    {
        var repoRoot = RepoRoot();
        if (!File.Exists(Path.Combine(repoRoot, "vendor", "autoload.php")))
        {
            // No composer install in this checkout — every [Fact] below
            // guards on this same condition and calls Skip, matching
            // the @unittest.skipUnless/XCTSkip pattern the other two
            // platforms' own integration tests already use.
            return;
        }

        _port = GetFreePort();
        _phpProcess = new Process
        {
            StartInfo = new ProcessStartInfo
            {
                FileName = "php",
                Arguments = $"-S 127.0.0.1:{_port} -t public public/router.php",
                WorkingDirectory = repoRoot,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
            },
        };
        _phpProcess.Start();
        await WaitUntilListeningAsync(_port).ConfigureAwait(false);
    }

    public Task DisposeAsync()
    {
        if (_phpProcess is { HasExited: false })
        {
            _phpProcess.Kill();
        }
        _phpProcess?.Dispose();
        return Task.CompletedTask;
    }

    [Fact]
    public async Task FetchesAndDecodesTheRealHomeScreen()
    {
        if (_phpProcess is null) return; // composer install hasn't run in this checkout

        var client = new ScreenClient("127.0.0.1", _port);
        var result = await client.FetchScreenAsync("home", null, 390, 844).ConfigureAwait(false);

        var success = Assert.IsType<FetchSuccess>(result);
        Assert.NotEmpty(success.Payload.Commands);
    }

    [Fact]
    public async Task AnActionTapRoundTripsAgainstTheRealServer()
    {
        if (_phpProcess is null) return;

        var client = new ScreenClient("127.0.0.1", _port);
        var first = await client.FetchScreenAsync("home", null, 390, 844).ConfigureAwait(false);
        var second = await client.FetchScreenAsync("home", "home_increment", 390, 844).ConfigureAwait(false);

        Assert.IsType<FetchSuccess>(first);
        Assert.IsType<FetchSuccess>(second);
    }

    private static int GetFreePort()
    {
        var listener = new TcpListener(System.Net.IPAddress.Loopback, 0);
        listener.Start();
        var port = ((System.Net.IPEndPoint)listener.LocalEndpoint).Port;
        listener.Stop();
        return port;
    }

    private static async Task WaitUntilListeningAsync(int port, int timeoutSeconds = 12)
    {
        var deadline = DateTime.UtcNow.AddSeconds(timeoutSeconds);
        while (DateTime.UtcNow < deadline)
        {
            try
            {
                using var client = new TcpClient();
                await client.ConnectAsync(System.Net.IPAddress.Loopback, port).ConfigureAwait(false);
                return;
            }
            catch (SocketException)
            {
                await Task.Delay(150).ConfigureAwait(false);
            }
        }
        throw new TimeoutException($"php -S never started listening on 127.0.0.1:{port}");
    }
}
