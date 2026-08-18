# Windows — couche protocole seulement, le rendu GDI+/WinForms n'est pas encore attaqué

Contrairement à Linux et macOS, ce dossier **ne contient pas encore de moteur de rendu**. Une seule raison, honnête : aucun outil de compilation .NET/C# n'était disponible dans l'environnement où ce code a été écrit (pas de `dotnet`, pas de `mono`, pas de `mingw`, pas de `wine` — voir l'historique des commits pour la vérification faite avant de commencer). Tout ce qui suit a été écrit et relu à la main, sans jamais pouvoir compiler une seule ligne localement — un cran de plus que macOS/iOS (qui, eux, s'appuyaient sur un vrai précédent Swift déjà vérifié dans ce même dépôt). Plutôt que d'écrire une grosse quantité de code Windows-spécifique (GDI+/Direct2D/WinForms) invérifiable même syntaxiquement avant de la pousser, seule la couche **protocole pure** a été portée — celle qui n'a besoin d'aucune API Windows pour être réelle et testée.

## Ce qui existe : `PhpNitroDesktop.Protocol`

Port C# de `PhpNitroProtocol` (iOS/macOS) / `draw_command.py`+`screen_client.py`+`navigation.py` (Linux) :

- `DrawCommand.cs` — décodage du même JSON `Engine\Native\Canvas::toJson()` que les trois autres plateformes, via un parseur manuel (`DrawCommandParser.ParseCommand(JsonElement)`) plutôt qu'un `JsonConverter<T>` personnalisé — délibérément, pour rester aussi proche que possible du décodage champ-par-champ déjà fait (et vérifié) côté Python/Swift, plutôt que d'introduire une API `System.Text.Json` plus indirecte et donc plus risquée à écrire à l'aveugle. Chaque appel utilise des arguments **nommés** (`X: ..., Y: ...`) précisément pour qu'une paire de `double` inversée soit une erreur de compilation, pas un bug silencieux.
- `ScreenNavigation.cs` — `ScreenNavigation.Reduce(action, stack)`, port direct de `ScreenNavigation.swift`/`navigation.py`.
- `ScreenClient.cs` — fetch HTTP via `HttpClient`, port direct de `ScreenClient.swift`/`screen_client.py`.

**Cible `net8.0` pur, pas `net8.0-windows`** — aucune API Windows nulle part dans ce projet, exactement comme `PhpNitroProtocol` n'a jamais importé UIKit. Vérifié en CI sur `ubuntu-latest` (`.github/workflows/ci.yml`, job `windows-desktop-protocol`) — un vrai `dotnet test`, y compris un vrai `php -S` lancé contre ce monorepo (même rigueur que `linux/tests/test_php_process.py`), pas seulement une compilation. Le nom du job est trompeur si on s'arrête au dossier : c'est la seule partie de ce chantier Windows qui tourne réellement quelque part pour l'instant, et elle tourne sur Linux — la preuve la plus concrète possible que cette couche n'a vraiment rien de spécifique à Windows.

## Ce qui manque — le vrai chantier Windows, pas commencé

1. **Le rendu lui-même** — un choix entre GDI+ (`System.Drawing`, simple, ancien, stable) et Direct2D (moderne, accéléré, mais programmation COM en C++ plus risquée à écrire sans compilateur sous la main). Pas tranché.
2. **Une vraie app** — `System.Windows.Forms` (`Form` + `Control.OnPaint`) est le candidat le plus simple pour héberger le rendu une fois choisi, mais rien n'est écrit.
3. **Le lancement du process PHP** — `System.Diagnostics.Process` fonctionne sur Windows exactement comme documenté pour macOS (`MacPhpProcess.swift`) et Linux (`php_process.py`) — pas de restriction de sandboxing comme sur iOS — mais aucun `WindowsPhpProcess.cs` n'existe encore.
4. **Un vrai job CI qui tourne sur `windows-latest`** — celui qui existe (`windows-desktop-protocol`) tourne délibérément sur `ubuntu-latest`, justement parce que rien ici n'a encore besoin de Windows pour de vrai.

À reprendre une fois qu'un vrai environnement Windows (ou au moins un `dotnet` local) est disponible pour vérifier au fur et à mesure, plutôt que de continuer à l'aveugle.
