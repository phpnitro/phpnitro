# Widgets

Chaque widget implémente `RenderNode` (`packages/ui/src/Native/RenderNode.php`) — `layout(Constraints): Size` puis `paint(NativeCanvas, x, y): void`, exactement comme un `RenderObject` Flutter. Toutes les classes vivent sous `Engine\Native\` (`packages/ui/src/Native/`). Voir [docs/architecture.md](architecture.md) pour le cycle complet.

`packages/ui/src` lui-même (hors `Native/`) ne garde que `Color` (palette Tailwind typée, réutilisée pour son `toHex()`) et `NativeDrawCommand` (le protocole `/native/demo` figé, Phase 0) — tout le reste a été supprimé une fois converti.

Un écran est une classe statique :

```php
final class MyScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Titre', backAction: 'back'),
        );
    }
}
```

dispatchée par nom depuis `public/index.php`'s `match ($screen) { 'monecran' => MyScreen::build(...), ... }`.

## Mise en page

| PHP | Rôle |
|---|---|
| `RenderFlex::row([$children], mainAxisAlignment:, crossAxisAlignment:)` / `::column(...)` | Flutter's `Row`/`Column` — même algorithme deux passes (enfants inflexibles d'abord, `Flexible` se partagent le reste) |
| `Flexible($child, flex: 1)` | enfant flexible dans un `RenderFlex`, comme `Flexible`/`Expanded` Flutter |
| `RenderWrap([$children], spacing:, runSpacing:)` | comme une `Row`, mais passe à la ligne au lieu de déborder |
| `RenderContainer($child, width:, height:, background:, radius:, borderColor:, borderWidth:, elevation:, gradientFrom:, gradientTo:, padding:)` | boîte à un seul enfant — fond, coins arrondis, bordure, ombre (élévation Material), dégradé |
| `RenderStack([$children])` | superpose les enfants ; un enfant `RenderPositioned` reçoit un offset explicite, les autres remplissent la zone |
| `RenderPositioned($child, top:, right:, bottom:, left:)` | positionnement absolu, seulement utile dans un `RenderStack` |
| `RenderCenter($child)` | centre l'enfant |
| `RenderAlign($child, Alignment::*)` | aligne l'enfant selon `Alignment::TOP_LEFT\|TOP_CENTER\|TOP_RIGHT\|CENTER_LEFT\|CENTER\|CENTER_RIGHT\|BOTTOM_LEFT\|BOTTOM_CENTER\|BOTTOM_RIGHT` |
| `RenderPadding(EdgeInsets, $child)` | espacement interne — `EdgeInsets::all($v)` / `::symmetric(horizontal:, vertical:)` / `::only(left:, top:, right:, bottom:)` |
| `RenderSizedBox($width, $height, $child = null)` | force une taille exacte, avec ou sans enfant |
| `NativeDivider()` | ligne de séparation |
| `NativeTable($rows, $headers = [])` | tableau ; `$rows`/cellules acceptent des `RenderNode` |
| `NativePageView([$pages], $currentIndex, $fieldName)` | pagination par tap (chevrons + points), état porté par un champ `$_GET` |
| `RenderLazyList($itemCount, $itemBuilder, $itemHeight, $scrollY, $viewportHeight)` | liste virtualisée — voir [docs/architecture.md#listes-longues-fenêtre-pas-tout](architecture.md) |

`RenderFlex::row/column` prennent `MainAxisAlignment::START\|CENTER\|END\|SPACE_BETWEEN\|SPACE_AROUND\|SPACE_EVENLY` et `CrossAxisAlignment::START\|CENTER\|END\|STRETCH`.

## Structure d'écran

| PHP | Rôle |
|---|---|
| `NativeScaffold($body, $screenWidth, $viewportHeight, appBar:, bottomNav:, fab:, drawer:)` | structure standard : réserve l'espace pour l'AppBar/BottomNav dans le corps défilable, peint le reste en overlay fixe |
| `NativeAppBar($screenWidth, $title, backAction:, leading:, background:)` | barre supérieure fixe |
| `NativeBottomNavigation($screenWidth, $items, $currentScreen, activeColor:)` | barre d'onglets fixe |
| `NativeFab($icon, $action, background:)` | bouton rond flottant |
| `NativeDrawer($screenWidth, $viewportHeight, $items, $title)` | menu latéral coulissant |
| `NativeCard($child, padding:, background:, borderColor:, borderWidth:, radius:, elevation:)` | conteneur avec padding par défaut + fond + coins arrondis |
| `NativeListTile($title, $subtitle, $leadingIcon, leadingColor:, trailingIcon:, trailingText:, action:, meta:)` | ligne icône + titre/sous-titre + traînée, la brique de base de la plupart des menus |

## Formulaires

| PHP | Rôle |
|---|---|
| `NativeTextField($name, $value, $placeholder, obscure:, multiline:)` | ouvre un vrai `EditText` overlay au tap (pas de saisie dessinée sur le Canvas) |
| `NativeSelectBox($name, $options, $selected)` | ouvre un vrai `AlertDialog.setItems()` |
| `NativeCheckbox($name, $label, $checked, accentColor:)` | case à cocher |
| `NativeSwitch($name, $label, $on, activeColor:)` | interrupteur on/off |
| `NativeDatePicker($name, $value)` | ouvre un vrai `DatePickerDialog` |
| `NativeTimePicker($name, $value)` | ouvre un vrai `TimePickerDialog` |
| `NativeBanner($message, $icon = 'warning', background:, foreground:)` | bandeau d'alerte/erreur — ne peint rien si `$message` est `null` |
| `NativeProgressBar($width, $percent, $height:, trackColor:, fillColor:)` | barre linéaire (0.0–1.0) |
| `NativeCircularProgress($percent, $size:, trackColor:, color:)` | indicateur circulaire (arc réel, `NativeCanvas::arc()`) |
| `NativeAlertButton($message, $title = 'Alerte')` | ouvre un vrai `AlertDialog` |
| `NativeConfirmButton($message, $action, $label)` | `AlertDialog` de confirmation ; n'appelle `$action` que depuis le callback OK |

Chaque champ lit/écrit sa valeur via `$_GET['nom-du-champ']` — pas de binding automatique, l'écran est responsable de relire ces valeurs au prochain rendu (voir n'importe quel `Native*Screen.php` pour le pattern).

## Texte riche

| PHP | Rôle |
|---|---|
| `RenderText($text, $fontSize, $color, bold:, letterSpacing:)` | un seul style, wrap automatique (mesure de caractère réelle, `TextMetrics`) |
| `RenderRichText([TextSpan, ...], $fontSize, $color)` | plusieurs styles dans UN paragraphe qui wrap ensemble — `TextSpan($text, color:, bold:, size:, letterSpacing:, action:)`, un `action` rend ce run précis tappable (lien inline) |
| `RenderIcon($name, $size, $color)` | glyphe Material Icons (2235 noms, `MaterialIcons::codepoint()`) |
| `RenderImage($url, $width, $height, radius:)` | bitmap chargé en arrière-plan (cache mémoire LRU), coins arrondis via `BitmapShader` |

```php
new RenderRichText([
    new TextSpan('PhpNitro rend en '),
    new TextSpan('natif', bold: true, color: Tokens::success()->toHex()),
    new TextSpan(', voir les '),
    new TextSpan('conditions', color: Tokens::inkSecondary()->toHex(), action: 'navigate:terms'),
    new TextSpan('.'),
], fontSize: Tokens::TEXT_BODY, color: Tokens::ink()->toHex());
```

## Animations

Deux mécanismes, décrits en détail dans [docs/architecture.md#animations](architecture.md) :

| PHP | Effet |
|---|---|
| *(rien à écrire)* | crossfade automatique entre deux rendus d'écran (`fadeProgress` côté Kotlin) |
| `RenderHero($child, $tag)` | FLIP réel entre deux écrans — même `$tag` des deux côtés d'une navigation |
| `RenderAnimated($child, $key)` | FLIP réel local — la même `$key` produisant un rectangle/couleur/rayon différent au rendu suivant anime au lieu de sauter, l'équivalent unifié de `AnimatedContainer`/`AnimatedPositioned`/`AnimatedOpacity` |

## Gestes

| PHP | Rôle |
|---|---|
| `RenderTappable($child, $action, $meta = null)` | enregistre une hit region — la brique derrière tous les widgets tappables |
| `NativeGestureDetector($child, onDoubleClick:, onSwipeLeft:, onSwipeRight:)` | double-tap/swipe rapide, détectés via `android.view.GestureDetector` côté Kotlin |
| `RenderDismissible($child, $key, $action)` | swipe-to-dismiss — le SEUL geste dont le suivi du doigt reste 100% côté client (`NativeCanvasView.checkScrollFollow`/drag), `$action` n'est appelé qu'au relâchement, une fois le seuil dépassé |
| `RenderFixed($child)` | épingle un sous-arbre au viewport (ne défile pas avec le corps) — ce que `NativeScaffold` utilise pour l'AppBar/BottomNav |

```php
new RenderDismissible(
    new NativeListTile($label, 'Glisse pour supprimer', 'task_alt'),
    "item-{$id}",
    "dismiss:{$id}",
);
// Côté public/index.php :
if ($action !== null && str_starts_with($action, 'dismiss:')) {
    // retirer l'item — PHP ne voit jamais le geste, seulement le résultat
}
```

## Dessin bas niveau

| PHP | Rôle |
|---|---|
| `RenderCustomPaint::make($width, $height)->rect(...)->circle(...)->line(...)->text(...)` | primitives dessinées une fois au montage — ce qu'`Engine\Canvas` demandait côté HTML |
| `NativeCanvas::rect/circle/line/arc/text/icon/image()` | l'API bas niveau que tous les widgets ci-dessus appellent en interne — rarement appelée directement |

## Tokens (design system)

`Engine\Native\Tokens` centralise espacement/rayons/tailles de texte/couleurs sémantiques — préfère toujours ça à une valeur en dur :

```php
Tokens::SPACE_XS|SM|MD|LG|XL|XXL      // 4/8/12/16/20/28
Tokens::RADIUS_SM|MD|LG|PILL          // 10/14/18/999
Tokens::TEXT_DISPLAY|TITLE|BODY|BODY_SMALL|CAPTION
Tokens::ink()|inkSecondary()|inkMuted()|surface()|surfaceMuted()|border()|success()|danger()  // -> Color
```

`Engine\Color` reste la palette Tailwind typée (`Color::blue(600)`, `Color::of('emerald', 600)`) — son `toHex()` est ce que tout widget natif consomme réellement, pas de classe CSS.

## Ce qui reste WebView-only

Rien, actuellement — la dernière page WebView (`WidgetsLayoutPage.php`) a été supprimée une fois `RenderHero` construit. Un futur widget qui aurait vraiment besoin d'un moteur HTML (rendu Markdown riche, iframe...) redeviendrait candidat à une page WebView ponctuelle, ouverte via `NativeDeviceBridge.kt::openWebView()`.
