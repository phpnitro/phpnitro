# App Android — Ma Boutique (examples/ecom)

**État : fonctionnel, vérifié sur device réel** (Infinix X6532, Android 14, `armeabi-v7a`) : installée via `adb install`, lancée, catalogue affiché avec les vrais prix depuis la base SQLite embarquée. Package `com.mobile.ecom` — distinct de la démo racine (`com.mobile.engine`), les deux peuvent être installées en même temps.

Mêmes mécanismes que `android/` à la racine (voir `../../../android/README.md` pour le détail : binaires PHP cross-compilés via le NDK, `PhpServer.kt`, pièges rencontrés) — ce dossier bundle simplement `examples/ecom/public/` + `examples/ecom/lib/` (+ les packages partagés `phpnitro/ui`/`phpnitro/database`, copiés en fichiers réels) au lieu de la démo du framework.

## Build & install

```bash
cd examples/ecom
bash bundle-android.sh          # copie public/ + lib/ + packages/*/src dans android/app/src/main/assets/www
cd android
echo "sdk.dir=$ANDROID_HOME" > local.properties   # une seule fois, adapte le chemin du SDK
gradle :app:assembleDebug
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

## Binaires PHP

Réutilisés tels quels depuis le framework racine (`android/app/src/main/jniLibs/`) — même binaire, n'importe quelle app PHP embarquée sur ce device peut s'en servir. Pas besoin de recompiler pour chaque exemple.
