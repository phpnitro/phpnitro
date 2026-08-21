<div align="center">

# PhpNitro

**Écris des applications mobiles natives en PHP.**

Un vrai runtime PHP embarqué sur le device (pas un serveur distant, pas de transpilation) calcule un arbre de widgets et des commandes de dessin ; un vrai `android.graphics.Canvas` (Skia) les rejoue — même famille d'architecture que Flutter (layout à contraintes, moteur de peinture), sans WebView ni HTML/CSS nulle part dans le pipeline de rendu.

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](composer.json)
[![Platforms](https://img.shields.io/badge/platforms-Android%20%7C%20iOS%20%7C%20Linux%20%7C%20macOS%20%7C%20Windows-informational.svg)](docs/mobile-builds.md)

[Démarrage rapide](#démarrage-rapide) ·
[Documentation](#documentation) ·
[Widgets](docs/widgets.md)

</div>

---

## Pourquoi PhpNitro

Flutter compile en code natif. React Native transpile en JS. **PhpNitro exécute du vrai PHP, en continu, sur le téléphone** — chaque interaction (tap, geste) est une vraie requête traitée par un vrai runtime PHP embarqué (Android : binaire cross-compilé via le NDK, déjà fourni dans le dépôt), qui recalcule l'écran et renvoie des commandes de dessin JSON rejouées sur un vrai Canvas. Pas de simulation, pas d'aller-retour vers un serveur distant.

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

- **~50 widgets natifs** — mise en page (`Flex`, `Stack`, `Wrap`...), formulaires (dialogues Android réels), texte riche multi-styles, animations implicites (`Animated`/`Hero`, FLIP réel), listes virtualisées (`LazyList`) — [référence complète](docs/widgets.md).
- **~30 capacités device réellement natives** — caméra, biométrie, NFC, géofencing, achat intégré, impression PDF, traduction sur l'appareil (ML Kit) — pas de simulation WebView, du vrai code Kotlin appelé directement. [Détails](docs/device-and-native.md).
- **Un geste vraiment continu** — `Dismissible` (glisser pour supprimer) suit le doigt à 100% côté client, zéro requête réseau par frame, PHP ne voit que le résultat final.
- **Backend unifié** — Symfony HttpFoundation + Doctrine DBAL, dans le même processus, zéro configuration réseau supplémentaire.
- **CLI complète** (`phpx`) — scaffold de projet, génération de pages/entités, bundle Android, packaging `.phar`. [Détails](docs/cli.md).

## Démarrage rapide

Prérequis : PHP ≥ 8.1 + Composer. (Android SDK + Gradle ≥ 8.9 + JDK seulement pour builder l'APK — `phpx build:android` les installe tout seul si besoin, voir [docs/mobile-builds.md](docs/mobile-builds.md).)

```bash
curl -fsSL https://github.com/phpnitro/phpnitro/releases/latest/download/phpx.phar -o /usr/local/bin/phpx
chmod +x /usr/local/bin/phpx

phpx new mon-app
cd mon-app
composer install
phpx make:page Home
phpx serve
```

`serve` affiche un QR code — scanné depuis **PhpNitro Go** (`android/go/` dans ce monorepo, une petite app compagnon qui n'a besoin d'aucun code de projet), ça ouvre ton écran natif réel sur un vrai device/émulateur, sans build ni simulateur pour développer l'UI. Honnêteté : PhpNitro Go n'est pas encore publié nulle part (ni Play Store, ni JitPack) — il faut le builder toi-même depuis ce monorepo pour l'instant (`cd android && gradle :go:assembleDebug`). Sans device sous la main, `curl http://127.0.0.1:8090/native/layout-demo?screen=home` renvoie directement le JSON de commandes de dessin — utile pour vérifier que le pipeline tourne, pas pour voir un rendu visuel. (`phpx` s'installe une seule fois, pas par projet — voir [docs/cli.md](docs/cli.md) pour l'installation en une commande et le détail de chaque commande.)

## Documentation

| Guide | Contenu |
|---|---|
| [Démarrage & architecture](docs/getting-started.md) | Structure d'un projet, écrire un écran, navigation, formulaires |
| [Widgets](docs/widgets.md) | Référence complète des ~50 widgets natifs, texte riche, animations, gestes |
| [Capacités device & natif](docs/device-and-native.md) | Caméra, biométrie, notifications, partage, impression, accessibilité |
| [CLI (`phpx`)](docs/cli.md) | Toutes les commandes, `phpnitro.yml`, packaging `.phar` |
| [Builds mobiles](docs/mobile-builds.md) | APK Android (PHP embarqué), état iOS |
| [Desktop — Linux](linux/README.md) | GTK4/Cairo, target le plus vérifié après Android (48 tests réels, dont de vrais pixels rendus) |
| [Desktop — macOS](macos/README.md) | Réutilise Core Graphics/le protocole d'iOS, jamais compilé sur un vrai Mac |
| [Desktop — Windows](windows/README.md) | Couche protocole seulement (C#), rendu GDI+/Direct2D pas encore commencé |
| [Architecture interne](docs/architecture.md) | Cycle de rendu, actions, gestes continus, backend, base de données |
| [Référence API](docs/api.md) | Générée automatiquement (`phpx docs:api`) pour les packages hors moteur natif |
| [Changelog](CHANGELOG.md) | Historique des changements notables |
| [Contribuer](CONTRIBUTING.md) | Installation, conventions, structure du code |

## État du projet

Honnêtement : **le runtime Android fonctionne réellement, vérifié sur device physique** (biométrie, navigation complète, animations, geste de glisser, impression PDF, arbre d'accessibilité pour le rendu Canvas) — ce n'est pas un prototype qui ne marche qu'en démo. **iOS** a un vrai moteur de rendu (Core Graphics), un client réseau et une pile d'écrans écrits et testés en continu (CI réelle sur `macos-14`) — mais rien n'a encore tourné sur un simulateur ou un device réel (pas de Mac disponible). **Linux** (GTK4/Cairo) est le desktop le plus avancé — 48 tests réels dont un vrai rendu Cairo vérifié pixel par pixel. **macOS** réutilise le protocole/Core Graphics d'iOS mais n'a jamais compilé sur un vrai Mac. **Windows** n'a que sa couche protocole (C#) ; le rendu GDI+/Direct2D n'est pas commencé. Aucune obfuscation du code, aucun écosystème de packages tiers, aucun test E2E automatisé au-delà d'Android. Le signing de release (keystore, R8/ProGuard) et le build one-command (`phpx build:android`) sont câblés mais pas encore vérifiés par un vrai build signé de bout en bout.

## Licence

[MIT](LICENSE) © Ronaldo AWADEME
