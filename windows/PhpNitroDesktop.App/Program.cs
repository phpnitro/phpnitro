using System;
using System.Windows.Forms;
using PhpNitroDesktop.Protocol;

namespace PhpNitroDesktop.App;

// Entry point — the Windows counterpart of linux/phpnitro_desktop/__main__.py's
// `python3 -m phpnitro_desktop <project_dir>` / `--connect HOST:PORT`. Two
// launch modes, mirroring that same convention:
//   PhpNitroDesktop.App <project_directory> [screen]   — spawns `php -S` itself
//   PhpNitroDesktop.App --connect HOST:PORT [screen]   — PhpNitro-Go-style
//                                                         remote client, no
//                                                         local process at all
internal static class Program
{
    [STAThread]
    private static void Main(string[] args)
    {
        if (args.Length < 1)
        {
            Console.Error.WriteLine("usage: PhpNitroDesktop.App <project_directory> [screen]");
            Console.Error.WriteLine("   or: PhpNitroDesktop.App --connect HOST:PORT [screen]");
            Environment.Exit(1);
            return;
        }

        Application.SetHighDpiMode(HighDpiMode.SystemAware);
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);

        if (args[0] == "--connect")
        {
            var parsed = args.Length > 1 ? HostPort.Parse(args[1]) : null;
            if (parsed is null)
            {
                Console.Error.WriteLine("--connect expects HOST:PORT, e.g. 192.168.1.23:8090");
                Environment.Exit(1);
                return;
            }
            var connectScreen = args.Length > 2 ? args[2] : "home";
            Application.Run(new ScreenForm(parsed.Value.Host, parsed.Value.Port, connectScreen));
            return;
        }

        var projectDirectory = args[0];
        var screen = args.Length > 1 ? args[1] : "home";

        using var phpProcess = new WindowsPhpProcess(projectDirectory);
        var port = phpProcess.Start();

        var form = new ScreenForm("127.0.0.1", port, screen);
        Application.Run(form);
    }
}
