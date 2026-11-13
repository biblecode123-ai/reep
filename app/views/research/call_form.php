<?php 
$title = ($call === null ? 'Create Research Call' : 'Edit Research Call: ' . ($call['title'] ?? '')); 
include APP_PATH . '/views/partials/header.php'; 
?>

<div class="page-head">
    <div class="page-head-title">
        <h1><?= $call === null ? 'Create Research Call' : 'Edit Call: ' . e($call['title']) ?></h1>
        <p>Define call scope, funding, calendar boundaries, and select associated themes from the global catalogue.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('action=calls')) ?>">← Back to Calls</a>
    </div>
</div>

<!-- Creation Wizard Indicator -->
<div class="wizard-steps">
    <div class="wizard-step completed">
        <div class="wizard-step-circle">1</div>
        <div class="wizard-step-title">
            <strong>Basic Information</strong>
            <small>Title & Reference</small>
        </div>
    </div>
    <div class="wizard-step active">
        <div class="wizard-step-circle">2</div>
        <div class="wizard-step-title">
            <strong>Theme Selection</strong>
            <small>Global Themes</small>
        </div>
    </div>
    <div class="wizard-step">
        <div class="wizard-step-circle">3</div>
        <div class="wizard-step-title">
            <strong>Calendar & Priorities</strong>
            <small>Open / Close Dates</small>
        </div>
    </div>
    <div class="wizard-step">
        <div class="wizard-step-circle">4</div>
        <div class="wizard-step-title">
            <strong>Publish & Configure</strong>
            <small>Eligibility & Criteria</small>
        </div>
    </div>
</div>

<form method="post" action="<?= e(url('action=call_save')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) ($call['id'] ?? 0) ?>">

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">1. Call Identification & Metadata</h2>
                <div class="card-subtitle">Official university title and tracking reference</div>
            </div>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Call Title <span class="required-star">*</span></label>
                    <input class="form-control" type="text" name="title" required maxlength="200" placeholder="e.g., 2026 Competitive Research Grant for Sustainable Agriculture" value="<?= e($call['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Reference Number <span class="required-star">*</span></label>
                    <input class="form-control" type="text" name="reference_number" required maxlength="60" placeholder="e.g., MWU/RPEE/CALL/2026/01" value="<?= e($call['reference_number'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description & Background</label>
                <textarea class="form-textarea" name="description" rows="3" placeholder="Provide background, rationale, and institutional context for this call..."><?= e($call['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Research Priorities & Objectives</label>
                <textarea class="form-textarea" name="priorities" rows="3" placeholder="List specific research priorities, thematic sub-topics, or focus areas..."><?= e($call['research_priorities'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Multi-Theme Selection from Global Catalogue -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">2. Associated Research Themes <span class="required-star">*</span></h2>
                <div class="card-subtitle">Select one or more themes from the global university catalogue. Applicants will choose exactly one theme from your selection.</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('action=themes')) ?>" target="_blank">Manage Global Themes</a>
        </div>
        <div class="card-body">
            <?php if (empty($themes)): ?>
                <div class="alert alert-warning">
                    No active themes found in the global catalogue. Please create themes first in the Research Themes management module.
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                    <?php foreach ($themes as $t): ?>
                    <?php $isSelected = in_array((int)$t['id'], $callThemes ?? [], true); ?>
                    <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; background: var(--bg-subtle); border-radius: var(--radius-md); border: 1px solid var(--border-light); cursor: pointer;">
                        <input type="checkbox" name="themes[]" value="<?= (int) $t['id'] ?>" <?= $isSelected ? 'checked' : '' ?> style="margin-top: 3px; accent-color: var(--brand);">
                        <div>
                            <strong style="color: var(--text-main); font-size: 13.5px;"><?= e($t['name']) ?></strong>
                            <?php if (!empty($t['code'])): ?>
                                <span class="badge badge-purple" style="margin-left: 4px;"><?= e($t['code']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($t['description'])): ?>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;"><?= e($t['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar Boundaries -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">3. Submission Calendar Boundaries</h2>
                <div class="card-subtitle">Define official opening and closing timestamps</div>
            </div>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Opening Date & Time</label>
                    <input class="form-control" type="datetime-local" name="opens_at" value="<?= e($call['opens_at'] ?? '') ?>">
                    <div class="form-hint">Call will automatically become visible and accept submissions from this moment.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Closing Date & Time</label>
                    <input class="form-control" type="datetime-local" name="closes_at" value="<?= e($call['closes_at'] ?? '') ?>">
                    <div class="form-hint">Submission portal will automatically close at this instant (exclusive boundary).</div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a class="btn btn-secondary" href="<?= e(url('action=calls')) ?>">Cancel</a>
            <button class="btn btn-primary" type="submit">💾 Save Call & Proceed</button>
        </div>
    </div>
</form>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
