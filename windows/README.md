# Windows — protocole, moteur de rendu Rust (P/Invoke), et maintenant une vraie app WinForms

Contrairement à Linux et macOS, ce dossier ne contient **aucun code de rendu écrit spécifiquement pour Windows** (pas de GDI+ à la main, pas de Direct2D) — et ce n'est pas un manque : la décision prise pour tout le projet (voir `rust/phpnitro-render/README.md`) est qu'**un seul moteur de rendu partagé en Rust** remplace les implémentations natives séparées, y compris celle que Windows aurait dû écrire de zéro. Windows n'a donc jamais eu à trancher entre GDI+ et Direct2D — la question posée dans une version précédente de ce README est devenue caduque par ce choix d'architecture. `PhpNitroDesktop.App` (voir plus bas) ne fait donc que blitter le buffer RGBA8 que Rust lui donne — **Rust est le SEUL chemin de rendu sur Windows**, contrairement à Linux/macOS/iOS/Android qui ont chacun aussi un port natif complet en plus.

Aucun outil de compilation .NET/C# n'était disponible dans l'environnement où le protocole/le pont P/Invoke ont été écrits — tout ça a été écrit et relu à la main, vérifié exclusivement en CI (`ubuntu-latest`, voir plus bas). `PhpNitroDesktop.App` (l'app WinForms) est différente : elle a sa **propre CI sur un vrai runner `windows-latest`** (job `windows-desktop-app`), la première fois que ce fichier peut dire "vérifié sur un vrai environnement Windows" plutôt que "écrit à l'aveugle".

## `PhpNitroDesktop.Protocol`

Port C# de `PhpNitroProtocol` (iOS/macOS) / `draw_command.py`+`screen_client.py`+`navigation.py` (Linux) :

- `DrawCommand.cs` — décodage du même JSON `Engine\Native\Canvas::toJson()` que les trois autres plateformes, via un parseur manuel (`DrawCommandParser.ParseCommand(JsonElement)`) plutôt qu'un `JsonConverter<T>` personnalisé — délibérément, pour rester aussi proche que possible du décodage champ-par-champ déjà fait (et vérifié) côté Python/Swift. Chaque appel utilise des arguments **nommés** (`X: ..., Y: ...`) précisément pour qu'une paire de `double` inversée soit une erreur de compilation, pas un bug silencieux.
- `ScreenNavigation.cs` — `ScreenNavigation.Reduce(action, stack)`, port direct de `ScreenNavigation.swift`/`navigation.py`.
- `ScreenClient.cs` — fetch HTTP via `HttpClient`, port direct de `ScreenClient.swift`/`screen_client.py`. `FetchSuccess` porte à la fois le `DrawCommandPayload` décodé ET le JSON brut (`RawJson`) — `RustRenderer`/`RustHitTester` prennent l'enveloppe JSON directement, pas l'objet C# décodé.
- `HostPort.cs` — port direct de `HostPort.swift` (iOS)/`HostPort.kt` (Android) : parse `HOST:PORT` (schéma `http(s)://` et `/` final optionnels, port validé dans `1..65535`), utilisé par `PhpNitroDesktop.App`'s `--connect`.

**Cible `net8.0` pur, pas `net8.0-windows`** — aucune API Windows nulle part dans ce projet, exactement comme `PhpNitroProtocol` n'a jamais importé UIKit. Vérifié en CI sur `ubuntu-latest` (job `windows-desktop-protocol`) — un vrai `dotnet test`, y compris un vrai `php -S` lancé contre ce monorepo, pas seulement une compilation.

## `PhpNitroDesktop.Render`

Liaisons P/Invoke vers l'ABI C de `rust/phpnitro-render` (`include/phpnitro_render.h`) — le pendant Windows/.NET de `linux/phpnitro_desktop/rust_render.py` (ctypes). `RustRenderer` (rendu d'une frame) et `RustHitTester` (hit-testing), même contrat que les autres consommateurs : Rust alloue/libère chaque handle, `NativeLibrary.SetDllImportResolver` localise `rust/phpnitro-render/target/{release,debug}/...` exactement comme `rust_render.py` cherche son `.so`.

Le P/Invoke de .NET Core est lui-même multiplateforme — `[DllImport("phpnitro_render")]` résout `libphpnitro_render.so` (Linux), `.dylib` (macOS) ou `phpnitro_render.dll` (Windows) selon l'OS qui l'exécute réellement. `PhpNitroDesktop.Render.Tests` tourne dans le job CI `windows-desktop-protocol` (`ubuntu-latest`) contre la **vraie bibliothèque Linux compilée** — une preuve que ce code C# fonctionne contre le vrai ABI. Le job `windows-desktop-app` (voir plus bas), lui, compile `rust/phpnitro-render` **nativement sur un vrai runner Windows** (`cargo build --release` sur `windows-latest` produit directement `phpnitro_render.dll`, cible `x86_64-pc-windows-msvc` de l'hôte) — donc la compilation Windows native de la bibliothèque elle-même est désormais aussi vérifiée, pas seulement le pont P/Invoke.

## `PhpNitroDesktop.App` — la vraie app WinForms

Le pendant Windows de `linux/phpnitro_desktop/app.py`/`macos/Sources/PhpNitroMacEngine/MacScreenViewController.swift` :

- **`WindowsPhpProcess.cs`** — lance le `php` système en sous-processus (`System.Diagnostics.Process`, qui fonctionne sur Windows exactement comme `Foundation.Process` sur macOS ou `subprocess.Popen` sur Linux — pas de restriction de sandbox comme sur iOS), port direct de `php_process.py`/`MacPhpProcess.swift` (même recherche de port libre par bind-puis-release, même attente active jusqu'à ce que le port écoute).
- **`ScreenForm.cs`** — un `System.Windows.Forms.Form` qui possède un `ScreenClient` + un `RustRenderer`, fetch l'écran initial au chargement, peint via `OnPaint` (le buffer RGBA8 prémultiplié de tiny-skia est converti en `Bitmap` `Format32bppPArgb` — même alpha prémultiplié, ordre de canaux BGRA au lieu de RGBA, donc un simple échange R/B suffit, pas de dé-prémultiplication). `OnMouseDown`/`OnMouseMove`/`OnMouseUp` portent la machine à états tactile de `NativeCanvasView.kt` (seuil de slop, verrouillage d'axe) pour le drag `hScroll`/`vScroll` et le drag du curseur d'un `slider` — un clic simple (aucun drag décisif) hit-teste à la position de relâchement (`RustHitTester.HitTest` → `ScreenNavigation.Reduce`, qui reçoit maintenant aussi le `meta` du hit pour le mécanisme `toggle:`/`fieldValues`), exactement comme les autres plateformes. Géométrie des régions `slider`/`hScroll`/`vScroll` lue via `DrawCommandParser.ParsePayload` (lecture seule, jamais re-sérialisée vers Rust) ; le rendu/hit-test lui-même reste piloté par l'état d'interaction (`activePanel`/`axisOffset`/`sliderValue`) déjà supporté par le moteur Rust.
- **`Program.cs`** — point d'entrée `PhpNitroDesktop.App <project_dir> [screen]`, **ou** `PhpNitroDesktop.App --connect HOST:PORT [screen]` (mode PhpNitro Go, sans lancer de processus `php` local) — même convention que `linux/phpnitro_desktop/__main__.py`.

**Cible `net8.0-windows` + `UseWindowsForms`** — le premier projet de ce dossier qui n'est PAS multiplateforme, puisque `System.Windows.Forms` n'existe que sur Windows. Vérifié par le nouveau job CI `windows-desktop-app`, sur un **vrai runner `windows-latest`** — compilation seulement (`dotnet build`), pas d'exécution : aucun environnement CI ici n'a de serveur d'affichage pour cliquer une vraie fenêtre, la même limite honnête que chaque autre plateforme de ce dépôt.

## Ce qui manque encore

1. **Jamais lancé/cliqué sur une vraie machine Windows** — seule la compilation est vérifiée (CI), pas l'exécution interactive.
2. **Pas de saisie clavier** — `Checkbox`/`Toggle`/`Slider` commitent bien via `fieldValues` (voir ci-dessus), mais un vrai champ texte (`TextField`) n'a toujours aucune capture : ni `ScreenForm` ni aucune autre plateforme n'ont de superposition de widget natif pour ça (même limite honnête que Linux/iOS/macOS, voir leurs propres README).
3. **Pas de redimensionnement live** — la fenêtre a une taille fixe au démarrage (390×844, comme les autres plateformes) ; redimensionner ne redemande pas un nouvel écran à une nouvelle taille.
