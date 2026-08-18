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

## Vérification

**Aucun Mac disponible pour écrire ni vérifier ce port** — même situation que le reste d'`ios/` jusqu'ici. Le job CI `macos-build` (`.github/workflows/ci.yml`, runner `macos-14`) :

1. `xcodebuild -list` en premier — confirme qu'il n'y a qu'**un seul scheme, `PhpNitroMacEngine`**, sans suffixe `-Package` (contrairement à `ios/`, qui a 4 produits et donc un scheme agrégé séparé — voir `ios/README.md`) : avec un seul produit dans tout le package, Xcode ne génère pas de scheme agrégé distinct, il nomme simplement l'unique scheme d'après ce produit. Deux échecs CI réels ont précédé cette version (mauvais nom de `package:` dans la dépendance de chemin local, puis ce même mauvais nom de scheme par analogie avec `ios/` avant que `-list` ne montre la vraie liste) — corrigés, pas juste supposés.
2. `brew install php` (télécharge sur le réseau du runner CI, pas celui de la machine qui a écrit ce code).
3. `xcodebuild -scheme PhpNitroMacEngine -destination 'platform=macOS' build`
4. `xcodebuild -scheme PhpNitroMacEngine -destination 'platform=macOS' test` — même scheme, action `test` : inclut `MacRenderingSupportTests` (police d'icônes, parsing couleur hex, `data:` URI) et `MacPhpProcessTests` (un vrai `php -S` lancé contre ce monorepo, avec un vrai fetch HTTP — la même rigueur que `linux/tests/test_php_process.py`, `XCTSkip` en repli propre si `php` n'est pas disponible plutôt qu'un échec trompeur).

## Ce qui manque encore, dans l'ordre de priorité

1. **Rien n'a jamais tourné sur un vrai Mac** — ni compilé, ni cliqué, ni vu à l'écran.
2. **Aucune app macOS réelle** — `MacScreenViewController`/`MacCanvasView`/`MacPhpProcess` sont des briques de bibliothèque ; un vrai `NSApplication`/`AppDelegate`/menu bar consommateur (l'équivalent macOS d'`ios/App/`) n'existe pas encore.
3. **`clientPanel`/`hScroll`/`vScroll`/`slider`** se décodent et se dessinent (rendu statique, comme sur iOS et Linux), sans interactivité côté client.
4. **Pas de scan QR / mode PhpNitro Go** — contrairement à Linux (`--connect HOST:PORT` dans `phpnitro_desktop`), ce port n'a pas encore son propre écran de connexion.
5. **Windows** — la dernière plateforme desktop, pas commencée. Contrairement à macOS, pas d'équivalent direct à Core Graphics/UIKit à réutiliser — demandera probablement d'embarquer un vrai moteur 2D (Direct2D natif, ou Skia) plutôt que de s'appuyer sur un héritage Core Graphics comme ce port l'a fait.
