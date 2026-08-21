# Le snap `phpx`

`snap install phpx` installe `phpx` avec son propre PHP embarqué, plus
tout ce que `phpx run` a besoin pour lancer le desktop Linux d'un
projet scaffoldé (`--all`) sans rien à installer soi-même : Python 3,
PyGObject/GTK4, Cairo, libshumate (MapView).

## Pourquoi `confinement: classic`

`phpx` écrit des projets n'importe où sur le disque (pas seulement
`$HOME`), ouvre des ports réseau (`serve`), parle à `adb` sur USB
(`dev:push`/`run`), et pour la cible Linux de `run`, ouvre une vraie
fenêtre GTK4 — plus de surface que les interfaces `strict` habituelles
(`home`/`network`/`raw-usb`) ne couvrent proprement ensemble sans
multiplier les plugs et les frictions d'auto-connexion.

**Conséquence réelle** : le confinement `classic` nécessite une revue
manuelle de Canonical avant de pouvoir publier sur un canal stable du
Snap Store (`snapcraft upload --release=stable` peut réussir à
téléverser une révision, mais sa publication effective reste soumise à
cette revue) — pas quelque chose que ce dépôt ou moi pouvons accélérer.

## Ce qui manque : librlottie (animations Lottie)

`librlottie0-1` sur `core24` (`0.1+dfsg-4ubuntu1.1+esm1`) vit dans le
dépôt Ubuntu Pro/ESM — un vrai `401 Unauthorized` a été renvoyé en
essayant de le stager ici, faute de token Ubuntu Pro sur la machine de
build. Lottie degrade déjà proprement à "pas d'overlay affiché" quand
la bibliothèque est absente (voir `linux/phpnitro_desktop/
lottie_render.py`) — ce snap vit dans cette situation par défaut,
MapView/VideoPlayer/tout le reste restant intacts.

## Vérifié pour de vrai avant publication

- `phpx new mon-app --all` : les cinq dossiers de plateforme
  (`android`/`ios`/`linux`/`macos`/`windows`) sont bien présents dans
  le projet scaffoldé — un vrai bug de `box.json` (les trois dossiers
  desktop n'y étaient jamais listés) a été trouvé et corrigé
  spécifiquement en testant CE snap, pas en relisant le code.
- `phpx run`, lancé à travers ce snap (PHP/Python3/GTK4/Shumate
  entièrement bundlés, rien du système hôte), a réellement ouvert une
  fenêtre sur un vrai affichage — confirmé visuellement.
- Extraction directe du `.snap` (`unsquashfs`, sans `snap install` —
  pas de sudo disponible pendant ce test) pour confirmer le contenu
  réel du paquet plutôt que de supposer que `stage-packages` avait
  fonctionné comme prévu ; a permis de trouver deux bugs réels avant
  publication : ni `php` ni `python3` n'existaient sous
  `$SNAP/usr/bin/` (les deux paquets système correspondants sont des
  métapaquets qui ne remontent jamais eux-mêmes le binaire versionné —
  `php8.4`/`python3.12` — comme dépendance de stage automatique).

## Builder et tester localement

```bash
snapcraft --destructive-mode   # pas de LXD/multipass configuré ici — build direct sur l'hôte
sudo snap install --dangerous --classic ./phpx_0.1.0_amd64.snap
```

Sans `sudo` disponible, `unsquashfs -d out/ phpx_0.1.0_amd64.snap` +
simuler les variables d'environnement que snapd fournirait
normalement (`SNAP`, `SNAP_ARCH`) reste la seule façon de vérifier le
contenu réel du paquet — voir l'historique de ce fichier pour le détail
exact des variables utilisées.
