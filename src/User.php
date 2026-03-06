<?php

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(string $email, string $password, string $fullName): bool {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (email, password_hash, full_name) VALUES (?, ?, ?)");
        return $stmt->execute([$email, $hash, trim($fullName)]);
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT id, email, full_name, role, stripe_customer_id, subscription_status, subscription_plan, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findAuthById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT id, email, password_hash, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateSubscription(int $userId, string $status, string $plan, ?string $customerId = null): bool {
        $sql = "UPDATE users SET subscription_status = ?, subscription_plan = ?";
        $params = [$status, $plan];

        if (!empty($customerId)) {
            $sql .= ", stripe_customer_id = ?";
            $params[] = $customerId;
        }

        $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params[] = $userId;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateProfile(int $userId, string $fullName): bool {
        $stmt = $this->db->prepare("UPDATE users SET full_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([trim($fullName), $userId]);
    }

    public function updatePassword(int $userId, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$hash, $userId]);
    }

    public function listAnalyses(int $userId, int $limit = 100): array {
        $limit = max(1, min($limit, 300));
        $stmt = $this->db->prepare(
            "SELECT
                a.*,
                CASE WHEN f.id IS NULL THEN 0 ELSE 1 END AS is_favorite
             FROM analyses a
             LEFT JOIN favorites f ON f.analysis_id = a.id AND f.user_id = ?
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public function findAnalysisForUser(int $analysisId, int $userId): array|false {
        $stmt = $this->db->prepare("SELECT id, user_id FROM analyses WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$analysisId, $userId]);
        return $stmt->fetch();
    }

    public function listFavorites(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT
                a.*,
                f.created_at AS favorited_at
             FROM favorites f
             JOIN analyses a ON a.id = f.analysis_id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addFavorite(int $userId, int $analysisId): bool {
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO favorites (user_id, analysis_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $analysisId]);
    }

    public function removeFavorite(int $userId, int $analysisId): bool {
        $stmt = $this->db->prepare("DELETE FROM favorites WHERE user_id = ? AND analysis_id = ?");
        return $stmt->execute([$userId, $analysisId]);
    }

    public function getDashboardStats(int $userId): array {
        $analysisCountStmt = $this->db->prepare("SELECT COUNT(*) FROM analyses WHERE user_id = ?");
        $analysisCountStmt->execute([$userId]);
        $analysisCount = (int) $analysisCountStmt->fetchColumn();

        $favoriteCountStmt = $this->db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        $favoriteCountStmt->execute([$userId]);
        $favoriteCount = (int) $favoriteCountStmt->fetchColumn();

        $avgConfidenceStmt = $this->db->prepare("SELECT AVG(confidence_score) FROM analyses WHERE user_id = ?");
        $avgConfidenceStmt->execute([$userId]);
        $avgConfidence = (float) ($avgConfidenceStmt->fetchColumn() ?? 0);

        return [
            'total_analyses' => $analysisCount,
            'favorite_analyses' => $favoriteCount,
            'avg_confidence' => $avgConfidence
        ];
    }
}
