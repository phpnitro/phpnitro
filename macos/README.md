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

## Vérification

**Aucun Mac disponible pour écrire ni vérifier ce port** — même situation que le reste d'`ios/` jusqu'ici. Le job CI `macos-build` (`.github/workflows/ci.yml`, runner `macos-14`) :

1. `cargo build --release` pour `rust/phpnitro-render` (nouveau, pour `RustMacRenderer`).
2. `xcodebuild -list` — avec l'ajout du produit `RustMacRenderer`, ce package en a maintenant **deux**, donc (par analogie confirmée avec `ios/`, qui a 4 produits) Xcode est attendu générer un scheme agrégé `PhpNitroMacEngine-Package` en plus des schemes par cible — à confirmer par ce `-list`, pas juste supposé par analogie, exactement la discipline qui avait déjà détecté un mauvais nom de scheme la première fois que ce package n'avait qu'un seul produit.
3. `brew install php` (télécharge sur le réseau du runner CI, pas celui de la machine qui a écrit ce code).
4. `xcodebuild -scheme PhpNitroMacEngine -destination 'platform=macOS' build` et `-scheme RustMacRenderer ... build` — chaque produit individuellement (schemes par cible, build seulement).
5. `xcodebuild -scheme PhpNitroMacEngine-Package -destination 'platform=macOS' test` — le scheme agrégé, seul câblé pour l'action Test avec plusieurs produits : `MacRenderingSupportTests`/`MacPhpProcessTests` (existants) et `RustMacRendererTests` (nouveau, contre la vraie bibliothèque Rust compilée à l'étape 1 — rendu pixel réel, hit-test réel, sur les mêmes fixtures dorées que `rust/phpnitro-render` et les deux autres ports).

## Ce qui manque encore, dans l'ordre de priorité

1. **Rien n'a jamais tourné sur un vrai Mac** — ni compilé, ni cliqué, ni vu à l'écran.
2. **Aucune app macOS réelle** — `MacScreenViewController`/`MacCanvasView`/`MacPhpProcess` sont des briques de bibliothèque ; un vrai `NSApplication`/`AppDelegate`/menu bar consommateur (l'équivalent macOS d'`ios/App/`) n'existe pas encore, et rien ne peint encore à l'écran via `RustMacRenderer` (seulement testé hors-écran pour l'instant).
3. **`clientPanel`/`hScroll`/`vScroll`/`slider`** se décodent et se dessinent (rendu statique, comme sur iOS et Linux), sans interactivité côté client.
4. **Pas de scan QR / mode PhpNitro Go** — contrairement à Linux (`--connect HOST:PORT` dans `phpnitro_desktop`), ce port n'a pas encore son propre écran de connexion.
