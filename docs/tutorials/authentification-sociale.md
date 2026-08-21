# Tutoriel : ajouter "Se connecter avec Google"

> **Périmé** : vérifié dans `packages/socialauth/src/OAuthProvider.php` — Google est désormais géré par le SDK natif Android (pas par le flux OAuth2 Authorization Code web décrit ci-dessous, qui reste réel pour Microsoft/GitHub/Facebook/Slack/X/Apple). Ce tutoriel utilise aussi une architecture antérieure (classes `Screen`, formulaires POST classiques) qui n'existe plus — voir [docs/getting-started.md](../getting-started.md) pour le modèle actuel. Pas encore réécrit, gardé pour référence historique seulement.

Ce tutoriel câble un vrai bouton Google Sign-In, de bout en bout. Il illustre aussi un principe de conception central à `Engine\SocialAuth\` (et à tous les services device — voir [Capacités device & natif](../device-and-native.md)) : **il n'y a pas de widget "Google Sign-In Button" prêt à l'emploi**. C'est un choix délibéré, pas un oubli — l'utilisateur décide à quoi ressemble son bouton, le service se contente de fournir l'action à déclencher.

## 1. Obtenir un client ID Google

Sur [console.cloud.google.com](https://console.cloud.google.com), créer des identifiants OAuth 2.0 (type "Application Web", même si l'app cible est mobile — le flux passe par une redirection navigateur classique). Noter le **Client ID** et le **Client Secret**, et déclarer une URI de redirection, par exemple `https://tonapp.com/auth/google/callback`.

Stocker les deux dans `.env` (jamais commité, voir `.gitignore`) :

```
GOOGLE_CLIENT_ID="ton_client_id"
GOOGLE_CLIENT_SECRET="ton_client_secret"
```

## 2. Le bouton — n'importe quel bouton

`Engine\SocialAuth\GoogleSignIn::onClick()` renvoie une simple redirection JS vers l'écran de consentement Google. On l'attache à un `Button` tout à fait normal, stylé comme n'importe quel autre bouton de l'app :

```php
use Engine\Button;
use Engine\SocialAuth\GoogleSignIn;

Button::make(
    'Continuer avec Google',
    onClick: GoogleSignIn::onClick(
        clientId: $_ENV['GOOGLE_CLIENT_ID'],
        redirectUri: 'https://tonapp.com/auth/google/callback',
    ),
    classes: 'bg-white border border-gray-300 text-gray-900 font-medium px-4 py-2 rounded-lg flex items-center gap-2',
),
```

Pas de composant "GoogleButton" à importer, pas de logo Google fourni par le framework — c'est vraiment un `Button::make()` ordinaire. Si demain l'app veut la même action sur une image, une carte entière (`LinkWrap`), ou un item de menu, c'est le même `onClick`.

## 3. La route de callback

Google redirige vers `redirectUri` avec un paramètre `?code=...`. Il faut une page/route qui le récupère et échange ce code contre les infos utilisateur :

```bash
bin/phpx make:page GoogleCallback /auth/google/callback
```

```php
<?php

namespace Engine\App;

use Engine\Navigator;
use Engine\SocialAuth\GoogleSignIn;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

final class GoogleCallbackPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        $code = $_GET['code'] ?? null;
        if ($code === null) {
            return Text::make('Connexion annulée.');
        }

        $user = GoogleSignIn::exchangeCode(
            code: $code,
            clientId: $_ENV['GOOGLE_CLIENT_ID'],
            clientSecret: $_ENV['GOOGLE_CLIENT_SECRET'],
            redirectUri: 'https://tonapp.com/auth/google/callback',
        );

        if ($user === null) {
            return Text::make('Échec de la connexion Google — réessaie.');
        }

        // $user = ['id' => '...', 'email' => '...', 'name' => '...']
        // À toi de jouer : créer/retrouver un compte local par $user['email'],
        // ouvrir une session applicative, puis rediriger.
        $_SESSION['user_email'] = $user['email'];

        return Text::make("Connecté en tant que {$user['name']} !");
    }
}
```

`exchangeCode()` fait l'appel serveur-à-serveur (échange du code contre un token, puis récupération du profil) et renvoie un tableau normalisé (`id`/`email`/`name`) — identique quel que soit le fournisseur (`GoogleSignIn`, `GithubSignIn`, `MicrosoftSignIn`...), pour ne pas avoir à écrire un code différent par provider.

## 4. Les autres fournisseurs, même principe

`Engine\SocialAuth\` fournit `GoogleSignIn`, `MicrosoftSignIn`, `GithubSignIn`, `FacebookSignIn`, `SlackSignIn`, `XSignIn` (PKCE) et `AppleSignIn` (JWT client secret ES256) — tous héritent de la même classe abstraite `OAuthProvider` et exposent exactement `onClick()`/`exchangeCode()`. Changer de fournisseur, c'est changer un nom de classe, pas réapprendre une API.

## Ce que ce tutoriel a couvert

Le principe général de ce framework pour toute capacité native ou tierce (device, paiement, auth) : **un service statique qui renvoie une chaîne JS**, jamais un widget imposé. Voir aussi [Intégrations](../integrations.md#authentification-sociale) pour le détail de chaque fournisseur, et [Capacités device & natif](../device-and-native.md) pour le même principe appliqué à la caméra, au capteur, etc.
