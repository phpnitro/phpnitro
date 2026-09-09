# HostApp

Mini-app hôte jetable, générée via [XcodeGen](https://github.com/yonaskolb/XcodeGen)
(`brew install xcodegen`) à partir de `project.yml`, qui embarque
`PhpNitroNativeEngine` (voir `HostApp/AppDelegate.swift`) — le premier moyen
de réellement lancer ce moteur de rendu sur un simulateur/device, plutôt que
seulement ses tests unitaires. C'est ce qui a permis de trouver et vérifier
le correctif de `NativeCanvasView.swift` (icônes invisibles au tout premier
rendu — voir son historique git).

Pointe vers `NativeScreenViewController(host: "127.0.0.1", port: 8090, screen: "home")` :
lance `php bin/phpx serve 8090` à la racine du monorepo avant de builder.

```bash
xcodegen generate   # régénère HostApp.xcodeproj depuis project.yml si besoin
xcodebuild -project HostApp.xcodeproj -scheme HostApp \
  -destination 'platform=iOS Simulator,name=iPhone 17' build
```

`HostApp.xcodeproj` est committé pour un usage immédiat sans dépendre de
XcodeGen — régénère-le après toute modification de `project.yml`.
