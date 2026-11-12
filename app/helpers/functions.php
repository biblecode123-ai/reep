<?php
declare(strict_types=1);

/**
 * Global view/controller helpers. All output escaping flows through e().
 */

/** Escape for HTML output (XSS prevention, spec §87). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base URL — absolute, host-aware so it works in subdirectories and proxies. */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base   = rtrim(($https ? 'https://' : 'http://') . $host . $dir, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return base_url('index.php' . ($path !== '' ? '?' . $path : ''));
}

function redirect(string $path): never
{
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . base_url($path));
    }
    exit;
}

function is_ajax_request(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function client_ip(): string
{
    // Behind a trusted proxy this could read X-Forwarded-For; direct mode is the safe default.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Render flash message(s) set via Session::flash(). */
function flash_html(): string
{
    $out = '';
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $msg = \App\Core\Session::flash($type);
        if ($msg !== null) {
            $out .= '<div class="alert alert-' . e($type) . '">' . e($msg) . '</div>';
        }
    }
    return $out;
}

function format_money(null|string|int|float $amount, string $currency = 'ETB'): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return number_format((float) $amount, 2) . ' ' . $currency;
}

function format_date(?string $date): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '—';
    }
    return date('d M Y', strtotime($date));
}

function format_datetime(?string $dt): string
{
    if ($dt === null || $dt === '' || str_starts_with($dt, '0000')) {
        return '—';
    }
    return date('d M Y H:i', strtotime($dt));
}

/** Badge CSS class for workflow status codes. */
function status_badge(string $status): string
{
    $map = [
        'DRAFT' => 'badge-secondary', 'SUBMITTED' => 'badge-info',
        'COLLEGE_RECEIVED' => 'badge-info', 'UNDER_COLLEGE_SCREENING' => 'badge-info',
        'EVALUATOR_ASSIGNMENT_PENDING' => 'badge-warning', 'EVALUATOR_ASSIGNED' => 'badge-warning',
        'EVALUATOR_ACCEPTED' => 'badge-warning', 'UNDER_EVALUATION' => 'badge-warning',
        'EVALUATION_COMPLETED' => 'badge-primary', 'REVISION_REQUIRED' => 'badge-danger',
        'REVISION_SUBMITTED' => 'badge-info', 'RE_EVALUATION' => 'badge-warning',
        'COLLEGE_REVIEW' => 'badge-info', 'COLLEGE_APPROVED' => 'badge-success',
        'SUBMITTED_TO_UNIVERSITY' => 'badge-info', 'UNIVERSITY_REVIEW' => 'badge-info',
        'UNIVERSITY_COMMITTEE_REVIEW' => 'badge-purple',
        'ETHICS_REVIEW' => 'badge-purple', 'APPROVED' => 'badge-success',
        'BUDGET_REVIEW' => 'badge-info', 'BUDGET_APPROVED' => 'badge-success',
        'FUNDED' => 'badge-success', 'ACTIVE' => 'badge-success',
        'PROGRESS_MONITORING' => 'badge-primary', 'FINAL_REPORT_SUBMITTED' => 'badge-info',
        'FINAL_EVALUATION' => 'badge-info', 'PRESENTATION_SCHEDULED' => 'badge-info',
        'PRESENTED' => 'badge-success', 'PRESENTATION_EVALUATION' => 'badge-info',
        'FINAL_APPROVAL' => 'badge-success', 'COMPLETED' => 'badge-success',
        'REPOSITORY' => 'badge-success', 'ARCHIVED' => 'badge-secondary',
        'REJECTED' => 'badge-danger', 'WITHDRAWN' => 'badge-secondary',
        'CANCELLED' => 'badge-danger', 'SUSPENDED' => 'badge-danger',
        'TERMINATED' => 'badge-danger', 'EXPIRED' => 'badge-danger',
    ];
    return $map[strtoupper($status)] ?? 'badge-secondary';
}

/** Human label from a stage code. */
function status_label(string $status): string
{
    return ucwords(strtolower(str_replace('_', ' ', $status)));
}

/** Simple paginator state from total count. */
function paginate(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = min(max(1, $page), $pages);
    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage];
}

/** Render pagination links preserving current query string. */
function pagination_links(array $p, string $queryString): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    parse_str($queryString, $qs);
    $out = '<nav class="pagination">';
    $mk  = static function (int $n, string $label, bool $active = false, bool $disabled = false) use ($qs): string {
        if ($disabled) {
            return '<span class="page-item disabled">' . e($label) . '</span>';
        }
        $qs['page'] = $n;
        $href = url(http_build_query($qs));
        $cls  = $active ? 'page-item active' : 'page-item';
        return '<a class="' . $cls . '" href="' . e($href) . '">' . e($label) . '</a>';
    };
    $out .= $mk(1, '«', false, $p['page'] === 1);
    for ($n = max(1, $p['page'] - 2); $n <= min($p['pages'], $p['page'] + 2); $n++) {
        $out .= $mk($n, (string) $n, $n === $p['page']);
    }
    $out .= $mk($p['pages'], '»', false, $p['page'] === $p['pages']);
    return $out . '</nav>';
}

/** CSRF-protected POST link helper. */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . e(\App\Core\Csrf::token()) . '">';
}

