# RiftCollect

RiftCollect est une application web indépendante créée pour la communauté francophone des collectionneurs du TCG Riftbound.

![Homepage](https://github.com/user-attachments/assets/856d0bb0-5703-48ab-8734-6bf7409592dd)

## 🎯 Objectifs

L'objectif est de proposer un espace simple et visuel pour :

- **Parcourir la base officielle des cartes** via l'API Riftbound
- **Gérer sa propre collection** (cartes possédées, manquantes, doublons, etc.)
- **Recevoir des notifications** lors de la sortie de nouvelles extensions ou événements
- **Consulter des statistiques** de rareté et de progression de collection

## 🚀 Technologies utilisées

- **Next.js 16** - Framework React avec App Router
- **TypeScript** - Pour un code type-safe
- **Tailwind CSS** - Pour un design moderne et responsive
- **React 19** - Dernière version de React

## 📋 Fonctionnalités

### 1. Parcourir les cartes
![Cards Page](https://github.com/user-attachments/assets/d206b6f7-dbc5-44ef-a46a-cd8a131990f2)

- Exploration de la base de données des cartes Riftbound
- Filtres par rareté, extension et recherche par nom
- Interface de carte avec détails (type, rareté, extension)
- Bouton d'ajout rapide à la collection

### 2. Gérer sa collection
![Collection Page](https://github.com/user-attachments/assets/39a1444c-d5c1-4bc6-b364-e4991f520cc4)

- Vue d'ensemble avec statistiques (total, possédées, manquantes, complétion)
- Barre de progression visuelle
- Onglets pour filtrer : cartes possédées, manquantes, doublons
- Gestion de quantités par carte

### 3. Statistiques détaillées
![Stats Page](https://github.com/user-attachments/assets/d697ee26-b0d4-4563-be5b-3a9b3c06ae8c)

- Vue d'ensemble globale
- Progression par rareté avec barres de progression
- Progression par extension
- Répartition de la collection
- Points clés et recommandations

### 4. Notifications
![Notifications Page](https://github.com/user-attachments/assets/d049446b-f3da-4094-ad53-e8ae7149298a)

- Liste des notifications (extensions, événements, infos)
- Filtres : toutes / non lues
- Gestion des notifications individuelles
- Paramètres de notification (email, push, types)

## 🛠️ Installation

### Prérequis

- Node.js 18+ 
- npm ou yarn

### Installation des dépendances

```bash
npm install
```

### Lancement en développement

```bash
npm run dev
```

L'application sera accessible sur [http://localhost:3000](http://localhost:3000)

### Build de production

```bash
npm run build
npm start
```

### Linting

```bash
npm run lint
```

## 📁 Structure du projet

```
riftcollect_web/
├── app/                      # Application Next.js (App Router)
│   ├── cards/               # Page de parcours des cartes
│   ├── collection/          # Page de gestion de collection
│   ├── stats/               # Page de statistiques
│   ├── notifications/       # Page de notifications
│   ├── layout.tsx           # Layout principal
│   ├── page.tsx             # Page d'accueil
│   └── globals.css          # Styles globaux
├── public/                  # Assets statiques
├── tailwind.config.ts       # Configuration Tailwind CSS
├── tsconfig.json           # Configuration TypeScript
├── next.config.js          # Configuration Next.js
└── package.json            # Dépendances du projet
```

## 🔮 Prochaines étapes

### Intégration API Riftbound
- Connexion à l'API officielle Riftbound
- Récupération des vraies données de cartes avec images
- Synchronisation en temps réel

### Gestion utilisateur
- Authentification (email/mot de passe)
- Profils utilisateurs
- Sauvegarde de collection en base de données

### Fonctionnalités avancées
- Système de wishlist
- Échange de cartes entre utilisateurs
- Graphiques de progression avancés
- Export de collection (CSV, PDF)
- Mode hors ligne (PWA)

### Optimisations
- Cache des données
- Optimisation des images
- SEO et métadonnées
- Tests unitaires et e2e

## 📄 Licence

ISC

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

## 💬 Support

Pour toute question ou suggestion, n'hésitez pas à ouvrir une issue sur GitHub.
