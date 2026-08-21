# Linux — le premier target desktop, vérifié plus profondément qu'aucun autre port jusqu'ici

Ce dossier est un vrai paquet Python (`phpnitro_desktop/`) qui rejoue le même protocole de rendu que `NativeCanvasView.kt` (Android) et `NativeCanvasView.swift` (iOS) — la même famille d'idée que ces deux ports, transposée au natif Linux : **GTK4 + Cairo/Pango**, le vrai moteur 2D que GTK lui-même utilise pour se dessiner (`GtkDrawingArea`'s propre signal `draw` donne un contexte Cairo vivant), pas un moteur réinventé — même raisonnement que `docs/proposals/moteur-rendu-natif.md` tient pour Skia/Core Graphics.

**Contrairement à iOS, ce port a pu être vérifié presque intégralement pour de vrai**, dans l'environnement même où il a été écrit — voir la section « Ce qui a été vérifié, et comment » plus bas.

## Pourquoi GTK4 + Cairo/Pango + Python, précisément

- **Cairo/Pango** : déjà installés sur toute distribution Linux avec GTK — pas une dépendance à ajouter.
- **GTK4** : équivalent Linux natif d'`android.graphics.Canvas`/Core Graphics — un vrai canvas 2D, pas une WebView.
- **Python + PyGObject** : dans l'environnement où ce port a été écrit, les en-têtes C de GTK4 (`gtk4.pc`) n'étaient pas installés et l'installation de paquets système était impossible (pas de sudo sans mot de passe) — mais les bindings **Python** GTK4 (PyGObject/`gi`) et pycairo, eux, étaient déjà présents et fonctionnels. Un vrai choix technique contraint par l'environnement réel, pas une préférence stylistique : voir le premier commit de ce dossier pour le détail de cette investigation.

## Contrairement au mobile : pas de PHP embarqué à construire

Sur Android/iOS, faire tourner le PHP du projet **sur l'appareil** demande soit un binaire cross-compilé (`libphp.so` via NDK, Android) soit un embed SAPI jamais terminé (iOS). Sur desktop, il n'y a rien de tel à construire : `php_process.py` lance simplement le `php` **système** en sous-processus, exactement l'invocation que `bin/phpx serve` utilise déjà pour le dev local (`php -S 127.0.0.1:<port> -t public public/router.php`). Contrepartie honnête : `php` doit être installé sur la machine qui fait tourner ce shell — pas de runtime portable embarqué comme sur mobile. Empaqueter un vrai binaire PHP portable par OS pour une app desktop livrable à un utilisateur final est un vrai chantier séparé, pas fait ici.

Cette simplicité rend une tranche verticale COMPLÈTE possible dès ce premier passage — pas seulement un moteur de rendu partiel comme iOS avait dû commencer.

## Deux modes, comme `bin/phpx` en a déjà deux liés

```bash
cd linux
python3 -m phpnitro_desktop /chemin/vers/un/projet-phpnitro   # lance php -S soi-même (comme :app)
python3 -m phpnitro_desktop --connect 192.168.1.23:8090        # client pur, façon PhpNitro Go
```

Le second mode fait de ce shell, gratuitement, un équivalent Linux de PhpNitro Go (voir `android/go/`, `ios/Sources/PhpNitroGo/`) — même `ScreenClient`/`NativeScreenViewController`-équivalent, juste un `host`/`port` différent, exactement comme `NativeRenderPocActivity` ne sait pas si son `serverHost` pointe vers un process local ou une machine de dev distante.

## Structure

```
linux/
  phpnitro_desktop/
    draw_command.py   décodeur JSON pur (aucune dépendance GTK)
    canvas.py          rendu Cairo/Pango — render_payload(ctx, payload) est une
                       fonction PURE d'un cairo.Context, testable hors écran
    fonts.py            enregistre MaterialIcons/FontAwesome auprès de fontconfig
                       (ctypes + FcConfigAppFontAddFile — fontconfig n'a pas de
                       binding GObject-Introspection)
    image_loader.py     cache + fetch réseau en thread pour la commande "image"
    navigation.py       pile d'écrans (navigate:/tab:/back/clientTab:/toggle:), fonction pure
    screen_client.py    fetch HTTP /native/layout-demo, miroir de ScreenClient.swift
    php_process.py      lance/arrête `php -S` en sous-processus
    app.py               assemblage GTK4 (PhpNitroCanvasWidget, ScreenWindow)
    __main__.py           point d'entrée CLI
    fonts/                copies de MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf
  tests/                 89 tests réels — voir plus bas
```

## Ce qui a été vérifié, et comment

**Aucun serveur d'affichage (X11/Wayland/Broadway) n'était disponible** dans l'environnement où ce port a été écrit — mais contrairement à ce que ça laisse penser, ça n'a bloqué presque rien :

1. **Le décodage JSON** (`draw_command.py`) — 16 tests, zéro dépendance GTK, y compris la même fixture golden-file que `DrawCommandTests.swift` (iOS).
2. **Le rendu Cairo réel** (`canvas.py`) — rendu dans une `cairo.ImageSurface` **en mémoire**, qui ne nécessite AUCUN serveur d'affichage (contrairement à une vraie fenêtre GTK). 14 tests avec de **vraies assertions sur des pixels réellement peints** : couleur exacte au centre d'un rect, coin non peint d'un rect arrondi (le radius n'est pas juste ignoré), un glyphe d'icône qui peint vraiment quelque chose (pas une police non enregistrée qui tomberait en silence), position du pouce d'un slider selon la formule exacte, clip d'un `hScroll` qui coupe vraiment le contenu débordant. Un rendu complet de l'écran d'accueil RÉEL du framework (récupéré depuis un vrai serveur PHP) a aussi été exporté en PNG et inspecté visuellement une fois — premier rendu jamais produit de ce framework sur Linux.
3. **Le client réseau** (`screen_client.py`, `php_process.py`) — testés contre un **vrai `php -S`** lancé sur ce monorepo, pas un mock HTTP. Un vrai bug (un jeton d'accès qui cassait toutes les requêtes) a été détecté et corrigé par ce test avant d'être commité.
4. **Le widget GTK4 interactif** (`app.py`'s `PhpNitroCanvasWidget`) — découverte faite en testant : `Gtk.Overlay`/`Gtk.DrawingArea` + `Gtk.GestureDrag` + `GLib.timeout_add` se construisent et s'exercent **sans aucun serveur d'affichage** (contrairement à `Gtk.ApplicationWindow`, confirmé échouer avec "Gtk couldn't be initialized" dans ce même environnement). 33 tests réels : timer d'animation démarré/arrêté à la demande, tap réel dispatché vers la bonne action, saisie clavier via un vrai `Gtk.Entry`/`Gtk.TextView`, le drag hScroll/vScroll/slider (seuil de slop, verrouillage d'axe, formule de valeur, clamp) simulé directement via les callbacks `drag-begin`/`drag-update`/`drag-end` de `Gtk.GestureDrag`, et un vrai `Gtk.Video` construit/configuré (autoplay, URI) sans serveur d'affichage — GTK ne demande un affichage réel que pour recevoir de VRAIS événements ou décoder une VRAIE frame vidéo, pas pour construire/exercer la logique elle-même.
5. **`ScreenWindow`/`Gtk.Application.run()`** (le reste d'`app.py`) — seule partie non vérifiable dans cet environnement précis, puisqu'elle construit un vrai `Gtk.ApplicationWindow`.

**En CI (`.github/workflows/ci.yml`, job `linux-desktop`)**, `sudo apt-get` fonctionne, donc `xvfb-run` est installé. Découverte réelle, pas anticipée : lancer la suite `unittest` **sans aucun serveur d'affichage** (comme dans l'environnement d'écriture) a fait planter la CI avec un vrai *segmentation fault* pendant le test du timer d'animation — alors que la même suite tournait sans problème, plusieurs fois, dans l'environnement de développement local (lui aussi sans Xvfb, mais avec des versions de paquets GTK4/PyGObject différentes). Cause exacte non isolée davantage ; le correctif appliqué (`xvfb-run -a python3 -m unittest ...`, pas seulement pour le test de fenêtre final) donne à GTK un vrai affichage (virtuel) pour toute la suite — la configuration qu'un vrai desktop Linux a toujours, pas un contournement qui cache le problème.

```bash
python3 -m unittest discover -s linux/tests -v
```

## Rendu optionnel via le moteur Rust partagé (essai, pas le défaut)

`rust_render.py` (liaisons `ctypes` vers `rust/phpnitro-render`, voir son
propre README) remplace maintenant `canvas.py`/Cairo **et**
`DrawCommandPayload.action_at()` **par défaut** — confirmé identique
pixel par pixel sur un vrai projet `phpx new` vierge, sur la machine
réelle de l'utilisateur. `PHPNITRO_RUST_RENDER=0` repasse sur le chemin
Cairo/Python d'origine (conservé intact, jamais retiré) ; une bascule
automatique vers Cairo reste aussi en place si la bibliothèque Rust est
absente ou qu'une frame échoue à se rendre, quelle que soit la valeur de
la variable. `linux/tests/test_rust_render_parity.py`
compare les deux côtés : rendu pixel par pixel sur de vraies fixtures
(tolérance de 2, pour l'anti-aliasing) et hit-testing (les 13 vraies
zones cliquables de l'écran d'accueil réel donnent exactement la même
action des deux côtés, vérifié à la main puis figé en test automatisé).
Une vérification manuelle bout-en-bout contre un vrai `php -S` de ce
monorepo a aussi produit un rendu Rust correct de l'écran d'accueil réel
(icônes, cartes, navigation, FAB) — pas seulement des fixtures isolées.

Ce qui n'est PAS encore fait : basculer le défaut vers Rust, retirer le
chemin Cairo/Python — décisions distinctes, volontairement non prises
ici. Le décalage de scroll côté widget (`axis_offset`, voir point 2 plus
bas) est désormais transmis au rendu ET au hit-testing Rust via
`interaction_state_json` — ce n'était pas le cas jusqu'ici puisque
`PhpNitroCanvasWidget` ne suivait alors aucun état de scroll lui-même,
rien ne divergeait encore à câbler.

## Ce qui manque encore, dans l'ordre de priorité

1. ~~Aucun test manuel/interactif réel au-delà d'une vraie capture d'écran~~ — corrigé : `phpx run` (voir `docs/cli.md`) a réellement lancé `python3 -m phpnitro_desktop` contre un vrai affichage — **la toute première fois qu'une vraie fenêtre de ce port s'est ouverte**, confirmé directement par la personne devant cet écran, pas juste un rendu Cairo exporté en PNG comme avant. Tout ce que les 89 tests promettaient (widget interactif, rendu Rust, Lottie...) s'est donc bien traduit en une vraie fenêtre visible, pas seulement en assertions. Toujours pas une session d'usage complète (clic réel, saisie réelle au clavier, etc.) — juste le lancement.
2. ~~`hScroll`/`vScroll`/`slider` sans interactivité côté client~~ — corrigé : `PhpNitroCanvasWidget` remplace son `Gtk.GestureClick` par un `Gtk.GestureDrag` (seuil de slop + verrouillage d'axe, formule identique à `ScreenForm.cs`(Windows)/`RustScreenView.swift`(macOS)) — `axis_offset`/`slider_value` vivent sur le widget, comme `client_tab_state` déjà, et sont désormais transmis au moteur Rust via un `interaction_state_json` unifié, pour le rendu ET le hit-test. Corrige au passage un vrai bug pré-existant : le changement d'onglet `clientTab:` ne se répercutait jamais visuellement côté rendu Rust avant ce correctif, cet état n'étant tout simplement jamais transmis à `render_frame` (seul le hit-test le recevait). `sliderRegions[]` (l'enveloppe top-level, distincte du `SliderCommand` du flux `commands` — un slider n'a pas de `HitRegion` propre, sa valeur dépendant d'où le drag tombe dans la piste) est désormais décodée dans `draw_command.py`. `toggle:`/`FieldUpdate` — jusque-là absent de ce port contrairement à Windows/macOS — porté dans `navigation.py` pour que le relâchement d'un slider commette sa valeur exactement comme un `Checkbox`/`Toggle` tapé le fait déjà. Corrigé aussi au passage : tout fetch qui n'était PAS déclenché par un redimensionnement (`navigate:`/`tab:`/`back`/`toggle:`) retombait sur la taille de construction fixe de GTK4 (390×844) au lieu de la dernière taille réellement connue — le même bug que celui déjà corrigé pour le tout premier redimensionnement, juste pas encore pour les fetches suivants.
3. ~~Aucun overlay pour TextField~~ — corrigé : `focus:[multiline:][secure:]name` crée un vrai `Gtk.Entry`/`Gtk.TextView` positionné par-dessus le rect+text statique déjà peint dessous (`PhpNitroCanvasWidget.show_text_input`, via `Gtk.Overlay` — le widget est désormais un `Gtk.Overlay` enveloppant son propre `Gtk.DrawingArea` interne plutôt que d'en être un directement), chaque frappe met à jour `field_values` immédiatement. ~~Aucun overlay pour VideoPlayer~~ — corrigé : `video:play:<url>` (`VideoPlayer.php`) crée un vrai `Gtk.Video` (natif à GTK4 depuis la 4.6, confirmé disponible ici en 4.14 — aucune dépendance nouvelle) positionné par-dessus la boîte "lecture" statique déjà peinte dessous (`PhpNitroCanvasWidget.show_video_overlay`), lecture automatique dès l'affichage via `Gio.File.new_for_uri()` (gère aussi bien une URL distante qu'un fichier local, contrairement à la distinction manuelle qu'Android doit faire). ~~Aucun overlay pour MapView~~ — corrigé : `map:open:<lat>:<lon>:<zoom>` crée un vrai `Shumate.SimpleMap` (`libshumate`/`gir1.2-shumate-1.0`, une vraie dépendance système AJOUTÉE — contrairement à `Gtk.Video`, ce n'est pas du GTK4 core) via `PhpNitroCanvasWidget.show_map_overlay`. Écrit dans un environnement où `libshumate` n'était pas installé (confirmé via `apt-cache policy`), donc relu à la main plutôt qu'exécuté au moment de l'écrire — mais **confirmé pour de vrai depuis** : `gir1.2-shumate-1.0` ajouté à `ci.yml`, `test_show_map_overlay_constructs_a_real_widget_when_shumate_is_available` s'exécute réellement là-bas (skip en local, où la lib reste absente) et passe — l'usage réel de `Shumate.SimpleMap`/`MapSourceRegistry`/`Map`/`center_on`/`get_viewport().set_zoom_level()` était le bon du premier coup. `Shumate is None` (bibliothèque absente) et toute exception à la construction dégradent quand même vers "aucun overlay affiché" plutôt qu'un crash, en filet de sécurité. ~~Lottie reste hors scope~~ — corrigé : contrairement à `libshumate`, `librlottie` (la seule option C sérieuse sur Linux) n'a aucun binding GObject-Introspection, donc `lottie_render.py` écrit de vraies liaisons `ctypes` à la main (façon `rust_render.py`, contre `rlottie_capi.h` de mémoire — jamais installé dans l'environnement où c'est écrit, confirmé via `ctypes.util.find_library`/`ldconfig -p`). Contrairement à MapView/VideoPlayer/TextField (des overlays ponctuels, déclenchés par une action et démontés à chaque nouveau payload), `lottieRegions[]` est un tableau top-level présent à CHAQUE rendu — `PhpNitroCanvasWidget._reconcile_lottie_overlays()` diffuse par `key` plutôt que de tout démonter, pour que l'animation continue de boucler à travers les taps/scrolls (voir `Lottie.php`'s docblock) ; chaque overlay est son propre `Gtk.DrawingArea` repositionné/redessiné par le même timer `GLib.timeout_add` partagé que les spinners/skeletons (`needs_animation()` étendu), le numéro de frame calculé à partir du temps écoulé réel, pas d'un compteur de tick. Chargement (réseau ou `assets/lottie/`) fait par `lottie_loader.py`, même forme que `image_loader.py` (thread + cache + `GLib.idle_add`). **Vérifié en deux temps** : toute la logique de réconciliation de widget (création/suppression/persistance par clé, GTK4 étant réellement disponible ici en 4.14) tourne réellement en local — 4 tests réels dans `test_app_widget.py` ; les liaisons `ctypes` elles-mêmes (`test_lottie_render.py`, fixture Lottie JSON réelle intégrée — un rect rouge, pixel vérifié) restent à confirmer via CI (`librlottie0-1` ajouté à `ci.yml`), comme `libshumate` avant elles.
4. **Pas de `lastHash`/dark mode/i18n/polling** — même périmètre volontairement restreint que `ScreenClient.swift` sur iOS.
5. **Packaging** — aucun `.deb`/Flatpak/AppImage, `python3 -m phpnitro_desktop` est le seul point d'entrée pour l'instant.
6. ~~macOS et Windows — pas commencées~~ — les deux ont depuis leur propre app réelle consommant le moteur Rust partagé (voir `windows/README.md`/`macos/README.md`), avec `--connect`, redimensionnement live, et (depuis le point 2 ci-dessus) la même interactivité live onglets/scroll/slider/texte — les trois ports Rust (Linux/Windows/macOS) sont désormais à parité sur ce socle précis, alors que ce port Linux a été le premier des trois à avoir `--connect`/le redimensionnement live.
