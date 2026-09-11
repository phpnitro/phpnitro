<div align="center">

# PhpNitro

**Écris des applications mobiles natives en PHP.**

Un vrai runtime PHP embarqué sur le device (pas un serveur distant, pas de transpilation) calcule un arbre de widgets et des commandes de dessin ; un vrai canvas 2D natif les rejoue — `android.graphics.Canvas` sur Android, Core Graphics sur iOS, un moteur Rust partagé (tiny-skia) sur macOS/Windows, GTK4/Cairo sur Linux — même famille d'architecture que Flutter (layout à contraintes, moteur de peinture), sans WebView ni HTML/CSS nulle part dans le pipeline de rendu.

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](composer.json)
[![Platforms](https://img.shields.io/badge/platforms-Android%20%7C%20iOS%20%7C%20Linux%20%7C%20macOS%20%7C%20Windows-informational.svg)](docs/mobile-builds.md)

[Démarrage rapide](#démarrage-rapide) ·
[Documentation](#documentation) ·
[Widgets](docs/widgets.md)

</div>

---

## Pourquoi PhpNitro

Flutter compile en code natif. React Native transpile en JS. **PhpNitro exécute du vrai PHP, en continu, sur le téléphone** — chaque interaction (tap, geste) est une vraie requête traitée par un vrai runtime PHP embarqué (binaire cross-compilé, déjà fourni dans le dépôt : NDK sur Android, embed SAPI sur iOS), qui recalcule l'écran et renvoie des commandes de dessin JSON rejouées sur un vrai Canvas. Pas de simulation, pas d'aller-retour vers un serveur distant.

Résultat : si tu sais écrire du PHP, tu sais écrire une app mobile. Pas de Dart, pas de JSX, pas de nouveau langage — un arbre de `Widget` (`Container`, `Flex`, `Button`...), exactement l'idée d'un `RenderObject` Flutter.

```php
final class HomeScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $count = (int) Preferences::get('count', '0');

        return new Scaffold(
            new Container(
                new Center(Flex::column([
                    new Text("Compteur : {$count}", Tokens::TEXT_TITLE, Tokens::ink()->toHex()),
                    new Button('Incrémenter', 'increment'),
                ])),
                width: $screenWidth,
            ),
            $screenWidth,
            $screenHeight,
        );
    }
}

// public/index.php, avant de construire l'arbre :
if ($action === 'increment') {
    Preferences::set('count', (string) ((int) Preferences::get('count', '0') + 1));
}
```

## Ce que ça donne concrètement

- **~80 widgets natifs** — mise en page (`Flex`, `Stack`, `Wrap`...), formulaires (dialogues Android réels), texte riche multi-styles, animations implicites (`Animated`/`Hero`, FLIP réel), listes virtualisées (`LazyList`) — [référence complète](docs/widgets.md).
- **~40 capacités device réellement natives** — caméra, biométrie, NFC, géofencing, achat intégré, impression PDF, traduction sur l'appareil (ML Kit) — pas de simulation WebView, du vrai code Kotlin appelé directement. [Détails](docs/device-and-native.md).
- **Un geste vraiment continu** — `Dismissible` (glisser pour supprimer) suit le doigt à 100% côté client, zéro requête réseau par frame, PHP ne voit que le résultat final.
- **Backend unifié** — Symfony HttpFoundation + Doctrine DBAL, dans le même processus, zéro configuration réseau supplémentaire.
- **CLI complète** (`phpx`) — scaffold de projet, génération de pages/entités, bundle Android, packaging `.phar`. [Détails](docs/cli.md).

## Démarrage rapide

Prérequis : PHP ≥ 8.1 + Composer. (Android SDK + Gradle ≥ 9.1 + JDK seulement pour builder l'APK — `phpx build:android` les installe tout seul si besoin, voir [docs/mobile-builds.md](docs/mobile-builds.md).)

```bash
curl -fsSL https://github.com/phpnitro/phpnitro/releases/latest/download/phpx.phar -o /usr/local/bin/phpx
chmod +x /usr/local/bin/phpx
# Sur Linux, alternative qui embarque aussi PHP + tout ce que `phpx run`
# demande pour ouvrir une vraie fenêtre desktop (Python/GTK4/Cairo) —
# rien à installer soi-même : sudo snap install phpx

phpx new mon-app
cd mon-app
composer install
phpx make:page Home
phpx serve
```

Envie d'un vrai build natif tout de suite plutôt que le rendu JSON de `serve` ? `phpx run` détecte tous les devices connectés (Android, iOS device/simulateur, macOS, Linux) et lance automatiquement le seul disponible — ou demande de choisir s'il y en a plusieurs, comme `flutter run`.

`serve` affiche un QR code — scanné depuis **PhpNitro Go** (`android/go/` dans ce monorepo, une petite app compagnon qui n'a besoin d'aucun code de projet), ça ouvre ton écran natif réel sur un vrai device/émulateur, sans build ni simulateur pour développer l'UI. `.github/workflows/release-phpnitro-go.yml` compile un APK debug installable directement (`https://github.com/phpnitro/phpnitro/releases/latest/download/go-debug.apk`) à chaque tag `go-v*` — **tant qu'aucun tag `go-v*` n'a encore été poussé**, cette URL n'existe pas encore (404) ; en attendant, `cd android && gradle :go:assembleDebug` le build depuis ce monorepo. Sans device sous la main, `curl http://127.0.0.1:8090/native/layout-demo?screen=home` renvoie directement le JSON de commandes de dessin — utile pour vérifier que le pipeline tourne, pas pour voir un rendu visuel. (`phpx` s'installe une seule fois, pas par projet — voir [docs/cli.md](docs/cli.md) pour l'installation en une commande et le détail de chaque commande.)

## Documentation

| Guide | Contenu |
|---|---|
| [Démarrage & architecture](docs/getting-started.md) | Structure d'un projet, écrire un écran, navigation, formulaires |
| [Widgets](docs/widgets.md) | Référence complète des ~80 widgets natifs, texte riche, animations, gestes |
| [Capacités device & natif](docs/device-and-native.md) | Caméra, biométrie, notifications, partage, impression, accessibilité |
| [CLI (`phpx`)](docs/cli.md) | Toutes les commandes, `phpnitro.yml`, packaging `.phar` |
| [Builds mobiles](docs/mobile-builds.md) | APK Android + app iOS (PHP embarqué sur les deux) |
| [Desktop — Linux](linux/README.md) | GTK4/Cairo, target le plus vérifié après Android (89 tests réels, dont de vrais pixels rendus) |
| [Desktop — macOS](macos/README.md) | Réutilise le protocole d'iOS + le moteur de rendu Rust partagé, vérifié pour de vrai sur un Mac physique |
| [Desktop — Windows](windows/README.md) | App WinForms réelle, moteur de rendu Rust partagé (P/Invoke) — vérifiée en CI sur un vrai runner `windows-latest` |
| [Architecture interne](docs/architecture.md) | Cycle de rendu, actions, gestes continus, backend, base de données |
| [Référence API](docs/api.md) | Générée automatiquement (`phpx docs:api`) pour les packages hors moteur natif |
| [Changelog](CHANGELOG.md) | Historique des changements notables |
| [Contribuer](CONTRIBUTING.md) | Installation, conventions, structure du code |

Une question, une idée, envie de montrer ce que tu as construit avec PhpNitro ? [Discussions](https://github.com/phpnitro/phpnitro/discussions) — les Issues restent réservées aux bugs et demandes de fonctionnalités précises.

## État du projet

Honnêtement : **le runtime Android fonctionne réellement, vérifié sur device physique** (biométrie, navigation complète, animations, geste de glisser, impression PDF, arbre d'accessibilité pour le rendu Canvas) — ce n'est pas un prototype qui ne marche qu'en démo. **iOS** a désormais tourné pour de vrai sur simulateur ET sur un vrai iPhone physique (iPhone 11 Pro Max) — navigation complète, scroll de page, drawer, persistance du compteur au redémarrage de l'app, PhpNitro Go bout en bout, plusieurs ponts de capacité native, le tout couvert par de vrais tests d'UI (`HostAppUITests`, tap-par-coordonnée réel). 42 des 44 capacités device concrètement portables d'Android (torche, batterie, Bluetooth, biométrie, achat intégré, geofencing, WebSocket...) sont déjà portées côté iOS — voir `ios/README.md`. **macOS** a tourné pour de vrai sur un Mac physique (`PhpNitroMacApp`, moteur de rendu Rust partagé) — deux bugs réels trouvés et corrigés dès ce premier lancement (rendu à l'envers, hit-test de la barre de navigation). **Windows** a maintenant une vraie app WinForms (moteur de rendu Rust partagé via P/Invoke), vérifiée en CI sur un vrai runner `windows-latest`, pas seulement sa couche protocole. **Linux** (GTK4/Cairo) reste le desktop le plus profondément vérifié — 89 tests réels dont un vrai rendu Cairo pixel par pixel, une vraie fenêtre confirmée sur un vrai affichage. Un audit récent a comparé les 5 moteurs de rendu entre eux (mêmes commandes JSON, résultat visuel attendu identique) et corrigé 6 vraies incohérences trouvées (couleur avec transparence, ombres et dégradés absents sur iOS, bordures qui débordaient, gras sans effet sur macOS/Windows, alignement des icônes) — `phpx run` détecte maintenant tous les devices connectés (physiques et virtuels, toutes plateformes) et choisit automatiquement s'il n'y en a qu'un, comme `flutter run`. `phpx build:android bundle` produit un vrai App Bundle Play Store (téléchargement réel par device réduit de 63 à 73%, mesuré avec `bundletool`). Chaque package (`packages/*`) est aussi publié indépendamment sur Packagist (`github.com/phpnitro/<nom>`, 23 dépôts) et `android/engine` sur JitPack — `phpx publish:packages` les garde synchronisés via de vraies PR, jamais de push direct. Aucune obfuscation du code applicatif (R8/ProGuard minifie mais n'obfusque pas les noms). Le signing de release (keystore, R8/ProGuard) et le build one-command (`phpx build:android`) sont câblés mais pas encore vérifiés par un vrai build signé de bout en bout.

## Licence

[MIT](LICENSE) © Ronaldo AWADEME
