<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Gate;

/**
 * Explicit API field control for researcher profiles (spec §11).
 * Fields are whitelisted per authorization level — never dumped from the row.
 */
final class ResearcherTransformer
{
    public static function transform(array $r, ?array $viewer = null, bool $full = false): array
    {
        $viewer = $viewer ?? Auth::user();
        $isSelf = $viewer !== null && (int) $viewer['id'] === (int) $r['id'];
        $isDirector = ResearcherPolicy::isDirectorate();
        $isSameCollege = !$isDirector && Gate::hasRole('college_coordinator')
            && in_array((int) ($r['college_id'] ?? 0), ResearcherPolicy::coordinatorCollegeIds(), true);
        $canSeeContact = $full ? ($isSelf || $isDirector || $isSameCollege) : ($isSelf || $isDirector);

        $out = [
            'id'             => (int) $r['id'],
            'full_name'      => $r['full_name'],
            'college'        => ['id' => $r['college_id'] !== null ? (int) $r['college_id'] : null, 'name' => $r['college_name'] ?? null],
            'department'     => ['id' => $r['department_id'] !== null ? (int) $r['department_id'] : null, 'name' => $r['department_name'] ?? null],
            'academic_rank'  => $r['academic_position'] ?? null,
            'qualification'  => $r['qualification'] ?? null,
            'specialization' => $r['specialization'] ?? null,
            'research_interests' => $r['research_interests'] !== null ? array_filter(array_map('trim', explode(',', (string) $r['research_interests']))) : [],
            'research_areas'     => $r['research_areas'] !== null ? array_filter(array_map('trim', explode(',', (string) $r['research_areas']))) : [],
            'orcid'          => $r['orcid'] ?? null,
            'google_scholar' => $r['google_scholar'] ?? null,
            'profile_photo'  => !empty($r['profile_photo_document_id']) ? 'index.php?action=researcher_photo&user_id=' . (int) $r['id'] : null,
        ];

        // Contact + biographical details: self, directorate, or same-college coordinator.
        if ($canSeeContact) {
            $out['staff_id']  = $r['employee_id'] ?? null;
            $out['email']     = $r['email'] ?? null;
            $out['phone']     = $r['phone'] ?? null;
            $out['biography'] = $r['biography'] ?? null;
        }

        // Account status: self and directorate only (§6).
        if ($isSelf || $isDirector) {
            $out['account_status'] = $r['status'] ?? null;
        }
        if ($isSelf || ($full && $isDirector)) {
            $out['gender'] = $r['gender'] ?? null;
        }

        // Research statistics (§11) — from live relationships, never hardcoded.
        $out['research_statistics'] = [
            'total_projects'     => (int) Database::scalar('SELECT COUNT(*) FROM research_project_members WHERE user_id = ? AND status = "active"', [(int) $r['id']]),
            'active_projects'    => (int) Database::scalar('SELECT COUNT(*) FROM research_project_members m JOIN research_projects p ON p.id = m.project_id WHERE m.user_id = ? AND m.status = "active" AND p.status_code IN ("ACTIVE","PROGRESS_MONITORING")', [(int) $r['id']]),
            'completed_projects' => (int) Database::scalar('SELECT COUNT(*) FROM research_project_members m JOIN research_projects p ON p.id = m.project_id WHERE m.user_id = ? AND m.status = "active" AND p.status_code IN ("COMPLETED","FINAL_REPORT_SUBMITTED")', [(int) $r['id']]),
        ];

        return $out;
    }
}

