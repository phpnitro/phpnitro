# Feuille de route — ce qui manque face à Flutter / React Native

Réécrite après la bascule complète vers le moteur de rendu natif (PHP layout/paint -> commandes JSON -> vrai `android.graphics.Canvas`, zéro WebView/HTML/CSS dans le pipeline de rendu). L'ancienne version de ce document décrivait un framework WebView+Tailwind qui n'existe plus — `packages/ui/src` ne garde que `Color`/`NativeDrawCommand`, tout le reste vit sous `Native/`. Voir [docs/architecture.md](docs/architecture.md) pour le fonctionnement actuel.

---

## Niveau 1 — Bloquant pour rivaliser vraiment avec Flutter

1. **Le modèle d'exécution reste HTTP-par-interaction, pas un arbre en mémoire.** Chaque tap refait tout le cycle layout+paint côté PHP et rejoue les commandes côté Kotlin — rapide (quelques ms de `renderTimeMs`), mais ce n'est pas du diffing d'arbre virtuel ni du hot-reload avec état préservé. `RenderDismissible` (glisser) est la seule interaction qui échappe déjà à ce modèle (état 100% côté client pendant le geste, sync uniquement au relâchement) — le pattern à généraliser pour drag-to-reorder, pinch-to-zoom, etc.
2. **iOS n'existe pas réellement** — pont natif Swift jamais compilé ni testé, et il ciblait l'ancienne architecture WebView. Reste : cross-compiler php-src pour `arm64-apple-ios`, écrire l'équivalent Swift de `NativeCanvasView.kt`/`NativeRenderPocActivity.kt` (un vrai `CALayer`/`UIView` qui rejoue les mêmes commandes JSON), compiler sur un vrai Xcode. Aucun Mac disponible actuellement.
3. **Pas de tests E2E automatisés** — aucun test scripté sur émulateur/device (équivalent `integration_test`/Detox/Maestro), pas de non-régression visuelle. La vérification actuelle est `php -l` + PHPUnit + curl sur `/native/layout-demo` + un vrai `gradle assembleDebug` — solide pour attraper les régressions de compilation/logique, aveugle aux régressions purement visuelles.
4. **Pipeline de publication absent** — signing Android + R8 faits, mais aucune fiche Play Store, politique de confidentialité, captures d'écran. Rien côté iOS.

## Niveau 2 — Important pour l'usage quotidien

5. **Scroll imbriqué indépendant** — `RenderLazyList` virtualise une liste plein écran, mais il n'existe pas encore de scrollable imbriqué dans un autre (ex. un carrousel horizontal DANS une liste verticale, façon `CustomScrollView`/`NestedScrollView`).
6. **Pas de shaders/dessin par frame** — `RenderCustomPaint` dessine une fois au montage, pas de boucle de rendu à 60fps pilotée par l'app elle-même (pas de jeu, pas de visualisation animée en continu). Cohérent avec le modèle HTTP-par-interaction (#1) : une vraie boucle de rendu continue nécessiterait de sortir de ce modèle pour ce cas d'usage précis.
7. **Accessibilité non implémentée** — aucun arbre sémantique/TalkBack pour le rendu Canvas (contrairement à l'ancien pipeline HTML qui avait au moins `aria-label`). `NativeCanvasView.kt` dessine des pixels, pas des vues Android individuelles — un lecteur d'écran n'a rien à annoncer sans un travail dédié (probablement un `AccessibilityNodeProvider` custom reflétant les `hitRegions`).
8. **Reorderable list / drag-to-reorder** — pas encore construit, mais `RenderDismissible` (#1) est exactement le bon patron à copier : état de drag 100% Kotlin, sync PHP au relâchement seulement.
9. **Un seul device réel testé** régulièrement — pas de couverture multi-version Android, tablettes, fabricants.
10. **Capacités device non vérifiées en conditions réelles** — NFC (lecture jamais confirmée avec un vrai tag), InAppPurchase (jamais contre un vrai produit Play Console), notifications push (jamais contre un vrai projet Firebase/APNs).

## Niveau 3 — Confort développeur / écosystème

11. **DevTools inexistant pour le pipeline natif** — l'ancien panneau (route/temps/mémoire/état) était injecté dans la page HTML, supprimé avec Tailwind. Pas d'inspecteur d'arbre `RenderNode`, pas de profiler visuel — `renderTimeMs` dans chaque réponse JSON et les logs Logcat (`NativeCanvasView`/`NativeRenderPoc`) sont l'outillage actuel.
12. **Pas de hot-reload avec état préservé** — `/_dev/version` (watch de fichiers) déclenche un re-fetch complet à la prochaine interaction, pas un patch en place façon Flutter.
13. **Zéro écosystème tiers** — packages internes au monorepo uniquement, rien publié sur Packagist.
14. **Documentation à jour mais pas générée** — `docs/architecture.md`/`widgets.md`/`device-and-native.md` réécrits pour refléter le moteur natif, mais à la main (l'ancien `phpx docs:api` générait depuis les docblocks d'un framework qui n'existe plus — à réévaluer si un générateur redevient utile pour `Native/`).
15. **Paiements/auth sociale/maps/dialogs supprimés, pas reconstruits en natif** — `packages/payments`, `packages/socialauth`, `packages/launcher`, `packages/diagnostics` (CrashReporter) étaient du code WebView-JS-bridge mort (zéro appelant après la bascule native) et ont été supprimés plutôt que réécrits. `packages/maps`/`packages/dialogs` ont un vrai remplaçant natif complet (`NativeMapView`/osmdroid, `NativeAlertButton`/`NativeConfirmButton`) ; les trois autres sont un vrai chantier futur (SDK natif par gateway/provider), pas une résurrection de l'ancien code.

---

## Ce qui EST fait, pour référence (ne pas re-vérifier)

Moteur de layout à contraintes complet (Flutter's `BoxConstraints`), ~50 widgets natifs (`packages/ui/src/Native/`), texte riche multi-styles avec wrap réel (`RenderRichText`), système d'animation implicite unifié (`RenderHero`/`RenderAnimated`, interpolation couleur+géométrie par commande), liste virtualisée avec fenêtre de préchargement (`RenderLazyList`), un geste continu réel (`RenderDismissible`), impression PDF native (`PrintManager`), ~30 capacités device natives (`NativeDeviceBridge.kt`), traduction sur l'appareil (ML Kit), cartes OpenStreetMap réelles (osmdroid). Voir [docs/widgets.md](docs/widgets.md) et [docs/device-and-native.md](docs/device-and-native.md) pour le détail.

## Priorité si l'objectif est une v1 production (pas la parité totale)

1. Généraliser le patron `RenderDismissible` à drag-to-reorder (#8) — c'est le geste manquant le plus demandé dans une vraie app à listes.
2. Un vrai accessibility tree pour le Canvas (#7) — actuellement zéro support lecteur d'écran, un vrai bloquant pour publier sérieusement.
3. Tests E2E même basiques sur émulateur (#3) — la confiance actuelle repose sur curl + lecture de JSON, pas sur ce que l'utilisateur voit réellement.
4. iOS (#2) — le chantier le plus lourd, nécessite un Mac, à traiter en dernier.
