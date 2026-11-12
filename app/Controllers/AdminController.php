<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLogModel;
use App\Models\OrgModel;
use App\Models\UserModel;
use App\Services\NumberingService;

final class AdminController
{
    // ================= USERS (§10, §102) =================

    public static function users(): void
    {
        Gate::authorize('user.view');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'college'  => (int) ($_GET['college_id'] ?? ($_GET['college'] ?? 0)),
            'status'   => (string) ($_GET['status'] ?? ''),
            'role'     => (string) ($_GET['role'] ?? ''),
        ];
        $p = paginate((int) Database::scalar('SELECT COUNT(*) FROM users u WHERE 1=1 ' . ($filters['college'] ? ' AND college_id = ' . $filters['college'] : '')) ?: 1, $page, 20);
        $result = UserModel::paginate($filters, $p['page'], $p['limit']);
        $p = paginate($result['total'], $page, 20);
        $rows = $result['rows'];
        $colleges = OrgModel::colleges();
        $roles = Database::all('SELECT * FROM roles WHERE name IN (?, ?, ?, ?) ORDER BY name', ['system_admin', 'rpee_director', 'college_coordinator', 'researcher']);
        
        // Pass query string for pagination links
        $qs = http_build_query(array_filter([
            'action'     => 'users',
            'q'          => $filters['q'] ?: null,
            'college_id' => $filters['college'] ?: null,
            'status'     => $filters['status'] ?: null,
            'role'       => $filters['role'] ?: null,
        ]));
        
        include APP_PATH . '/views/admin/users.php';
    }

    public static function userForm(): void
    {
        Gate::authorize('user.manage');
        $id = (int) ($_GET['id'] ?? 0);
        $user = $id > 0 ? UserModel::findById($id) : null;
        $userRoleIds = $id > 0 ? UserModel::roleIdsFor($id) : [];
        $colleges = OrgModel::colleges();
        $departments = ($user && $user['college_id']) ? OrgModel::departments((int)$user['college_id']) : [];
        $roles = Database::all('SELECT * FROM roles WHERE name IN (?, ?, ?, ?) ORDER BY name', ['system_admin', 'rpee_director', 'college_coordinator', 'researcher']);
        $errors = [];
        include APP_PATH . '/views/admin/user_form.php';
    }

    public static function userSave(): void
    {
        Gate::authorize('user.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);

        [$ok, $errors, $clean] = Validator::check($_POST, [
            'full_name'   => 'required|max:150',
            'email'       => 'required|email|max:190',
            'phone'       => 'phone',
            'employee_id' => 'max:50',
            'position'    => 'max:100',
            'college_id'  => 'integer',
            'department_id' => 'integer',
            'status'      => 'in:active,inactive,suspended,pending',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=user_form' . ($id ? '&id=' . $id : ''));
        }
        if (!empty($_POST['password']) && strlen((string) $_POST['password']) < 8) {
            Session::flash('error', 'Password must be at least 8 characters.');
            redirect('index.php?action=user_form' . ($id ? '&id=' . $id : ''));
        }

        $data = [
            'full_name'    => $clean['full_name'],
            'email'        => strtolower($clean['email']),
            'phone'        => $clean['phone'] ?: null,
            'employee_id'  => $clean['employee_id'] ?: null,
            'position_title' => $clean['position'] ?: null,
            'college_id'   => $clean['college_id'] ?: null,
            'department_id' => $clean['department_id'] ?: null,
            'status'       => $clean['status'],
        ];
        if (!empty($_POST['password'])) {
            $data['password_hash'] = password_hash((string) $_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $data['must_change_password'] = 1;
        }
        // Modified to prevent accidental role removal during profile update
        $roleIds = null;
        if (isset($_POST['roles'])) {
            $roleIds = array_map('intval', (array) ($_POST['roles'] ?? []));
        }

        try {
            if ($id > 0) {
                UserModel::update($id, $data, $roleIds);
                if ($roleIds !== null) {
                    Audit::log('user.updated', 'user', $id, null, ['roles' => $roleIds]);
                } else {
                    Audit::log('user.updated', 'user', $id);
                }
                Session::flash('success', 'User updated.');
            } else {
                if (empty($_POST['password'])) {
                    Session::flash('error', 'A password is required for new users.');
                    redirect('index.php?action=user_form');
                }
                $id = UserModel::create($data, $roleIds);
                Audit::log('user.created', 'user', $id, null, ['email' => $data['email']]);
                Session::flash('success', 'User created.');
            }
        } catch (\PDOException $e) {
            Session::flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'Email or employee ID already exists.' : 'Database error.');
        }
        redirect('index.php?action=users');
    }

    public static function userDelete(): void
    {
        Gate::authorize('user.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        
        // Prevent deleting yourself
        if ($id === Auth::id()) {
            Session::flash('error', 'You cannot delete your own account.');
            redirect('index.php?action=users');
        }

        UserModel::update($id, ['status' => 'inactive'], []);
        Audit::log('user.deactivated', 'user', $id);
        Session::flash('success', 'User marked as inactive.');
        redirect('index.php?action=users');
    }

    public static function userDestroy(): void
    {
        Gate::authorize('user.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        
        // Prevent deleting yourself
        if ($id === Auth::id()) {
            Session::flash('error', 'You cannot delete your own account.');
            redirect('index.php?action=users');
        }

        UserModel::hardDelete($id);
        Audit::log('user.deactivated', 'user', $id);
        Session::flash('success', 'User deactivated and marked as inactive.');
        redirect('index.php?action=users');
    }

    public static function userRoles(): void
    {
        Gate::authorize('user.roles');
        $id = (int) ($_GET['id'] ?? 0);
        $user = UserModel::findById($id);
        if ($user === null) {
            Session::flash('error', 'User not found.');
            redirect('index.php?action=users');
        }
        $userRoleIds = UserModel::roleIdsFor($id);
        $roles = Database::all('SELECT * FROM roles WHERE name IN (?, ?, ?, ?) ORDER BY name', ['system_admin', 'rpee_director', 'college_coordinator', 'researcher']);
        include APP_PATH . '/views/admin/user_roles.php';
    }

    public static function userRolesSave(): void
    {
        Gate::authorize('user.roles');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $submittedRoleIds = array_map('intval', (array) ($_POST['roles'] ?? []));
        
        // Validate against authorized roles (4 roles)
        $authorized = Database::all('SELECT id FROM roles WHERE name IN (?, ?, ?, ?)', ['system_admin', 'rpee_director', 'college_coordinator', 'researcher']);
        $authorizedIds = array_column($authorized, 'id');
        
        $roleIds = [];
        foreach ($submittedRoleIds as $rid) {
            if (in_array($rid, $authorizedIds, true)) {
                $roleIds[] = $rid;
            }
        }
        
        UserModel::update($id, [], $roleIds);
        Gate::flush();
        Audit::log('user.roles_changed', 'user', $id, null, ['roles' => $roleIds]);
        Session::flash('success', 'Roles updated.');
        redirect('index.php?action=users');
    }

    // ================= ORGANIZATION (§10) =================

    public static function apiDepartments(): void
    {
        header('Content-Type: application/json');
        $collegeId = (int) ($_GET['college_id'] ?? 0);
        if ($collegeId <= 0) {
            echo json_encode([]);
            return;
        }
        echo json_encode(OrgModel::departments($collegeId));
    }

    public static function colleges(): void
    {
        Gate::authorize('org.view');
        $colleges = OrgModel::colleges();
        include APP_PATH . '/views/admin/colleges.php';
    }

    public static function collegeSave(): void
    {
        Gate::authorize('org.manage');
        Csrf::verifyRequest();
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'name' => 'required|max:150', 'code' => 'required|max:20', 'status' => 'in:active,inactive',
        ]);
        $id = (int) ($_POST['id'] ?? 0);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=colleges');
        }
        $data = ['name' => $clean['name'], 'code' => strtoupper($clean['code']), 'status' => $clean['status'],
                 'description' => trim((string) ($_POST['description'] ?? '')) ?: null];
        if ($id > 0) {
            OrgModel::updateCollege($id, $data);
            Audit::log('college.updated', 'college', $id);
            Session::flash('success', 'College updated.');
        } else {
            $id = OrgModel::createCollege($data);
            Audit::log('college.created', 'college', $id);
            Session::flash('success', 'College created.');
        }
        redirect('index.php?action=colleges');
    }

    public static function departments(): void
    {
        Gate::authorize('org.view');
        $collegeId = (int) ($_GET['college_id'] ?? 0);
        $departments = OrgModel::departments($collegeId > 0 ? $collegeId : null);
        $colleges = OrgModel::colleges(true);
        include APP_PATH . '/views/admin/departments.php';
    }

    public static function departmentSave(): void
    {
        Gate::authorize('org.manage');
        Csrf::verifyRequest();
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'name' => 'required|max:150', 'code' => 'required|max:20',
            'college_id' => 'required|integer', 'status' => 'in:active,inactive',
        ]);
        $id = (int) ($_POST['id'] ?? 0);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=departments');
        }
        $data = ['name' => $clean['name'], 'code' => strtoupper($clean['code']),
                 'college_id' => (int) $clean['college_id'], 'status' => $clean['status']];
        if ($id > 0) {
            OrgModel::updateDepartment($id, $data);
            Audit::log('department.updated', 'department', $id);
            Session::flash('success', 'Department updated.');
        } else {
            $id = OrgModel::createDepartment($data);
            Audit::log('department.created', 'department', $id);
            Session::flash('success', 'Department created.');
        }
        redirect('index.php?action=departments');
    }

    // ================= COMMITTEES (§68) =================

    public static function committees(): void
    {
        Gate::authorize('org.view');
        $committees = OrgModel::committees();
        $colleges = OrgModel::colleges(true);
        include APP_PATH . '/views/admin/committees.php';
    }

    public static function committeeSave(): void
    {
        Csrf::verifyRequest();
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'name' => 'required|max:150', 'code' => 'required|max:30',
            'committee_type' => 'in:university_research,college_research,ethics,publication,extension,presentation_panel,integrity,other',
        ]);
        $id = (int) ($_POST['id'] ?? 0);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=committees');
        }
        $data = [
            'name' => $clean['name'], 'code' => strtoupper($clean['code']),
            'committee_type' => $clean['committee_type'],
            'college_id' => ((int) ($_POST['college_id'] ?? 0)) ?: null,
            'quorum_size' => ((int) ($_POST['quorum_size'] ?? 0)) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            OrgModel::updateCommittee($id, $data);
            Audit::log('committee.updated', 'committee', $id);
            Session::flash('success', 'Committee updated.');
        } else {
            $id = OrgModel::createCommittee($data);
            Audit::log('committee.created', 'committee', $id);
            Session::flash('success', 'Committee created.');
        }
        redirect('index.php?action=committees');
    }

    public static function committeeMembers(): void
    {
        Gate::authorize('org.view');
        $id = (int) ($_GET['id'] ?? 0);
        $committee = OrgModel::committee($id);
        if ($committee === null) {
            Session::flash('error', 'Committee not found.');
            redirect('index.php?action=committees');
        }
        $collegeId = (int) ($_GET['college_id'] ?? 0);
        $members = OrgModel::committeeMembers($id);
        $users = UserModel::listForSelect($collegeId ?: null);
        $colleges = OrgModel::colleges();
        include APP_PATH . '/views/admin/committee_members.php';
    }

    public static function committeeMemberSave(): void
    {
        Csrf::verifyRequest();
        $committeeId = (int) ($_POST['committee_id'] ?? 0);
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'user_id' => 'required|integer',
            'member_role' => 'in:chair,secretary,member',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=committee_members&id=' . $committeeId);
        }
        OrgModel::addCommitteeMember([
            'committee_id'   => $committeeId,
            'user_id'        => (int) $clean['user_id'],
            'member_role'    => $clean['member_role'],
            'appointed_from' => ($_POST['appointed_from'] ?? '') ?: null,
            'appointed_to'   => ($_POST['appointed_to'] ?? '') ?: null,
        ]);
        $committee = OrgModel::committee($committeeId);
        \App\Services\NotificationService::send(
            (int) $clean['user_id'],
            'committee_membership',
            'Added to committee',
            'You have been added to: ' . ($committee['name'] ?? 'a committee'),
            'index.php?action=committees'
        );
        Audit::log('committee.member_added', 'committee', $committeeId, null, ['user' => $clean['user_id']]);
        Session::flash('success', 'Member added.');
        redirect('index.php?action=committee_members&id=' . $committeeId);
    }

    public static function committeeMemberRemove(): void
    {
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $committeeId = (int) ($_POST['committee_id'] ?? 0);
        OrgModel::updateCommitteeMember($id, ['status' => 'retired']);
        Audit::log('committee.member_removed', 'committee', $committeeId, null, ['member' => $id]);
        Session::flash('success', 'Member retired.');
        redirect('index.php?action=committee_members&id=' . $committeeId);
    }

    // ================= CLASSIFICATIONS (Rule 2) =================

    public static function classifications(): void
    {
        Gate::authorize('classification.manage');
        $themes = OrgModel::themes();
        $types = OrgModel::types();
        $docTypes = OrgModel::documentTypes();
        include APP_PATH . '/views/admin/classifications.php';
    }

    public static function classificationSave(): void
    {
        Gate::authorize('classification.manage');
        Csrf::verifyRequest();
        $kind = (string) ($_POST['kind'] ?? '');
        [$ok, $errors, $clean] = Validator::check($_POST, [
            'name' => 'required|max:150', 'code' => 'required|max:30',
        ]);
        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=classifications');
        }
        $data = ['name' => $clean['name'], 'code' => strtolower($clean['code']),
                 'status' => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
                 'description' => trim((string) ($_POST['description'] ?? '')) ?: null];
        $id = (int) ($_POST['id'] ?? 0);
        $label = match ($kind) {
            'theme' => 'research theme', 'type' => 'research type', 'doc_type' => 'document type',
            default => null,
        };
        if ($label === null) {
            Session::flash('error', 'Unknown classification kind.');
            redirect('index.php?action=classifications');
        }
        if ($id > 0) {
            match ($kind) {
                'theme'    => OrgModel::updateTheme($id, $data),
                'type'     => OrgModel::updateType($id, $data),
                'doc_type' => OrgModel::updateDocumentType($id, $data),
            };
        } else {
            match ($kind) {
                'theme'    => OrgModel::createTheme($data),
                'type'     => OrgModel::createType($data),
                'doc_type' => OrgModel::createDocumentType($data),
            };
        }
        Audit::log($label . '.' . ($id ? 'updated' : 'created'), $label, $id ?: null);
        Session::flash('success', ucfirst($label) . ' saved.');
        redirect('index.php?action=classifications');
    }

    // ================= SETTINGS (§103) =================

    public static function settings(): void
    {
        Gate::authorize('settings.manage');
        $settings = Database::all('SELECT * FROM system_settings ORDER BY setting_group, setting_key');
        include APP_PATH . '/views/admin/settings.php';
    }

    public static function settingsSave(): void
    {
        Gate::authorize('settings.manage');
        Csrf::verifyRequest();
        $keys = (array) ($_POST['settings'] ?? []);
        foreach ($keys as $key => $value) {
            \App\Core\Settings::set((string) $key, (string) $value, Auth::id());
        }
        Audit::log('settings.updated', null, null, null, array_keys($keys));
        Session::flash('success', 'Settings saved.');
        redirect('index.php?action=settings');
    }

    // ================= AUDIT (§65) =================

    public static function auditLogs(): void
    {
        Gate::authorize('audit.view');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'action'    => trim((string) ($_GET['action_filter'] ?? '')),
            'entity'    => trim((string) ($_GET['entity'] ?? '')),
            'user_id'   => (int) ($_GET['user_id'] ?? 0),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];
        $p = paginate(1, $page, 30);
        $result = AuditLogModel::paginate($filters, $p['page'], $p['limit']);
        $p = paginate($result['total'], $page, 30);
        $logs = $result['logs'];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        include APP_PATH . '/views/admin/audit.php';
    }
}

