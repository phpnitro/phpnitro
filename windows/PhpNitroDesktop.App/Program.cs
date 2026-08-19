using System;
using System.Windows.Forms;

namespace PhpNitroDesktop.App;

// Entry point — the Windows counterpart of linux/phpnitro_desktop/__main__.py's
// `python3 -m phpnitro_desktop <project_dir>`. Local mode only for now (no
// `--connect HOST:PORT` remote/PhpNitro-Go mode yet — see __main__.py's own
// docblock for that convention, real follow-up work here, not an oversight).
internal static class Program
{
    [STAThread]
    private static void Main(string[] args)
    {
        if (args.Length < 1)
        {
            Console.Error.WriteLine("usage: PhpNitroDesktop.App <project_directory> [screen]");
            Environment.Exit(1);
            return;
        }

        var projectDirectory = args[0];
        var screen = args.Length > 1 ? args[1] : "home";

        Application.SetHighDpiMode(HighDpiMode.SystemAware);
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);

        using var phpProcess = new WindowsPhpProcess(projectDirectory);
        var port = phpProcess.Start();

        var form = new ScreenForm("127.0.0.1", port, screen);
        Application.Run(form);
    }
}
