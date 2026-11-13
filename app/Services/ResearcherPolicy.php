<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Gate;

/**
 * Researcher-profile authorization policy (spec §4, §5, §12, §13).
 *
 * Visibility matrix (enforced server-side, never by the UI alone):
 *   - Researcher            → own profile only
 *   - College Coordinator   → researchers of the coordinator's own college
 *   - Directorate (director, RPEE officer) / admin → all university researchers
 *   - Everyone else         → no researcher-profile access
 *
 * Profile visibility ≠ document/dataset access: those stay behind the
 * separate document policies (DocumentService, repository rules).
 */
final class ResearcherPolicy
{
    /** May the current user view this researcher profile (user row + researchers row)? */
    public static function canView(array $targetUser): bool
    {
        if (!Auth::check() || Gate::denies('researcher.view')) {
            return false;
        }
        if ((int) $targetUser['id'] === (int) Auth::id()) {
            return true; // everyone may see themselves
        }
        if (self::isDirectorate()) {
            return true; // directorate: university-wide
        }
        if (Gate::hasRole('college_coordinator')) {
            // Single-college model: coordinator's own college from their account.
            return self::coordinatorCollegeIds() !== []
                && in_array((int) ($targetUser['college_id'] ?? 0), self::coordinatorCollegeIds(), true);
        }
        return false;
    }

    /** 403 unless the current user may view this profile. */
    public static function authorizeView(array $targetUser): void
    {
        if (!self::canView($targetUser)) {
            \App\Core\Logger::security('Researcher profile access denied', [
                'target_user' => (int) $targetUser['id'],
                'user'        => Auth::id(),
                'uri'         => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            http_response_code(403);
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'You are not authorized to view this researcher profile.']);
            } else {
                include APP_PATH . '/views/errors/403.php';
            }
            exit;
        }
    }

    /** Whether the current user is Research Directorate staff (director or RPEE officer) or admin. */
    public static function isDirectorate(): bool
    {
        return Gate::hasRole('rpee_director') || Gate::hasRole('rpee_officer') || Gate::isAdmin();
    }

    /**
     * Colleges the current coordinator is assigned to. The current business
     * rule is one coordinator → one college (users.college_id), returned as a
     * list so a future assignment table can extend this without touching callers.
     *
     * @return int[]
     */
    public static function coordinatorCollegeIds(): array
    {
        $user = Auth::user();
        if ($user === null || ($user['college_id'] ?? null) === null) {
            return [];
        }
        return [(int) $user['college_id']];
    }

    /** Whether the current viewer may edit the given profile (ownership only). */
    public static function canEdit(array $targetUser): bool
    {
        return Auth::check() && (int) $targetUser['id'] === (int) Auth::id();
    }

    /**
     * Base query fragment + params that scope a researcher directory query to
     * what the current viewer is allowed to list (query-level scoping, §13).
     *
     * @return array{where:string, params:string[]}
     */
    public static function listScope(): array
    {
        if (self::isDirectorate()) {
            return ['where' => '1=1', 'params' => []];
        }
        if (Gate::hasRole('college_coordinator')) {
            $ids = self::coordinatorCollegeIds();
            if (!$ids) {
                return ['where' => '1=0', 'params' => []];
            }
            return [
                'where'  => 'u.college_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                'params' => array_map('strval', $ids),
            ];
        }
        // Plain researcher (or any other holder of researcher.view): own record only.
        return ['where' => 'u.id = ?', 'params' => [(string) Auth::id()]];
    }
}
