<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ThemeModel;
use App\Core\Audit;

final class ThemeController
{
    public static function index(): void
    {
        Gate::authorize('research.manage');
        $themes = ThemeModel::all();
        include APP_PATH . '/views/research/themes.php';
    }

    public static function form(): void
    {
        Gate::authorize('research.manage');
        $id = (int) ($_GET['id'] ?? 0);
        $theme = $id > 0 ? ThemeModel::find($id) : null;
        include APP_PATH . '/views/research/theme_form.php';
    }

    public static function save(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);

        [$ok, $errors, $clean] = Validator::check($_POST, [
            'name' => 'required|max:150',
            'code' => 'required|max:30',
            'description' => 'max:500',
            'status' => 'in:active,inactive'
        ]);

        if (!$ok) {
            Session::flash('error', implode(' ', $errors));
            redirect('index.php?action=theme_form' . ($id ? '&id=' . $id : ''));
        }

        $data = [
            'name' => $clean['name'],
            'code' => strtoupper($clean['code']),
            'description' => $clean['description'] ?: null,
            'status' => $clean['status'],
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($id > 0) {
            ThemeModel::update($id, $data);
            Audit::log('theme.updated', 'research_theme', $id);
            Session::flash('success', 'Theme updated.');
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = ThemeModel::create($data);
            Audit::log('theme.created', 'research_theme', $id);
            Session::flash('success', 'Theme created.');
        }
        redirect('index.php?action=themes');
    }

    public static function changeStatus(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
        
        ThemeModel::update($id, ['status' => $status]);
        Audit::log('theme.status_changed', 'research_theme', $id, null, ['status' => $status]);
        Session::flash('success', 'Theme status updated.');
        redirect('index.php?action=themes');
    }

    public static function delete(): void
    {
        Gate::authorize('research.manage');
        Csrf::verifyRequest();
        $id = (int) ($_POST['id'] ?? 0);
        
        if (ThemeModel::countCalls($id) > 0) {
            Session::flash('error', 'Cannot delete theme with associated research calls.');
            redirect('index.php?action=themes');
        }

        \App\Core\Database::run('DELETE FROM research_themes WHERE id = ?', [$id]);
        Audit::log('theme.deleted', 'research_theme', $id);
        Session::flash('success', 'Theme deleted.');
        redirect('index.php?action=themes');
    }
}

