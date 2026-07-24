# Feuille de route — ce qui manque face à Flutter / React Native

Uniquement ce qui reste à faire. Pas d'historique de vérification ici (voir CHANGELOG.md pour ça).

---

## Niveau 1 — Bloquant

1. **iOS n'existe pas réellement** — pont natif Swift écrit (capacité par capacité, mêmes noms de méthodes que côté Android) mais rien n'est compilé ni testé. Reste : cross-compiler php-src en SAPI embed pour `arm64-apple-ios`, écrire le pont C/Objective-C, implémenter `PhpEmbedBridge.swift`, compiler sur un vrai Xcode, tester sur un vrai iPhone. Aucun Mac disponible.
2. **Code PHP lisible en clair dans l'APK** — minifié (commentaires/docblocks retirés) mais pas obfusqué : pas de renommage d'identifiants, pas d'encodage de chaînes. Un APK décompressé expose tout le code métier.
3. **Pipeline de publication absent** — signing Android + R8 faits, mais aucune fiche Play Store, politique de confidentialité, captures d'écran. Rien côté iOS (compte Developer, certificats, provisioning, App Store Connect).
4. **Moteur d'animation minimal** — seulement une animation d'entrée (`FadeIn`) et une transition de page en fondu. Manque : `AnimatedContainer` (tween entre deux valeurs), `Hero`/shared element transition, animations de sortie, animations liées au geste (drag-to-dismiss).
5. **Pas de moteur de layout avancé** — `Canvas` ne fait qu'un dessin unique au montage (pas de redraw par frame, pas de shaders). Pas de `ConstrainedBox`/`IntrinsicWidth`. Tout reste du flexbox/CSS direct.
6. **Pas de tests E2E automatisés** — aucun test scripté sur émulateur/device (équivalent `integration_test`/Detox/Maestro). Pas de non-régression visuelle (screenshot diffing). La CI build l'APK mais ne le fait tourner nulle part.

## Niveau 2 — Important

7. **Paiements non vérifiés** — Feexpay partiellement testé sur device réel (Infinix X6532, `examples/ecom` installée séparément, `com.mobile.ecom`) : panier → checkout → formulaire Feexpay (réseau + numéro) rendus et fonctionnels, soumission avec numéro vide échoue proprement ("Le paiement Feexpay n'a pas pu être initié — vérifie le numéro et réessaie", pas de crash, pas de blocage). Un vrai envoi avec un numéro mobile money valide reste **volontairement pas retenté** : `paiementLocal()` avait déjà bloqué >20s sans réponse lors d'un essai précédent, et taper un numéro plausible au hasard risquerait de déclencher un vrai push USSD vers une personne réelle. iZiChangePay/TresorPay jamais vérifiés (gabarits avec TODO). Kkiapay/FedaPay jamais exercés contre un sandbox réel. Pas d'Apple Pay/Google Pay natifs, pas de remboursements, pas de webhooks asynchrones.
8. ~~**Capacités device manquantes**~~ — fait, et vérifié sur l'Infinix X6532 : NFC (mode écoute activé, "En attente d'un tag NFC..." affiché, aucun tag physique disponible pour tester une vraie lecture), InAppPurchase (connexion Play Billing réelle établie, requête produit exécutée, "Aucun produit trouvé" — honnête, `demo_product` n'existe pas dans un vrai Play Console), Geofence (zone ajoutée sans erreur ni crash, permissions fine+background location accordées). Pincer-zoomer/rotation sur `GestureDetector` : couvert par test unitaire uniquement — `adb input` ne simule pas le multi-touch, jamais confirmé à l'écran avec de vrais doigts. App Links HTTPS toujours pas vérifiable (domaine placeholder, voir `docs/mobile-builds.md`).
9. **Notifications push non vérifiées** — jamais testées contre un vrai projet Firebase/APNs.
10. **API de style typée très partielle** — `Container`/`Button` + maintenant `Theme` (registre injectable primary/secondary), `FloatingActionButton`, `ProgressBar`, `BottomNavigation`, `Checkbox`, `SwitchToggle`. Les ~50 autres widgets utilisent encore des chaînes Tailwind brutes.
11. **Accessibilité peu auditée** — seul l'écran d'accueil vérifié avec TalkBack. Formulaires, dialogues, Stepper, cartes non audités. VoiceOver (iOS) inexistant. Aucune méthode répétable/CI.
12. **Un seul device réel testé** — aucune couverture multi-version Android, tablettes, fabricants avec WebView personnalisées (Samsung, Xiaomi).

## Niveau 3 — Confort développeur / écosystème

13. **Pas de binaire global distribué** — `phpx.phar` fonctionne mais n'est publié nulle part (pas de formule Homebrew/apt).
14. **DevTools basique** — lecteur route/temps/mémoire/état seulement. Pas d'inspecteur d'arbre de widgets, pas de profiler, pas d'inspecteur réseau.
15. **Zéro écosystème tiers** — packages internes au monorepo uniquement, rien publié sur Packagist.
16. **Documentation incomplète** — pas de référence API générée, pas de tutoriels au-delà de `docs/`, pas de galerie d'exemples au-delà d'`examples/ecom`, pas de templates GitHub issues/PR.
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

1. Vérifier 2-3 gateways de paiement contre un vrai sandbox (#7) — blocage n°1 pour un vrai commerce.
2. Tester sur 3-4 devices Android différents (#12) — confiance actuelle toujours sur un seul appareil. Reste aussi : confirmer pincer-zoomer/rotation avec de vrais doigts (adb ne simule pas le multi-touch), tester une vraie installation SDK from scratch (#19), et vérifier un vrai App Link HTTPS une fois un domaine disponible (#8).
3. Publier la release sur le Play Store (#3).
4. iOS et obfuscation (#1, #2) — chantiers les plus lourds, nécessitent un Mac.
