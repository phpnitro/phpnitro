# phpnitro-render — le moteur de rendu partagé

Ce crate remplace les 4 moteurs de rendu natifs séparés
(`android/engine/.../NativeCanvasView.kt`, `ios/`+`macos/`'s Core
Graphics, `linux/phpnitro_desktop/canvas.py` Cairo, et Windows, qui n'a
jamais eu de moteur natif propre) par **un seul** interprète du protocole
JSON de commandes de dessin produit par `Engine\Native\Canvas::toJson()`
(`packages/ui/src/Native/Canvas.php`) — écrit une fois en Rust
(`tiny-skia` pour le dessin, `cosmic-text` pour le texte/les polices),
exposé via une interface C (`include/phpnitro_render.h`).

**Statut par plateforme** :
- **Linux** — `PHPNITRO_RUST_RENDER` est le chemin par défaut
  (`PHPNITRO_RUST_RENDER=0` repasse sur Cairo), confirmé pixel-par-pixel
  sur un vrai projet `phpx new` vierge, sur la machine réelle de
  l'utilisateur.
- **Windows** — Rust est le **seul** chemin de rendu (`windows/PhpNitroDesktop.App`,
  une vraie app WinForms) : ce port n'a jamais eu de moteur GDI+/Direct2D
  natif à remplacer.
- **macOS** — un target exécutable SPM séparé (`macos/Sources/PhpNitroMacApp`)
  consomme Rust, délibérément à côté du chemin Core Graphics existant
  (`MacCanvasView`/`MacScreenViewController`), qui reste le chemin de
  production tel quel.
- **iOS/Android** — liaisons complètes (Swift/C-interop, JNI), vérifiées
  uniquement par des tests hors-écran/compilation croisée en CI — pas
  encore branchées dans le chemin de production natif de chaque
  plateforme (aucun test sur un vrai appareil possible ici).

## Pourquoi ce chantier existe

Seul Android (`NativeCanvasView.kt`, ~2850 lignes) était une implémentation
complète et vérifiée sur device réel du protocole. iOS/macOS/Linux
n'implémentaient qu'un sous-ensemble, et Windows n'avait qu'une couche de
décodage sans aucun rendu. Décision de l'utilisateur : un seul moteur
partagé, quitte à ce que ce soit un chantier plus lourd que 4 moteurs
plus simples.

## Ce qui est réellement implémenté et testé

| Module | Couvre | Vérifié par |
|---|---|---|
| `protocol.rs` | Décodage complet de l'enveloppe `toJson()` — tous les types de commande, `hitRegions`/`heroRegions`/`dismissRegions`/`reorderRegions`/`lottieRegions`/`sheetRegions`, `fixed`/`hero`/`dismiss`/`reorder` sur toute commande | Décodage des fixtures dorées de `packages/ui/tests/Golden/__fixtures__/` (dans `tests/golden_render.rs`), y compris `screen_widgets_forms.json` (170 Ko) sans un seul type inconnu, récursivement |
| `raster.rs` | `rect` (coins arrondis, bordure, gradient linéaire, ombre portée par box blur maison), `circle`, `line`, `arc`, `spinner`, `skeleton`, `slider` (piste pilule + remplissage actif + pouce), `clientPanel`/`hScroll`/`vScroll` (peints via un calque tampon composité + masque de clip, récursif — texte/icônes imbriqués inclus), `image` (voir `image.rs`) — **et** l'état d'interaction live (`InteractionState`, même forme que `hittest.rs`) qui pilote la sélection de panneau, l'offset de scroll et la valeur du slider de ces 4 derniers | Tests pixel réels (couleur exacte, clip de viewport, composition d'offsets imbriqués, override par l'état live) + rendu visuel sur de vraies fixtures de production |
| `image.rs` | `image` dont l'`url` est un `data:image/png;base64,...` inline — décodeur base64 maison (aucun crate `base64` en cache), rasterisé via `Pixmap::decode_png` (déjà lié par `tiny-skia`) | 7 tests unitaires (dont le payload réel de `image_network_and_data_uri.json`) + rendu pixel bout-en-bout sur cette même fixture |
| `transition.rs` | Crossfade (220ms, `DecelerateInterpolator`) + hero/FLIP (280ms linéaire, 5 courbes : LINEAR/EASE_IN/EASE_IN_OUT/BOUNCE/ELASTIC + défaut EASE_OUT) entre deux enveloppes — port exact des constantes/formules de `NativeCanvasView.kt` (matrice translate→scale→translate, interpolation numérique + blend ARGB par commande, exclusion des tags hero en vol des deux passes ordinaires) | 11 tests unitaires (courbes, blend couleur, transform matriciel, offsets de transition par type) + 1 test FFI de bout en bout |
| `text.rs` | `text`/`icon` via `cosmic-text`, 3 polices embarquées (`MaterialIcons-Regular.ttf`/`FontAwesome-Solid.ttf`/`Roboto-Regular.ttf`, copies vérifiées identiques par md5sum aux fichiers Android) | Tests "un vrai glyphe peint quelque chose" + rendu visuel |
| `animate.rs` | Formules horloge murale spinner (période 1100ms/balayage 110°) et skeleton (période 1300ms/largeur 0.6×), constantes copiées depuis `NativeCanvasView.kt` | 7 tests, y compris "la rotation ne boucle jamais exactement à 360°" |
| `hittest.rs` | Parcours `hitRegions` + `clientPanel`/`hScroll`/`vScroll` imbriqués, ordre premier-match-gagne confirmé en lisant le Kotlin directement | 12 tests, cas adverses inclus |
| `charts.rs` | `custom:sparkline`/`barChart`/`pieChart` | 4 tests pixel + rendu visuel |
| `lib.rs` (FFI) | `extern "C"`, ownership Rust-alloue/Rust-libère, `catch_unwind` sur tout point d'entrée, transition crossfade/hero optionnelle (`previous_envelope_json`/`transition_elapsed_ms`, `NULL`/`0` = comportement inchangé) | Tests Rust + `tests/ffi_smoke.c`, un vrai programme C compilé et lié contre la bibliothèque réellement produite par ce build |
| `jni_bridge.rs` | Pont JNI Android (`#[cfg(target_os = "android")]`) | Compilation croisée réelle en CI (`rust-render-android-jni`) — jamais exécuté par une JVM |

Vérifié d'abord en local (`cargo test --offline`, dépendances déjà en
cache), puis en CI via le job `rust-render-core` sur chaque push.

## Ce qui est délibérément hors de portée pour l'instant

- **`image` réseau (`https://`) et non-PNG** (`data:image/jpeg`, WebP...) —
  seul `data:image/png;base64,...` inline est rasterisé (voir `image.rs`).
  Un vrai fetch réseau et un décodeur JPEG/WebP demanderaient chacun un
  crate que ce chantier n'a pas en cache hors-ligne ; une image `https://`
  ou non-PNG ne peint donc toujours rien, silencieusement.
- **`LottieRegion`** n'a aucun équivalent dessinable dans le protocole
  (c'est une vraie vue Lottie superposée) — restera hors du rasterizer
  quel que soit l'avancement de ce crate.
- **`AutoNavigate`/`Snackbar`** n'ont aucune implication de peinture sur
  Android non plus (confirmé en lisant `NativeCanvasView.kt` — zéro
  référence) : ce sont des effets de bord purs de la coquille applicative
  (navigation programmée, overlay `TextView` animé), pas une
  responsabilité de ce crate.

## Structure

```
rust/phpnitro-render/
  Cargo.toml
  src/
    lib.rs        surface FFI (extern "C"), ownership + catch_unwind
    protocol.rs    décodage serde de l'enveloppe toJson()
    raster.rs      rendu tiny-skia (rect/circle/line/arc/spinner/skeleton/
                   slider/clientPanel/hScroll/vScroll, récursif)
    transition.rs  crossfade + hero/FLIP entre deux enveloppes
    text.rs        rendu cosmic-text (text/icon) + les 3 polices embarquées
    animate.rs      formules horloge murale spinner/skeleton, pur calcul
    hittest.rs      parcours hitRegions imbriqué, état d'interaction en paramètre
    charts.rs       custom:sparkline/barChart/pieChart
    jni_bridge.rs   pont JNI Android, #[cfg(target_os = "android")] uniquement
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
cargo test --offline                          # local, dépendances déjà en cache
cargo clippy --offline --all-targets           # zéro avertissement
cargo run --example render_fixture -- <fixture.json> [width] [height] [out.png]
```
