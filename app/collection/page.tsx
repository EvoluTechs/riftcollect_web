"use client";

import { useState } from "react";
import Link from "next/link";

interface CollectionCard {
  id: string;
  name: string;
  rarity: string;
  set: string;
  owned: number;
  duplicates: number;
}

export default function CollectionPage() {
  const [view, setView] = useState<"owned" | "missing" | "duplicates">("owned");

  // Données d'exemple de collection
  const mockCollection: CollectionCard[] = [
    { id: "1", name: "Dragon des Rifts", rarity: "Légendaire", set: "Extension Basique", owned: 1, duplicates: 0 },
    { id: "2", name: "Gardien Mystique", rarity: "Rare", set: "Extension Basique", owned: 2, duplicates: 1 },
    { id: "3", name: "Épée de Lumière", rarity: "Épique", set: "Extension Basique", owned: 0, duplicates: 0 },
    { id: "4", name: "Bouclier Éternel", rarity: "Rare", set: "Extension 1", owned: 3, duplicates: 2 },
    { id: "5", name: "Mage de Bataille", rarity: "Commun", set: "Extension 1", owned: 5, duplicates: 4 },
    { id: "6", name: "Portail des Ombres", rarity: "Épique", set: "Extension 2", owned: 0, duplicates: 0 },
  ];

  const ownedCards = mockCollection.filter(c => c.owned > 0);
  const missingCards = mockCollection.filter(c => c.owned === 0);
  const duplicateCards = mockCollection.filter(c => c.duplicates > 0);

  const totalCards = mockCollection.length;
  const ownedCount = ownedCards.length;
  const completionPercentage = Math.round((ownedCount / totalCards) * 100);

  const displayedCards = view === "owned" ? ownedCards : 
                         view === "missing" ? missingCards : 
                         duplicateCards;

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-900 dark:to-gray-800">
      <header className="bg-white dark:bg-gray-800 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                Ma collection
              </h1>
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Gérez vos cartes Riftbound TCG
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
        {/* Statistiques */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div className="text-sm text-gray-600 dark:text-gray-400 mb-1">Total de cartes</div>
            <div className="text-3xl font-bold text-gray-900 dark:text-white">{totalCards}</div>
          </div>
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div className="text-sm text-gray-600 dark:text-gray-400 mb-1">Cartes possédées</div>
            <div className="text-3xl font-bold text-green-600 dark:text-green-400">{ownedCount}</div>
          </div>
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div className="text-sm text-gray-600 dark:text-gray-400 mb-1">Cartes manquantes</div>
            <div className="text-3xl font-bold text-orange-600 dark:text-orange-400">{missingCards.length}</div>
          </div>
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div className="text-sm text-gray-600 dark:text-gray-400 mb-1">Complétion</div>
            <div className="text-3xl font-bold text-blue-600 dark:text-blue-400">{completionPercentage}%</div>
          </div>
        </div>

        {/* Barre de progression */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-4">
            Progression de la collection
          </h2>
          <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6 overflow-hidden">
            <div 
              className="bg-gradient-to-r from-blue-500 to-purple-500 h-full flex items-center justify-center text-white text-sm font-semibold transition-all duration-500"
              style={{ width: `${completionPercentage}%` }}
            >
              {completionPercentage}%
            </div>
          </div>
        </div>

        {/* Onglets de vue */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md mb-8">
          <div className="border-b border-gray-200 dark:border-gray-700">
            <nav className="flex -mb-px">
              <button
                onClick={() => setView("owned")}
                className={`px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                  view === "owned"
                    ? "border-blue-500 text-blue-600 dark:text-blue-400"
                    : "border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                }`}
              >
                Cartes possédées ({ownedCards.length})
              </button>
              <button
                onClick={() => setView("missing")}
                className={`px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                  view === "missing"
                    ? "border-blue-500 text-blue-600 dark:text-blue-400"
                    : "border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                }`}
              >
                Cartes manquantes ({missingCards.length})
              </button>
              <button
                onClick={() => setView("duplicates")}
                className={`px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                  view === "duplicates"
                    ? "border-blue-500 text-blue-600 dark:text-blue-400"
                    : "border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                }`}
              >
                Doublons ({duplicateCards.length})
              </button>
            </nav>
          </div>
        </div>

        {/* Liste de cartes */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Carte
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Rareté
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Extension
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Quantité
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {displayedCards.map((card) => (
                  <tr key={card.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900 dark:text-white">
                        {card.name}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        card.rarity === "Légendaire" ? "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200" :
                        card.rarity === "Épique" ? "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200" :
                        card.rarity === "Rare" ? "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200" :
                        "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                      }`}>
                        {card.rarity}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                      {card.set}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                      {view === "duplicates" ? `${card.duplicates} doublon(s)` : `${card.owned} exemplaire(s)`}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      <button className="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                        Modifier
                      </button>
                      {view === "missing" && (
                        <button className="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                          Ajouter
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {displayedCards.length === 0 && (
          <div className="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <p className="text-gray-600 dark:text-gray-400 text-lg">
              {view === "owned" && "Vous n'avez pas encore de cartes dans votre collection"}
              {view === "missing" && "Félicitations ! Votre collection est complète"}
              {view === "duplicates" && "Vous n'avez pas de doublons"}
            </p>
          </div>
        )}
      </main>
    </div>
  );
}
