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

## Ce que ça démontre

| Écran | Route | Widgets / fonctionnalités |
|---|---|---|
| Catalogue | `/` | Grille de produits (`Column` en mode grid), `LinkWrap`, `Image`, produits stockés en SQLite (Doctrine DBAL) |
| Détail produit | `/product/{id}` | `MapView` (retrait en magasin), `Button` avec action, `SingleScrollView` |
| Panier | `/cart` | `ListView`, `Form` + champ caché pour retirer un article, état partagé entre écrans (`Cart`, session) |
| Paiement | `/checkout` | `Form`, `TextField`, `SelectBox`, `Checkbox`, `FingerprintButton` (WebAuthn), un gateway de paiement réel parmi 7 selon `.env` (sinon mode démo sans paiement — voir [Paiement](#paiement)), validation + redirection programmatique |
| Confirmation | `/order/{id}` | `StreamBuilder` — le statut de la commande progresse tout seul (confirmée → en préparation → expédiée → livrée) sans rechargement de page |
| Connexion / Inscription | `/login`, `/register` | `Form`, mots de passe hashés (`password_hash`/`password_verify`), session |
| Compte | `/account` | `ThemeToggle`, déconnexion, protégé par état de session |

## Backend

`lib/backend/src/Repository/` : `ProductRepository` (6 produits de démo auto-seedés), `OrderRepository` (statut calculé depuis le temps écoulé, pas de worker), `UserRepository` (comptes, mots de passe hashés). Toutes les routes UI appellent ces repositories **en mémoire** (pas de HTTP), même mécanisme que le framework principal — le backend est toujours disponible, implicitement. Un seul `composer.json`/`vendor/` pour tout l'exemple : son autoload PSR-4 pointe directement sur les packages partagés du framework (`../../packages/ui/src`, `../../packages/database/src`, à la racine du dépôt) — aucun code de framework dupliqué ici.

## Paiement

Par défaut (`.env` sans aucune clé de paiement), `/checkout` reste en **mode démo** : bouton "Valider la commande (mode démo, sans paiement)", exactement le comportement d'avant. `CheckoutPage::selectPaymentWidget()` choisit le **premier** gateway configuré, dans cet ordre — voir `.env` pour les noms exacts de variables de chacun :

1. **Kkiapay** — widget JS, callback `transaction_id`. Confiance élevée dans le pattern, non testé contre un vrai sandbox.
2. **PayPal** — vrai JS SDK (`paypal.Buttons()`), vérification serveur = vrai flux OAuth2 + capture. Confiance élevée, non testé contre une vraie app sandbox.
3. **FedaPay** — même forme que Kkiapay. Confiance moyenne-élevée, non testé.
4. **Stripe** — aucune clé publique/SDK client : bouton simple, tout se passe côté serveur (`StripeCheckout::createSessionUrl()`, API REST Stripe directe). Confirmé qu'une clé invalide échoue proprement (commande créée, redirection locale de secours) plutôt que de planter.
5. **Feexpay**, 6. **iZiChangePay**, 7. **TresorPay** — gabarits structurels seulement (voir le docblock de chaque widget dans `packages/ui/src/`) : URL de script et nom des fonctions JS marqués `TODO`, à vérifier contre la doc de chaque gateway avant usage réel. Si une clé secrète est configurée, `CheckoutPage` **refuse** la transaction plutôt que de faire semblant de la vérifier — seul le mode démo (aucune clé secrète) fonctionne réellement ici.

Chaque widget place, à l'intérieur du `Form::make(...)` du panier, son callback de succès qui sérialise aussi les champs du formulaire (nom, adresse, conditions) et les poste avec l'identifiant de transaction vers son `onConfirmX()` — la commande n'est créée qu'après vérification (sauf en mode démo, où la signature client est acceptée telle quelle : sandbox uniquement, ne fais jamais ça pour un vrai magasin).

## Limites de cet exemple

- Photos produits : images aléatoires ([picsum.photos](https://picsum.photos)) plutôt que des vraies photos produit — suffisant pour démontrer la mise en page, pas pour un vrai catalogue.
- Paiement réel possible via 4 des 7 gateways (voir ci-dessus) mais seulement testé en mode démo dans cet environnement (pas de compte sandbox disponible pour aucun d'entre eux) — le bouton biométrique reste une confirmation locale (WebAuthn), il ne débite rien à lui seul.
- Les images produits mettent quelques secondes à charger sur le device réel (réseau du téléphone) — comportement normal, pas un bug.
