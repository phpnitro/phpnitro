# Ma Boutique — exemple e-commerce

Démonstration complète du framework : catalogue, panier, checkout, compte utilisateur, carte, biométrie, suivi de commande en direct. **Vérifié de bout en bout** (curl + captures d'écran) : inscription → ajout panier → commande → confirmation avec statut qui évolue tout seul.

## Lancer

```bash
cd examples/ecom
composer install
php -S 127.0.0.1:8090 -t public public/router.php
```

Ouvre `http://127.0.0.1:8090/`.

## Android

APK dédié (package `com.mobile.ecom`, distinct de la démo racine — les deux s'installent en même temps) : voir `android/README.md`. **Vérifié sur device réel** (Infinix X6532) : catalogue affiché, données servies par le PHP embarqué sur le téléphone.

```bash
bash bundle-android.sh
cd android && gradle :app:assembleDebug
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

L'icône du launcher et le nom natif (`strings.xml`) sont générés depuis `phpnitro.yml`'s `icon`/`icon_background`/`name` par `bundle-android.sh` (voir la section `phpnitro.yml` du [README racine](../../README.md)) — `assets/icon.png` ici est un **placeholder généré** (à remplacer par un vrai logo, il suffit de changer le chemin dans `phpnitro.yml`). Vérifié sur device réel : l'icône du launcher et l'icône animée de l'écran de démarrage (`themes.xml`) reflètent bien le PNG source.

## Ce que ça démontre

| Écran | Route | Widgets / fonctionnalités |
|---|---|---|
| Catalogue | `/` | Grille de produits (`Column` en mode grid), `LinkWrap`, `Image`, produits stockés en SQLite (Doctrine DBAL) |
| Détail produit | `/product/{id}` | `Maps\MapView` (retrait en magasin — voir [Cartes](../../README.md#cartes)), `Button` avec action, `SingleScrollView` |
| Panier | `/cart` | `ListView`, `Form` + champ caché pour retirer un article, état partagé entre écrans (`Cart`, session) |
| Paiement | `/checkout` | `Form`, `TextField`, `SelectBox`, `Checkbox`, `Button` + `Device\Fingerprint::onClick()` (biométrie), un gateway de paiement réel parmi 7 selon `.env` (sinon mode démo sans paiement — voir [Paiement](#paiement)), validation + redirection programmatique |
| Confirmation | `/order/{id}` | `StreamBuilder` — le statut de la commande progresse tout seul (confirmée → en préparation → expédiée → livrée) sans rechargement de page |
| Connexion / Inscription | `/login`, `/register` | `Form`, mots de passe hashés (`password_hash`/`password_verify`), session |
| Compte | `/account` | `ThemeToggle`, déconnexion, protégé par état de session |

## Backend

`lib/backend/src/Repository/` : `ProductRepository` (6 produits de démo auto-seedés), `OrderRepository` (statut calculé depuis le temps écoulé, pas de worker), `UserRepository` (comptes, mots de passe hashés). Toutes les routes UI appellent ces repositories **en mémoire** (pas de HTTP), même mécanisme que le framework principal — le backend est toujours disponible, implicitement. Un seul `composer.json`/`vendor/` pour tout l'exemple : son autoload PSR-4 pointe directement sur les packages partagés du framework (`../../packages/ui/src`, `../../packages/database/src`, `../../packages/payments/src`, `../../packages/maps/src`, `../../packages/dialogs/src`, `../../packages/device/src`, à la racine du dépôt) — aucun code de framework dupliqué ici.

## Paiement

Par défaut (`.env` sans aucune clé de paiement), `/checkout` reste en **mode démo** : bouton "Valider la commande (mode démo, sans paiement)", exactement le comportement d'avant. `CheckoutPage::selectPaymentWidgets()` choisit le **premier** gateway configuré, dans cet ordre — voir `.env` pour les noms exacts de variables de chacun. Cinq des sept gateways sont des **services** (`Engine\Payments\Kkiapay`/`Fedapay`/`Feexpay`/`IziChangePay`/`TresorPay`) : un `scriptTag()` + un `payOnClick()` attaché à un `Button::make(...)` ordinaire, pas un widget pré-stylé — voir [le README racine](../../README.md#paiement) pour le détail de cette architecture :

1. **Kkiapay** — service (`scriptTag()` + `payOnClick()` + `onSuccess()`), callback `transaction_id`. Confiance élevée dans le pattern, non testé contre un vrai sandbox.
2. **PayPal** — vrai JS SDK (`paypal.Buttons()`), reste un widget (`PaypalButton`, exception documentée — le SDK dessine lui-même son bouton). Vérification serveur = vrai flux OAuth2 + capture. Confiance élevée, non testé contre une vraie app sandbox.
3. **FedaPay** — service, même forme que Kkiapay. Confiance moyenne-élevée, non testé.
4. **Stripe** — deux sous-modes selon les clés renseignées : `STRIPE_PUBLIC_KEY` **et** `STRIPE_SECRET_KEY` → `Payments\Stripe::cardElement()` + `::confirmPaymentOnClick()` (champ carte intégré, Stripe Elements, la carte reste dans un iframe géré par Stripe) ; `STRIPE_SECRET_KEY` seule → redirection hébergée (`StripeCheckout::createSessionUrl()`, API REST directe, aucun SDK client, juste un `Button::make(action: ...)`). Confirmé que la création de PaymentIntent échoue proprement (repli sur la redirection hébergée) avec une clé invalide plutôt que de planter.
5. **Feexpay**, 6. **iZiChangePay**, 7. **TresorPay** — services, gabarits structurels seulement (voir le docblock de chaque classe dans `packages/payments/src/`) : URL de script et nom des fonctions JS marqués `TODO`, à vérifier contre la doc de chaque gateway avant usage réel. Si une clé secrète est configurée, `CheckoutPage` **refuse** la transaction plutôt que de faire semblant de la vérifier — seul le mode démo (aucune clé secrète) fonctionne réellement ici.

Le déclencheur de chaque service stashe le formulaire du panier (`this.closest('form')`) dans une variable JS partagée au moment du clic ; son callback de succès sérialise donc aussi les champs du formulaire (nom, adresse, conditions) et les poste avec l'identifiant de transaction vers son `onConfirmX()` — la commande n'est créée qu'après vérification (sauf en mode démo, où la signature client est acceptée telle quelle : sandbox uniquement, ne fais jamais ça pour un vrai magasin).

## Limites de cet exemple

- Photos produits : images aléatoires ([picsum.photos](https://picsum.photos)) plutôt que des vraies photos produit — suffisant pour démontrer la mise en page, pas pour un vrai catalogue.
- Paiement réel possible via 4 des 7 gateways (voir ci-dessus) mais seulement testé en mode démo dans cet environnement (pas de compte sandbox disponible pour aucun d'entre eux) — le bouton biométrique reste une confirmation locale (WebAuthn), il ne débite rien à lui seul.
- Les images produits mettent quelques secondes à charger sur le device réel (réseau du téléphone) — comportement normal, pas un bug.
