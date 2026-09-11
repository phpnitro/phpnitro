# HostApp

Mini-app hôte jetable, générée via [XcodeGen](https://github.com/yonaskolb/XcodeGen)
(`brew install xcodegen`) à partir de `project.yml`, qui embarque
`PhpNitroNativeEngine` (voir `HostApp/AppDelegate.swift`) — le premier moyen
de réellement lancer ce moteur de rendu sur un simulateur/device, plutôt que
seulement ses tests unitaires. C'est ce qui a permis de trouver et vérifier
le correctif de `NativeCanvasView.swift` (icônes invisibles au tout premier
rendu — voir son historique git).

Sert son propre `public/index.php` bundlé (staged par `phpx bundle:ios`,
voir `PhpEmbedRuntime`/`EmbeddedScreenDataSource`) via `NativeScreenViewController(embeddedScreen:)`
— PHP tourne en process, aucun `phpx serve` requis. `AppDelegate.swift`
lit deux arguments de lancement optionnels : `-screen <nom>` (écran PHP
initial, défaut `"home"`) et `-host <ip>` — passer `-host` bascule sur
l'ancien chemin réseau (`NativeScreenViewController(host:port:screen:)`)
pour comparer contre un vrai `phpx serve 8090` tournant sur cette IP,
utile pour un test A/B réseau-vs-embarqué mais plus le chemin par
défaut.

```bash
xcodegen generate   # régénère HostApp.xcodeproj depuis project.yml si besoin
xcodebuild -project HostApp.xcodeproj -scheme HostApp \
  -destination 'platform=iOS Simulator,name=iPhone 17' build
```

`HostApp.xcodeproj` est committé pour un usage immédiat sans dépendre de
XcodeGen — régénère-le après toute modification de `project.yml`.

## Sur un device physique

`127.0.0.1` ne pointe que vers l'hôte du simulateur — un vrai iPhone a
besoin de l'IP LAN réelle de la machine qui fait tourner `phpx serve`
(`ipconfig getifaddr en0` sur macOS), passée via `-host` :

```bash
xcodebuild -project HostApp.xcodeproj -scheme HostApp \
  -destination 'platform=iOS,id=<UDID du device>' -allowProvisioningUpdates build
xcrun devicectl device install app --device <UDID> <chemin du .app>
xcrun devicectl device process launch --device <UDID> --terminate-existing \
  com.phpnitro.hostapp -- -host <IP LAN de la machine>
```

Signature automatique (`CODE_SIGN_STYLE: Automatic` dans `project.yml`,
`DEVELOPMENT_TEAM` déjà renseigné) — un compte Apple personnel/gratuit
suffit pour la plupart des capacités `device:*`, sauf le NFC (voir
`ios/README.md`, réservé aux comptes Apple Developer Program payants).
