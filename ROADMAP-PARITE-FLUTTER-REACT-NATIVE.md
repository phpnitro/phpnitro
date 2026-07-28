# Feuille de route — ce qui manque face à Flutter / React Native

Uniquement ce qui reste à faire. Pas d'historique de vérification ici (voir CHANGELOG.md pour ça).

---

## Niveau 1 — Bloquant

1. **iOS n'existe pas réellement** — pont natif Swift écrit (capacité par capacité, mêmes noms de méthodes que côté Android) mais rien n'est compilé ni testé. Reste : cross-compiler php-src en SAPI embed pour `arm64-apple-ios`, écrire le pont C/Objective-C, implémenter `PhpEmbedBridge.swift`, compiler sur un vrai Xcode, tester sur un vrai iPhone. Aucun Mac disponible.
2. **Code PHP lisible en clair dans l'APK** — minifié (commentaires/docblocks retirés) mais pas obfusqué : pas de renommage d'identifiants, pas d'encodage de chaînes. Un APK décompressé expose tout le code métier.
3. **Pipeline de publication absent** — signing Android + R8 faits, mais aucune fiche Play Store, politique de confidentialité, captures d'écran. Rien côté iOS (compte Developer, certificats, provisioning, App Store Connect).
4. **Moteur d'animation minimal — `AnimatedContainer` et `Hero` ajoutés (FLIP), pas encore reverifiés sur device** — `Engine\AnimatedContainer` (tween entre deux rendus serveur : background-color/width/height/border-radius/padding/opacity) et `Engine\Hero` (élément partagé qui vole d'une position/taille à l'autre) utilisent la technique FLIP sur un nouvel évènement `phpx:beforeSwap` (nav.js) — pas une vraie reactivité/diffing (toujours aucune, voir FadeIn), mais un vrai tween visuel entre deux renders serveur, contrairement à `FadeIn` (animation d'entrée uniquement). 258 tests (PHPUnit), démo sur `/widgets/layout`, respecte `prefers-reduced-motion`. **Pas encore revérifié sur device réel** (déconnecté en cours de session) — seulement vérifié en local (tests + lecture du rendu HTML). Manque toujours : animations de sortie (exit), animations liées au geste (drag-to-dismiss).
5. **Pas de moteur de layout avancé** — `Canvas` ne fait qu'un dessin unique au montage (pas de redraw par frame, pas de shaders). Pas de `ConstrainedBox`/`IntrinsicWidth`. Tout reste du flexbox/CSS direct.
6. **Pas de tests E2E automatisés** — aucun test scripté sur émulateur/device (équivalent `integration_test`/Detox/Maestro). Pas de non-régression visuelle (screenshot diffing). La CI build l'APK mais ne le fait tourner nulle part.

## Niveau 2 — Important

7. ~~**Paiements : Feexpay**~~ — **entièrement vérifié en conditions réelles, transaction réelle confirmée de bout en bout.** Bug de fond trouvé et corrigé au passage : le PHP embarqué sur Android n'avait **ni `curl` ni `openssl`** (confirmé en exécutant le binaire directement sur device) — aucun appel HTTPS sortant n'a jamais fonctionné depuis ce binaire, pas seulement pour Feexpay. Corrigé (OpenSSL cross-compilé statiquement, `cacert.pem` embarqué — voir `android/README.md`), `Engine\Payments\Feexpay` réécrit pour utiliser des streams plutôt que le SDK vendeur (qui appelle `curl_*` directement), plus un bug annexe (Feexpay rejette un `amount` non entier). **Le parcours complet a tourné pour de vrai** (accord explicite du propriétaire du compte, sa propre clé API et son propre numéro mobile money) : panier → checkout → push USSD reçu sur le téléphone → confirmé par l'utilisateur → `Feexpay::status()` a bien détecté le succès → commande créée → page "Merci, Hb ! Commande #1 — 153,90 € — Statut : confirmée" affichée. Un vrai SMS MTN MoMo a confirmé le débit réel. Paiements restants toujours pas vérifiés :
   - Cette même correction HTTPS lève potentiellement le même blocage pour Stripe/OAuth/Firebase (jamais vérifiés sur device faute de HTTPS fonctionnel) — pas encore retesté individuellement pour ces trois-là.
   - iZiChangePay/TresorPay jamais vérifiés (gabarits avec TODO). Kkiapay/FedaPay jamais exercés contre un sandbox réel. Pas d'Apple Pay/Google Pay natifs, pas de remboursements, pas de webhooks asynchrones.
8. ~~**Capacités device manquantes**~~ — fait, et vérifié sur l'Infinix X6532 : NFC (mode écoute activé, "En attente d'un tag NFC..." affiché, aucun tag physique disponible pour tester une vraie lecture), InAppPurchase (connexion Play Billing réelle établie, requête produit exécutée, "Aucun produit trouvé" — honnête, `demo_product` n'existe pas dans un vrai Play Console), Geofence (zone ajoutée sans erreur ni crash, permissions fine+background location accordées). Pincer-zoomer/rotation sur `GestureDetector` : couvert par test unitaire uniquement — `adb input` ne simule pas le multi-touch, jamais confirmé à l'écran avec de vrais doigts. App Links HTTPS toujours pas vérifiable (domaine placeholder, voir `docs/mobile-builds.md`).
9. **Notifications push non vérifiées** — jamais testées contre un vrai projet Firebase/APNs.
10. **API de style typée partielle — 7 widgets de plus** — `Container`/`Button`/`Theme`/`FloatingActionButton`/`ProgressBar`/`BottomNavigation`/`Checkbox`/`SwitchToggle`, plus maintenant `AppBar` (background), `Dropdown` (background/foreground), `LocationButton` (background/foreground), `IconButton` (foreground), `Divider` (color), `Stepper` (activeColor, suit `Theme::primary()` par défaut comme les autres). `ProgressBar::$barColor` était en fait une chaîne brute malgré la mention "typé" précédente — corrigé en vrai `?Color`. 268 tests (PHPUnit), pas encore revérifié visuellement sur device (déconnecté en session). Column/Row/Wrap/Stack/Padding/Margin restent délibérément sans background/rounded typés — `Container` existe déjà pour ce rôle, l'ajouter ailleurs dupliquerait sans clarifier. Les ~40 autres widgets restants (formulaires, media, structurels) utilisent encore des chaînes Tailwind brutes.
11. **Accessibilité peu auditée** — seul l'écran d'accueil vérifié avec TalkBack. Formulaires, dialogues, Stepper, cartes non audités. VoiceOver (iOS) inexistant. Aucune méthode répétable/CI.
12. **Un seul device réel testé** — aucune couverture multi-version Android, tablettes, fabricants avec WebView personnalisées (Samsung, Xiaomi).

## Niveau 3 — Confort développeur / écosystème

13. **Pas de binaire global distribué** — `phpx.phar` fonctionne mais n'est publié nulle part (pas de formule Homebrew/apt).
14. **DevTools basique** — lecteur route/temps/mémoire/état seulement. Pas d'inspecteur d'arbre de widgets, pas de profiler, pas d'inspecteur réseau.
15. **Zéro écosystème tiers** — packages internes au monorepo uniquement, rien publié sur Packagist.
16. ~~**Documentation incomplète**~~ — fait : `phpx docs:api` génère `docs/api/*.md` (137 classes, 14 packages) depuis les docblocks/signatures réels via Reflection, jamais à éditer à la main. Deux tutoriels pas-à-pas ajoutés (`docs/tutorials/` : construire une fonctionnalité complète, brancher une vraie authentification sociale). Templates GitHub (`bug_report.md`, `feature_request.md`, `PULL_REQUEST_TEMPLATE.md`) ajoutés. Reste : pas de galerie d'exemples au-delà d'`examples/ecom`.
17. **Couverture de tests widgets incomplète** — largement comblée : ~30 widgets UI (Align, Center, CircularProgress, Table, GestureDetector, StreamBuilder/FutureBuilder...), Navigation/Navigator, tous les nouveaux `Engine\Device\*`, et PaypalButton ont maintenant des tests (185 → 253). Restent délibérément non testés (documenté inline, pas un oubli) : `PageRenderer` (`exit()`), `StripeCheckout`/`Feexpay` (vrais appels HTTP).
18. **Pas de comparatif de performance réel** — chiffres PhpNitro isolés (taille APK, démarrage, mémoire), jamais mesurés face à une app Flutter/RN équivalente dans les mêmes conditions.
19. ~~**SDK Android non auto-installé**~~ — fait : `resolveOrInstallAndroidSdk()` télécharge les cmdline-tools et installe platform-tools/platform/build-tools (versions lues depuis `build.gradle.kts`) si aucun SDK n'est trouvé. Écrit, non encore vérifié par une vraie installation from scratch (le SDK est déjà présent sur cette machine — voir #12).

---

## Kivy — écarts spécifiques

- Aucun support desktop (Windows/macOS/Linux) — pas même un stub. Chantier à part entière (GTK+WebKit / Cocoa+WKWebView / WebView2), pas traité.
- Pas de dessin bas niveau réel (canvas OpenGL ES, shaders GLSL) — encore plus loin que `Engine\Canvas` (#5), qui reste un dessin unique au montage sans shaders.
- Framework multitouch dédié — désormais couvert au niveau basique par le pincer-zoomer/rotation ajoutés en #8 ; toujours pas un framework de gestes dédié comme celui de Kivy.
- Écosystème communautaire type Kivy Garden : zéro (#15) — nécessite de vrais contributeurs externes, pas quelque chose qu'une session de code peut produire.

---

## Priorité si l'objectif est une v1 production (pas la parité totale)

1. ~~Vérifier Feexpay contre un vrai compte (#7)~~ — fait, transaction réelle confirmée de bout en bout. Reste à vérifier 1-2 gateways de plus (Kkiapay/FedaPay en priorité, sandbox déjà disponible) — toujours le blocage principal pour un vrai commerce multi-gateway.
2. Tester sur 3-4 devices Android différents (#12) — confiance actuelle toujours sur un seul appareil. Reste aussi : confirmer pincer-zoomer/rotation avec de vrais doigts (adb ne simule pas le multi-touch), tester une vraie installation SDK from scratch (#19), et vérifier un vrai App Link HTTPS une fois un domaine disponible (#8).
3. Publier la release sur le Play Store (#3).
4. iOS et obfuscation (#1, #2) — chantiers les plus lourds, nécessitent un Mac.
