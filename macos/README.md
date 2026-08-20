# macOS — réutilise Core Graphics/le protocole d'iOS, jamais compilé (comme le reste d'iOS)

Ce dossier est un Swift Package **séparé** de `ios/` (voir son propre `Package.swift` pour le pourquoi de cette séparation — en résumé : `ios/`'s aggregate scheme `-Package` construirait aussi les cibles UIKit-only si on essayait de le cibler pour macOS, ce qui casserait tout ; un package séparé évite ce risque complètement, sans toucher une seule ligne du code iOS déjà vérifié).

## Ce qui est réutilisé tel quel depuis iOS

`ios/Sources/PhpNitroProtocol/` (décodage JSON, client réseau, réducteur de navigation) — **aucune duplication, aucune modification** : ce package dépend du produit `PhpNitroProtocol` d'`ios/` via une dépendance de chemin local (`.package(path: "../ios")`). Ces trois fichiers n'ont jamais importé UIKit — seulement Foundation/CoreGraphics — ce qui en fait le premier morceau de ce framework véritablement multiplateforme au niveau du code source, pas seulement du protocole JSON.

## Ce qui est réécrit pour AppKit

Les appels Core Graphics eux-mêmes (`context.addPath`, `fillPath`, `strokePath`, `drawLinearGradient`...) sont **identiques mot pour mot** à `NativeCanvasView.swift` (iOS) — Core Graphics est un framework C partagé par UIKit et AppKit, pas la propriété de l'un ou l'autre. Ce qui change réellement :

| iOS (UIKit) | macOS (AppKit) |
|---|---|
| `UIView` | `NSView` — avec `isFlipped` surchargé à `true`, sans quoi tout se dessinerait à l'envers (AppKit a une origine bas-gauche par défaut, contrairement à UIKit/Android/Cairo) |
| `UIColor`/`UIFont` | `NSColor`/`NSFont` — API quasi identiques |
| `UIBezierPath(roundedRect:cornerRadius:)` | `CGPath(roundedRect:cornerWidth:cornerHeight:transform:)` — Core Graphics pur, évite `NSBezierPath` qui n'a pas d'équivalent direct à `.cgPath` |
| `UITapGestureRecognizer` | `mouseDown(with:)` surchargé directement |
| `CADisplayLink` | `Timer` — `CADisplayLink` n'existe sur macOS que depuis macOS 14 (Sonoma), après la cible minimale de ce package (macOS 13, Ventura) |
| *(rien — iOS ne peut pas)* | `Foundation.Process` — voir plus bas |

## La vraie différence structurelle : pas de PHP embarqué à construire, comme sur Linux

`Process` (l'équivalent Foundation de `NSTask`) **n'existe pas sur iOS** (restriction du bac à sable Apple) — c'est précisément ce qui bloque le PHP embarqué côté iOS (`PhpEmbedBridge.swift`, jamais terminé). Sur macOS, `Process` existe et fonctionne normalement pour une app non distribuée via le sandboxing du Mac App Store. `MacPhpProcess.swift` en profite exactement comme `linux/phpnitro_desktop/php_process.py` : lance le `php` système en sous-processus contre le `public/` du projet, aucun binaire cross-compilé à embarquer.

## Le moteur de rendu partagé Rust : `RustMacRenderer`

Deuxième produit de ce package (`Sources/RustMacRenderer/`), à côté de `PhpNitroMacEngine` — liaisons Swift/C vers l'ABI de `rust/phpnitro-render` (`include/phpnitro_render.h`, copié verbatim dans `Sources/CPhpNitroRenderMac/`, un target C minimal exposant juste le header via un `module.modulemap` écrit à la main — nommé `CPhpNitroRenderMac`, pas `CPhpNitroRender`, pour ne pas entrer en collision avec le target du même nom dans `../ios/`, résolu dans le même graphe SPM à cause de la dépendance de chemin local vers `PhpNitroProtocol`). Pendant macOS de `linux/phpnitro_desktop/rust_render.py` (ctypes) et `windows/PhpNitroDesktop.Render/RustRenderer.cs` (P/Invoke).

Différence structurelle avec les deux autres : ici la liaison se fait **au moment du build**, pas à l'exécution — `Package.swift` calcule le chemin absolu de `rust/phpnitro-render/target/release/` depuis sa propre position (`#filePath`, même idée que `[CallerFilePath]`/`Path(__file__)` ailleurs dans ce dépôt) et le passe en `-L`/`-lphpnitro_render`/`-rpath` au linker — pas de recherche de bibliothèque à l'exécution comme côté ctypes/P-Invoke. `cargo build --release` doit tourner (voir CI ci-dessous) avant que `xcodebuild` ne lie quoi que ce soit.

**Compilation native, pas de cross-compilation** : le runner CI `macos-14` est déjà la cible (arm64 macOS), donc `cargo build --release` compile directement pour cet hôte — aucun `rustup target add` nécessaire, contrairement à ce qu'il faudra pour iOS (Simulator) ou pour Android (NDK).

Ceci n'a, comme le reste de ce port, **jamais tourné sur un vrai Mac** — écrit et relu à la main.

## `PhpNitroMacApp` — la vraie app, Rust-only

Troisième produit de ce package (`Sources/PhpNitroMacApp/`) — un exécutable SPM réel (`swift run PhpNitroMacApp <project_dir>`), pas juste des briques de bibliothèque. Délibérément une implémentation **séparée** de `MacScreenViewController`/`MacCanvasView` (Core Graphics), pas un interrupteur ajouté dessus :

- **`RustScreenView.swift`** — le pendant Rust de `MacCanvasView` : peint directement via `RustRenderer.renderFrame()` (le buffer RGBA8 prémultiplié de tiny-skia se wrappe tel quel dans un `CGImage` via `CGImageAlphaInfo.premultipliedLast` — contrairement à GDI+ sur Windows, Core Graphics n'a pas besoin d'échange de canaux R/B). Possède entièrement l'état d'interaction live (`activePanel`/`axisOffset`/`sliderValue`, même répartition des responsabilités que `NativeCanvasView.kt`/`NativeRenderPocActivity.kt`) : `mouseDown`/`mouseDragged`/`mouseUp` portent la machine à états tactile de `NativeCanvasView.kt` (seuil de slop, verrouillage d'axe) pour le drag `hScroll`/`vScroll` et le drag du curseur d'un `slider` (géométrie lue via `PhpNitroProtocol.DrawCommandPayload`, en lecture seule, jamais re-sérialisée) ; un clic simple (aucun drag décisif) hit-teste à la position de relâchement via `rustHitTest()`, en passant désormais aussi le rect de la région tapée (`onAction`) pour positionner un vrai `NSTextField`/`NSSecureTextField` sur un `focus:` (`showTextInput`), un vrai `AVPlayer`/`AVPlayerLayer` sur un `video:play:` (`showVideoOverlay`), ou un vrai `MKMapView` sur un `map:open:` (`showMapOverlay`) — un seul overlay de chaque type à la fois, effacés dès qu'une nouvelle enveloppe arrive.
- **`RustScreenController.swift`** — fait son propre fetch HTTP minimal (au lieu de réutiliser `PhpNitroProtocol.ScreenClient`, qui ne renvoie que le `DrawCommandPayload` **décodé** — or `DrawCommandPayload` n'est que `Decodable`, pas `Encodable`, donc impossible de le re-sérialiser fidèlement pour Rust) — récupère le JSON brut, et réutilise tel quel `ScreenNavigation.reduce` (logique pure, aucun décodage impliqué), qui gère maintenant aussi `toggle:` (`Checkbox`/`Toggle`/`Slider`) comme une mise à jour locale de `fieldValues` suivie d'un refetch, exactement comme le gestionnaire générique `"toggle:"` de `NativeRenderPocActivity.kt`. `RustScreenView.onResize` (tout changement réel de taille de frame) déclenche un refetch dès que la taille diffère de la dernière réellement fetchée.
- **`AppDelegate.swift`/`main.swift`** — bootstrap `NSApplication` classique (`main.swift` de haut niveau, pas `@main`), possède un `MacPhpProcess` (démarré/arrêté avec l'app) et une `NSWindow` dont le `contentView` est celui du contrôleur ci-dessus — AppKit redimensionne ce `contentView` automatiquement à chaque redimensionnement réel de la fenêtre (`.resizable` dans son `styleMask`), ce qui déclenche `setFrameSize`/`onResize` sans plomberie supplémentaire. Deux modes de lancement, comme `linux/phpnitro_desktop/__main__.py` : `PhpNitroMacApp <project_dir> [screen]` (spawn `php -S` local) ou `PhpNitroMacApp --connect HOST:PORT [screen]` (mode PhpNitro Go, aucun processus local) — `HostPort.swift` (`PhpNitroMacEngine`, port local de `ios/Sources/PhpNitroGo/HostPort.swift`) fait le parsing.

`MacScreenViewController.swift`/`MacCanvasView.swift` (chemin Core Graphics) gagnent la MÊME détection de redimensionnement (`MacCanvasView.onResize`, câblé via Auto Layout — le pin aux bords du conteneur suffit à propager un redimensionnement de fenêtre) et les MÊMES overlays `focus:`/`video:play:`/`map:open:` (`MacCanvasView.showTextInput`/`showVideoOverlay`/`showMapOverlay`, `PhpNitroProtocol.DrawCommandPayload.region(at:)` ajouté pour donner le rect du hit à `onAction`) — les seuls changements apportés à ce chemin déjà éprouvé, sans toucher au rendu Core Graphics lui-même.

## Vérification

**Aucun Mac disponible pour écrire ni vérifier ce port** — même situation que le reste d'`ios/` jusqu'ici. Le job CI `macos-build` (`.github/workflows/ci.yml`, runner `macos-14`) :

1. `cargo build --release` pour `rust/phpnitro-render`.
2. `xcodebuild -list` — 3 produits maintenant (`PhpNitroMacEngine`, `RustMacRenderer`, `PhpNitroMacApp`), donc un scheme agrégé `PhpNitroMacEngine-Package` en plus des schemes par cible — confirmé par ce `-list`, pas supposé par analogie.
3. `brew install php` (télécharge sur le réseau du runner CI, pas celui de la machine qui a écrit ce code).
4. `xcodebuild -scheme PhpNitroMacEngine ... build`, `-scheme RustMacRenderer ... build`, **`-scheme PhpNitroMacApp ... build`** (nouveau) — chaque produit individuellement, build seulement (pas d'exécution : aucun serveur d'affichage en CI pour cliquer une vraie fenêtre).
5. `xcodebuild -scheme PhpNitroMacEngine-Package -destination 'platform=macOS' test` — le scheme agrégé : `MacRenderingSupportTests`/`MacPhpProcessTests`/`RustMacRendererTests` (rendu pixel réel, hit-test réel, mêmes fixtures dorées que `rust/phpnitro-render` et les deux autres ports).

## Ce qui manque encore, dans l'ordre de priorité

1. **Rien n'a jamais tourné/cliqué sur un vrai Mac** — seule la compilation est vérifiée (CI), pas l'exécution interactive.
2. ~~Pas de saisie clavier~~ — corrigé, sur les DEUX chemins (Core Graphics et Rust) : `focus:[multiline:][secure:]name` (`TextField.php`/`PasswordField.php`) crée un vrai `NSTextField`/`NSSecureTextField` positionné par-dessus le rect+text statique déjà peint dessous (`MacCanvasView.showTextInput`/`RustScreenView.showTextInput`), chaque frappe (`NSTextFieldDelegate.controlTextDidChange`) met à jour `fieldValues` immédiatement.
3. **`clientPanel`/`hScroll`/`vScroll`/`slider` côté Core Graphics** (`MacCanvasView`) se décodent et se dessinent (rendu statique), sans interactivité de drag/tab côté client (le `focus:`/TextField ci-dessus est une exception, déjà branché) — côté Rust (`rust/phpnitro-render` et `PhpNitroMacApp`), cette limite est désormais levée (voir plus haut et le propre README du moteur), mais le chemin Core Graphics de ce port reste tel quel pour le reste, sans régression.
4. ~~Aucun overlay pour VideoPlayer~~ — corrigé, sur les DEUX chemins : `video:play:<url>` (`VideoPlayer.php`) crée un vrai `AVPlayer`/`AVPlayerLayer` positionné par-dessus la boîte "lecture" statique déjà peinte dessous (`MacCanvasView.showVideoOverlay`/`RustScreenView.showVideoOverlay`), lecture automatique dès l'affichage, pas de barre de transport. ~~Aucun overlay pour MapView~~ — corrigé, sur les DEUX chemins : `map:open:<lat>:<lon>:<zoom>` (`MapView.php`) crée un vrai `MKMapView` centré sur la position (pan/zoom déjà inclus par `MapKit`, aucune clé d'API requise) — `MacCanvasView.showMapOverlay`/`RustScreenView.showMapOverlay`. Lottie demanderait une vraie dépendance tierce (`lottie-ios`), décision pas encore prise.
4. **Pas de scan QR** — contrairement à `ios/`'s `PhpNitroGo` (UIKit, caméra), ce port n'a pas d'écran de connexion dédié ; `--connect HOST:PORT` en ligne de commande (ci-dessus) couvre le même besoin sans UI.
