<?php
// classes/Badge.php

class Badge
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie et attribue les badges pertinents après une partie.
     * @param int $userId
     * @param int|null $lastScoreId id du score inséré (optionnel mais recommandé)
     */
    public function checkAndAward(int $userId, ?int $lastScoreId = null): void
    {
        $badges = $this->getBadgesMap(); // code => id

        // 1) PREMIER PAS : jouer au moins 1 partie
        if (isset($badges['premier_pas']) && !$this->hasBadge($userId, $badges['premier_pas'])) {
            if ($this->countTotalPlays($userId) >= 1) {
                $this->awardBadge($userId, $badges['premier_pas']);
            }
        }

        // 2) EXPLORATEUR : jouer tous les thèmes au moins une fois
        if (isset($badges['explorateur']) && !$this->hasBadge($userId, $badges['explorateur'])) {
            $played = $this->countDistinctQuestionnairesPlayed($userId);
            $totalThemes = $this->countActiveQuestionnaires();
            if ($totalThemes > 0 && $played >= $totalThemes) {
                $this->awardBadge($userId, $badges['explorateur']);
            }
        }

        // 3) PERFECTIONNISTE : obtenir 10/10 (score == 10 et total_questions == 10)
        if (isset($badges['perfectionniste']) && !$this->hasBadge($userId, $badges['perfectionniste'])) {
            if ($this->hasPerfect10($userId)) {
                $this->awardBadge($userId, $badges['perfectionniste']);
            }
        }

        // 4) MARATHON : 10 parties dans la même journée
        if (isset($badges['marathon']) && !$this->hasBadge($userId, $badges['marathon'])) {
            if ($lastScoreId !== null) {
                $date = $this->getScoreDate($lastScoreId);
                if ($date && $this->countPlaysOnDate($userId, $date) >= 10) {
                    $this->awardBadge($userId, $badges['marathon']);
                }
            } else {
                if ($this->hasDayWithAtLeastNPlays($userId, 10)) {
                    $this->awardBadge($userId, $badges['marathon']);
                }
            }
        }
    }

    /* --------------------
       Méthodes utilitaires
       -------------------- */

    private function getBadgesMap(): array
    {
        $stmt = $this->pdo->query("SELECT id, code FROM badges");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['code']] = (int)$r['id'];
        }
        return $map;
    }

    private function hasBadge(int $userId, int $badgeId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM user_badges WHERE user_id = :u AND badge_id = :b LIMIT 1");
        $stmt->execute([':u' => $userId, ':b' => $badgeId]);
        return (bool)$stmt->fetchColumn();
    }

    private function awardBadge(int $userId, int $badgeId): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO user_badges (user_id, badge_id, awarded_at) VALUES (:u, :b, NOW())");
            $stmt->execute([':u' => $userId, ':b' => $badgeId]);
            return true;
        } catch (PDOException $e) {
            // doublon possible (uq_user_badge) => ignore proprement
            return false;
        }
    }

    private function countTotalPlays(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM scores WHERE user_id = :u");
        $stmt->execute([':u' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    private function countDistinctQuestionnairesPlayed(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT questionnaire_id) FROM scores WHERE user_id = :u");
        $stmt->execute([':u' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    private function countActiveQuestionnaires(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM questionnaires WHERE actif = 1");
        return (int)$stmt->fetchColumn();
    }

    private function hasPerfect10(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM scores WHERE user_id = :u AND score = 10 AND total_questions = 10 LIMIT 1");
        $stmt->execute([':u' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    private function getScoreDate(int $scoreId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT date_jeu FROM scores WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $scoreId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['date_jeu'] : null;
    }

    private function countPlaysOnDate(int $userId, string $dateTime): int
    {
        $d = (new DateTime($dateTime))->format('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM scores WHERE user_id = :u AND DATE(date_jeu) = :d");
        $stmt->execute([':u' => $userId, ':d' => $d]);
        return (int)$stmt->fetchColumn();
    }

    private function hasDayWithAtLeastNPlays(int $userId, int $n): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM (
                SELECT DATE(date_jeu) ds, COUNT(*) cnt
                FROM scores
                WHERE user_id = :u
                GROUP BY DATE(date_jeu)
                HAVING cnt >= :n
                LIMIT 1
            ) x
        ");
        $stmt->execute([':u' => $userId, ':n' => $n]);
        return (bool)$stmt->fetchColumn();
    }
}
