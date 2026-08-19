# phpnitro-render — le futur moteur de rendu partagé

Ce crate est destiné à remplacer les 4 moteurs de rendu natifs séparés
(`android/engine/.../NativeCanvasView.kt`, `ios/`+`macos/`'s Core
Graphics, `linux/phpnitro_desktop/canvas.py` Cairo, et le futur moteur
Windows) par **un seul** interprète du protocole JSON de commandes de
dessin produit par `Engine\Native\Canvas::toJson()` (`packages/ui/src/Native/Canvas.php`) —
écrit une fois en Rust (`tiny-skia` pour le dessin, `cosmic-text` pour
le texte/les polices), exposé via une interface C (`include/phpnitro_render.h`).

**Statut par plateforme** : Linux est la première intégration réelle —
`linux/phpnitro_desktop/rust_render.py` (liaisons ctypes) peut rendre
un écran en direct derrière `PHPNITRO_RUST_RENDER=1`, testé pixel par
pixel contre le chemin Cairo existant (`linux/tests/test_rust_render_parity.py`)
et vérifié à la main de bout en bout contre un vrai `php -S` de ce
monorepo (voir `linux/README.md`). **Le chemin Cairo reste le défaut** —
Rust est un essai opt-in, pas encore la voie principale. Android, iOS,
macOS, Windows ne consomment pas encore ce crate. Ce README décrit ce
qui est réellement vérifié en CI aujourd'hui, pas une promesse
d'intégration à venir pour les plateformes qui ne l'ont pas encore.

## Pourquoi ce chantier existe

Seul Android (`NativeCanvasView.kt`, ~2850 lignes) est une implémentation
complète et vérifiée sur device réel du protocole. iOS/macOS/Linux
n'implémentent qu'un sous-ensemble (pas de hit-testing imbriqué, pas de
`custom:*`, peu ou pas de scroll/drag), et Windows n'a aujourd'hui qu'une
couche de décodage sans aucun rendu. Cette dérive s'aggrave à chaque
nouvelle fonctionnalité du protocole tant que 4 implémentations
indépendantes doivent chacune la rattraper. Décision de l'utilisateur :
un seul moteur partagé, quitte à ce que ce soit un chantier plus lourd
que 4 moteurs plus simples — voir le plan de migration original pour le
détail des compromis (Rust+tiny-skia vs Skia/C++) et le phasage complet.

## Ce qui est réellement implémenté et testé

| Module | Couvre | Vérifié par |
|---|---|---|
| `protocol.rs` | Décodage complet de l'enveloppe `toJson()` — tous les types de commande, `hitRegions`/`heroRegions`/`dismissRegions`/`reorderRegions`/`lottieRegions`/`sheetRegions`, `fixed`/`hero`/`dismiss`/`reorder` sur toute commande y compris `clientPanel`/`hScroll`/`vScroll` | 9 tests unitaires + décodage des 12 fixtures dorées de `packages/ui/tests/Golden/__fixtures__/` (dans `tests/golden_render.rs`), y compris `screen_widgets_forms.json` (170 Ko) sans un seul type inconnu, récursivement |
| `raster.rs` | `rect` (coins arrondis via approximation cubique-Bézier, bordure), `circle`, `line`, `arc` (convention Android `drawArc`), `spinner`, `skeleton` (dégradé de reflet découpé au masque) | Tests pixel réels (couleur exacte, coin non peint hors du radius) + rendu visuel inspecté à l'œil |
| `text.rs` | `text`/`icon` via `cosmic-text`, 3 polices embarquées (`MaterialIcons-Regular.ttf`/`FontAwesome-Solid.ttf`/`Roboto-Regular.ttf`, copies vérifiées identiques par md5sum aux fichiers Android) — `FontSystem::new_with_fonts()` ne scanne aucune police système | Tests "un vrai glyphe peint quelque chose" (même idiome que `linux/README.md`) + rendu visuel |
| `animate.rs` | Formules horloge murale spinner (période 1100ms/balayage 110°) et skeleton (période 1300ms/largeur 0.6×), constantes copiées depuis `NativeCanvasView.kt` | 7 tests, y compris "la rotation ne boucle jamais exactement à 360°" |
| `hittest.rs` | Parcours `hitRegions` + `clientPanel`/`hScroll`/`vScroll` imbriqués, ordre premier-match-gagne confirmé en lisant le Kotlin directement (pas les docblocks, qui se contredisent) | 12 tests, cas adverses inclus (régions superposées, tap hors zone, `fixed` insensible au scroll, décalage de glissement) |
| `charts.rs` | `custom:sparkline`/`barChart`/`pieChart` — première fois que ce sont des primitives du moteur, pas du code applicatif Android | 4 tests pixel + rendu visuel |
| `lib.rs` (FFI) | 15 fonctions `extern "C"`, ownership Rust-alloue/Rust-libère, `catch_unwind` sur tout point d'entrée | 7 tests Rust + `tests/ffi_smoke.c`, un vrai programme C compilé et lié contre la bibliothèque réellement produite par ce build |

**73 tests au total**, vérifiés d'abord en local (`cargo test`, dépendances
téléchargées une fois avec l'accord explicite de l'utilisateur), puis en
CI via le job `rust-render-core` (`.github/workflows/ci.yml`) sur chaque
push.

## Ce qui est délibérément hors de portée pour l'instant

- **Android/iOS/macOS/Windows ne consomment pas encore ce crate.** Seul
  Linux a un câblage réel (`ctypes`, voir plus haut). Android est prévu
  ensuite (JNI, précédent déjà établi dans ce dépôt via `libphp.so`),
  puis iOS/macOS (C-interop Swift), puis Windows (P/Invoke — premier
  vrai moteur de rendu Windows, pas un remplacement).
- **Même sur Linux, ce n'est pas le chemin par défaut** — Cairo reste
  actif sans configuration ; Rust ne s'active qu'avec
  `PHPNITRO_RUST_RENDER=1`, et retombe sur Cairo automatiquement en cas
  d'échec. Basculer le défaut (ou supprimer le chemin Cairo) est une
  décision distincte, délibérément non prise ici, qui attend une
  validation interactive de l'utilisateur.
- **`image`** est décodé (`ImageCommand`) mais jamais rasterisé — charger
  une image (réseau ou `data:` URI) est une responsabilité que ce crate
  n'a pas encore, contrairement au texte/aux formes.
- **`clientPanel`/`hScroll`/`vScroll` ne sont pas peints** (seulement
  décodés et hit-testés) : leurs `commands[]` imbriquées sont confirmées
  relatives à l'origine du panneau (voir `hscroll_basic.json` et le
  commit qui l'a ajouté), mais peindre dedans demande un concept de
  canvas translaté que `raster.rs` n'a pas encore.
- **`slider`** n'est ni peint ni hit-testé (`sliderRegions[]` reste une
  liste de `Value` brute — sa forme exacte n'a jamais été confirmée
  contre une fixture réelle).
- **Transitions crossfade/hero** — nécessiteraient une frame précédente +
  une fraction de progression dans la surface FFI ; seul Android a
  quelque chose d'équivalent aujourd'hui, donc reporter ceci est un recul
  nul pour les 3 autres plateformes.
- **`LottieRegion`** n'a aucun équivalent dessinable dans le protocole
  (c'est une vraie vue Lottie superposée) — restera hors du rasterizer
  quel que soit l'avancement de ce crate.
- **`elevation`/`gradientFrom`/`gradientTo`** sur `rect` sont décodés mais
  pas peints (pas d'ombre portée, pas de dégradé) — aucune fixture dorée
  actuelle n'exerce ces champs.

## Structure

```
rust/phpnitro-render/
  Cargo.toml
  src/
    lib.rs        surface FFI (extern "C"), ownership + catch_unwind
    protocol.rs    décodage serde de l'enveloppe toJson()
    raster.rs      rendu tiny-skia (rect/circle/line/arc/spinner/skeleton)
    text.rs        rendu cosmic-text (text/icon) + les 3 polices embarquées
    animate.rs      formules horloge murale spinner/skeleton, pur calcul
    hittest.rs      parcours hitRegions imbriqué, état d'interaction en paramètre
    charts.rs       custom:sparkline/barChart/pieChart
  include/
    phpnitro_render.h   écrit à la main, tenu à jour avec lib.rs
  fonts/            copies des mêmes 3 fichiers déjà dupliqués sur android/ios/macos/linux
  examples/
    render_fixture.rs   export PNG manuel pour inspection visuelle (pas lancé par cargo test)
  tests/
    golden_render.rs    référence directement packages/ui/tests/Golden/__fixtures__/
    ffi_smoke.c/.rs     compile+lie+exécute un vrai programme C
```

## Vérification

```bash
cd rust/phpnitro-render
cargo test                                    # 73 tests
cargo clippy --all-targets                     # zéro avertissement
cargo run --example render_fixture -- <fixture.json> [width] [height] [out.png]
```
