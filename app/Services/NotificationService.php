<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Internal notification system (spec §66–§67).
 * Rows land in `notifications`; the UI bell shows unread count.
 * Email dispatch is a future concern (settings-gated), not faked.
 */
final class NotificationService
{
    /** Send to one user. */
    public static function send(int $userId, string $type, string $title, ?string $body = null, ?string $link = null, string $priority = 'normal'): void
    {
        try {
            Database::insert('notifications', [
                'user_id'           => $userId,
                'notification_type' => $type,
                'title'             => mb_substr($title, 0, 200),
                'body'              => $body,
                'link_url'          => $link,
                'priority'          => $priority,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Notification insert failed', ['msg' => $e->getMessage()]);
        }
    }

    /** Send to every user holding any of the given role names. */
    public static function sendToRoles(array $roleNames, string $type, string $title, ?string $body = null, ?string $link = null, string $priority = 'normal'): void
    {
        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        $users = Database::all(
            "SELECT DISTINCT u.id FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.name IN ($placeholders) AND u.status = 'active'",
            $roleNames
        );
        foreach ($users as $u) {
            self::send((int) $u['id'], $type, $title, $body, $link, $priority);
        }
    }

    /** Send to all users in a college holding the given role (coordinator routing, §17). */
    public static function sendToCollegeRole(int $collegeId, string $roleName, string $type, string $title, ?string $body = null, ?string $link = null, string $priority = 'normal'): void
    {
        $users = Database::all(
            "SELECT DISTINCT u.id FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.name = ? AND u.college_id = ? AND u.status = 'active'",
            [$roleName, $collegeId]
        );
        foreach ($users as $u) {
            self::send((int) $u['id'], $type, $title, $body, $link, $priority);
        }
    }

    /** Notify relevant parties after a workflow transition (§66). */
    public static function notifyStageChange(string $entityType, int $entityId, array $transition): void
    {
        $to = $transition['to_code'] ?? '';
        if ($entityType === 'research_application') {
            $app = Database::one(
                'SELECT a.id, a.pi_user_id, a.college_id, a.title FROM research_applications a WHERE a.id = ?',
                [$entityId]
            );
            if ($app === null) {
                return;
            }
            $link = 'index.php?action=application_view&id=' . $entityId;
            $map = [
                'SUBMITTED' => function () use ($app, $link): void {
                    self::sendToCollegeRole((int) $app['college_id'], 'college_coordinator', 'application_submitted',
                        'New application received', 'Application #' . $app['id'] . ' requires routing/screening.', $link, 'high');
                },
                'REVISION_REQUIRED' => function () use ($app, $link): void {
                    self::send((int) $app['pi_user_id'], 'revision_requested', 'Revision requested',
                        'Your proposal requires revision: ' . $app['title'], $link, 'high');
                },
                'COLLEGE_APPROVED' => function () use ($app, $link): void {
                    self::send((int) $app['pi_user_id'], 'proposal_approved', 'College approval granted',
                        'Your proposal passed college review.', $link);
                },
                'APPROVED' => function () use ($app, $link): void {
                    self::send((int) $app['pi_user_id'], 'proposal_approved', 'University approval',
                        'Your proposal was approved at university level.', $link, 'high');
                },
                'REJECTED' => function () use ($app, $link): void {
                    self::send((int) $app['pi_user_id'], 'proposal_rejected', 'Application decision',
                        'A decision was recorded on your application.', $link, 'high');
                },
                'FUNDED' => function () use ($app, $link): void {
                    self::send((int) $app['pi_user_id'], 'funding_released', 'Funding released',
                        'Funds were released for your research project.', $link, 'high');
                },
            ];
            (isset($map[$to]) ? $map[$to] : static function (): void {})();
        }

        if ($entityType === 'ethics_application' && in_array($to, ['SUBMITTED', 'UNDER_SCREENING'], true)) {
            self::sendToRoles(['ethics_member', 'rpee_officer'], 'ethics_submitted', 'Ethics application submitted',
                'An ethics application awaits screening/review.', 'index.php?action=ethics_view&id=' . $entityId, 'high');
        }
    }

    /** Evaluator-specific notification (§20–§21). */
    public static function notifyEvaluatorAssigned(int $evaluatorUserId, int $applicationId, string $title): void
    {
        self::send($evaluatorUserId, 'evaluator_assigned', 'You were assigned an evaluation',
            'Proposal: ' . $title, 'index.php?action=evaluation_form&application_id=' . $applicationId, 'high');
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }
}
