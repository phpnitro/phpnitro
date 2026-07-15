# Coquille Android (non vérifiée dans cette session)

Projet Gradle minimal : une `MainActivity` qui affiche une `WebView` pointée sur le serveur PHP (`engine/`).

**État actuel** : ce code est écrit mais **n'a pas pu être lancé/vérifié** dans cet environnement (pas d'AVD configuré, pas de device connecté, Gradle non installé). À tester sur ta machine :

```bash
cd engine && php -S 0.0.0.0:8090 -t public public/router.php   # serveur PHP accessible depuis l'émulateur
```

Puis ouvre `android/` dans Android Studio et lance sur un émulateur (Android Studio provisionnera Gradle automatiquement — il n'y a pas de `gradlew` fourni ici, faute de Gradle installé pour le générer).

## Permissions et capacités device

`MainActivity` demande au runtime les permissions caméra/micro/localisation et configure la `WebView` (`WebChromeClient.onPermissionRequest`, `onGeolocationPermissionsShowPrompt`, `setGeolocationEnabled`) pour que les widgets `VibrateButton`, `LocationButton`, `CameraPreview` et `MicrophoneButton` d'`engine/` fonctionnent réellement une fois lancés sur un vrai device/émulateur. **Non vérifié dans cette session** pour la même raison que le reste (pas d'émulateur disponible) — à tester en conditions réelles.

## Limite majeure actuelle

`MainActivity` pointe vers `http://10.0.2.2:8090/` — l'alias que l'émulateur Android utilise pour joindre le `localhost` de la machine hôte. **Il n'y a pas encore de runtime PHP embarqué sur le device lui-même** : c'est une pièce séparée et bien plus grosse (cross-compiler PHP pour Android via le NDK, l'embarquer dans l'APK, le lancer en sous-processus au démarrage de l'app). Cette coquille WebView prouve seulement que l'app Android peut afficher et faire fonctionner l'UI servie par PHP — pas encore que PHP tourne sur le téléphone.
