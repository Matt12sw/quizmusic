<?php
session_start();

require_once 'classes/Database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = Database::getConnexion();
$stmt = $pdo->prepare("SELECT pseudo, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

$stmtBadges = $pdo->prepare("
    SELECT b.id, b.titre, b.description, b.icone, b.code, ub.awarded_at
    FROM badges b
    INNER JOIN user_badges ub ON b.id = ub.badge_id
    WHERE ub.user_id = ?
    ORDER BY ub.awarded_at DESC
");
$stmtBadges->execute([$_SESSION['user_id']]);
$userBadges = $stmtBadges->fetchAll();

$stmtAllBadges = $pdo->query("SELECT id, titre, description, icone, code FROM badges ORDER BY id ASC");
$allBadges = $stmtAllBadges->fetchAll();

$obtainedBadgeIds = array_column($userBadges, 'id');

$stmtStats = $pdo->prepare("SELECT COUNT(*) as total_parties, SUM(score) as total_points FROM scores WHERE user_id = ?");
$stmtStats->execute([$_SESSION['user_id']]);
$stats = $stmtStats->fetch();
$totalParties = $stats['total_parties'] ?? 0;
$totalPoints = $stats['total_points'] ?? 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - QuizMusic 🎵</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <nav class="flex justify-between items-center mb-8">
            <div class="text-white">
                <h2 class="text-xl font-semibold">👤 Mon profil</h2>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-all duration-200 backdrop-blur-sm">
                    🏠 Accueil
                </a>
                <a href="historique.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-all duration-200 backdrop-blur-sm">
                    📊 Historique
                </a>
                <a href="logout.php" class="bg-red-500/80 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-all duration-200">
                    🚪 Déconnexion
                </a>
            </div>
        </nav>

        <header class="text-center mb-8">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">
                👤 Mon Profil
            </h1>
            <p class="text-xl text-purple-200">
                Vos informations et vos badges
            </p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex items-center justify-center text-white text-3xl font-bold">
                            <?php echo strtoupper(substr($user['pseudo'], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($user['pseudo']); ?></h2>
                            <p class="text-gray-500">Membre de QuizMusic</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <div class="p-6 rounded-xl bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                📧 Informations du compte
                            </h3>
                            <div class="space-y-3 text-gray-700">
                                <div class="flex items-center gap-3">
                                    <span class="font-medium text-purple-600">👤 Pseudo :</span>
                                    <span><?php echo htmlspecialchars($user['pseudo']); ?></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-medium text-purple-600">📧 Email :</span>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-xl p-6 text-center">
                    <div class="text-5xl mb-2">🎮</div>
                    <div class="text-4xl font-bold text-purple-600 mb-2"><?php echo $totalParties; ?></div>
                    <div class="text-gray-600">Parties jouées</div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl p-6 text-center">
                    <div class="text-5xl mb-2">⭐</div>
                    <div class="text-4xl font-bold text-blue-600 mb-2"><?php echo $totalPoints; ?></div>
                    <div class="text-gray-600">Points totaux</div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl p-6 text-center">
                    <div class="text-5xl mb-2">🏆</div>
                    <div class="text-4xl font-bold text-yellow-600 mb-2"><?php echo count($userBadges); ?></div>
                    <div class="text-gray-600">Badges obtenus</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                🏆 Mes Badges
            </h2>

            <?php if (count($userBadges) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php foreach ($userBadges as $badge): ?>
                        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 border-2 border-yellow-300 rounded-xl p-6 transform hover:scale-105 transition-all duration-200 shadow-md">
                            <div class="text-center">
                                <div class="text-6xl mb-3"><?php echo $badge['icone']; ?></div>
                                <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($badge['titre']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($badge['description']); ?></p>
                                <p class="text-xs text-gray-500">
                                    Obtenu le <?php echo date('d/m/Y', strtotime($badge['awarded_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-6xl mb-4">🎯</div>
                    <p class="text-gray-600 text-lg">Vous n'avez pas encore de badges.</p>
                    <p class="text-gray-500">Jouez à des quiz pour en débloquer !</p>
                </div>
            <?php endif; ?>

            <?php
            $lockedBadges = array_filter($allBadges, function($badge) use ($obtainedBadgeIds) {
                return !in_array($badge['id'], $obtainedBadgeIds);
            });
            ?>

            <?php if (count($lockedBadges) > 0): ?>
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">🔒 Badges à débloquer</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($lockedBadges as $badge): ?>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-6 opacity-60">
                                <div class="text-center">
                                    <div class="text-6xl mb-3 grayscale"><?php echo $badge['icone']; ?></div>
                                    <h3 class="text-xl font-bold text-gray-600 mb-2"><?php echo htmlspecialchars($badge['titre']); ?></h3>
                                    <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($badge['description']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 text-center">
            <h2 class="text-2xl font-bold text-white mb-4">
                💡 Comment obtenir des badges ?
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-purple-200">
                <div>
                    <div class="text-3xl mb-2">🎮</div>
                    <h3 class="font-semibold mb-2">Jouez régulièrement</h3>
                    <p class="text-sm">Participez à des quiz pour débloquer des badges</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">🎯</div>
                    <h3 class="font-semibold mb-2">Relevez des défis</h3>
                    <p class="text-sm">Obtenez des scores parfaits et explorez tous les thèmes</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">🏆</div>
                    <h3 class="font-semibold mb-2">Collectionnez-les tous</h3>
                    <p class="text-sm">Débloquez tous les badges disponibles</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-8">
        <p class="text-purple-300 text-sm">🎵 QuizMusic - Profil utilisateur</p>
    </footer>
</body>
</html>
