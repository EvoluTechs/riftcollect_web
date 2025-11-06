"use client";

import { useState } from "react";
import Link from "next/link";

// Types pour les cartes
interface Card {
  id: string;
  name: string;
  rarity: string;
  set: string;
  type: string;
  imageUrl?: string;
}

export default function CardsPage() {
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedRarity, setSelectedRarity] = useState("all");
  const [selectedSet, setSelectedSet] = useState("all");

  // Données d'exemple - à remplacer par l'API Riftbound
  const mockCards: Card[] = [
    { id: "1", name: "Dragon des Rifts", rarity: "Légendaire", set: "Extension Basique", type: "Créature" },
    { id: "2", name: "Gardien Mystique", rarity: "Rare", set: "Extension Basique", type: "Créature" },
    { id: "3", name: "Épée de Lumière", rarity: "Épique", set: "Extension Basique", type: "Équipement" },
    { id: "4", name: "Bouclier Éternel", rarity: "Rare", set: "Extension 1", type: "Équipement" },
    { id: "5", name: "Mage de Bataille", rarity: "Commun", set: "Extension 1", type: "Créature" },
    { id: "6", name: "Portail des Ombres", rarity: "Épique", set: "Extension 2", type: "Sort" },
  ];

  const filteredCards = mockCards.filter(card => {
    const matchesSearch = card.name.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesRarity = selectedRarity === "all" || card.rarity === selectedRarity;
    const matchesSet = selectedSet === "all" || card.set === selectedSet;
    return matchesSearch && matchesRarity && matchesSet;
  });

  const rarities = ["all", ...Array.from(new Set(mockCards.map(c => c.rarity)))];
  const sets = ["all", ...Array.from(new Set(mockCards.map(c => c.set)))];

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-900 dark:to-gray-800">
      <header className="bg-white dark:bg-gray-800 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                Base de cartes
              </h1>
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Parcourez toutes les cartes Riftbound TCG
              </p>
            </div>
            <Link 
              href="/" 
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Retour
            </Link>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {/* Filtres */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-4">
            Filtres
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label htmlFor="search" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Rechercher
              </label>
              <input
                id="search"
                type="text"
                placeholder="Nom de la carte..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div>
              <label htmlFor="rarity" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Rareté
              </label>
              <select
                id="rarity"
                value={selectedRarity}
                onChange={(e) => setSelectedRarity(e.target.value)}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                {rarities.map(rarity => (
                  <option key={rarity} value={rarity}>
                    {rarity === "all" ? "Toutes" : rarity}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="set" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Extension
              </label>
              <select
                id="set"
                value={selectedSet}
                onChange={(e) => setSelectedSet(e.target.value)}
                className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                {sets.map(set => (
                  <option key={set} value={set}>
                    {set === "all" ? "Toutes" : set}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
            {filteredCards.length} carte{filteredCards.length !== 1 ? "s" : ""} trouvée{filteredCards.length !== 1 ? "s" : ""}
          </div>
        </div>

        {/* Grille de cartes */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {filteredCards.map((card) => (
            <div 
              key={card.id} 
              className="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow"
            >
              <div className="h-48 bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900 dark:to-purple-900 flex items-center justify-center">
                <span className="text-6xl">🃏</span>
              </div>
              <div className="p-4">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                  {card.name}
                </h3>
                <div className="space-y-1 text-sm">
                  <p className="text-gray-600 dark:text-gray-300">
                    <span className="font-medium">Type:</span> {card.type}
                  </p>
                  <p className="text-gray-600 dark:text-gray-300">
                    <span className="font-medium">Rareté:</span> {card.rarity}
                  </p>
                  <p className="text-gray-600 dark:text-gray-300">
                    <span className="font-medium">Extension:</span> {card.set}
                  </p>
                </div>
                <button className="mt-4 w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                  Ajouter à ma collection
                </button>
              </div>
            </div>
          ))}
        </div>

        {filteredCards.length === 0 && (
          <div className="text-center py-12">
            <p className="text-gray-600 dark:text-gray-400 text-lg">
              Aucune carte trouvée avec ces critères
            </p>
          </div>
        )}

        {/* Note API */}
        <div className="mt-12 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
            💡 Note sur l&apos;intégration API
          </h3>
          <p className="text-blue-800 dark:text-blue-200">
            Les cartes affichées sont des données d&apos;exemple. L&apos;intégration avec l&apos;API Riftbound 
            permettra d&apos;afficher les vraies cartes avec images, descriptions complètes et métadonnées.
          </p>
        </div>
      </main>
    </div>
  );
}
