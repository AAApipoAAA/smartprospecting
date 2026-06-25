# SmartProspecting — Module Dolibarr

> Trouvez et importez vos prospects automatiquement depuis INSEE, Pappers, Google Places et plus encore.

[![Dolibarr](https://img.shields.io/badge/Dolibarr-17%2B-blue)](https://www.dolibarr.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![Licence](https://img.shields.io/badge/Licence-GPLv3-green)](LICENSE)

---

## ✨ Fonctionnalités V1

- 🔍 **Recherche multicritères** : secteur NAF, département, rayon géographique
- 🏛️ **Source INSEE SIRENE** : 10 millions d'entreprises françaises (gratuit)
- 📋 **Source Pappers.fr** : données enrichies + dirigeants (clé API requise)
- ✅ **Import automatique** : création des Tiers Dolibarr en un clic
- 🔄 **Déduplication** : par SIRET et par nom (évite les doublons)
- 📊 **Dashboard** : statistiques d'import et historique des recherches
- 👤 **Création de contacts** : dirigeant importé comme contact Dolibarr

## 🗺️ Roadmap

### V1 (actuelle)
- [x] Squelette module Dolibarr
- [x] Connecteur INSEE SIRENE
- [x] Connecteur Pappers.fr
- [x] Import manager avec déduplication
- [x] Dashboard et historique
- [x] Page de configuration clés API
- [ ] Page de résultats détaillée (`search_result.php`)
- [ ] Tests sur serveur réel

### V2 (prochaine)
- [ ] Connecteur Google Places
- [ ] Enrichissement email (Hunter.io / Dropcontact)
- [ ] Séquences de relance automatiques
- [ ] Scoring IA des prospects
- [ ] Cron d'import planifié

### V3 (future)
- [ ] Intégration LinkedIn (via extension)
- [ ] IA générative pour personnaliser les emails
- [ ] Alertes nouvelles entreprises dans la zone
- [ ] Mode white-label pour intégrateurs

---

## 🚀 Installation

### Prérequis
- Dolibarr 17.0 ou supérieur
- PHP 7.4 ou supérieur
- Extensions PHP : `curl`, `json`, `mbstring`

### Installation manuelle

1. **Clonez le dépôt** dans le dossier `custom` de Dolibarr :
```bash
cd /var/www/html/dolibarr/htdocs/custom/
git clone https://github.com/votre-repo/smartprospecting.git
```

2. **Activez le module** dans Dolibarr :
   - Menu → Configuration → Modules
   - Cherchez "SmartProspecting"
   - Cliquez sur "Activer"

3. **Configurez les clés API** :
   - Menu SmartProspecting → Configuration
   - Ajoutez vos clés INSEE, Pappers, etc.

### Structure des fichiers
```
htdocs/custom/smartprospecting/
├── core/modules/modSmartProspecting/    # Descripteur module
│   └── modSmartProspecting.class.php
├── class/                               # Classes PHP
│   ├── SmartProspecting.class.php       # Classe principale
│   ├── SmartProspectingSourceINSEE.class.php
│   ├── SmartProspectingSourcePappers.class.php
│   └── SmartProspectingImportManager.class.php
├── sql/                                 # Tables BDD
│   ├── llx_smartprospecting.sql
│   └── llx_smartprospecting.key.sql
├── admin/                               # Pages admin
│   └── setup.php
├── lib/                                 # Helpers
│   └── smartprospecting.lib.php
├── langs/                               # Traductions
│   ├── fr_FR/smartprospecting.lang
│   └── en_US/smartprospecting.lang
├── index.php                            # Dashboard
├── search.php                           # Formulaire recherche
├── history.php                          # Historique
└── sequences.php                        # Séquences (V2)
```

---

## 🔑 APIs utilisées

| Source | Type | Coût | Documentation |
|--------|------|------|---------------|
| **INSEE SIRENE** | Officielle | Gratuit (30 req/min) | [api.insee.fr](https://api.insee.fr) |
| **Pappers.fr** | Officielle | Gratuit 500 req/mois | [pappers.fr/api](https://www.pappers.fr/api) |
| **Google Places** | Officielle | ~0,017$/req | [developers.google.com](https://developers.google.com/maps/documentation/places/web-service) |
| **Hunter.io** | Officielle | Gratuit 25 req/mois | [hunter.io](https://hunter.io/api) |
| **Dropcontact** | Officielle | Payant | [dropcontact.com](https://www.dropcontact.com) |
| **API Géo** | Officielle | Gratuit | [api-adresse.data.gouv.fr](https://api-adresse.data.gouv.fr) |

---

## ⚖️ RGPD et légalité

- ✅ **INSEE SIRENE** : données publiques légales, pas de problème
- ✅ **Pappers.fr** : données issues du registre officiel (INPI)
- ⚠️ **Emails nominatifs** : sourcer uniquement depuis des bases opt-in
- ✅ **Prospection B2B** : légale sous "intérêt légitime" (RGPD Art. 6.1.f)
- 📋 **Obligation** : mention légale dans les premiers emails de prospection

---

## 🤝 Contribution

Les PR sont les bienvenues ! Voir [CONTRIBUTING.md](CONTRIBUTING.md).

## 📄 Licence

GPLv3 — voir [LICENSE](LICENSE)
