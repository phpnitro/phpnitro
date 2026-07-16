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
| Paiement | `/checkout` | `Form`, `TextField`, `SelectBox`, `Checkbox`, `FingerprintButton` (WebAuthn), `KkiapayButton` (paiement réel si `KKIAPAY_PUBLIC_KEY` renseignée dans `.env`, sinon mode démo sans paiement), validation + redirection programmatique |
| Confirmation | `/order/{id}` | `StreamBuilder` — le statut de la commande progresse tout seul (confirmée → en préparation → expédiée → livrée) sans rechargement de page |
| Connexion / Inscription | `/login`, `/register` | `Form`, mots de passe hashés (`password_hash`/`password_verify`), session |
| Compte | `/account` | `ThemeToggle`, déconnexion, protégé par état de session |

## Backend

`lib/backend/src/Repository/` : `ProductRepository` (6 produits de démo auto-seedés), `OrderRepository` (statut calculé depuis le temps écoulé, pas de worker), `UserRepository` (comptes, mots de passe hashés). Toutes les routes UI appellent ces repositories **en mémoire** (pas de HTTP), même mécanisme que le framework principal — le backend est toujours disponible, implicitement. Un seul `composer.json`/`vendor/` pour tout l'exemple : son autoload PSR-4 pointe directement sur les packages partagés du framework (`../../packages/ui/src`, `../../packages/database/src`, à la racine du dépôt) — aucun code de framework dupliqué ici.

## Paiement (Kkiapay)

Par défaut (`.env` sans `KKIAPAY_PUBLIC_KEY`), `/checkout` reste en **mode démo** : bouton "Valider la commande (mode démo, sans paiement)", exactement le comportement d'avant. Pour activer le vrai bouton de paiement :

```bash
# dans examples/ecom/.env
KKIAPAY_PUBLIC_KEY="ta_cle_publique_sandbox"
```

`CheckoutPage` affiche alors `KkiapayButton` à la place — cliquer dessus ouvre le vrai widget Kkiapay ; à la fin du paiement, son callback poste le `transaction_id` **et** les champs du formulaire (nom, adresse, conditions) ensemble vers `onConfirmPayment()`, qui crée la commande seulement après vérification.

Ajoute aussi `KKIAPAY_PRIVATE_KEY` pour activer la vérification serveur-à-serveur réelle (`CheckoutPage::verifyKkiapayTransaction()`) — **non testée dans cet environnement** (pas de compte sandbox disponible ici) : vérifie le format exact de l'appel contre la doc Kkiapay actuelle avant un déploiement réel. Sans clé privée, la transaction cliente est acceptée telle quelle (mode démo/sandbox uniquement — ne fais jamais ça pour un vrai magasin).

## Limites de cet exemple

- Photos produits : images aléatoires ([picsum.photos](https://picsum.photos)) plutôt que des vraies photos produit — suffisant pour démontrer la mise en page, pas pour un vrai catalogue.
- Paiement réel possible via Kkiapay (voir ci-dessus) mais seulement testé en mode démo/sans clé privée dans cet environnement — le bouton biométrique reste une confirmation locale (WebAuthn), il ne débite rien à lui seul.
- Les images produits mettent quelques secondes à charger sur le device réel (réseau du téléphone) — comportement normal, pas un bug.
