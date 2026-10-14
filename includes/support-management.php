<div class="portal-content">
  <div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card" style="--kpi-color:var(--violet); --kpi-bg:rgba(124,58,237,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-headset"></i></div></div>
      <div class="kpi-value"><?php echo number_format($totalTickets); ?></div>
      <div class="kpi-label">Tickets</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--amber); --kpi-bg:rgba(245,158,11,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-folder-open"></i></div></div>
      <div class="kpi-value"><?php echo number_format($openTickets); ?></div>
      <div class="kpi-label">Open</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--green); --kpi-bg:rgba(16,185,129,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-check-circle"></i></div></div>
      <div class="kpi-value"><?php echo number_format($resolvedTickets); ?></div>
      <div class="kpi-label">Resolved</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--cyan); --kpi-bg:rgba(6,182,212,.1);">
      <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-bolt"></i></div></div>
      <div class="kpi-value"><?php echo $slaMet; ?>%</div>
      <div class="kpi-label">Resolved Rate</div>
    </div>
  </div>

  <div class="card glass-card animate-fade-in">
    <div class="card-header">
      <div>
        <div class="card-title"><?php echo htmlspecialchars($ticketViewTitle); ?></div>
        <div class="card-subtitle"><?php echo htmlspecialchars($ticketViewSubtitle); ?></div>
      </div>
      <?php if ($canManage): ?>
        <button class="btn btn-primary" type="button" id="newTicketButton"><i class="fas fa-plus"></i> New Ticket</button>
      <?php endif; ?>
    </div>

    <div class="table-container" style="border:none; background:transparent;">
      <table class="data-table" id="ticketsTable">
        <thead><tr><th>Ticket ID</th><th>Requestor</th><th>Issue</th><th>Priority</th><th>Status</th><th>Assigned To</th><th>Created</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($tickets as $ticket): ?>
            <?php
              $ticketJson = json_encode([
                  'ticket_id' => (int) $ticket['ticket_id'],
                  'user_id' => $ticket['user_id'] === null ? '' : (int) $ticket['user_id'],
                  'assigned_to' => $ticket['assigned_to'] === null ? '' : (int) $ticket['assigned_to'],
                  'subject' => $ticket['subject'],
                  'description' => $ticket['description'],
                  'category' => $ticket['category'],
                  'priority' => $ticket['priority'],
                  'status' => $ticket['status'],
              ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
              $priorityClass = $ticket['priority'] === 'urgent' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : ($ticket['priority'] === 'medium' ? 'info' : 'success'));
              $statusClass = in_array($ticket['status'], ['open', 'waiting_customer'], true) ? 'warning' : ($ticket['status'] === 'in_progress' ? 'info' : (in_array($ticket['status'], ['resolved', 'closed'], true) ? 'success' : 'info'));
            ?>
            <tr data-id="<?php echo (int) $ticket['ticket_id']; ?>" data-ticket='<?php echo htmlspecialchars($ticketJson, ENT_QUOTES, 'UTF-8'); ?>'>
              <td style="font-weight:600; color:var(--violet);"><?php echo htmlspecialchars('#SUP-' . $ticket['ticket_id']); ?></td>
              <td><?php echo htmlspecialchars($ticket['requestor_name'] ?? 'Unknown'); ?></td>
              <td style="max-width:300px; white-space:normal;"><strong><?php echo htmlspecialchars($ticket['subject']); ?></strong><br><?php echo htmlspecialchars($ticket['description']); ?></td>
              <td><span class="status-badge <?php echo $priorityClass; ?>"><span class="status-dot"></span><?php echo htmlspecialchars(ucfirst($ticket['priority'])); ?></span></td>
              <td><span class="status-badge <?php echo $statusClass; ?>"><span class="status-dot"></span><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['status']))); ?></span></td>
              <td><?php echo htmlspecialchars($ticket['assignee_name'] ?? 'Unassigned'); ?></td>
              <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($ticket['created_at']))); ?></td>
              <?php if ($canManage): ?>
                <td>
                  <div class="action-buttons" style="display:flex; gap:6px;">
                    <button class="header-btn edit-btn" type="button" title="Edit ticket" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn delete-btn" type="button" title="Delete ticket" data-action="delete" style="color:var(--red);"><i class="fas fa-trash"></i></button>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($tickets)): ?>
            <tr><td colspan="<?php echo $canManage ? 8 : 7; ?>" style="text-align:center; padding:24px; color:var(--ink-secondary);">No tickets found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:16px;">
      <span style="font-size:13px; color:var(--ink-secondary);">
        <?php if ($ticketViewTotal > 0): ?>
          Showing <?php echo $ticketViewOffset + 1; ?>-<?php echo min($ticketViewOffset + 25, $ticketViewTotal); ?> of <?php echo $ticketViewTotal; ?> tickets
        <?php else: ?>
          No tickets
        <?php endif; ?>
        (Page <?php echo $ticketViewPage; ?> of <?php echo $ticketViewTotalPages; ?>)
      </span>
      <nav aria-label="Ticket pagination" style="display:flex; gap:8px;">
        <?php if ($ticketViewPage > 1): ?>
          <a class="btn btn-secondary" href="?page=<?php echo $ticketViewPage - 1; ?>">Previous</a>
        <?php else: ?>
          <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Previous</span>
        <?php endif; ?>
        <?php if ($ticketViewPage < $ticketViewTotalPages): ?>
          <a class="btn btn-secondary" href="?page=<?php echo $ticketViewPage + 1; ?>">Next</a>
        <?php else: ?>
          <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Next</span>
        <?php endif; ?>
      </nav>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
<?php include_once __DIR__ . '/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>
<script>
  const ticketPageUrl = <?php echo json_encode($ticketOpenOnly ? 'open-tickets.php' : 'support.php'); ?>;
  window.MODAL_CONFIGS = window.MODAL_CONFIGS || {};
  window.MODAL_CONFIGS.supportTicket = {
    title: 'Support Ticket',
    fields: [
      {name:'user_id', label:'Requestor', type:'select', required:false, options:[
        {value:'', label:'Unknown / walk-in'},
        <?php foreach ($customers as $customer): ?>
        {value:<?php echo json_encode((string) $customer['user_id']); ?>, label:<?php echo json_encode($customer['full_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>},
        <?php endforeach; ?>
      ]},
      {name:'assigned_to', label:'Assigned Staff', type:'select', required:false, options:[
        {value:'', label:'Unassigned'},
        <?php foreach ($staffMembers as $staffMember): ?>
        {value:<?php echo json_encode((string) $staffMember['staff_id']); ?>, label:<?php echo json_encode($staffMember['full_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>},
        <?php endforeach; ?>
      ]},
      {name:'category', label:'Category', type:'select', required:true, options:[
        {value:'technical', label:'Technical'},
        {value:'billing', label:'Billing'},
        {value:'account', label:'Account'},
        {value:'general', label:'General'}
      ]},
      {name:'priority', label:'Priority', type:'select', required:true, options:[
        {value:'low', label:'Low'},
        {value:'medium', label:'Medium'},
        {value:'high', label:'High'},
        {value:'urgent', label:'Urgent'}
      ]},
      {name:'status', label:'Status', type:'select', required:true, options:[
        {value:'open', label:'Open'},
        {value:'in_progress', label:'In Progress'},
        {value:'waiting_customer', label:'Waiting Customer'},
        {value:'resolved', label:'Resolved'},
        {value:'closed', label:'Closed'}
      ]},
      {name:'subject', label:'Subject', type:'text', required:true, fullWidth:true, placeholder:'Enter ticket subject'},
      {name:'description', label:'Description', type:'textarea', required:true, fullWidth:true, placeholder:'Describe the issue'}
    ],
    api:'api/support.php',
    idField:'ticket_id'
  };

  document.getElementById('newTicketButton').addEventListener('click', () => {
    ModalController.open('supportTicket', 'create', {category:'general', priority:'medium', status:'open'});
  });

  document.getElementById('ticketsTable').addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const row = button.closest('tr[data-id]');
    const ticket = JSON.parse(row.dataset.ticket);

    if (button.dataset.action === 'edit') {
      ModalController.open('supportTicket', 'edit', ticket);
      return;
    }

    if (button.dataset.action === 'delete') {
      if (!(await CtlDialog.confirm({title: `Delete ticket #SUP-${ticket.ticket_id}?`, message: 'This permanently removes the ticket and its history.', tone: 'danger', icon: 'fa-trash', confirmText: 'Delete ticket'}))) return;
      await saveTicket('DELETE', {ticket_id:ticket.ticket_id});
    }
  });

  async function saveTicket(method, payload) {
    try {
      const response = await fetch('api/support.php', {
        method,
        credentials:'same-origin',
        headers:{'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
        body:JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Ticket could not be saved');
      ModalController.showNotification(result.message || 'Ticket updated', 'success');
      window.location.href = ticketPageUrl;
    } catch (error) {
      ModalController.showNotification(error.message || 'Ticket could not be saved', 'error');
    }
  }

  window.refreshTable = () => { window.location.href = ticketPageUrl; };
</script>
<?php endif; ?>
