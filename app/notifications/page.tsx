"use client";

import { useState } from "react";
import Link from "next/link";

interface Notification {
  id: string;
  type: "extension" | "event" | "info";
  title: string;
  message: string;
  date: string;
  read: boolean;
}

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<Notification[]>([
    {
      id: "1",
      type: "extension",
      title: "Nouvelle extension disponible !",
      message: "Extension 3: Les Gardiens du Rift est maintenant disponible avec 50 nouvelles cartes à découvrir.",
      date: "2024-01-15",
      read: false,
    },
    {
      id: "2",
      type: "event",
      title: "Tournoi communautaire",
      message: "Participez au tournoi mensuel ce weekend ! Inscriptions ouvertes jusqu'au vendredi.",
      date: "2024-01-12",
      read: false,
    },
    {
      id: "3",
      type: "info",
      title: "Mise à jour des raretés",
      message: "Les statistiques de rareté ont été mises à jour suite aux derniers ajouts.",
      date: "2024-01-10",
      read: true,
    },
    {
      id: "4",
      type: "extension",
      title: "Cartes promo disponibles",
      message: "De nouvelles cartes promotionnelles sont disponibles pour une durée limitée.",
      date: "2024-01-08",
      read: true,
    },
  ]);

  const [filter, setFilter] = useState<"all" | "unread">("all");
  const [emailNotifications, setEmailNotifications] = useState(true);
  const [pushNotifications, setPushNotifications] = useState(false);
  const [extensionNotifications, setExtensionNotifications] = useState(true);
  const [eventNotifications, setEventNotifications] = useState(true);

  const markAsRead = (id: string) => {
    setNotifications(notifications.map(notif => 
      notif.id === id ? { ...notif, read: true } : notif
    ));
  };

  const markAllAsRead = () => {
    setNotifications(notifications.map(notif => ({ ...notif, read: true })));
  };

  const deleteNotification = (id: string) => {
    setNotifications(notifications.filter(notif => notif.id !== id));
  };

  const filteredNotifications = filter === "all" 
    ? notifications 
    : notifications.filter(n => !n.read);

  const unreadCount = notifications.filter(n => !n.read).length;

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case "extension":
        return "🎴";
      case "event":
        return "🎮";
      case "info":
        return "ℹ️";
      default:
        return "🔔";
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-900 dark:to-gray-800">
      <header className="bg-white dark:bg-gray-800 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                Notifications
              </h1>
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Restez informé des dernières actualités Riftbound
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
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Liste des notifications */}
          <div className="lg:col-span-2 space-y-6">
            {/* Barre d'actions */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex gap-2">
                  <button
                    onClick={() => setFilter("all")}
                    className={`px-4 py-2 rounded-md transition-colors ${
                      filter === "all"
                        ? "bg-blue-600 text-white"
                        : "bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600"
                    }`}
                  >
                    Toutes ({notifications.length})
                  </button>
                  <button
                    onClick={() => setFilter("unread")}
                    className={`px-4 py-2 rounded-md transition-colors ${
                      filter === "unread"
                        ? "bg-blue-600 text-white"
                        : "bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600"
                    }`}
                  >
                    Non lues ({unreadCount})
                  </button>
                </div>
                <button
                  onClick={markAllAsRead}
                  className="px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:underline"
                  disabled={unreadCount === 0}
                >
                  Tout marquer comme lu
                </button>
              </div>
            </div>

            {/* Notifications */}
            <div className="space-y-4">
              {filteredNotifications.length === 0 ? (
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-12 text-center">
                  <div className="text-6xl mb-4">📭</div>
                  <p className="text-gray-600 dark:text-gray-400 text-lg">
                    {filter === "unread" 
                      ? "Aucune notification non lue"
                      : "Aucune notification"}
                  </p>
                </div>
              ) : (
                filteredNotifications.map((notification) => (
                  <div
                    key={notification.id}
                    className={`bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 transition-all ${
                      !notification.read ? "border-l-4 border-blue-500" : ""
                    }`}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex items-start gap-4 flex-1">
                        <div className="text-3xl">{getNotificationIcon(notification.type)}</div>
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                              {notification.title}
                            </h3>
                            {!notification.read && (
                              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                Nouveau
                              </span>
                            )}
                          </div>
                          <p className="text-gray-600 dark:text-gray-300 mb-2">
                            {notification.message}
                          </p>
                          <p className="text-sm text-gray-500 dark:text-gray-400">
                            {new Date(notification.date).toLocaleDateString("fr-FR", {
                              day: "numeric",
                              month: "long",
                              year: "numeric",
                            })}
                          </p>
                        </div>
                      </div>
                      <div className="flex gap-2">
                        {!notification.read && (
                          <button
                            onClick={() => markAsRead(notification.id)}
                            className="text-blue-600 dark:text-blue-400 hover:underline text-sm"
                          >
                            Marquer comme lu
                          </button>
                        )}
                        <button
                          onClick={() => deleteNotification(notification.id)}
                          className="text-red-600 dark:text-red-400 hover:underline text-sm"
                        >
                          Supprimer
                        </button>
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Paramètres de notifications */}
          <div className="lg:col-span-1">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 sticky top-6">
              <h2 className="text-xl font-bold text-gray-900 dark:text-white mb-6">
                Paramètres
              </h2>
              
              <div className="space-y-6">
                <div>
                  <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    Canaux de notification
                  </h3>
                  <div className="space-y-3">
                    <label className="flex items-center justify-between cursor-pointer">
                      <span className="text-sm text-gray-700 dark:text-gray-300">
                        Notifications email
                      </span>
                      <input
                        type="checkbox"
                        checked={emailNotifications}
                        onChange={(e) => setEmailNotifications(e.target.checked)}
                        className="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                      />
                    </label>
                    <label className="flex items-center justify-between cursor-pointer">
                      <span className="text-sm text-gray-700 dark:text-gray-300">
                        Notifications push
                      </span>
                      <input
                        type="checkbox"
                        checked={pushNotifications}
                        onChange={(e) => setPushNotifications(e.target.checked)}
                        className="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                      />
                    </label>
                  </div>
                </div>

                <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
                  <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    Types de notification
                  </h3>
                  <div className="space-y-3">
                    <label className="flex items-center justify-between cursor-pointer">
                      <span className="text-sm text-gray-700 dark:text-gray-300">
                        Nouvelles extensions
                      </span>
                      <input
                        type="checkbox"
                        checked={extensionNotifications}
                        onChange={(e) => setExtensionNotifications(e.target.checked)}
                        className="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                      />
                    </label>
                    <label className="flex items-center justify-between cursor-pointer">
                      <span className="text-sm text-gray-700 dark:text-gray-300">
                        Événements
                      </span>
                      <input
                        type="checkbox"
                        checked={eventNotifications}
                        onChange={(e) => setEventNotifications(e.target.checked)}
                        className="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
                      />
                    </label>
                  </div>
                </div>

                <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
                  <button className="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    Enregistrer les paramètres
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
