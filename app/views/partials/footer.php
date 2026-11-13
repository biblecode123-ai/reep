</main>
<footer class="footer">
  <div><?= e(\App\Core\Settings::get('university_name', 'Madda Walabu University')) ?> · <?= e(\App\Core\Settings::get('office_name', '')) ?></div>
  <div class="muted">Integrated Research Management System · <?= e(date('Y')) ?></div>
</footer>
<?= \App\Core\Auth::check() ? "</div><!-- /app-content -->" : "" ?>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
