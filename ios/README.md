# Coquille iOS (stub non testé — pas de Mac/Xcode disponible ici)

Équivalent iOS de `android/` : `WKWebView` native affichant l'UI servie par PHP. **Aucun code ici n'a pu être compilé ni lancé** dans cet environnement (Xcode ne tourne que sur macOS). Traite ce dossier comme un point de départ documenté, pas comme une app fonctionnelle.

## Ce qui existe

- `App/AppDelegate.swift` — démarre la fenêtre et pousse `ViewController`.
- `App/ViewController.swift` — équivalent de `MainActivity.kt` : une `WKWebView` plein écran pointée sur le serveur PHP.
- `App/Info.plist` — permissions caméra/micro/localisation (équivalent des `<uses-permission>` Android) + exception ATS pour autoriser le HTTP local en dev.

## Ce qui manque (le vrai travail restant)

1. **Un vrai projet Xcode** (`.xcodeproj`/`.xcworkspace`) — à créer dans Xcode (File → New → Project → App, UIKit, Swift), puis remplacer les fichiers générés par ceux d'`App/` ci-dessus.
2. **PHP embarqué sur le device** — contrairement à Android (voir `android/README.md`, cross-compilé via le NDK), il n'existe **aucun binaire PHP pour iOS** dans ce projet. Il faudrait :
   - Cross-compiler PHP pour `arm64` iOS via le toolchain Xcode (`clang -target arm64-apple-ios...`), probablement en adaptant la même recette que pour Android (php-ndk) au SDK iOS plutôt qu'au NDK Android.
   - Vérifier les contraintes de l'App Store sur l'exécution de binaires/code dynamique (PHP interprété doit être embarqué à la compilation, jamais téléchargé/exécuté dynamiquement — cf. réflexion initiale du projet sur ce sujet).
   - Lancer ce binaire en sous-processus au démarrage de l'app (`Process` en Swift, équivalent de `ProcessBuilder` dans `PhpServer.kt`), et pointer la `WKWebView` sur `http://127.0.0.1:<port>/`.
3. En attendant ce travail, `ViewController.swift` pointe sur un PHP hébergé sur le réseau local (`YOUR_COMPUTER_LAN_IP`) — à remplacer par l'IP réelle de la machine qui fait tourner `php bin/phpx serve` pour tester quoi que ce soit.

## Prochaine étape recommandée

Ne pas commencer par le cross-compile PHP directement — d'abord valider que ce stub `WKWebView` s'affiche et parle bien au PHP hébergé sur le réseau (comme la toute première itération Android, avant le passage au PHP embarqué). Une fois ça confirmé sur un vrai Mac/simulateur, seulement ensuite s'attaquer au cross-compile PHP pour iOS.
