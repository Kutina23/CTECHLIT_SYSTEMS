<div class="portal-content">
  <div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card" style="--kpi-color:var(--violet); --kpi-bg:rgba(124,58,237,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-comments"></i></div></div>
      <div class="kpi-value"><?php echo number_format($totalFeedback); ?></div>
      <div class="kpi-label">Total Feedback</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--green); --kpi-bg:rgba(16,185,129,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-smile"></i></div></div>
      <div class="kpi-value"><?php echo $positiveRate; ?>%</div>
      <div class="kpi-label">Positive</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--amber); --kpi-bg:rgba(245,158,11,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div></div>
      <div class="kpi-value"><?php echo number_format($pendingFeedback); ?></div>
      <div class="kpi-label">Pending</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--red); --kpi-bg:rgba(239,68,68,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-exclamation-circle"></i></div></div>
      <div class="kpi-value"><?php echo number_format($escalatedFeedback); ?></div>
      <div class="kpi-label">Escalations</div>
    </div>
  </div>

  <div class="card glass-card animate-fade-in">
    <div class="card-header">
      <div>
        <div class="card-title"><?php echo htmlspecialchars($feedbackViewTitle); ?></div>
        <div class="card-subtitle"><?php echo htmlspecialchars($feedbackViewSubtitle); ?></div>
      </div>
      <?php if ($canRespond): ?>
        <button class="btn btn-primary" type="button" id="newFeedbackButton"><i class="fas fa-plus"></i> New Feedback</button>
      <?php endif; ?>
    </div>

    <div class="table-container" style="border:none; background:transparent;">
      <table class="data-table" id="feedbackTable">
        <thead><tr><th>Source</th><th>Topic</th><th>Rating</th><th>Comment</th><th>Status</th><th>Testimonial</th><th>Date</th><?php if ($canRespond): ?><th>Actions</th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($feedbackItems as $item): ?>
            <?php
              $source = $item['user_name'] ?: 'Anonymous';
              $feedbackJson = json_encode([
                  'feedback_id' => (int) $item['feedback_id'],
                  'user_id' => $item['user_id'] === null ? '' : (int) $item['user_id'],
                  'category' => $item['category'],
                  'rating' => (int) $item['rating'],
                  'comment' => $item['comment'] ?? '',
                  'is_resolved' => (int) $item['is_resolved'],
                  'testimonial_approved' => (int) $item['testimonial_approved'],
              ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            ?>
            <tr data-id="<?php echo (int) $item['feedback_id']; ?>" data-feedback='<?php echo htmlspecialchars($feedbackJson, ENT_QUOTES, 'UTF-8'); ?>'>
              <td><?php echo htmlspecialchars($source); ?></td>
              <td><?php echo htmlspecialchars(ucfirst($item['category'])); ?></td>
              <td><?php echo number_format((float) $item['rating'], 1); ?> / 5</td>
              <td style="max-width:360px; white-space:normal;"><?php echo htmlspecialchars($item['comment'] ?? ''); ?></td>
              <td><span class="status-badge <?php echo $item['is_resolved'] ? 'success' : 'warning'; ?>"><span class="status-dot"></span><?php echo $item['is_resolved'] ? 'Resolved' : 'Pending'; ?></span></td>
              <td><span class="status-badge <?php echo $item['testimonial_approved'] ? 'success' : 'warning'; ?>"><span class="status-dot"></span><?php echo $item['testimonial_approved'] ? 'Approved' : 'Not approved'; ?></span></td>
              <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($item['created_at']))); ?></td>
              <?php if ($canRespond): ?>
                <td>
                  <div class="action-buttons" style="display:flex; gap:6px;">
                    <button class="header-btn view-btn" type="button" title="View feedback" data-action="view"><i class="fas fa-eye"></i></button>
                    <button class="header-btn edit-btn" type="button" title="Edit feedback" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn" type="button" title="<?php echo $item['is_resolved'] ? 'Reopen feedback' : 'Mark resolved'; ?>" data-action="toggle-status" style="color:var(--cyan);"><i class="fas fa-<?php echo $item['is_resolved'] ? 'rotate-left' : 'check'; ?>"></i></button>
                    <button class="header-btn" type="button" title="<?php echo $item['testimonial_approved'] ? 'Remove from testimonials' : 'Approve as testimonial'; ?>" data-action="toggle-testimonial" style="color:var(--violet);"><i class="fas fa-<?php echo $item['testimonial_approved'] ? 'eye-slash' : 'star'; ?>"></i></button>
                    <button class="header-btn delete-btn" type="button" title="Delete feedback" data-action="delete" style="color:var(--red);"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($feedbackItems)): ?>
            <tr><td colspan="<?php echo $canRespond ? 8 : 7; ?>" style="text-align:center; padding:24px; color:var(--ink-secondary);">No feedback found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:16px;">
      <span style="font-size:13px; color:var(--ink-secondary);">
        <?php if ($feedbackViewTotal > 0): ?>
          Showing <?php echo $feedbackViewOffset + 1; ?>-<?php echo min($feedbackViewOffset + 25, $feedbackViewTotal); ?> of <?php echo $feedbackViewTotal; ?> entries
        <?php else: ?>
          No feedback entries
        <?php endif; ?>
        (Page <?php echo $feedbackViewPage; ?> of <?php echo $feedbackViewTotalPages; ?>)
      </span>
      <nav aria-label="Feedback pagination" style="display:flex; gap:8px;">
        <?php if ($feedbackViewPage > 1): ?>
          <a class="btn btn-secondary" href="?page=<?php echo $feedbackViewPage - 1; ?>">Previous</a>
        <?php else: ?>
          <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Previous</span>
        <?php endif; ?>
        <?php if ($feedbackViewPage < $feedbackViewTotalPages): ?>
          <a class="btn btn-secondary" href="?page=<?php echo $feedbackViewPage + 1; ?>">Next</a>
        <?php else: ?>
          <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Next</span>
        <?php endif; ?>
      </nav>
    </div>
  </div>
</div>

<?php if ($canRespond): ?>
  <?php
    $customerOptions = [['value' => '', 'label' => 'Anonymous']];
    if (!empty($customers)) {
      foreach ($customers as $customer) {
        $customerOptions[] = ['value' => (int) $customer['user_id'], 'label' => $customer['full_name']];
      }
    }
  ?>
  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
    window.MODAL_CONFIGS = {
      feedback: {
        title: 'Feedback',
        fields: [
          {name: 'user_id', label: 'Customer', type: 'select', required: false, options: <?php echo json_encode($customerOptions); ?>},
          {name: 'category', label: 'Topic', type: 'select', required: true, options: [
            {value: 'service', label: 'Service'},
            {value: 'station', label: 'Station'},
            {value: 'staff', label: 'Staff'},
            {value: 'cleanliness', label: 'Cleanliness'},
            {value: 'other', label: 'Other'},
          ]},
          {name: 'rating', label: 'Rating', type: 'select', required: true, options: [
            {value: 1, label: '1 / 5'},
            {value: 2, label: '2 / 5'},
            {value: 3, label: '3 / 5'},
            {value: 4, label: '4 / 5'},
            {value: 5, label: '5 / 5'},
          ]},
          {name: 'is_resolved', label: 'Status', type: 'select', required: true, options: [
            {value: 0, label: 'Pending'},
            {value: 1, label: 'Resolved'},
          ]},
          {name: 'comment', label: 'Comment', type: 'textarea', required: false, placeholder: 'Enter feedback comment'},
        ],
        api: 'api/feedback.php',
        table: 'feedback',
        idField: 'feedback_id',
      }
    };

    window.refreshTable = () => location.reload();

    document.getElementById('newFeedbackButton').addEventListener('click', () => {
      ModalController.open('feedback', 'create');
    });

    const feedbackTable = document.getElementById('feedbackTable');

    feedbackTable.addEventListener('click', (e) => {
      const button = e.target.closest('button[data-action]');
      if (!button) return;
      const row = button.closest('tr[data-id]');
      let data;
      try {
        data = JSON.parse(row.dataset.feedback);
      } catch (error) {
        console.error('Unable to read feedback record', error);
        ModalController.showNotification('Unable to read this feedback record.', 'error');
        return;
      }

      const action = button.dataset.action;
      if (action === 'view') {
        ModalController.openView('feedback', data);
      } else if (action === 'edit') {
        ModalController.open('feedback', 'edit', data);
      } else if (action === 'delete') {
        ModalController.openConfirm({
          title: 'Delete Feedback',
          message: 'Delete this feedback permanently?',
          detail: 'This action cannot be undone.',
          icon: 'danger',
          confirmText: 'Delete',
          confirmClass: 'btn-danger',
          onConfirm: () => deleteFeedback(data.feedback_id)
        });
      } else if (action === 'toggle-status') {
        toggleFeedbackStatus(data);
      } else if (action === 'toggle-testimonial') {
        toggleTestimonialApproval(data);
      }
    });

    async function deleteFeedback(id) {
      try {
        const response = await fetch('api/feedback.php', {
          method: 'DELETE',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({feedback_id: id})
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Feedback could not be deleted');
        ModalController.showNotification(result.message || 'Feedback deleted successfully', 'success');
        refreshTable();
      } catch (error) {
        ModalController.showNotification(error.message || 'Feedback could not be deleted', 'error');
      }
    }

    async function toggleFeedbackStatus(data) {
      try {
        const response = await fetch('api/feedback.php', {
          method: 'PUT',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({...data, is_resolved: data.is_resolved ? 0 : 1})
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Feedback status could not be updated');
        ModalController.showNotification(result.message || 'Feedback updated', 'success');
        refreshTable();
      } catch (error) {
        ModalController.showNotification(error.message || 'Feedback status could not be updated', 'error');
      }
    }

    async function toggleTestimonialApproval(data) {
      try {
        const response = await fetch('api/feedback.php', {
          method: 'PUT',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({
            action: 'testimonial_approval',
            feedback_id: data.feedback_id,
            testimonial_approved: data.testimonial_approved ? 0 : 1
          })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Testimonial approval could not be updated');
        ModalController.showNotification(result.message || 'Testimonial approval updated', 'success');
        refreshTable();
      } catch (error) {
        ModalController.showNotification(error.message || 'Testimonial approval could not be updated', 'error');
      }
    }
  </script>
<?php endif; ?>