"use client";

import Link from "next/link";

export default function StatsPage() {
  // Données de statistiques d'exemple
  const rarityStats = [
    { rarity: "Légendaire", owned: 1, total: 5, percentage: 20 },
    { rarity: "Épique", owned: 2, total: 10, percentage: 20 },
    { rarity: "Rare", owned: 5, total: 20, percentage: 25 },
    { rarity: "Commun", owned: 15, total: 40, percentage: 37.5 },
  ];

  const setStats = [
    { set: "Extension Basique", owned: 12, total: 30, percentage: 40 },
    { set: "Extension 1", owned: 8, total: 25, percentage: 32 },
    { set: "Extension 2", owned: 3, total: 20, percentage: 15 },
  ];

  const totalOwned = rarityStats.reduce((sum, stat) => sum + stat.owned, 0);
  const totalCards = rarityStats.reduce((sum, stat) => sum + stat.total, 0);
  const overallCompletion = Math.round((totalOwned / totalCards) * 100);

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-900 dark:to-gray-800">
      <header className="bg-white dark:bg-gray-800 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                Statistiques
              </h1>
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Analysez votre progression et vos statistiques de collection
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
        {/* Vue d'ensemble */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 mb-8">
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            Vue d&apos;ensemble
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="text-center">
              <div className="text-5xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                {totalOwned}
              </div>
              <div className="text-gray-600 dark:text-gray-400">
                Cartes possédées
              </div>
            </div>
            <div className="text-center">
              <div className="text-5xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                {totalCards}
              </div>
              <div className="text-gray-600 dark:text-gray-400">
                Cartes au total
              </div>
            </div>
            <div className="text-center">
              <div className="text-5xl font-bold text-green-600 dark:text-green-400 mb-2">
                {overallCompletion}%
              </div>
              <div className="text-gray-600 dark:text-gray-400">
                Complétion globale
              </div>
            </div>
          </div>
        </div>

        {/* Statistiques par rareté */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 mb-8">
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            Progression par rareté
          </h2>
          <div className="space-y-6">
            {rarityStats.map((stat) => (
              <div key={stat.rarity}>
                <div className="flex justify-between items-center mb-2">
                  <div className="flex items-center gap-2">
                    <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${
                      stat.rarity === "Légendaire" ? "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200" :
                      stat.rarity === "Épique" ? "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200" :
                      stat.rarity === "Rare" ? "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200" :
                      "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                    }`}>
                      {stat.rarity}
                    </span>
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      {stat.owned} / {stat.total}
                    </span>
                  </div>
                  <span className="text-sm font-semibold text-gray-900 dark:text-white">
                    {stat.percentage}%
                  </span>
                </div>
                <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                  <div
                    className={`h-full transition-all duration-500 ${
                      stat.rarity === "Légendaire" ? "bg-gradient-to-r from-yellow-400 to-yellow-600" :
                      stat.rarity === "Épique" ? "bg-gradient-to-r from-purple-400 to-purple-600" :
                      stat.rarity === "Rare" ? "bg-gradient-to-r from-blue-400 to-blue-600" :
                      "bg-gradient-to-r from-gray-400 to-gray-600"
                    }`}
                    style={{ width: `${stat.percentage}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Statistiques par extension */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 mb-8">
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            Progression par extension
          </h2>
          <div className="space-y-6">
            {setStats.map((stat) => (
              <div key={stat.set}>
                <div className="flex justify-between items-center mb-2">
                  <div className="flex items-center gap-2">
                    <span className="text-base font-medium text-gray-900 dark:text-white">
                      {stat.set}
                    </span>
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      {stat.owned} / {stat.total}
                    </span>
                  </div>
                  <span className="text-sm font-semibold text-gray-900 dark:text-white">
                    {stat.percentage}%
                  </span>
                </div>
                <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-500"
                    style={{ width: `${stat.percentage}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Graphique de répartition */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8">
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
              Répartition par rareté
            </h2>
            <div className="space-y-4">
              {rarityStats.map((stat) => (
                <div key={stat.rarity} className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className={`w-4 h-4 rounded ${
                      stat.rarity === "Légendaire" ? "bg-yellow-500" :
                      stat.rarity === "Épique" ? "bg-purple-500" :
                      stat.rarity === "Rare" ? "bg-blue-500" :
                      "bg-gray-500"
                    }`} />
                    <span className="text-gray-900 dark:text-white">{stat.rarity}</span>
                  </div>
                  <span className="text-gray-600 dark:text-gray-400">
                    {stat.owned} ({Math.round((stat.owned / totalOwned) * 100)}%)
                  </span>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8">
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">
              Points clés
            </h2>
            <div className="space-y-4">
              <div className="flex items-start gap-3">
                <span className="text-2xl">🎯</span>
                <div>
                  <div className="font-semibold text-gray-900 dark:text-white">
                    Objectif proche
                  </div>
                  <div className="text-sm text-gray-600 dark:text-gray-400">
                    Plus que {Math.ceil(totalCards * 0.5) - totalOwned} cartes pour atteindre 50% de complétion
                  </div>
                </div>
              </div>
              <div className="flex items-start gap-3">
                <span className="text-2xl">⭐</span>
                <div>
                  <div className="font-semibold text-gray-900 dark:text-white">
                    Rareté à améliorer
                  </div>
                  <div className="text-sm text-gray-600 dark:text-gray-400">
                    Les cartes Légendaires sont les plus rares dans votre collection
                  </div>
                </div>
              </div>
              <div className="flex items-start gap-3">
                <span className="text-2xl">📦</span>
                <div>
                  <div className="font-semibold text-gray-900 dark:text-white">
                    Extension prioritaire
                  </div>
                  <div className="text-sm text-gray-600 dark:text-gray-400">
                    Concentrez-vous sur &quot;Extension 2&quot; pour améliorer votre collection
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
