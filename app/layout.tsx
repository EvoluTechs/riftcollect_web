import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "RiftCollect - Gestionnaire de collection Riftbound TCG",
  description: "Application de gestion de collection pour le jeu de cartes Riftbound. Parcourez les cartes, gérez votre collection et suivez vos statistiques.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr">
      <body className="antialiased">
        {children}
      </body>
    </html>
  );
}
