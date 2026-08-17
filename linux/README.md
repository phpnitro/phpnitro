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
    navigation.py       pile d'écrans (navigate:/tab:/back/clientTab:), fonction pure
    screen_client.py    fetch HTTP /native/layout-demo, miroir de ScreenClient.swift
    php_process.py      lance/arrête `php -S` en sous-processus
    app.py               assemblage GTK4 (PhpNitroCanvasWidget, ScreenWindow)
    __main__.py           point d'entrée CLI
    fonts/                copies de MaterialIcons-Regular.ttf/FontAwesome-Solid.ttf
  tests/                 48 tests réels — voir plus bas
```

## Ce qui a été vérifié, et comment

**Aucun serveur d'affichage (X11/Wayland/Broadway) n'était disponible** dans l'environnement où ce port a été écrit — mais contrairement à ce que ça laisse penser, ça n'a bloqué presque rien :

1. **Le décodage JSON** (`draw_command.py`) — 13 tests, zéro dépendance GTK, y compris la même fixture golden-file que `DrawCommandTests.swift` (iOS).
2. **Le rendu Cairo réel** (`canvas.py`) — rendu dans une `cairo.ImageSurface` **en mémoire**, qui ne nécessite AUCUN serveur d'affichage (contrairement à une vraie fenêtre GTK). 14 tests avec de **vraies assertions sur des pixels réellement peints** : couleur exacte au centre d'un rect, coin non peint d'un rect arrondi (le radius n'est pas juste ignoré), un glyphe d'icône qui peint vraiment quelque chose (pas une police non enregistrée qui tomberait en silence), position du pouce d'un slider selon la formule exacte, clip d'un `hScroll` qui coupe vraiment le contenu débordant. Un rendu complet de l'écran d'accueil RÉEL du framework (récupéré depuis un vrai serveur PHP) a aussi été exporté en PNG et inspecté visuellement une fois — premier rendu jamais produit de ce framework sur Linux.
3. **Le client réseau** (`screen_client.py`, `php_process.py`) — testés contre un **vrai `php -S`** lancé sur ce monorepo, pas un mock HTTP. Un vrai bug (un jeton d'accès qui cassait toutes les requêtes) a été détecté et corrigé par ce test avant d'être commité.
4. **Le widget GTK4 interactif** (`app.py`'s `PhpNitroCanvasWidget`) — découverte faite en testant : `Gtk.DrawingArea` + `Gtk.GestureClick` + `GLib.timeout_add` se construisent et s'exercent **sans aucun serveur d'affichage** (contrairement à `Gtk.ApplicationWindow`, confirmé échouer avec "Gtk couldn't be initialized" dans ce même environnement). 6 tests réels : timer d'animation démarré/arrêté à la demande, clic réel dispatché vers la bonne action.
5. **`ScreenWindow`/`Gtk.Application.run()`** (le reste d'`app.py`) — seule partie non vérifiable dans cet environnement précis, puisqu'elle construit un vrai `Gtk.ApplicationWindow`.

**En CI (`.github/workflows/ci.yml`, job `linux-desktop`)**, `sudo apt-get` fonctionne, donc `xvfb-run` est installé et utilisé pour un vrai test avec fenêtre : l'app est lancée pour de vrai contre ce monorepo (mode local, PHP réel), on vérifie qu'elle reste en vie 5 secondes au lieu de planter immédiatement — la seule chose qui restait à couvrir.

```bash
python3 -m unittest discover -s linux/tests -v
```

## Ce qui manque encore, dans l'ordre de priorité

1. **Aucun test manuel/interactif réel** — rien n'a été cliqué par un humain sur un vrai écran. La CI confirme que l'app démarre et tient 5 secondes, pas que l'expérience est bonne.
2. **`clientPanel`/`hScroll`/`vScroll`/`slider`** se décodent et se dessinent (rendu statique, comme sur iOS), mais sans interactivité côté client — pas de bascule d'onglet au tap, pas de drag de scroll/slider.
3. **Aucun overlay pour les Views natives** (TextField, VideoPlayer, MapView...) — `set_field_value()` existe côté `ScreenWindow` mais rien ne peut encore l'appeler.
4. **Pas de `lastHash`/dark mode/i18n/polling** — même périmètre volontairement restreint que `ScreenClient.swift` sur iOS.
5. **Packaging** — aucun `.deb`/Flatpak/AppImage, `python3 -m phpnitro_desktop` est le seul point d'entrée pour l'instant.
6. **macOS et Windows** — les deux autres cibles desktop, pas commencées dans cette même passe. macOS pourra réutiliser une bonne partie du raisonnement Core Graphics déjà fait pour iOS (`PhpNitroNativeEngine`) ; Windows n'a pas d'équivalent OS-natif aussi direct et demandera probablement d'embarquer un vrai moteur 2D plutôt que de s'appuyer sur l'OS.
