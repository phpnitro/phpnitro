# Contribuer à PhpNitro

## Installation

```bash
composer install
```

Voir [docs/getting-started.md](docs/getting-started.md) pour la structure complète d'un projet.

## Lancer les vérifications

```bash
vendor/bin/phpunit                          # tests, tous les packages (autoload-dev PSR-4)
php -l chemin/vers/fichier.php               # vérification syntaxique rapide
vendor/bin/phpstan analyse packages lib      # analyse statique
```

Un `bin/phpx serve` local + `curl` sur `/native/layout-demo?screen=...` reste le moyen le plus sûr de valider un changement touchant au moteur de rendu natif (`packages/ui/src/Native/`, `lib/pages/Native*Screen.php`). Pour tout ce qui touche au pont natif Android (`NativeDeviceBridge.kt`, `NativeCanvasView.kt`, permissions, capteurs...), il n'y a pas de substitut à un `php bin/phpx build:android` réel — pas de `gradlew` commité dans ce repo, `phpx` installe Java/Gradle/le SDK Android tout seul si besoin (voir `docs/cli.md`) — (voire un test sur device via `adb`) ; les tests PHPUnit ne couvrent que la génération des commandes de dessin côté PHP, jamais le code Kotlin lui-même.

```bash
cd android && gradle :app:connectedDebugAndroidTest   # tests E2E réels sur device/émulateur connecté
```

`android/app/src/androidTest/java/com/mobile/engine/NativeUiE2ETest.kt` — UI Automator, pas Espresso : chaque écran est un seul `NativeCanvasView.onDraw()` qui peint des pixels bruts, pas une vue Android par widget, donc les matchers de vue d'Espresso n'ont rien à cibler. UI Automator pilote à la place le même arbre virtuel de nœuds d'accessibilité que `CanvasAccessibilityNodeProvider` expose à TalkBack. Un seul test tourne réellement pour l'instant (`tappingIncrementButtonAdvancesTheCounter` — vrai tap, vrai device, vraie vérification via l'arbre d'accessibilité) ; deux autres (drag-to-reorder, scroll horizontal indépendant) sont `@Ignore`d avec la raison exacte du blocage dans leur annotation — les deux fonctionnent réellement à la main sur device, mais pas encore automatisables dans ce harnais.

## Où va le code

- `packages/ui/src/` — le widget SDK (`Text`, `Button`, `Column`...). Chaque widget est une classe finale, `render(): string`, un `make()` statique en façade du constructeur.
- `packages/*/src/` — un package = un domaine (`device`, `payments`, `maps`, `socialauth`...), namespace dédié, déclaré dans `composer.json` (`autoload` **et** `autoload-dev`).
- `android/app/src/main/java/com/mobile/engine/` — le pont natif. Toute méthode exposée à `window.AndroidNative` doit être annotée `@JavascriptInterface` **et** couverte par la règle `-keepclassmembers` de `proguard-rules.pro` (sinon R8 la supprime silencieusement en release).
- `assets/js/` — un fichier par capacité (`device.js`, `canvas.js`...), chacun listé explicitement dans `public/index.php` pour être servi.
- `docs/` — référence détaillée par sujet ; le `README.md` reste court, c'est la vitrine.

## Conventions

- Un widget/service = une classe, `final`, un constructeur avec des paramètres nommés + un `make()` statique équivalent.
- Les "services" (`Engine\Device\*`, `Engine\SocialAuth\*`) ne rendent jamais de HTML : ils exposent des méthodes qui renvoient une chaîne JS (`onClick(): string`), attachable à *n'importe quel* bouton via `Button::make($label, onClick: Torch::onClick())` — jamais de widget pré-stylé imposé à l'utilisateur.
- Toute sortie interpolée dans du HTML passe par `htmlspecialchars(..., ENT_QUOTES)`. Toute donnée interpolée dans un attribut `data-*` JSON passe par `json_encode` puis `htmlspecialchars`.
- Un commentaire n'explique jamais *quoi* (le code le dit déjà) — seulement *pourquoi*, quand ce n'est pas évident (contrainte cachée, workaround, piège déjà rencontré).
- Pas d'abstraction ajoutée par anticipation d'un besoin futur — trois lignes similaires valent mieux qu'une fausse généralisation.

## Commits et PR

Un commit = un changement atomique et cohérent (pas de mélange fix + feature). Message au format impératif court, dans le style de l'historique existant (`git log --oneline`). Toute PR touchant à l'Android natif doit préciser si elle a été vérifiée sur un device réel ou seulement compilée.

## Licence

En contribuant, tu acceptes que ton code soit distribué sous licence MIT (voir [LICENSE](LICENSE)).
