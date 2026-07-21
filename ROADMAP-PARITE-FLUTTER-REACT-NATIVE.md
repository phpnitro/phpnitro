# Feuille de route — atteindre le niveau de Flutter / React Native

Ce document liste, honnêtement et sans enjoliver, ce qu'il reste à faire pour que PhpNitro soit un concurrent sérieux de Flutter/React Native — pas juste un prototype qui marche sur un device. Chaque point a été vérifié dans le code actuel avant d'être listé ici (pas de supposition).

**Cadrage honnête au départ** : Flutter et React Native sont développés par des centaines d'ingénieurs payés (Google, Meta) depuis 8-10 ans, avec des millions d'apps en production. Cette liste n'est pas "quelques semaines de travail" — certains points (moteur de rendu, iOS réel, sécurité du binaire) sont des chantiers de plusieurs mois chacun, réalistes seulement avec une équipe, pas un seul développeur.

---

## Niveau 1 — Bloquant (sans ça, ce n'est pas un concurrent crédible)

### 1. iOS n'existe pas réellement — **en cours, le pont natif est écrit**
**Mise à jour** : le pont natif device/dialogues est maintenant entièrement écrit — `ios/App/WebAppInterface.swift` reproduit `WebAppInterface.kt` capacité par capacité (vibreur, photo native, sélecteur d'image, biométrie Face ID/Touch ID, son, notification, impression, alertes/confirmations), exposé en JS sous `window.iOSNative` avec **exactement les mêmes noms de méthodes** que `window.AndroidNative` — `assets/js/device.js`/`dialogs.js` détectent déjà les deux (`window.AndroidNative || window.iOSNative`), donc aucun widget/service PHP n'a besoin d'un chemin spécifique iOS. `ViewController.swift` injecte ce pont via `WKUserScript`/`WKScriptMessageHandler` et gère les permissions caméra/micro (`WKUIDelegate`) et le geste de retour (`allowsBackForwardNavigationGestures`). `Info.plist` a les bonnes clés (dont `NSFaceIDUsageDescription`, oubliée dans la première version). **Rien de tout ça n'est compilé ni testé** — toujours pas de Mac/Xcode disponible ici.

**Correction importante faite au passage** : l'idée initiale de ce document ("lancer le PHP cross-compilé en sous-processus via `Process`/`NSTask`, comme sur Android") était **fausse** — `Process`/`NSTask` n'existe pas sur iOS (sandbox Apple, macOS uniquement). La bonne approche, documentée en détail dans `ios/App/PhpEmbedBridge.swift` (squelette d'architecture avec des `TODO` explicites, pas du code qui compile) : le SAPI **embed** de PHP, lié statiquement, exécuté in-process, servi au WebView via `WKURLSchemeHandler` plutôt qu'un serveur socket.

Ce qui reste, pour atteindre la parité :
- Cross-compiler php-src pour `arm64-apple-ios` (device) et les cibles simulateur avec `--enable-embed=static` — aucun binaire n'existe aujourd'hui, contrairement à Android où `armeabi-v7a`/`arm64-v8a` sont déjà prêts dans `jniLibs/`. Flags exacts et extensions à activer non vérifiés (pas de toolchain php-src/Xcode disponible ici).
- Écrire le header de pont C/Objective-C entre ce binaire et Swift (Swift ne peut pas appeler certaines macros C de php-src directement).
- Implémenter pour de vrai `PhpEmbedBridge.swift`'s `webView(_:start:)` : traduire chaque `WKURLSchemeTask` en superglobales PHP, exécuter `public/index.php` via le SAPI embed, renvoyer la réponse capturée.
- **Compiler et tester le pont natif déjà écrit** sur un vrai projet Xcode, d'abord contre un PHP hébergé sur le réseau (étape recommandée avant même de toucher au SAPI embed — voir `ios/README.md`).
- Tester sur un vrai device iPhone (aucun disponible ici).

### 2. Le code source PHP est lisible en clair dans l'APK
Vérifié : `bin/phpx bundle:android` copie les fichiers `.php` **tels quels** (texte brut) dans les assets de l'APK. N'importe qui peut décompresser l'APK (`unzip`) et lire tout le code métier, y compris les clés/logique côté serveur qui tournent sur le device. Flutter compile en code natif (AOT), React Native transforme en bundle JS minifié/obfusqué (Hermes bytecode) — aucun des deux n'expose son code source aussi directement.
- Il faut soit un compilateur PHP → bytecode (type `opcache` précompilé packagé, ou un vrai compilateur AOT PHP — projet de recherche à part entière), soit au minimum un minifieur/obfuscateur PHP appliqué au moment du bundle.
- Sans ça, n'importe quelle app construite avec ce framework expose tout son code métier à l'utilisateur final — un vrai problème de sécurité/propriété intellectuelle pour un usage commercial.

### 3. Aucune release signée, aucun pipeline de publication
Vérifié dans `android/app/build.gradle.kts` : le `buildType release` existe mais n'a **aucun `signingConfig`** assigné, et `isMinifyEnabled = false` (pas de R8/ProGuard, donc pas de réduction de taille ni d'obfuscation du bytecode Kotlin/Java non plus). Aujourd'hui, seul `assembleDebug` a été utilisé et testé.
- Générer et gérer une vraie clé de release (keystore), la brancher dans `signingConfigs.release`.
- Activer R8 (minification + obfuscation du code Kotlin natif).
- Documenter/scripter la publication sur le Play Store (fiche store, captures d'écran, politique de confidentialité — obligatoire pour les permissions caméra/micro/localisation demandées).
- Idem côté iOS : compte Apple Developer, certificats, provisioning profiles, App Store Connect — rien de tout ça n'existe.

### 4. Zéro moteur d'animation — **premier jalon posé, très loin de la parité**
**Mise à jour** : trois choses ajoutées cette session, toutes vérifiées (rendu testé en standalone PHP + tests PHPUnit ajoutés + `npm run build` exécuté réellement, sortie de `public/tailwind.css` inspectée pour confirmer que les nouvelles règles y sont bien compilées) — **et revérifiées plus tard dans la session sur le téléphone physique** (Infinix X6532) : APK réel installé, page `/widgets` → "Mise en page" ouverte, capture d'écran confirmant le rendu correct des trois exemples `FadeIn` (couleurs, textes, disposition) et du `Container` typé `rounded-full`. Une capture statique ne prouve pas que l'animation a joué (elle est déjà retombée au moment du screenshot), seulement que le rendu final est correct :
- `Curves.php` : constantes de courbes d'easing nommées à la Flutter (`EASE_IN_OUT`, `FAST_OUT_SLOW_IN`, `OVERSHOOT`...), qui ne sont que des chaînes CSS `timing-function` — pas un vrai système de courbes appliqué à des valeurs animables.
- `FadeIn.php` (`packages/ui/src/`) : widget qui fait jouer un fondu + léger glissement au **montage** via une pure animation CSS keyframe (`@keyframes phpx-fade-in` dans `assets/css/input.css`), sans JS — le même principe que `FlashMessage`/`phpx-flash` qui existait déjà. Configurable (durée, délai, courbe, distance) via des custom properties CSS injectées en `style` inline.
- Transition de page dans `nav.js` : le contenu injecté par `applyPayload()` est maintenant enveloppé dans un `<div class="phpx-page-enter">` frais à chaque swap, ce qui déclenche automatiquement un fondu de 200 ms à l'insertion — remplace le "remplacement `innerHTML` instantané et brut" décrit plus haut par une vraie transition, pour la première fois.
- `@media (prefers-reduced-motion: reduce)` ajouté pour désactiver ces animations (et `phpx-flash`) chez les utilisateurs qui l'ont demandé au niveau OS — lié à l'item #11 (accessibilité jamais auditée).
- **Mise à jour** : la même classe `.phpx-animate` (celle de `FadeIn`, avec ses valeurs par défaut) est maintenant réutilisée par `future.js` (résolution one-shot, toujours enveloppée) et `stream.js` (poll récurrent — enveloppée **seulement si le contenu a changé** par rapport au dernier fetch, sinon aucune animation ne se rejoue à chaque tick, pour éviter un effet de scintillement sur un `StreamBuilder` dont les données ne changent pas à chaque intervalle).

**Ce que ça n'est toujours PAS**, pour rester honnête : il n'y a **aucune reactivité/diffing côté client** dans ce projet (vérifié — chaque interaction est un aller-retour serveur complet + remplacement `innerHTML`, voir `nav.js`/`stream.js`/`future.js`). Un vrai `AnimatedContainer` Flutter (qui anime la transition **entre deux valeurs** d'une propriété quand elle change) n'est donc pas faisable sans un système de réactivité/diffing bien plus gros que ce chantier — ce que `FadeIn` fait est une animation d'entrée qui joue une fois au montage, rien de plus. Toujours absents : `Hero`/shared element transition, `AnimationController`/`Tween` programmable, animation de sortie (exit), tout ce qui touche au geste (drag-to-dismiss animé, etc.).

### 5. Pas de vrai moteur de layout avancé — **Stack/Positioned/Wrap ajoutés, CustomPaint/Canvas toujours absent**
**Mise à jour** : `Stack::make()` (superpose des enfants ; un enfant `Positioned` reçoit un offset explicite top/right/bottom/left en pixels via `style` inline, les autres remplissent la zone en `absolute inset-0`) et `Wrap::make()` (comme `Row`, mais `flex-wrap` — passe à la ligne au lieu de déborder) sont réels : rendu vérifié en standalone PHP + PHPUnit (81 tests, 155 assertions) + **confirmé visuellement sur le téléphone Infinix X6532** (badge rouge positionné en incrustation sur un bloc bleu, 5 tags qui passent bien à la ligne). `Positioned` utilise volontairement du `style` inline plutôt que des classes Tailwind arbitraires (`top-[12px]`) : ce sont des valeurs calculées à l'exécution que le scanner JIT de Tailwind (qui lit les fichiers source au build, pas la sortie PHP) ne peut pas voir, contrairement aux valeurs fixes de `Color`/`Rounded` sur `Container`.

Toujours absent : pas de `CustomPaint`/Canvas (graphiques dessinés à la main), pas de contraintes de layout façon Flutter (`ConstrainedBox`, `IntrinsicWidth`) — tout reste du flexbox/positionnement CSS direct, pas un moteur de layout avec son propre passage de mesure/disposition.

### 6. Aucun test automatisé de bout en bout, build Android et analyse statique ajoutées en CI — **partiellement traité, exécuté réellement (pas juste écrit)**
`.github/workflows/ci.yml` lance `vendor/bin/phpunit` + `bin/test.sh` à chaque push/PR — **ce n'est pas rien**, mais ça restait limité. Deux jobs ajoutés cette session :
- **`android-build`** : nouveau job qui exécute `php bin/phpx bundle:android` puis `gradle :app:assembleDebug` et upload l'APK en artefact. **Mise à jour — vérifié pour de vrai cette session**, PHP 8.4 et un vrai téléphone Android étant devenus disponibles : SDK Android installé à la main (`cmdline-tools`, `platform-tools`, `platforms;android-35`, `build-tools`), Gradle 8.10.2 téléchargé, `bundle:android` puis `gradle :app:assembleDebug` exécutés en dehors de CI — **`BUILD SUCCESSFUL`**, APK généré (11,4 Mo), installé sur le téléphone connecté (`adb install`, après désinstallation de l'ancienne build — signature de debug différente), lancé (`adb shell am start`), et vérifié sans crash (`logcat` sans `FATAL EXCEPTION`/`AndroidRuntime`, captures d'écran prises). Les binaires natifs (`jniLibs/*/libphp.so`, `libsqlite3.so`) sont bien commités dans le dépôt (vérifié via `git ls-files`, contrairement à ce que laissait entendre `android/README.md`) et se sont chargés normalement au runtime. Reste un écart avec la CI GitHub Actions elle-même : le SDK/Gradle ont été installés à la main ici, pas via `gradle/actions/setup-gradle`/l'image `ubuntu-latest` — le **premier run réel en CI reste à surveiller**, mais la commande elle-même (`bundle:android` + `assembleDebug`) est maintenant prouvée fonctionnelle de bout en bout, pas une supposition.
- **`phpstan`** : `phpstan/phpstan` ^2.1 ajouté à `composer.json`, `phpstan.neon.dist` au niveau 1 sur `packages/*/src`, `lib/`, `public/`. **Mise à jour — exécuté réellement avec PHP 8.4 : `[OK] No errors`, niveau 1 propre sans baseline.** `vendor/bin/phpunit` aussi exécuté réellement, rejoué après chaque ajout de widget cette session : **81 tests, 155 assertions, tous verts** (y compris les tests `FadeIn`/`Curves`/`Container`/`Stack`/`Positioned`/`Wrap`). `bin/test.sh` (smoke test phpx complet, y compris `bundle:android`) également rejoué avec succès à plusieurs reprises.
- Reste non traité : aucun test E2E automatisé sur émulateur/device (l'équivalent de `integration_test` de Flutter ou Detox/Maestro pour React Native) — la vérification "ça marche sur device" de ce projet reste **manuelle** (comme à l'instant), pas scriptée/répétable en CI. Aucun test de non-régression visuelle (screenshot diffing).

---

## Niveau 2 — Important, mais pas bloquant pour un usage réel limité

### 7. Paiements : 5 des 7 gateways ne sont pas vérifiés
- **Feexpay, iZiChangePay, TresorPay** : gabarits structurels avec des `TODO` explicites sur le nom exact des fonctions JS et l'URL du SDK — jamais vérifiés contre un vrai compte. Inutilisables en l'état pour de l'argent réel.
- **Kkiapay, FedaPay** : confiance moyenne à élevée sur le pattern, mais jamais exercés contre un vrai sandbox (aucun compte disponible dans cet environnement).
- Manque aussi : Apple Pay / Google Pay natifs (aucun des deux n'est implémenté), gestion des remboursements, réception de webhooks asynchrones (le flux actuel est 100% synchrone côté client).

### 8. Capacités device incomplètes face à Flutter/RN
Ce qui existe (vibreur, caméra, micro, biométrie, notifications, son, impression, sélecteur d'image, géolocalisation) couvre l'essentiel — mais il manque, par rapport à l'écosystème de plugins Flutter/RN :
- Capteurs (accéléromètre, gyroscope, boussole).
- Bluetooth / NFC.
- Contacts, calendrier.
- Partage natif (share sheet Android/iOS).
- Achats intégrés (in-app purchase, obligatoire pour certains modèles économiques sur les stores).
- Tâches en arrière-plan (background execution, geofencing).
- Deep linking / universal links.
- Stockage sécurisé type Keychain/Keystore (aujourd'hui : SQLite + session PHP, pas un coffre-fort chiffré dédié aux tokens sensibles).

### 9. Notifications push non vérifiées en conditions réelles
Le stockage des tokens et l'envoi serveur (`FirebaseMessaging::send()`) sont codés, mais **jamais testés contre un vrai projet Firebase** (aucun compte de service disponible ici). La réception Android (`FcmService.kt.example`) est désactivée par défaut et nécessite un `google-services.json` réel. Rien de tout ça n'existe côté iOS (APNs).

### 10. API de style typée très partielle — **Container gagne background/rounded typés, encore loin d'être généralisé**
**Mise à jour** : `Container::make()` accepte maintenant `background: ?Color` et `rounded: ?Rounded` (nouvel enum, même famille que `TextSize`/`FontWeight`) — vérifié par rendu standalone + tests PHPUnit + confirmé visuellement sur device réel (capture d'écran, section "Container — background/rounded typés" de `/widgets`). Contrairement à `Text` (où un paramètre typé remplace entièrement `$classes`), ici les classes typées s'**ajoutent** par-dessus `$classes` : `Container::make()` porte souvent du layout structurel (`h-24`, `flex`...) qu'un paramètre `background`/`rounded` n'a pas vocation à remplacer. `Padding`/`Margin` gardent **volontairement** une chaîne Tailwind brute (choix déjà documenté dans `Padding.php` avant cette session : "DOM-native syntax instead of a dedicated value object") — pas de nouvel enum d'espacement, pour rester cohérent avec cette décision.

Reste non traité : `Button` (son fond `bg-blue-600 hover:bg-blue-700` mélange couleur de base et état hover, ce qui demanderait une convention de mapping teinte→hover avant de pouvoir le typer proprement), et tous les ~55 autres widgets. Toujours aucun thème injectable façon `ThemeData` — seul un bascule clair/sombre binaire existe, pas de thème personnalisable centralement (couleurs de marque, typographie custom, espacement cohérent).

### 11. Accessibilité — **premier audit réel effectué avec TalkBack, deux bugs trouvés et corrigés**
**Mise à jour** : TalkBack activé pour de vrai sur le téléphone connecté (`adb shell settings put secure enabled_accessibility_services`), arbre d'accessibilité inspecté (`uiautomator dump`) contre l'app en cours d'exécution — pas une inspection de code, une vérification en conditions réelles. Deux bugs concrets trouvés et corrigés :
- `DrawerToggle` (hamburger) et le bouton fermer de `Drawer` sont de simples `<label>` (le motif "zéro JS, case à cocher + label" utilisé ailleurs dans le framework) — malgré un `aria-label` déjà présent dans le HTML, **aucun des deux n'était exposé comme élément interactif** par le pont d'accessibilité de Chromium : le hamburger n'apparaissait **pas du tout** dans l'arbre, le bouton fermer apparaissait mais non cliquable et sans nom. Un utilisateur TalkBack ne pouvait ni découvrir ni ouvrir le menu. Corrigé par `role="button" tabindex="0"` sur les deux — vérifié après coup par un nouveau dump : les deux apparaissent maintenant en `android.widget.Button`, `clickable=true`, avec le bon `content-desc`.
- `FloatingActionButton` n'avait aucun moyen de donner un nom accessible différent du glyphe affiché — confirmé que le FAB "+" de la page d'accueil était annoncé littéralement "plus". Ajout d'un paramètre `$ariaLabel` (même idiome que `IconButton`), utilisé sur la démo ("Incrémenter").

Reste non audité : le reste de l'app (formulaires, dialogues, `Stepper`, cartes...), VoiceOver (iOS, qui n'existe toujours pas), et tout test structuré/répétable — cet audit a couvert l'écran d'accueil un écran à la fois, à la main, pas une méthode systématique qui pourrait tourner en CI. `SwitchToggle` (case à cocher réelle avec `sr-only`, pas un `<label>` nu) n'a pas montré le même problème dans un test rapide mais n'a pas été vérifié aussi rigoureusement que `Drawer`/`FloatingActionButton`.

### 12. Un seul device réel testé, jamais de tests multi-devices
Toute la validation "ça marche vraiment" de ce projet repose sur **un seul téléphone** (Infinix X6532, Android 14, armeabi-v7a). Aucune couverture : autres versions Android (minSdk 24 = Android 7, jamais testé), tablettes, résolutions d'écran variées, fabricants avec des WebView personnalisées (Samsung, Xiaomi ont des comportements WebView parfois différents). Flutter/RN sont testés sur des fermes de devices (Firebase Test Lab, BrowserStack) — rien de tel ici.

---

## Niveau 3 — Confort développeur / écosystème (ce qui fait "framework mature")

### 13. Pas de vrai binaire autonome — **`.phar` packagé via box, mais toujours pas un binaire installable globalement**
**Mise à jour** : `box.json` ajouté à la racine, un `phpx.phar` réel a été compilé (`php box.phar compile`, ~53 Mo, non commité — voir `.gitignore` — c'est un artefact de build, pas du code source) et **vérifié de bout en bout** : `php phpx.phar payments`/`maps` fonctionnent, et surtout `php phpx.phar new mon-app` scaffold un vrai projet exploitable (`composer install` + `./bin/phpx make:page` + `bin/phpx serve` répondent HTTP 200). Deux bugs réels trouvés et corrigés au passage dans `cmdNew()`/`copyDirectory()` (les deux affectaient aussi le `phpx new` **sans** phar, pas seulement le cas phar) :
- `copy()` ne préserve jamais les bits de permission de la source — `bin/phpx` scaffoldé perdait son bit exécutable. Fixé par un `chmod(0755)` explicite après coup (pas en recopiant `fileperms()` de la source : lue depuis un phar, `fileperms()` renvoie une valeur synthétique `0444` uniforme pour toutes les entrées, peu importe le mode réel — recopier cette valeur aurait rendu tous les fichiers scaffoldés en lecture seule, un bug pire que l'original).
- Box retire la ligne shebang (`#!/usr/bin/env php`) du fichier "main" qu'il empaquette — la copie de `bin/phpx` dans un projet scaffoldé depuis le `.phar` n'était donc plus directement exécutable (`./bin/phpx` était interprété par le shell, pas PHP). Fixé en réinjectant le shebang après coup si absent.

**Ce que ça ne résout toujours pas**, honnêtement : `bin/phpx` reste conçu pour être co-localisé avec le projet sur lequel il opère — `PHPX_ROOT` (`bin/phpx:25`) vaut `dirname(__DIR__)`, utilisé à la fois comme "racine du gabarit à copier" (dans `cmdNew()`, où c'est correct même packagé en phar) et comme "racine du projet courant" dans **toutes les autres commandes** (`serve`, `bundle:android`, `payments`, `maps`, `make:page`...). Pour un vrai binaire global façon `flutter`/`composer` (installé une fois, appelé depuis n'importe quel dossier de projet), ces deux usages doivent se séparer : garder `PHPX_ROOT` pour les gabarits de `new`, et faire lire `getcwd()` à toutes les autres commandes. Ce refactor (~25 sites d'utilisation de `PHPX_ROOT` à trier un par un) n'a pas été fait cette session — identifié précisément mais pas exécuté, par prudence sur le rayon d'impact (touche `make:page`/`make:entity`/`serve`/`bundle:android`/`payments`/`maps`/`icon`/`firebase`, donc toute la suite `bin/test.sh`).

### 14. Aucun DevTools/inspecteur
Pas d'inspecteur d'arbre de widgets, pas de profiler de performance, pas d'inspecteur réseau dédié — l'équivalent de Flutter DevTools ou du panneau React Native Debugger n'existe pas. Le seul outil de debug est l'écran d'erreur PHP existant (`set_exception_handler`).

### 15. Zéro écosystème de packages tiers
Rien n'est publié sur Packagist sous un nom d'organisation dédié — chaque widget de ce projet a été écrit dans ce même dépôt monolithique. Flutter (pub.dev) et React Native (npm) ont des dizaines de milliers de packages communautaires. Sans écosystème, chaque développeur qui adopte PhpNitro doit tout réécrire lui-même.

### 16. Documentation incomplète face à un vrai framework
Le README est dense et honnête (bon point), mais il manque : une référence API générée automatiquement (type dartdoc/TypeDoc), des tutoriels pas-à-pas au-delà du README, une galerie d'exemples au-delà d'`examples/ecom`, un changelog versionné (aucune release taguée `v1.0.0` n'existe), des templates de contribution/issues.

### 17. Pas de couverture de tests widgets complète
`packages/ui/tests/WidgetsTest.php` ne teste qu'une partie des ~60 widgets/services existants — le reste n'est vérifié que visuellement sur `/widgets`, pas par un test automatisé qui échouerait en cas de régression.

### 18. Pas de mesure de performance réelle — **premiers chiffres réels obtenus, pas encore de comparatif Flutter/RN**
**Mise à jour** : premières mesures chiffrées sur le téléphone connecté (Infinix X6532, Android 14), via `adb` — pas des outils de profiling dédiés (Android Studio Profiler non disponible dans cet environnement), mais des chiffres réels, pas des estimations :
- **Taille de l'APK debug** : 11 383 295 octets (≈ 10,9 Mio), confirmée identique sur le téléphone (`pm path` + `ls`) et en local — non signé release, non minifié (`isMinifyEnabled = false`, voir #3), donc probablement réductible.
- **Démarrage à froid** (`adb shell am force-stop` puis `am start -W`, 4 mesures) : **1,3 à 2,0 s** (`TotalTime`), moyenne ≈ 1,58 s — couvre le lancement du binaire PHP embarqué, l'init de la WebView, et le premier rendu.
- **Démarrage à chaud** (processus déjà vivant, juste ramené au premier plan) : **232 ms** — le serveur PHP embarqué tourne déjà, seule la WebView reprend.
- **Mémoire (PSS)** : ≈ 146 Mo (`adb shell dumpsys meminfo`) une fois l'app chargée — dans la fourchette attendue pour une app basée WebView/Chromium (le moteur de rendu lui-même en consomme une bonne partie, indépendamment de PhpNitro), mais pas mesuré face à un équivalent Flutter/RN pour relativiser.
- Non mesurable ici : taille des données sur disque après premier lancement (`/data/data/...` refusé, device non rooté), FPS de scroll (pas d'outil de profiling GPU disponible).

Reste à faire pour que ce soit un vrai comparatif : mesurer une app Flutter/RN équivalente dans les mêmes conditions (même device, même méthode `adb`) pour avoir un point de comparaison réel plutôt que des chiffres isolés.

---

## Ce qui existe déjà et qu'il ne faut PAS refaire

Pour être honnête dans les deux sens : ces briques sont réelles, fonctionnelles, et vérifiées — ce n'est pas un château de cartes.
- Le runtime PHP embarqué sur Android **fonctionne réellement** sur un vrai device (biométrie native comprise).
- La navigation SPA (`nav.js`) avec barre de navigation persistante (corrigée cette session) est solide.
- ~20 widgets de layout/formulaire/affichage sont réels et testés.
- Les services device/paiement (refactorisés cette session en architecture "service", pas "widget imposé") sont une bonne décision d'architecture, alignée avec la façon dont Flutter/RN structurent leurs plugins.
- La CI existe et tourne réellement (PHPUnit + smoke test) à chaque push.
- Le backend en-process (Symfony HttpFoundation + Doctrine DBAL/SQLite) est un vrai choix d'architecture cohérent, pas un placeholder.
- `FadeIn`/`Curves` (widget d'animation d'entrée + courbes d'easing) et la transition de fondu sur `nav.js` sont réels et vérifiés (rendu + build Tailwind + device réel) — mais c'est un tout premier jalon sur l'item #4, pas une réponse à "zéro moteur d'animation".
- `android-build`/`phpstan` en CI (#6) : la commande `bundle:android` + `gradle :app:assembleDebug` a été rejouée pour de vrai cette session (SDK Android installé, Gradle téléchargé) et l'APK produit a été installé, lancé et vérifié sans crash sur le téléphone Infinix X6532 — au-delà du fichier CI lui-même, la chaîne de build qu'il automatise est confirmée fonctionnelle.
- `Stack`/`Positioned`/`Wrap` (#5) et la réutilisation de `.phpx-animate` par `stream.js`/`future.js` (#4) sont réels, vérifiés (PHPUnit + device réel) — premiers jalons, pas des réponses complètes aux deux items.
- **Hot reload** (`assets/js/dev-reload.js` + `/_dev/version`) — retiré de la liste des manques cette session : ce document affirmait à tort qu'aucun rafraîchissement automatique n'existait ; en réalité le mécanisme (poll + reload sur hash de mtime différent) fonctionnait déjà, testé pour de vrai. Seul un angle mort (`packages/*/src` non surveillé) a été corrigé — voir `public/index.php`.
- `box.json` + `phpx.phar` (#13) : packaging réel, vérifié de bout en bout (`phpx new` scaffoldé depuis le `.phar`, projet résultant qui répond HTTP 200) — deux bugs de permissions/shebang trouvés et corrigés au passage. Ne résout pas encore le vrai objectif de #13 (binaire global indépendant du répertoire courant) : voir le détail dans l'item lui-même.

---

## Par où commencer, si l'objectif est une v1 utilisable en production (pas la parité totale)

Dans l'ordre, ce qui débloque le plus de valeur réelle le plus vite :
1. Signer une vraie release Android (#3) — sans ça, aucune app construite avec ce framework n'est publiable.
2. Vérifier au moins 2-3 gateways de paiement contre un vrai sandbox (#7) — sans ça, aucun vrai commerce n'est possible.
3. Tester sur 3-4 devices Android différents (#12) — la confiance actuelle repose sur un seul appareil.
4. ~~Ajouter un job CI qui build l'APK (#6)~~ — fait cette session (`android-build` + `phpstan`), et la chaîne de build elle-même (`bundle:android` + `assembleDebug`) a été rejouée avec succès en dehors de CI (PHP 8.4 + device réels) ; seul le run GitHub Actions lui-même (image `ubuntu-latest` + `gradle/actions/setup-gradle`) reste à observer en conditions réelles (voir #6).
5. iOS et l'obfuscation du code (#1, #2) restent les deux chantiers les plus lourds — le pont natif iOS (#1) a une longueur d'avance (code écrit, capacité par capacité), mais le PHP embarqué et la compilation/tests sur un vrai Mac n'ont pas commencé. À traiter sérieusement une fois la base Android solide en production.
