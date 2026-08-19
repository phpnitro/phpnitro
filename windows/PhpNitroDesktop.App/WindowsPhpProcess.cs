using System;
using System.Diagnostics;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Threading;

namespace PhpNitroDesktop.App;

// Spawns the project's own PHP server as a real child process — the
// Windows counterpart of linux/phpnitro_desktop/php_process.py and
// macos/Sources/PhpNitroMacEngine/MacPhpProcess.swift. System.Diagnostics.Process
// works on Windows exactly like Foundation.Process on macOS/subprocess.Popen
// on Linux (no iOS-style sandbox restriction on launching a child process),
// so this just runs the SYSTEM `php` binary (whatever `php -v` on PATH
// already resolves to) straight against the project directory's own
// public/ — same invocation `bin/phpx serve` itself already uses.
//
// Known, deliberate scoping gap, same as every other desktop port: this
// means PHP itself must already be installed on the machine running this
// shell. Bundling a portable per-OS PHP binary is real, separate
// follow-up work.
public sealed class WindowsPhpProcess : IDisposable
{
    private readonly string _projectDirectory;
    private Process? _process;

    public int Port { get; private set; }

    public WindowsPhpProcess(string projectDirectory)
    {
        _projectDirectory = projectDirectory;
    }

    private static int FindFreePort()
    {
        var listener = new TcpListener(IPAddress.Loopback, 0);
        listener.Start();
        var port = ((IPEndPoint)listener.LocalEndpoint).Port;
        listener.Stop();
        return port;
    }

    private static bool IsListening(int port)
    {
        try
        {
            using var client = new TcpClient();
            var connectTask = client.ConnectAsync(IPAddress.Loopback, port);
            return connectTask.Wait(TimeSpan.FromMilliseconds(200)) && client.Connected;
        }
        catch
        {
            return false;
        }
    }

    private static void WaitUntilListening(int port, TimeSpan timeout)
    {
        var deadline = DateTime.UtcNow + timeout;
        while (DateTime.UtcNow < deadline)
        {
            if (IsListening(port))
            {
                return;
            }
            Thread.Sleep(150);
        }
        throw new TimeoutException($"php -S never started listening on 127.0.0.1:{port}");
    }

    public int Start()
    {
        var publicDir = Path.Combine(_projectDirectory, "public");
        if (!Directory.Exists(publicDir))
        {
            throw new DirectoryNotFoundException($"no public/ directory found at {publicDir}");
        }

        Port = FindFreePort();
        var router = Path.Combine(publicDir, "router.php");

        var startInfo = new ProcessStartInfo
        {
            FileName = "php",
            WorkingDirectory = _projectDirectory,
            UseShellExecute = false,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };
        startInfo.ArgumentList.Add("-S");
        startInfo.ArgumentList.Add($"127.0.0.1:{Port}");
        startInfo.ArgumentList.Add("-t");
        startInfo.ArgumentList.Add(publicDir);
        if (File.Exists(router))
        {
            startInfo.ArgumentList.Add(router);
        }

        // No PHPNITRO_ACCESS_TOKEN here — deliberately, same as
        // php_process.py's own choice: that token guards against a
        // DIFFERENT, lower-trust app on the same device reaching this
        // one's PHP over loopback, a real Android/iOS sandbox boundary
        // that doesn't exist between ordinary desktop processes owned
        // by the same user.
        _process = Process.Start(startInfo)
            ?? throw new InvalidOperationException("Process.Start(php) returned null");
        WaitUntilListening(Port, TimeSpan.FromSeconds(12));
        return Port;
    }

    public void Stop()
    {
        if (_process is null)
        {
            return;
        }
        try
        {
            if (!_process.HasExited)
            {
                _process.Kill(entireProcessTree: true);
                _process.WaitForExit(5000);
            }
        }
        catch (InvalidOperationException)
        {
            // Already exited between the check and the call — fine.
        }
        finally
        {
            _process.Dispose();
            _process = null;
        }
    }

    public bool IsRunning => _process is { HasExited: false };

    public void Dispose() => Stop();
}
