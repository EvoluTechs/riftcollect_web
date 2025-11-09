import Link from "next/link";

export default function Home() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-900 dark:to-gray-800">
      <header className="bg-white dark:bg-gray-800 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
            RiftCollect
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
            Gestionnaire de collection Riftbound TCG
          </p>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="text-center mb-12">
          <h2 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Bienvenue sur RiftCollect
          </h2>
          <p className="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            Votre espace dédié pour gérer votre collection de cartes Riftbound TCG
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
          <Link href="/cards" className="group">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 hover:shadow-xl transition-shadow">
              <div className="text-4xl mb-4">🃏</div>
              <h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                Parcourir les cartes
              </h3>
              <p className="text-gray-600 dark:text-gray-300">
                Explorez la base officielle des cartes Riftbound
              </p>
            </div>
          </Link>

          <Link href="/collection" className="group">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 hover:shadow-xl transition-shadow">
              <div className="text-4xl mb-4">📚</div>
              <h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                Ma collection
              </h3>
              <p className="text-gray-600 dark:text-gray-300">
                Gérez vos cartes possédées, manquantes et doublons
              </p>
            </div>
          </Link>

          <Link href="/stats" className="group">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 hover:shadow-xl transition-shadow">
              <div className="text-4xl mb-4">📊</div>
              <h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                Statistiques
              </h3>
              <p className="text-gray-600 dark:text-gray-300">
                Consultez vos statistiques de rareté et progression
              </p>
            </div>
          </Link>

          <Link href="/notifications" className="group">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 hover:shadow-xl transition-shadow">
              <div className="text-4xl mb-4">🔔</div>
              <h3 className="text-xl font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                Notifications
              </h3>
              <p className="text-gray-600 dark:text-gray-300">
                Restez informé des nouvelles extensions et événements
              </p>
            </div>
          </Link>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8">
          <h3 className="text-2xl font-bold text-gray-900 dark:text-white mb-4">
            Fonctionnalités principales
          </h3>
          <ul className="space-y-3 text-gray-600 dark:text-gray-300">
            <li className="flex items-start">
              <span className="text-green-500 mr-2">✓</span>
              <span>Parcourir la base officielle des cartes via l&apos;API Riftbound</span>
            </li>
            <li className="flex items-start">
              <span className="text-green-500 mr-2">✓</span>
              <span>Gérer votre collection personnelle (cartes possédées, manquantes, doublons)</span>
            </li>
            <li className="flex items-start">
              <span className="text-green-500 mr-2">✓</span>
              <span>Recevoir des notifications pour les nouvelles extensions et événements</span>
            </li>
            <li className="flex items-start">
              <span className="text-green-500 mr-2">✓</span>
              <span>Consulter des statistiques de rareté et de progression de collection</span>
            </li>
          </ul>
        </div>
      </main>

      <footer className="bg-white dark:bg-gray-800 mt-12 py-6 border-t border-gray-200 dark:border-gray-700">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-600 dark:text-gray-300">
          <p>RiftCollect - Application communautaire pour collectionneurs Riftbound TCG</p>
          <p className="text-sm mt-2">Application indépendante créée pour la communauté francophone</p>
        </div>
      </footer>
    </div>
  );
}
