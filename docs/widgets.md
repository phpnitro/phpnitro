# Widgets

Chaque widget implémente `Widget` (`packages/ui/src/Native/Widget.php`) — `layout(Constraints): Size` puis `paint(Canvas, x, y): void`, exactement comme un `RenderObject` Flutter. Toutes les classes vivent sous `Engine\Native\` (`packages/ui/src/Native/`). Voir [docs/architecture.md](architecture.md) pour le cycle complet.

`packages/ui/src` lui-même (hors `Native/`) ne garde que `Color` (palette Tailwind typée, réutilisée pour son `toHex()`) et `NativeDrawCommand` (le protocole `/native/demo` figé, Phase 0) — tout le reste a été supprimé une fois converti.

Un écran est une classe statique :

```php
final class MyScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Titre', backAction: 'back'),
        );
    }
}
```

dispatchée par nom depuis `public/index.php`'s `match ($screen) { 'monecran' => MyScreen::build(...), ... }`.

## Mise en page

| PHP | Rôle |
|---|---|
| `Flex::row([$children], mainAxisAlignment:, crossAxisAlignment:)` / `::column(...)` | Flutter's `Row`/`Column` — même algorithme deux passes (enfants inflexibles d'abord, `Flexible` se partagent le reste) |
| `Flexible($child, flex: 1)` | enfant flexible dans un `Flex`, comme `Flexible`/`Expanded` Flutter |
| `Wrap([$children], spacing:, runSpacing:)` | comme une `Row`, mais passe à la ligne au lieu de déborder |
| `Container($child, width:, height:, background:, radius:, borderColor:, borderWidth:, elevation:, gradientFrom:, gradientTo:, padding:)` | boîte à un seul enfant — fond, coins arrondis, bordure, ombre (élévation Material), dégradé |
| `Stack([$children])` | superpose les enfants ; un enfant `Positioned` reçoit un offset explicite, les autres remplissent la zone |
| `Positioned($child, top:, right:, bottom:, left:)` | positionnement absolu, seulement utile dans un `Stack` |
| `Center($child)` | centre l'enfant |
| `Align($child, Alignment::*)` | aligne l'enfant selon `Alignment::TOP_LEFT\|TOP_CENTER\|TOP_RIGHT\|CENTER_LEFT\|CENTER\|CENTER_RIGHT\|BOTTOM_LEFT\|BOTTOM_CENTER\|BOTTOM_RIGHT` |
| `Padding(EdgeInsets, $child)` | espacement interne — `EdgeInsets::all($v)` / `::symmetric(horizontal:, vertical:)` / `::only(left:, top:, right:, bottom:)` |
| `SizedBox($width, $height, $child = null)` | force une taille exacte, avec ou sans enfant |
| `Divider()` | ligne de séparation |
| `Table($rows, $headers = [])` | tableau ; `$rows`/cellules acceptent des `Widget` |
| `PageView([$pages], $currentIndex, $fieldName)` | pagination par tap (chevrons + points), état porté par un champ `$_GET` |
| `PageIndicator($count, $currentIndex, $dotSize:)` | la rangée de points que `PageView` utilise déjà en interne — pilotable seul pour un carrousel géré autrement |
| `LazyList($itemCount, $itemBuilder, $itemHeight, $scrollY, $viewportHeight)` | liste virtualisée — voir [docs/architecture.md#listes-longues-fenêtre-pas-tout](architecture.md) |
| `Grid($itemCount, $itemBuilder, $columns, $itemHeight, $scrollY, $viewportHeight, spacing:, bufferViewports:)` | même fenêtre virtualisée que `LazyList`, mais en grille à `$columns` colonnes fixes |
| `HorizontalScroll($key, [$children], gap:)` | carrousel horizontal DANS un écran qui défile déjà verticalement — le glisser latéral est capturé 100% côté client (comme `Dismissible`), aucune requête PHP pendant le geste. Non virtualisé (tous les enfants sont posés d'un coup) — pour un rail borné, pas une longue liste ; imbriquer un second scroll indépendant à l'intérieur n'est pas supporté. |
| `NestedScroll($key, $child, $viewportHeight)` | un second scroll vertical, indépendant de celui de l'écran, à l'intérieur d'un écran qui défile déjà — même idée que `HorizontalScroll` mais verticale |

`Flex::row/column` prennent `MainAxisAlignment::START\|CENTER\|END\|SPACE_BETWEEN\|SPACE_AROUND\|SPACE_EVENLY` et `CrossAxisAlignment::START\|CENTER\|END\|STRETCH`.

## Structure d'écran

| PHP | Rôle |
|---|---|
| `Scaffold($body, $screenWidth, $viewportHeight, appBar:, bottomNav:, fab:, drawer:)` | structure standard : réserve l'espace pour l'AppBar/BottomNav dans le corps défilable, peint le reste en overlay fixe |
| `AppBar($screenWidth, $title, backAction:, leading:, background:)` | barre supérieure fixe |
| `BottomNavigation($screenWidth, $items, $currentScreen, activeColor:)` | barre d'onglets fixe |
| `Fab($icon, $action, background:)` | bouton rond flottant |
| `Drawer($screenWidth, $viewportHeight, $items, $title)` | menu latéral coulissant |
| `Card($child, padding:, background:, borderColor:, borderWidth:, radius:, elevation:)` | conteneur avec padding par défaut + fond + coins arrondis |
| `ListTile($title, $subtitle, $leadingIcon, leadingColor:, trailingIcon:, trailingText:, action:, meta:)` | ligne icône + titre/sous-titre + traînée, la brique de base de la plupart des menus |

## Décoration & petits composants

| PHP | Rôle |
|---|---|
| `Badge($count = null, background:, max: 99)` | pastille — un simple point plein si `$count` est `null`, un nombre sinon (`99+` au-delà de `$max`) |
| `Chip($label, $selected:, $onTap:, $onDismiss:, accentColor:)` | étiquette filtrable et/ou fermable (croix si `$onDismiss` est fourni) |
| `IconCircle($icon, $diameter, background:, iconColor:, action:, meta:)` | icône dans un disque, tappable si `$action` est fourni (ex. les boutons +/− de `NumberPicker`) |
| `ImageCircle($url, $diameter)` | `Image` découpée en cercle (avatar) |
| `DottedBorder($child, $color:, $strokeWidth:, $dashLength:, $gapLength:)` | bordure en tirets autour de n'importe quel enfant |

## Formulaires

| PHP | Rôle |
|---|---|
| `Button($label, $action, $icon:, $width:, $height: 54.0, background:, foreground:, meta:)` | bouton plein, icône optionnelle |
| `TextField($name, $value, $placeholder, obscure:, multiline:)` | ouvre un vrai `EditText` overlay au tap (pas de saisie dessinée sur le Canvas) |
| `PasswordField($name, $value, $placeholder, width:, height:, error:)` | `TextField` avec un bouton œil pour révéler/masquer la saisie |
| `PinCodeField($name, $value, $length: 4, $boxSize:, error:)` | saisie de code, une case par caractère |
| `SelectBox($name, $options, $selected, $placeholder = 'Choisir...', $height:)` | ouvre un vrai `AlertDialog.setItems()` |
| `RadioGroup($name, $options, $selected, accentColor:, size:)` | choix unique parmi plusieurs options, un round-trip par sélection |
| `Checkbox($name, $label, $checked, accentColor:)` | case à cocher |
| `Toggle($name, $label, $on, activeColor:)` | interrupteur on/off |
| `Slider($name, $value, width:, trackColor:, activeColor:, thumbColor:, trackHeight:, thumbSize:)` | curseur continu 0.0–1.0 — le drag reste 100% côté client (comme `Dismissible`), seule la valeur au relâchement fait un round-trip |
| `NumberPicker($name, $value, $min: 0, $max: 100, $step: 1)` | +/− bornés, construit sur deux `IconCircle` |
| `DatePicker($name, $value)` | ouvre un vrai `DatePickerDialog` |
| `TimePicker($name, $value)` | ouvre un vrai `TimePickerDialog` |
| `CountryPicker($name, $selected, $placeholder, $french:)` | `SelectBox` pré-rempli avec la liste des pays (drapeau + nom) |
| `CountryCodePicker($name, $selected, $width:)` | même liste que `CountryPicker`, affiche l'indicatif téléphonique (`🇫🇷 +33`) |
| `IntlPhoneNumberInput($countryFieldName, $phoneFieldName, $selectedCountry, $phoneValue, $placeholder, error:)` | `CountryCodePicker` + `TextField` assemblés côte à côte |
| `EmojiPicker($name, $emoji:, $columns: 8, $cellSize:, $scrollY:, $viewportHeight:)` | grille d'emoji tappables (construite sur `Grid`) |
| `Banner($message, $icon = 'warning', background:, foreground:)` | bandeau d'alerte/erreur — ne peint rien si `$message` est `null` |
| `ProgressBar($width, $percent, $height:, trackColor:, fillColor:)` | barre linéaire (0.0–1.0) |
| `CircularProgress($percent, $size:, trackColor:, color:)` | indicateur circulaire (arc réel, `Canvas::arc()`) |
| `AlertButton($message, $title = 'Alerte')` | ouvre un vrai `AlertDialog` |
| `ConfirmButton($message, $action, $label)` | `AlertDialog` de confirmation ; n'appelle `$action` que depuis le callback OK |

Chaque champ lit/écrit sa valeur via `$_GET['nom-du-champ']` — pas de binding automatique, l'écran est responsable de relire ces valeurs au prochain rendu (voir n'importe quel `Native*Screen.php` pour le pattern).

## Texte riche

| PHP | Rôle |
|---|---|
| `Text($text, $fontSize, $color, bold:, letterSpacing:)` | un seul style, wrap automatique (mesure de caractère réelle, `TextMetrics`) |
| `RichText([TextSpan, ...], $fontSize, $color)` | plusieurs styles dans UN paragraphe qui wrap ensemble — `TextSpan($text, color:, bold:, size:, letterSpacing:, action:)`, un `action` rend ce run précis tappable (lien inline) |
| `Icon($name, $size, $color)` | glyphe Material Icons (2235 noms, `MaterialIcons::codepoint()`) |
| `Image($url, $width, $height, radius:)` | bitmap chargé en arrière-plan (cache mémoire LRU), coins arrondis via `BitmapShader` |
| `GoogleFontText($text, $fontFamily, $fontSize:, $color:, $bold:, $width:)` | comme `Text`, mais avec une police Google Fonts téléchargée et mise en cache au lieu de la police système |

```php
new RichText([
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
| `Hero($child, $tag)` | FLIP réel entre deux écrans — même `$tag` des deux côtés d'une navigation |
| `Animated($child, $key)` | FLIP réel local — la même `$key` produisant un rectangle/couleur/rayon différent au rendu suivant anime au lieu de sauter, l'équivalent unifié de `AnimatedContainer`/`AnimatedPositioned`/`AnimatedOpacity` |
| `Confetti()` | déclenche une pluie de confettis côté client dès que ce widget est peint, sans tap — `Confetti::triggerAction()` pour un bouton "🎉 encore" qui la rejoue à la demande |
| `Splash($content, $nextScreen, $durationMs: 1800)` | affiche `$content` pendant `$durationMs`, puis navigue automatiquement vers `$nextScreen` — aucun tap requis |

## Chargement

| PHP | Rôle |
|---|---|
| `Skeleton($width, $height, $radius:)` / `::circle($diameter)` / `::lines($count, $width, $lineHeight:, $gap:)` | placeholder animé (shimmer) pendant qu'un vrai contenu n'est pas encore prêt |
| `Spinner($diameter:, $color:, $trackColor:)` | indicateur circulaire animé (arc qui tourne en continu) — distinct de `CircularProgress`, qui affiche une valeur connue plutôt qu'une attente indéterminée |
| `Async($taskKey, $handlerClass, $handlerMethod, $args, $loading, $builder, $pollIntervalMs: 400)` | lance un traitement en arrière-plan (`AsyncTask::poll()` — fichier de cache + verrou, pas de queue/websocket) et affiche `$loading` jusqu'à son terme, sondé côté client ; l'équivalent d'un `FutureBuilder` Flutter sans infrastructure supplémentaire |

## Gestes

| PHP | Rôle |
|---|---|
| `Tappable($child, $action, $meta = null)` | enregistre une hit region — la brique derrière tous les widgets tappables |
| `GestureDetector($child, onDoubleClick:, onSwipeLeft:, onSwipeRight:)` | double-tap/swipe rapide, détectés via `android.view.GestureDetector` côté Kotlin |
| `Dismissible($child, $key, $action)` | swipe-to-dismiss — le SEUL geste dont le suivi du doigt reste 100% côté client (`NativeCanvasView.checkScrollFollow`/drag), `$action` n'est appelé qu'au relâchement, une fois le seuil dépassé |
| `Reorderable($group, $items, $action)` | glisser-déposer pour réordonner une liste — `$action` reçoit le nouvel ordre au relâchement, le drag lui-même ne fait aucun round-trip |
| `Fixed($child)` | épingle un sous-arbre au viewport (ne défile pas avec le corps) — ce que `Scaffold` utilise pour l'AppBar/BottomNav |
| `ClientTabs($key, $panels, $initialIndex: 0)` | bascule instantanée entre plusieurs panneaux, 100% côté client, zéro round-trip — le mécanisme générique derrière `BottomSheet` ci-dessous (voir [docs/architecture.md](architecture.md)) |
| `BottomSheet($key, $content)` | panneau modal ancré en bas, scrim tap-outside-to-dismiss, ouverture/fermeture animées + une vraie poignée de drag continu pour le fermer à la main ; `BottomSheet::openAction($key)`/`::closeAction($key)` comme actions |

```php
new Dismissible(
    new ListTile($label, 'Glisse pour supprimer', 'task_alt'),
    "item-{$id}",
    "dismiss:{$id}",
);
// Côté public/index.php :
if ($action !== null && str_starts_with($action, 'dismiss:')) {
    // retirer l'item — PHP ne voit jamais le geste, seulement le résultat
}
```

## Graphiques

| PHP | Rôle |
|---|---|
| `BarChart($values, $width, $height, color:, gap:)` | histogramme vertical simple |
| `PieChart($values, $diameter, colors:)` | camembert |
| `Sparkline($values, $width, $height, color:)` | mini-courbe inline, sans axes ni légende |

## Superpositions natives

Ces six widgets peignent une boîte statique (icône + texte, ou un vrai
rendu sur le Canvas pour `QrCode`) qui devient une vraie vue native au
tap ou au rendu — jamais un lecteur/carte HTML embarqué.

| PHP | Rôle |
|---|---|
| `MapView($latitude, $longitude, $zoom, $width, $height:)` | boîte "ouvrir la carte" ; le tap ouvre une vraie carte interactive native (MapKit/osmdroid/libshumate — voir [docs/device-and-native.md](device-and-native.md)) |
| `VideoPlayer($url, $width, $height:)` | boîte "lecture" ; le tap lance un vrai lecteur vidéo natif, en overlay par-dessus |
| `YoutubePlayer($videoId, $width, $height:, $label:)` | même mécanisme que `VideoPlayer`, dédié à une vidéo YouTube |
| `Lottie($url, $width, $height, loop:, autoplay:, key:)` | animation Lottie rejouée par une vraie vue native, continue de boucler à travers les taps/scrolls (pas rejouée depuis zéro à chaque fetch) |
| `QrCode($data, $boxSize:, $foreground:, $background:)` | QR code généré et dessiné directement sur le Canvas — pas une image chargée |
| `Snackbar($message, $durationMs: 3000)` | toast temporaire ; ne peint rien lui-même, juste `Canvas::showSnackbar()` |

## Dessin bas niveau

| PHP | Rôle |
|---|---|
| `CustomPaint::make($width, $height)->rect(...)->circle(...)->line(...)->text(...)` | primitives dessinées une fois au montage — ce qu'`Engine\Canvas` demandait côté HTML |
| `Canvas::rect/circle/line/arc/text/icon/image()` | l'API bas niveau que tous les widgets ci-dessus appellent en interne — rarement appelée directement |

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

Rien, actuellement — la dernière page WebView (`WidgetsLayoutPage.php`) a été supprimée une fois `Hero` construit. Un futur widget qui aurait vraiment besoin d'un moteur HTML (rendu Markdown riche, iframe...) redeviendrait candidat à une page WebView ponctuelle, ouverte via `NativeDeviceBridge.kt::openWebView()`.
