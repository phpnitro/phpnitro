# Widgets

Chaque widget est une classe PHP avec un constructeur (propriétés configurables, comme dans Flutter) et une méthode `render(): string` qui produit du HTML :

```php
Button::make('Connexion');
// -> <button class="bg-blue-600 ...">Connexion</button>
```

Toutes les classes sont dans le namespace `Engine\` sauf préfixe explicite (`Maps\`, `Dialogs\`, `Payments\`, `Firebase\`, `Device\`, `Countries\`, `SocialAuth\`, `Connectivity\`, `Launcher\`, `Preferences\`, `Format\`) — des packages dédiés, chacun un second namespace PSR-4 dans le `composer.json` racine, pas des packages Composer séparés.

**Widgets vs services.** La plupart des classes ci-dessous sont des widgets : `::make()` retourne un `Widget` qui rend son propre HTML complet. Mais un bouton d'action pré-stylé (paiement, vibreur, partage, authentification sociale...) impose sa propre apparence — si tu veux ton propre label/style, tu es coincé. `Engine\Device\`, `Engine\SocialAuth\` et une partie d'`Engine\Payments\` sont donc des **services** : des méthodes statiques qui retournent une expression JS brute (ou une redirection) à attacher à N'IMPORTE QUEL bouton via `Button::make($label, onClick: ...)`, plutôt qu'un widget imposé.

Chaque widget est démontré quelque part : la route `/widgets` de l'app démo couvre tout sauf les paiements (voir [examples/ecom](../examples/ecom/README.md#paiement)).

## Mise en page

| PHP | Rend en |
|---|---|
| `Column::make([$children], $classes)` | `<div class="flex flex-col ...">` |
| `Row::make([$children], $classes)` | `<div class="flex flex-row ...">` |
| `Wrap::make([$children], $classes)` | comme `Row`, mais passe à la ligne (`flex-wrap`) au lieu de déborder |
| `Container::make($child, $classes, background: ?Color, rounded: ?Rounded)` | `<div>` à un seul enfant — `background`/`rounded` typés s'ajoutent par-dessus `$classes` |
| `Stack::make([$children], $classes = 'relative')` | superpose les enfants ; un enfant `Positioned` reçoit un offset explicite, les autres remplissent la zone |
| `Positioned::make($child, top:, right:, bottom:, left:)` | positionnement absolu explicite (pixels), seulement utile dans un `Stack` |
| `Center::make($child)` | centre l'enfant |
| `Align::make($child, $alignment)` | aligne l'enfant selon une constante `Alignment::*` |
| `Padding::make($child, $classes = 'p-4')` | espacement interne |
| `Margin::make($child, $classes = 'm-4')` | espacement externe |
| `Divider::make($classes)` | `<hr>` |
| `SingleScrollView::make($child, $classes)` | conteneur défilable vertical |
| `ListView::make([$children], $classes)` | liste verticale avec séparateurs |
| `Table::make($rows, $headers, $border)` | `<table>` ; `$rows` accepte des chaînes ou des `Widget` par cellule |
| `PageView::make([$pages])` | carrousel de pages avec swipe natif (CSS `scroll-snap`, zéro JS) |

`Alignment::TOP_LEFT\|TOP_CENTER\|TOP_RIGHT\|CENTER_LEFT\|CENTER\|CENTER_RIGHT\|BOTTOM_LEFT\|BOTTOM_CENTER\|BOTTOM_RIGHT` — préréglages `items-*`/`justify-*` Tailwind. `Rounded::NONE\|SM\|MD\|LG\|XL\|FULL`.

## Structure d'écran

| PHP | Rôle |
|---|---|
| `Scaffold::make($body, appBar:, hasBottomNav:, floatingActionButton:, drawer:)` | structure standard : AppBar fixe, corps défilable, FAB, Drawer |
| `AppBar::make($title, leading:, actions:)` | barre supérieure fixe |
| `BottomNavigation::make([['label','href'], ...])` | `<nav>` fixée en bas, rendue **une seule fois**, jamais recréée par `nav.js` |
| `FloatingActionButton::make($label, action:, classes:, ariaLabel:)` | bouton rond flottant (bottom-right, `position: fixed`) |
| `Drawer::make([$items], $title)` + `DrawerToggle::make()` | menu latéral coulissant, zéro JS |
| `Dropdown::make($label, [$items])` | menu déroulant natif (`<details>`) |

## Formulaires

| PHP | Rend en |
|---|---|
| `Form::make([$fields], action:)` | `<form>` avec CSRF |
| `TextField::make($name, label:, value:, type:, error:)` | `<input>` |
| `Textarea::make($name, label:, value:, placeholder:, rows:, error:)` | `<textarea>` |
| `SelectBox::make($name, [$options], value:, label:, error:)` | `<select>` |
| `Checkbox::make($name, $label, checked:)` | case à cocher |
| `SwitchToggle::make($name, $label, $on)` | interrupteur on/off, zéro JS |
| `DatePicker::make($name, label:, value:, min:, max:)` | `<input type="date">` — sélecteur natif |
| `TimePicker::make($name, label:, value:)` | `<input type="time">` |
| `ErrorBanner::make($message)` | boîte d'erreur explicite |
| `ProgressBar::make($value)` | barre linéaire (0-100) |
| `CircularProgress::make($value, $size)` | indicateur circulaire (SVG pur) |
| `Stepper::make($currentStep, $totalSteps, $stepLabels, $body, backAction:, nextAction:)` | assistant multi-étapes |

## Animations

**Ce que ce n'est PAS** : il n'y a aucune réactivité/diffing côté client — chaque interaction est un aller-retour serveur complet. Un `AnimatedContainer` façon Flutter (qui anime la transition **entre deux valeurs**) n'existe donc pas. Ce qui existe : des animations d'entrée pures CSS, jouées au montage.

| PHP | Effet |
|---|---|
| `FadeIn::make($child, durationMs:, delayMs:, curve:, distancePx:)` | fondu + léger glissement au montage |
| `Curves::LINEAR\|EASE\|EASE_IN\|EASE_OUT\|EASE_IN_OUT\|FAST_OUT_SLOW_IN\|OVERSHOOT` | courbes nommées (chaînes CSS `timing-function`) |
| `AnimatedText::make([$texts], typeSpeedMs:, pauseMs:, deleteSpeedMs:)` | effet machine à écrire, cycle entre plusieurs chaînes |
| `AutoSizeText::make($text, minSize:, maxSize:)` | réduit la taille de police jusqu'à tenir dans son conteneur |
| `LottieView::make($src, loop:, autoplay:)` | animation Lottie (JSON), lecteur `lottie-web` vendorisé (offline, pas de CDN) |

Une vraie navigation entre écrans joue aussi un fondu (`nav.js`), respecte `prefers-reduced-motion`.

## Contenu dynamique

| PHP | Rôle |
|---|---|
| `StreamBuilder::make($endpoint, $render)` | interroge une route JSON en polling, re-rend au changement |
| `FutureBuilder::make($endpoint, $loading)` | charge une route une seule fois au chargement |
| `InfiniteScrollList::make($endpoint, [$initialItems])` | charge la page suivante au scroll (`?page=N`, `IntersectionObserver`) |
| `GestureDetector::make($child, onDoubleClick:, onSwipeLeft:, onSwipeRight:)` | détecte double-clic/swipe, déclenche une action serveur |
| `Flash::set($message)` + `FlashMessage::make()` | message flash en session, auto-masqué |

## Média & icônes

| PHP | Rend en |
|---|---|
| `Image::make($src, alt:, classes:)` / `Image::network(...)` | `<img>` |
| `AudioPlayer::make($src, controls:, autoplay:, loop:)` | `<audio>` natif |
| `VideoPlayer::make($src, controls:, autoplay:, loop:, poster:)` | `<video>` natif |
| `Icon::home()`, `::settings()`, `::check()`, `::close()`, `::search()`, `::heart()`, `::star()`, `::trash()`, `::edit()`, `::download()`, `::upload()`, `::share()`, `::calendar()`, `::clock()`, `::mail()`, `::phone()`, `::lock()`, `::bell()`, `::plus()`, `::minus()`, `::chevronLeft\|Right\|Up\|Down()`, `::arrowLeft\|Right()`, `::info()`, `::eye()`, ... | icônes SVG inline, construites à la main (pas un port de Font Awesome — pas de licence tierce, pas de données de tracé mal reproduites) |

## Divers

| PHP | Rôle |
|---|---|
| `Link::make($label, $href, $classes)` | `<a href>` — vraie route HTTP |
| `Navigator::to($path)` / `::back($fallback)` / `::link($label, $path)` | sucre de nommage façon Flutter |
| `LocationButton::make($label)` | déclenche `navigator.geolocation` |
| `Html::raw($html)` | passthrough HTML/JS brut |
| `GoogleTranslate::make($pageLanguage, $includedLanguages)` | widget officiel Google Website Translator |
| `Translator::load($locale, $translations)` / `::t($key, $params)` | i18n par clés, côté serveur |
| `ThemeToggle::make()` | bouton bascule clair/sombre |

## API de style typée (façon Flutter)

En plus de `$classes` (chaîne Tailwind libre), certains widgets acceptent des paramètres typés qui priment sur `$classes` :

```php
Text::make('Titre', size: TextSize::XL2, weight: FontWeight::BOLD, color: Color::gray(900));

Container::make(
    Text::make('badge', color: Color::of('white', 0)),
    background: Color::blue(600),
    rounded: Rounded::FULL,
);
```

- `Text` : `size` (`TextSize::SM|BASE|LG|XL|XL2|XL3`), `weight` (`FontWeight::NORMAL|MEDIUM|SEMIBOLD|BOLD`), `color` (`Color::gray/blue/red/green(shade)` ou `Color::of('nom', shade)`) — remplacent entièrement `$classes` dès qu'un seul est fourni.
- `Container` : `background`/`rounded` — s'**ajoutent** à `$classes` au lieu de le remplacer (`$classes` porte souvent du layout structurel que le style typé n'a pas vocation à effacer).
- `Padding`/`Margin` gardent **volontairement** une chaîne Tailwind brute — pas d'objet de valeur dédié, choix assumé pour rester DOM-natif.

Reste non typé : la plupart des ~75 widgets n'acceptent que `$classes` en chaîne brute. Pas de thème injectable façon `ThemeData` — seul un bascule clair/sombre binaire existe.

## Concepts internes Flutter sans équivalent DOM

PhpNitro rend vers du DOM/CSS (pas de moteur de rendu custom comme Skia), donc certains types Flutter n'ont pas de classe dédiée : ils se traduisent directement en classes Tailwind.

| Flutter | Équivalent PhpNitro |
|---|---|
| `BoxFit` | `object-cover`/`object-contain`/`object-fill` sur `Image` |
| `BoxShape` | `rounded-full` vs `rounded-none`/`rounded-lg` |
| `Brightness` | bascule clair/sombre déjà native |
| `Clip` | `overflow-hidden` + `rounded-*` |
| `MainAxisAlignment`/`CrossAxisAlignment` | classes flex Tailwind directement sur `Column`/`Row`, ou `Alignment::*` |

## Accessibilité

HTML sémantique + `aria-label` (`IconButton`, `FloatingActionButton`). Premier audit réel effectué avec TalkBack (Android) : deux bugs trouvés et corrigés (contrôles pilotés par case à cocher cachée invisibles pour un lecteur d'écran — corrigés avec `role="button" tabindex="0"` ; FAB annoncé par son glyphe brut — corrigé avec `ariaLabel`). Reste non audité : le reste de l'app, VoiceOver (iOS).
