<div align="center">

# PhpNitro

**Écris des applications mobiles natives en PHP.**

Un vrai runtime PHP embarqué sur le device (pas un serveur distant, pas de transpilation), affiché dans une WebView native, avec un pont natif Android/iOS pour tout ce qu'une app "juste web" ne peut pas faire.

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](composer.json)
[![Platforms](https://img.shields.io/badge/platforms-Android%20%7C%20iOS-informational.svg)](docs/mobile-builds.md)

[Démarrage rapide](#démarrage-rapide) ·
[Documentation](#documentation) ·
[Widgets](docs/widgets.md) ·
[Roadmap honnête](ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md)

</div>

---

## Pourquoi PhpNitro

Flutter compile en code natif. React Native transpile en JS. **PhpNitro exécute du vrai PHP, en continu, sur le téléphone** — chaque interaction (clic, formulaire, navigation) est une vraie requête traitée par un vrai runtime PHP embarqué (Android : binaire cross-compilé via le NDK, déjà fourni dans le dépôt), pas une simulation ni un aller-retour vers un serveur distant.

Résultat : si tu sais écrire du PHP, tu sais écrire une app mobile. Pas de Dart, pas de JSX, pas de nouveau langage — juste des classes PHP (`Screen`, `Widget`) et Tailwind CSS pour le style.

```php
final class HomePage extends Screen
{
    protected function initialState(): array
    {
        return ['count' => 0];
    }

    protected function onIncrement(): void
    {
        $this->state['count']++;
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Compteur : ' . $this->state['count']),
            Button::make('Incrémenter', action: 'increment'),
        ]);
    }
}
```

## Ce que ça donne concrètement

- **~75 widgets & services** — mise en page (`Stack`, `Positioned`, `Wrap`, `Row`, `Column`...), formulaires, média, animations (`FadeIn`, `AnimatedText`), pagination infinie, Lottie — [référence complète](docs/widgets.md).
- **Capacités device réellement natives** — caméra, biométrie (Face ID/Touch ID/empreinte), notifications hors-ligne, impression/PDF, partage natif, deep linking, icône d'app dynamique, alarme planifiée — pas de simulation WebView, du vrai code Kotlin/Swift. [Détails](docs/device-and-native.md).
- **7 fournisseurs d'authentification sociale** — Google, Apple, Microsoft, GitHub, Slack, Facebook, X — en services attachables à n'importe quel bouton, pas des widgets imposés. [Détails](docs/integrations.md#authentification-sociale).
- **7 gateways de paiement**, 3 fournisseurs de cartes, Firebase (Auth/Messaging/Firestore), 194 pays offline avec villes/capitales/drapeaux. [Détails](docs/integrations.md).
- **Backend unifié** — Symfony HttpFoundation + Doctrine DBAL, dans le même processus, zéro configuration réseau supplémentaire.
- **CLI complète** (`phpx`) — scaffold de projet, génération de pages/entités, bundle Android, packaging `.phar`. [Détails](docs/cli.md).

## Démarrage rapide

Prérequis : PHP ≥ 8.1 + Composer. (Node.js seulement pour reconstruire le CSS Tailwind ; Android SDK + Gradle ≥ 8.9 seulement pour builder l'APK — voir [docs/mobile-builds.md](docs/mobile-builds.md).)

```bash
php bin/phpx new mon-app
cd mon-app
composer install
bin/phpx make:page Home /
bin/phpx serve
```

Ouvre `http://127.0.0.1:8090/`. C'est tout — pas de build step, pas de simulateur requis pour développer l'UI.

## Documentation

| Guide | Contenu |
|---|---|
| [Démarrage & architecture](docs/getting-started.md) | Structure d'un projet, écrire un écran, navigation, formulaires |
| [Widgets](docs/widgets.md) | Référence complète des ~75 widgets & services, API de style typée, animations |
| [Capacités device & natif](docs/device-and-native.md) | Caméra, biométrie, notifications, partage, deep linking, accessibilité |
| [Paiements](docs/payments.md) | 7 gateways, niveau de confiance de chacun, sécurité PCI-DSS |
| [Intégrations](docs/integrations.md) | Cartes, Firebase, Countries (offline), authentification sociale, formats |
| [CLI (`phpx`)](docs/cli.md) | Toutes les commandes, `phpnitro.yml`, packaging `.phar` |
| [Builds mobiles](docs/mobile-builds.md) | APK Android (PHP embarqué), état iOS |
| [Architecture interne](docs/architecture.md) | Rendu, navigation SPA, backend, base de données |

## État du projet

Honnêtement : **le runtime Android fonctionne réellement, vérifié sur device physique** (biométrie, navigation complète, animations, partage natif, deep linking) — ce n'est pas un prototype qui ne marche qu'en démo. Les paiements, eux, ne sont vérifiés qu'en mode démo : 5 des 7 gateways n'ont jamais tourné contre un vrai compte sandbox (voir [docs/payments.md](docs/payments.md)). iOS a un pont natif complet mais non compilé (pas de Mac disponible pendant son développement). Aucune obfuscation du code, aucun écosystème de packages tiers, aucune release signée pour l'instant.

Le détail complet, sans enjoliver, de ce qui est vérifié vs supposé, et de ce qu'il reste pour rivaliser avec Flutter/React Native, est dans **[ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md](ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md)**.

## Exemple complet

[`examples/ecom`](examples/ecom/README.md) — une boutique en ligne (catalogue, panier, checkout multi-gateway, compte, carte interactive, biométrie, suivi de commande en direct) qui utilise la quasi-totalité du framework.

## Licence

[MIT](LICENSE) © Ronaldo AWADEME
