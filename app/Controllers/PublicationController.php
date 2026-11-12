<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Session;

/**
 * Publication management (spec §69–§74).
 */
final class PublicationController
{
    public static function index(): void
    {
        Gate::authorize('publication.view');
        $publications = Database::all('SELECT * FROM publications ORDER BY created_at DESC');
        include APP_PATH . '/views/publications/index.php';
    }

    public static function create(): void
    {
        Gate::authorize('publication.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyRequest();
            $data = [
                'title' => $_POST['title'],
                'journal_name' => $_POST['journal_name'] ?? null,
                'publication_type' => $_POST['publication_type'] ?? 'journal_article',
                'status' => 'draft',
                'created_by' => Auth::id()
            ];
            Database::insert('publications', $data);
            Session::flash('success', 'Publication draft created.');
            redirect('index.php?action=publications');
        }
        include APP_PATH . '/views/publications/form.php';
    }

    public static function approve(): void
    {
        Gate::authorize('publication.publish');
        $id = (int) ($_GET['id'] ?? 0);
        Database::run('UPDATE publications SET status = "published", published_date = ? WHERE id = ?', [date('Y-m-d'), $id]);
        Session::flash('success', 'Publication approved and published.');
        redirect('index.php?action=publications');
    }
}

