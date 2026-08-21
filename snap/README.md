# Le snap `phpx`

`snap install phpx` installe `phpx` avec son propre PHP embarqué, plus
tout ce que `phpx run` a besoin pour lancer le desktop Linux d'un
projet scaffoldé (`--all`) sans rien à installer soi-même : Python 3,
PyGObject/GTK4, Cairo, libshumate (MapView).

## Pourquoi `confinement: strict` (et pas `classic`)

Une première version utilisait `classic` (accès disque/réseau/USB non
restreint) — abandonnée après avoir réellement tenté de publier :
`classic` nécessite systématiquement une revue manuelle de Canonical
avant de pouvoir sortir sur un canal réel du Snap Store, peu importe
qui publie (`snapcraft upload --release=stable` réussit à téléverser
la révision, mais reste bloqué en "will need manual review" tant que
cette revue n'a pas eu lieu). Pas quelque chose que ce dépôt ou moi
pouvons accélérer, et pas nécessaire : chaque besoin réel de `phpx`
correspond à une interface `strict` standard, listée avec sa raison
dans `snapcraft.yaml`'s propre commentaire (`home`, `network`+
`network-bind`, `desktop`/`desktop-legacy`/`wayland`/`x11`/`opengl`
pour la fenêtre GTK4, `raw-usb` pour `adb`).

**Restriction réelle acceptée en échange** : `phpx new`/`serve`/`run`
ne peuvent plus scaffolder/servir un projet que sous `$HOME` — plus de
chemin arbitraire ailleurs sur le disque, ce que `classic` permettait.
Couvre l'usage normal (`cd ~/projects && phpx new mon-app`).

**Friction résiduelle** : `raw-usb` (pour `adb`) ne se connecte pas
automatiquement à l'installation — `sudo snap connect phpx:raw-usb`
une fois, avant que `dev:push`/`run`'s cible Android fonctionnent.

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
sudo snap install --dangerous ./phpx_0.1.0_amd64.snap
sudo snap connect phpx:raw-usb   # une fois, pour dev:push/run sur Android
```

**Limite honnête** : sans `sudo` disponible dans l'environnement où ce
fichier a été écrit, `unsquashfs -d out/ phpx_0.1.0_amd64.snap` +
simuler les variables d'environnement que snapd fournirait normalement
(`SNAP`, `SNAP_ARCH`) a permis de vérifier le CONTENU du paquet (les
bons binaires présents, le script wrapper qui résout les bons chemins,
`phpx run` ouvrant réellement une fenêtre) — mais pas le sandbox
`strict` lui-même (apparmor/seccomp), qui ne s'active que via un vrai
`snap install`. Si `phpx new`/`run` échouent avec une erreur de
permission une fois installé pour de vrai, c'est le premier endroit à
vérifier — un plug manquant dans `snapcraft.yaml`'s `apps.phpx.plugs`,
pas un bug du framework lui-même.
