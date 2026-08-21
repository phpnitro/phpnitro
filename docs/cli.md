# CLI (`bin/phpx`)

```bash
php bin/phpx serve                  # sert public/ sur le port 8090, affiche le QR PhpNitro Go
php bin/phpx make:page About        # crée lib/pages/app/AboutPage.php + lib/backend/src/Controller/AboutController.php
php bin/phpx make:auth              # scaffold Login/Register/ForgotPassword/ResetPassword + Controllers pairés
php bin/phpx make:entity Product    # crée lib/backend/src/Entity/Product.php + Repository/ProductRepository.php
php bin/phpx make:migration nom     # crée une migration de base de données horodatée
php bin/phpx migrate                # applique les migrations en attente
php bin/phpx migrate:rollback       # annule le dernier lot de migrations
php bin/phpx migrate:status         # liste les migrations appliquées/en attente
php bin/phpx new mon-app            # scaffold un nouveau projet complet
php bin/phpx bundle:android         # copie public/ + lib/ + packages/ + .env dans l'app Android (PHP minifié)
php bin/phpx build:android [debug|release]  # bundle: + gradle assemble, JDK/Gradle/SDK auto-installés si besoin
php bin/phpx dev:push [--watch]     # pousse le PHP sur un device connecté, sans rebuild/reinstall
php bin/phpx run                    # détecte une cible dispo (device Android, desktop Linux) et lance dessus
php bin/phpx payments               # liste les gateways déclarés dans phpnitro.yml et leur statut dans .env
php bin/phpx maps                   # idem pour les fournisseurs de carte
php bin/phpx icon                   # régénère l'icône Android depuis phpnitro.yml's `icon`
php bin/phpx firebase               # liste la config Firebase déclarée et son statut
php bin/phpx docs:api               # génère docs/api/*.md depuis les docblocks des classes publiques
php bin/phpx doctor                 # vérifie que la machine est prête (PHP, Composer, Java, Gradle, SDK, adb)
```

`make:page` génère la classe et l'ajoute automatiquement au routeur (`public/index.php`), **et** génère un Controller pairé dans `lib/backend/src/Controller/`, câblé dans `Backend\Kernel` — façon Symfony, sans attributs de routage. `make:entity` fait la même chose côté données.

`make:page` ne prend qu'un seul argument (le nom) — la route native (`?screen=...`) est toujours le kebab-case de ce nom (`HomePage` → `home`, `AboutPage` → `about`), sans exception. Côté Controller/API, `home` spécifiquement route vers `/api` tout court (pas `/api/home`) — le seul cas spécial, câblé en dur dans `cmdMakePage()`, pas un second argument à passer toi-même.

## `phpx run` — l'équivalent `flutter run`

Détecte automatiquement une cible de lancement et démarre dessus, sans avoir à se souvenir de la bonne combinaison de commandes :

- **Un device/émulateur Android connecté** (`adb devices` en montre au moins un) — build + installe automatiquement si l'app n'est pas déjà présente, lance via `monkey -c android.intent.category.LAUNCHER` (résout le lanceur réellement actif, y compris si le mécanisme d'icône dynamique du projet en a changé), puis reste actif à surveiller `lib/pages`, `lib/backend/src`, `packages/*/src` — chaque sauvegarde repousse le PHP direct sur le device (même mécanisme que `dev:push --watch`).
- **Le desktop Linux de la machine courante**, si le projet a été scaffoldé avec `phpx new --all` (dossier `linux/` présent). Aucune étape de "watch" nécessaire ici — `python3 -m phpnitro_desktop` lance `php -S` directement contre `public/` du projet, donc un fichier PHP édité est déjà pris en compte à la requête suivante.

Si plusieurs cibles sont disponibles en même temps, `phpx run` affiche une liste numérotée et demande laquelle choisir. macOS et Windows ne sont pas encore branchés à `run` — ni l'un ni l'autre n'a jamais tourné une seule fois sur sa propre plateforme (voir leur README.md respectif), donc deviner leur commande de lancement exacte n'aurait rien apporté de fiable.

**Vérifié pour de vrai** : lancé contre un vrai affichage — la toute première fois qu'une fenêtre de ce port Linux s'est réellement ouverte (voir `linux/README.md`). Le nettoyage des processus enfants (`python3`, et le `php -S` que lui-même lance) sur Ctrl+C/kill a aussi été testé en tuant un vrai `phpx run` en cours — un premier essai laissait les deux orphelins (`proc_open()` ne cascade aucun signal, et `pcntl_signal()` ne se déclenche jamais tant que PHP reste bloqué dans l'appel bloquant `proc_close()`) ; corrigé avec une boucle de sondage `proc_get_status()` côté `phpx` et un vrai gestionnaire `signal.signal(SIGTERM/SIGINT, ...)` côté `phpnitro_desktop` (qui route vers `app.quit()`, le même chemin qu'une fermeture de fenêtre normale).

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

`phpx.phar` est un vrai binaire global, installable une fois et appelable depuis n'importe quel dossier — `PHPX_TOOL_ROOT` (où vit le script/le `.phar` lui-même, utilisé UNIQUEMENT par `cmdNew()` pour retrouver ses gabarits `lib/`/`android/`/`ios/`/`assets/`/`public/`/`vendor/`, tous bundlés dans l'archive via `box.json`) et `PHPX_ROOT` (`getcwd()`, le projet courant, pour absolument toutes les autres commandes) sont deux constantes séparées depuis le début de ce fichier — confirmé en construisant un `.phar` minimal et en l'exécutant depuis un dossier totalement différent : `dirname(__DIR__)` résout bien vers la racine virtuelle de l'archive (`phar://...`), pas vers le dossier d'où la commande est lancée.

```bash
# installation globale, une fois — voir "Installation en une commande" plus bas
curl -fsSL https://github.com/phpnitro/phpnitro/releases/latest/download/phpx.phar -o /usr/local/bin/phpx
chmod +x /usr/local/bin/phpx
phpx new mon-app   # fonctionne depuis n'importe quel dossier, à partir de maintenant
```

## Installation en une commande

Chaque tag de version (`.github/workflows/release.yml`) compile `phpx.phar` (via `humbug/box`, téléchargé côté CI, jamais côté poste de dev) et le publie comme artefact de la Release GitHub correspondante — pas de compilation locale nécessaire pour un utilisateur final, la même expérience qu'un `flutter`/`composer.phar` téléchargé une fois. `php box.phar compile` (ci-dessus) reste la façon de le compiler soi-même pour du développement local de `phpx` lui-même.

Vérifié pour de vrai à la publication de `v0.1.0` : deux bugs de `box.json` ont fait échouer les deux premières tentatives (`package.json`/`package-lock.json` listés dans `files` alors qu'ils n'existent nulle part dans ce dépôt ; `bin` absent de `directories`, donc `bin/QrCode.php` — requis sans condition par `bin/phpx` — manquait du bundle et faisait planter le phar dès son lancement). La 3ᵉ tentative a réellement téléchargé, lancé et scaffoldé un projet avec ce `phpx.phar` publié — l'URL ci-dessus fonctionne.

Si tu clones ce monorepo pour contribuer plutôt que pour l'utiliser en dépendance, `php bin/phpx <commande>` depuis sa racine reste équivalent (voir [CONTRIBUTING.md](../CONTRIBUTING.md)).
