# CLI (`bin/phpx`)

```bash
php bin/phpx serve                  # sert public/ sur le port 8090
php bin/phpx make:page About        # crée lib/pages/app/AboutPage.php + lib/backend/src/Controller/AboutController.php
                                     # enregistre /about (page) ET /api/about (controller)
php bin/phpx make:entity Product    # crée lib/backend/src/Entity/Product.php + Repository/ProductRepository.php
php bin/phpx new mon-app            # scaffold un nouveau projet complet
php bin/phpx bundle:android         # copie public/ + lib/ + packages/ + .env dans l'app Android (PHP minifié)
php bin/phpx payments               # liste les gateways déclarés dans phpnitro.yml et leur statut dans .env
php bin/phpx maps                   # idem pour les fournisseurs de carte
php bin/phpx icon                   # régénère l'icône Android depuis phpnitro.yml's `icon`
php bin/phpx firebase               # liste la config Firebase déclarée et son statut
```

`make:page` génère la classe et l'ajoute automatiquement au routeur (`public/index.php`), **et** génère un Controller pairé dans `lib/backend/src/Controller/`, câblé dans `Backend\Kernel` — façon Symfony, sans attributs de routage. `make:entity` fait la même chose côté données.

Par défaut, `make:page Home` (sans second argument) enregistre la route `/home`, pas `/` — passe explicitement `/` en second argument pour la page racine.

## `phpnitro.yml` — le manifeste de l'app

```yaml
name: Mon App
description: ...
version: 1.0.0
php: ">=8.1"

icon: assets/icon.png       # optionnel — PNG carré, génère l'icône Android
icon_background: "#2563EB"  # optionnel — couleur de fond de l'icône adaptative

payments:
  kkiapay:
    public_key_env: KKIAPAY_PUBLIC_KEY
    secret_key_env: KKIAPAY_PRIVATE_KEY

maps:
  mapbox:
    access_token_env: MAPBOX_ACCESS_TOKEN
  google:
    api_key_env: GOOGLE_MAPS_API_KEY

firebase:
  service_account_env: FIREBASE_SERVICE_ACCOUNT_JSON
  project_id_env: FIREBASE_PROJECT_ID
  web_api_key_env: FIREBASE_WEB_API_KEY
```

Même rôle que `pubspec.yaml` pour Flutter. `name` est la source de vérité pour `APP_NAME` dans `.env` **et** pour le label natif Android (`strings.xml`), resynchronisés automatiquement par `phpx serve`/`phpx bundle:android`. `payments`/`maps`/`firebase` déclarent quelles variables d'environnement chaque gateway/fournisseur attend, sans jamais lire les clés elles-mêmes.

## Minification (pas obfuscation)

`bundle:android` fait tourner chaque fichier `.php` (framework + app, pas `vendor/`) à travers un minifieur maison basé sur `token_get_all()` — retire commentaires/docblocks, compresse les espaces (~41% de réduction). **Ce n'est pas de l'obfuscation** : aucun renommage d'identifiant, aucun encodage de chaîne — un APK décompressé montre toujours du PHP lisible, juste sans les commentaires.

## Tester la CLI

```bash
bash bin/test.sh
```

Fait un vrai `phpx new` dans un dossier temporaire, un vrai `composer install`, un vrai `make:page`/`make:entity`, lance un vrai serveur et l'interroge en HTTP, puis un vrai `bundle:android` — vérifie aussi que chaque fichier bundlé reste syntaxiquement valide (`php -l`) et qu'un fichier connu pour avoir un docblock ne l'a plus après minification.

```bash
vendor/bin/phpunit    # widgets/Screen/Router/Csrf + toutes les suites de packages
vendor/bin/phpstan analyse
```

## Hot reload

Éditer un fichier PHP dans `lib/pages/app`, `lib/backend/src` ou `packages/*/src` déclenche un rafraîchissement automatique de la WebView (`assets/js/dev-reload.js` interroge `/_dev/version`, un hash des dates de modification, toutes les secondes en mode debug) — pas besoin de recharger à la main.

## Packaging `.phar`

```bash
php box.phar compile   # génère phpx.phar (~53 Mo, artefact de build, pas commité)
php phpx.phar new mon-app
```

`phpx.phar` fonctionne aujourd'hui surtout comme "un seul fichier à copier" plutôt qu'un vrai binaire global façon `flutter`/`composer` : `bin/phpx` reste conçu pour être co-localisé avec le projet sur lequel il opère (`PHPX_ROOT` sert à la fois de racine de gabarit pour `new` et de racine de projet courant pour toutes les autres commandes). Un vrai binaire installable une fois et appelable depuis n'importe quel dossier demanderait de séparer ces deux usages (`getcwd()` pour tout sauf `new`) — identifié, pas encore fait (voir la roadmap, item #13).
