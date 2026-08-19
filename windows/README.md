# Windows — protocole + moteur de rendu Rust (P/Invoke), pas encore de fenêtre réelle

Contrairement à Linux et macOS, ce dossier ne contient **aucun code de rendu écrit spécifiquement pour Windows** (pas de GDI+, pas de Direct2D) — et ce n'est plus un manque : la décision prise pour tout le projet (voir `rust/phpnitro-render/README.md`) est qu'**un seul moteur de rendu partagé en Rust** remplace les implémentations natives séparées, y compris celle que Windows aurait dû écrire de zéro. Windows n'a donc jamais eu à trancher entre GDI+ et Direct2D — la question posée dans une version précédente de ce README est devenue caduque par ce choix d'architecture, pas résolue par un choix Windows-spécifique.

Aucun outil de compilation .NET/C# n'était disponible dans l'environnement où ce code a été écrit (pas de `dotnet`, pas de `mono`, pas de `mingw`, pas de `wine`). Tout ce qui suit a été écrit et relu à la main, sans jamais pouvoir compiler une seule ligne localement — un cran de plus que macOS/iOS (qui, eux, s'appuyaient sur un vrai précédent Swift déjà vérifié dans ce même dépôt). Vérifié exclusivement en CI, un vrai `dotnet test` à chaque push, jamais une simple relecture.

## Ce qui existe : `PhpNitroDesktop.Protocol`

Port C# de `PhpNitroProtocol` (iOS/macOS) / `draw_command.py`+`screen_client.py`+`navigation.py` (Linux) :

- `DrawCommand.cs` — décodage du même JSON `Engine\Native\Canvas::toJson()` que les trois autres plateformes, via un parseur manuel (`DrawCommandParser.ParseCommand(JsonElement)`) plutôt qu'un `JsonConverter<T>` personnalisé — délibérément, pour rester aussi proche que possible du décodage champ-par-champ déjà fait (et vérifié) côté Python/Swift, plutôt que d'introduire une API `System.Text.Json` plus indirecte et donc plus risquée à écrire à l'aveugle. Chaque appel utilise des arguments **nommés** (`X: ..., Y: ...`) précisément pour qu'une paire de `double` inversée soit une erreur de compilation, pas un bug silencieux.
- `ScreenNavigation.cs` — `ScreenNavigation.Reduce(action, stack)`, port direct de `ScreenNavigation.swift`/`navigation.py`.
- `ScreenClient.cs` — fetch HTTP via `HttpClient`, port direct de `ScreenClient.swift`/`screen_client.py`.

**Cible `net8.0` pur, pas `net8.0-windows`** — aucune API Windows nulle part dans ce projet, exactement comme `PhpNitroProtocol` n'a jamais importé UIKit. Vérifié en CI sur `ubuntu-latest` (`.github/workflows/ci.yml`, job `windows-desktop-protocol`) — un vrai `dotnet test`, y compris un vrai `php -S` lancé contre ce monorepo (même rigueur que `linux/tests/test_php_process.py`), pas seulement une compilation.

## Ce qui existe maintenant aussi : `PhpNitroDesktop.Render`

Liaisons P/Invoke vers l'ABI C de `rust/phpnitro-render` (`include/phpnitro_render.h`) — le pendant Windows/.NET de `linux/phpnitro_desktop/rust_render.py` (ctypes). `RustRenderer` (rendu d'une frame) et `RustHitTester` (hit-testing), même contrat que les autres consommateurs : Rust alloue/libère chaque handle, `NativeLibrary.SetDllImportResolver` localise `rust/phpnitro-render/target/{release,debug}/...` exactement comme `rust_render.py` cherche son `.so`.

**Le point fort de cette approche** : le P/Invoke de .NET Core est lui-même multiplateforme — `[DllImport("phpnitro_render")]` résout `libphpnitro_render.so` (Linux), `.dylib` (macOS) ou `phpnitro_render.dll` (Windows) selon l'OS qui l'exécute réellement, sans une seule ligne de code spécifique à un OS. `PhpNitroDesktop.Render.Tests` tourne donc, dans le même job CI `windows-desktop-protocol` (`ubuntu-latest`), contre la **vraie bibliothèque Linux compilée** (`cargo build --release` ajouté à ce même job) — une preuve réelle que ce code C# fonctionnerait aussi contre un `.dll` Windows, pas une affirmation non vérifiée. Mêmes fixtures dorées que `rust/phpnitro-render` (`button_with_icon.json`) : rendu pixel réel du fond du bouton, hit-test qui retrouve la bonne action.

Honnêteté : ceci prouve que **le pont P/Invoke** fonctionne, pas que `phpnitro_render.dll` compile ou tourne réellement sur Windows — ça, aucun environnement disponible ici ne peut le vérifier (voir plus bas).

## Ce qui manque — le vrai chantier Windows, pas commencé

1. **Compiler `rust/phpnitro-render` pour la cible Windows elle-même** (`x86_64-pc-windows-msvc` ou `-gnu`) — aucun `rustup target add` n'a été fait ici (aucune installation locale, et ça resterait de toute façon à vérifier en CI sur un vrai runner Windows). Le job CI actuel ne prouve que le pont P/Invoke, pas la compilation Windows native de la bibliothèque.
2. **Une vraie app** — `System.Windows.Forms` (`Form` + `Control.OnPaint`, blit du buffer RGBA8 retourné par `RenderFrame()`) est le candidat le plus simple pour héberger le rendu, mais rien n'est écrit.
3. **Le lancement du process PHP** — `System.Diagnostics.Process` fonctionne sur Windows exactement comme documenté pour macOS (`MacPhpProcess.swift`) et Linux (`php_process.py`) — pas de restriction de sandboxing comme sur iOS — mais aucun `WindowsPhpProcess.cs` n'existe encore.
4. **Un vrai job CI qui tourne sur `windows-latest`** — celui qui existe tourne délibérément sur `ubuntu-latest`, pour prouver que le protocole ET le pont P/Invoke sont réellement indépendants de l'OS, pas parce que Windows n'en a jamais besoin.

À reprendre une fois qu'un vrai environnement Windows (ou au moins un `dotnet`+`rustup target add x86_64-pc-windows-msvc` local) est disponible pour vérifier au fur et à mesure, plutôt que de continuer à l'aveugle.
