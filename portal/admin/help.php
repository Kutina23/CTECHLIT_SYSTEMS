<?php
/**
 * Help center resource management.
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('dashboard.view');

$canManageHelp = hasPermission('settings.edit');
$helpTableExists = Database::tableExists('help_resources');
$helpStorageError = '';
if ($canManageHelp && !$helpTableExists) {
    try {
        Database::execute(
            "CREATE TABLE IF NOT EXISTS help_resources (
                resource_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                resource_type ENUM('documentation', 'tutorial', 'faq') NOT NULL,
                title VARCHAR(255) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                url VARCHAR(2048) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_published BOOLEAN NOT NULL DEFAULT TRUE,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
                INDEX idx_type_published_order (resource_type, is_published, sort_order),
                INDEX idx_updated (updated_at)
            ) ENGINE=InnoDB"
        );
        $helpTableExists = Database::tableExists('help_resources');
        if (!$helpTableExists) {
            throw new RuntimeException('Help content storage could not be initialized.');
        }
    } catch (Throwable $exception) {
        error_log('Help center storage initialization failed: ' . $exception->getMessage());
        $helpStorageError = 'Help content storage could not be initialized. Run database/migrations/005_help_center.sql or grant database CREATE permission.';
    }
}
$helpResources = $helpTableExists
    ? Database::fetchAll(
        "SELECT resource_id, resource_type, title, content, url, sort_order, is_published, updated_at
         FROM help_resources
         ORDER BY resource_type, sort_order, title"
    )
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help Center Management — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left:var(--sidebar-width);">
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>
    <div class="portal-content">
      <?php if ($helpStorageError !== ''): ?>
        <div class="card" role="alert" style="margin-bottom:20px; color:var(--red);">
          <?php echo htmlspecialchars($helpStorageError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php elseif (!$helpTableExists): ?>
        <div class="card" role="alert" style="margin-bottom:20px; color:var(--amber);">
          Help content storage is not installed. Run <code>database/migrations/005_help_center.sql</code>.
        </div>
      <?php endif; ?>

      <div class="card glass-card">
        <div class="card-header">
          <div>
            <div class="card-title">Help Center Content</div>
            <div class="card-subtitle">Manage public documentation, tutorials, and FAQs</div>
          </div>
          <?php if ($canManageHelp && $helpTableExists): ?>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
              <button class="btn btn-primary" type="button" data-create-type="documentation"><i class="fas fa-book"></i> Add Documentation</button>
              <button class="btn btn-primary" type="button" data-create-type="tutorial"><i class="fas fa-video"></i> Add Tutorial Video</button>
              <button class="btn btn-secondary" type="button" data-create-type="faq"><i class="fas fa-circle-question"></i> Add FAQ</button>
            </div>
          <?php endif; ?>
        </div>

        <div class="table-container" style="border:none; background:transparent;">
          <table class="data-table" id="helpResourcesTable">
            <thead><tr><th>Type</th><th>Title</th><th>Content</th><th>Link</th><th>Visibility</th><th>Order</th><?php if ($canManageHelp): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($helpResources as $resource): ?>
                <?php
                  $safeResourceUrl = $resource['url'] ?? '';
                  $allowedUploadPattern = $resource['resource_type'] === 'tutorial'
                      ? '#^uploads/help-center/[a-f0-9]{32}\.(mp4|webm)$#'
                      : ($resource['resource_type'] === 'documentation'
                          ? '#^uploads/help-center/[a-f0-9]{32}\.(pdf|docx|txt)$#'
                          : '#(?!)#');
                  $isUploadedFile = (bool) preg_match($allowedUploadPattern, $safeResourceUrl);
                  if (!$isUploadedFile) {
                      $urlParts = parse_url($safeResourceUrl);
                      if (!filter_var($safeResourceUrl, FILTER_VALIDATE_URL) || !in_array(strtolower($urlParts['scheme'] ?? ''), ['http', 'https'], true)) {
                          $safeResourceUrl = '';
                      }
                  }
                  $resourceJson = json_encode([
                      'resource_id' => (int) $resource['resource_id'],
                      'resource_type' => $resource['resource_type'],
                      'title' => $resource['title'],
                      'content' => $resource['content'],
                      'url' => $safeResourceUrl,
                      'sort_order' => (int) $resource['sort_order'],
                      'is_published' => (int) $resource['is_published'],
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                <tr data-resource='<?php echo htmlspecialchars($resourceJson, ENT_QUOTES, 'UTF-8'); ?>'>
                  <td><?php echo htmlspecialchars(ucfirst($resource['resource_type'])); ?></td>
                  <td><?php echo htmlspecialchars($resource['title']); ?></td>
                  <td style="max-width:360px; white-space:normal;"><?php echo htmlspecialchars(mb_strimwidth($resource['content'], 0, 180, '…')); ?></td>
                  <td><?php if ($safeResourceUrl !== ''): ?><a href="<?php echo htmlspecialchars($safeResourceUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $isUploadedFile ? 'Open uploaded file' : 'Open link'; ?></a><?php else: ?>—<?php endif; ?></td>
                  <td><span class="status-badge <?php echo $resource['is_published'] ? 'success' : 'warning'; ?>"><span class="status-dot"></span><?php echo $resource['is_published'] ? 'Published' : 'Draft'; ?></span></td>
                  <td><?php echo (int) $resource['sort_order']; ?></td>
                  <?php if ($canManageHelp): ?>
                    <td>
                      <div class="action-buttons" style="display:flex; gap:6px;">
                        <?php if ($isUploadedFile): ?>
                          <button class="header-btn" type="button" title="Preview uploaded file" data-action="preview"><i class="fas fa-eye"></i></button>
                        <?php endif; ?>
                        <button class="header-btn edit-btn" type="button" title="Edit content" data-action="edit"><i class="fas fa-edit"></i></button>
                        <button class="header-btn delete-btn" type="button" title="Delete content" data-action="delete" style="color:var(--red);"><i class="fas fa-trash"></i></button>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($helpResources)): ?>
                <tr><td colspan="<?php echo $canManageHelp ? 7 : 6; ?>" style="text-align:center; padding:24px; color:var(--ink-secondary);">No help content yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php if ($canManageHelp && $helpTableExists): ?>
      <div class="modal-overlay" id="helpDrawerOverlay" role="dialog" aria-modal="true" aria-labelledby="helpFormTitle">
        <form id="helpResourceForm" class="modal modal-xl" enctype="multipart/form-data">
          <input type="hidden" id="resourceId" name="resource_id">
          <div class="modal-header">
            <h3 class="modal-title" id="helpFormTitle">Create Help Content</h3>
            <button class="modal-close" type="button" id="closeHelpResource" aria-label="Close form"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body">
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px;">
              <div>
                <label class="form-label" for="resourceType">Content type</label>
                <select class="form-input" id="resourceType" name="resource_type" required>
                  <option value="documentation">Documentation</option>
                  <option value="tutorial">Tutorial</option>
                  <option value="faq">FAQ</option>
                </select>
              </div>
              <div>
                <label class="form-label" for="resourceTitle">Title</label>
                <input class="form-input" id="resourceTitle" name="title" maxlength="255" required>
              </div>
              <div>
                <label class="form-label" for="resourceUrl">External link (optional)</label>
                <input class="form-input" id="resourceUrl" name="url" type="text" inputmode="url" maxlength="2048" placeholder="https://">
              </div>
              <div>
                <label class="form-label" for="resourceFile">Upload file (optional)</label>
                <input class="form-input" id="resourceFile" name="file" type="file" accept=".pdf,.docx,.txt,.mp4,.webm">
                <small id="resourceFileHelp">Documentation: PDF, DOCX, or TXT. Tutorials: MP4 or WEBM, up to 25 MB. FAQ entries use text only.</small>
              </div>
              <div>
                <label class="form-label" for="resourceOrder">Display order</label>
                <input class="form-input" id="resourceOrder" name="sort_order" type="number" step="1" value="0">
              </div>
              <div style="display:flex; align-items:center; gap:8px;">
                <input id="resourcePublished" name="is_published" type="checkbox" value="1" checked>
                <label for="resourcePublished">Published publicly</label>
              </div>
              <div style="grid-column:1/-1;">
                <label class="form-label" for="resourceContent">Content / description</label>
                <textarea class="form-input" id="resourceContent" name="content" rows="6"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" type="button" id="cancelHelpResource">Cancel</button>
            <button class="btn btn-primary" type="submit" id="saveHelpResource">Save Content</button>
          </div>
        </form>
      </div>
      <div class="modal-overlay" id="helpPreviewOverlay" role="dialog" aria-modal="true" aria-labelledby="helpPreviewTitle">
        <section class="modal modal-xl">
          <div class="modal-header">
            <h3 class="modal-title" id="helpPreviewTitle">Preview Help Content</h3>
            <button class="modal-close" type="button" id="closeHelpPreview" aria-label="Close preview"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-body" id="helpPreviewBody"></div>
          <div class="modal-footer">
            <a class="btn btn-secondary" id="openHelpPreviewFile" href="#" target="_blank" rel="noopener noreferrer">Open in new tab</a>
            <button class="btn btn-primary" type="button" id="closeHelpPreviewButton">Close</button>
          </div>
        </section>
      </div>
    <?php endif; ?>
  </main>
  <?php if ($canManageHelp && $helpTableExists): ?>
  <script src="../../assets/js/modal-controller.js"></script>
  <script>
    const helpForm = document.getElementById('helpResourceForm');
    const helpDrawerOverlay = document.getElementById('helpDrawerOverlay');
    const helpPreviewOverlay = document.getElementById('helpPreviewOverlay');
    const helpTable = document.getElementById('helpResourcesTable');

    function closeHelpPreview() {
      helpPreviewOverlay.classList.add('closing');
      window.setTimeout(() => {
        helpPreviewOverlay.classList.remove('active', 'closing');
        document.getElementById('helpPreviewBody').replaceChildren();
        document.body.style.overflow = '';
      }, 250);
    }

    async function showHelpPreview(resource) {
      const extension = resource.url.split('.').pop().toLowerCase();
      const fileUrl = `../../${resource.url}`;
      const previewBody = document.getElementById('helpPreviewBody');
      document.getElementById('helpPreviewTitle').textContent = `Preview: ${resource.title}`;
      const openFileLink = document.getElementById('openHelpPreviewFile');
      openFileLink.href = fileUrl;
      openFileLink.textContent = extension === 'docx' ? 'Download DOCX' : 'Open in new tab';
      previewBody.replaceChildren();
      openFileLink.removeAttribute('download');

      if (['mp4', 'webm'].includes(extension)) {
        const video = document.createElement('video');
        video.controls = true;
        video.preload = 'metadata';
        video.style.cssText = 'display:block; width:100%; max-height:70vh; background:#111;';
        const source = document.createElement('source');
        source.src = fileUrl;
        source.type = extension === 'mp4' ? 'video/mp4' : 'video/webm';
        video.appendChild(source);
        previewBody.appendChild(video);
      } else if (['pdf', 'txt'].includes(extension)) {
        const frame = document.createElement('iframe');
        frame.src = fileUrl;
        frame.title = `Preview of ${resource.title}`;
        frame.style.cssText = 'display:block; width:100%; height:70vh; border:0; background:#fff;';
        previewBody.appendChild(frame);
      } else if (extension === 'docx') {
        const loading = document.createElement('p');
        loading.textContent = 'Loading document preview...';
        previewBody.appendChild(loading);
        openFileLink.setAttribute('download', '');
        try {
          const response = await fetch(`../api/help-resources?preview=${encodeURIComponent(resource.resource_id)}`, {
            credentials:'same-origin',
            headers:{'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}
          });
          const result = await response.json();
          if (!response.ok || !result.success) {
            throw new Error(result.message || 'The document preview could not be loaded.');
          }
          const documentText = document.createElement('pre');
          documentText.style.cssText = 'white-space:pre-wrap; overflow-wrap:anywhere; font-size:14px; line-height:1.6; margin:0;';
          documentText.textContent = result.data.content || 'This document does not contain extractable text. Download it to view its contents.';
          if (helpPreviewOverlay.classList.contains('active')) {
            previewBody.replaceChildren(documentText);
          }
        } catch (error) {
          const message = document.createElement('p');
          message.textContent = error.message || 'The document preview could not be loaded. Download the file to view it.';
          if (helpPreviewOverlay.classList.contains('active')) {
            previewBody.replaceChildren(message);
          }
          ModalController.showNotification(message.textContent, 'error');
        }
      } else {
        const message = document.createElement('p');
        message.textContent = 'This file type cannot be previewed in the browser.';
        previewBody.appendChild(message);
      }
      helpPreviewOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeHelpDrawer() {
      helpDrawerOverlay.classList.add('closing');
      window.setTimeout(() => {
        helpDrawerOverlay.classList.remove('active', 'closing');
        document.body.style.overflow = '';
      }, 250);
    }

    function showHelpDrawer() {
      helpDrawerOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      const firstInput = helpForm.querySelector('input:not([type="hidden"]):not([type="file"]), select, textarea');
      if (firstInput) window.setTimeout(() => firstInput.focus(), 100);
    }

    function resetHelpForm() {
      helpForm.reset();
      document.getElementById('resourceId').value = '';
      document.getElementById('resourcePublished').checked = true;
      document.getElementById('saveHelpResource').textContent = 'Save Content';
      document.getElementById('helpFormTitle').textContent = 'Create Help Content';
      updateHelpFormFields();
    }

    const resourceType = document.getElementById('resourceType');
    const resourceUrl = document.getElementById('resourceUrl');
    const resourceFile = document.getElementById('resourceFile');
    const resourceContent = document.getElementById('resourceContent');

    function updateHelpFormFields() {
      const type = resourceType.value;
      const isFaq = type === 'faq';
      resourceFile.disabled = isFaq;
      resourceFile.required = false;
      resourceFile.accept = isFaq ? '' : (type === 'tutorial' ? '.mp4,.webm' : '.pdf,.docx,.txt');
      document.getElementById('resourceFileHelp').textContent = isFaq
        ? 'FAQ entries use text only.'
        : type === 'tutorial'
          ? 'Tutorials: MP4 or WEBM video, up to 25 MB.'
          : 'Documentation: PDF, DOCX, or TXT, up to 25 MB.';
      resourceContent.required = isFaq || (!resourceUrl.value.trim() && !resourceFile.files.length);
    }

    resourceType.addEventListener('change', () => {
      if (resourceType.value === 'faq') resourceFile.value = '';
      updateHelpFormFields();
    });
    resourceUrl.addEventListener('input', updateHelpFormFields);
    resourceFile.addEventListener('change', updateHelpFormFields);

    function openHelpForm(type) {
      resetHelpForm();
      resourceType.value = type;
      updateHelpFormFields();
      showHelpDrawer();
      document.getElementById('helpFormTitle').textContent = `Create ${type === 'tutorial' ? 'Tutorial Video' : type === 'documentation' ? 'Documentation' : 'FAQ'}`;
    }

    document.querySelectorAll('[data-create-type]').forEach(button => {
      button.addEventListener('click', () => openHelpForm(button.dataset.createType));
    });
    document.getElementById('cancelHelpResource').addEventListener('click', () => {
      closeHelpDrawer();
      resetHelpForm();
    });
    document.getElementById('closeHelpResource').addEventListener('click', () => {
      closeHelpDrawer();
      resetHelpForm();
    });
    document.getElementById('closeHelpPreview').addEventListener('click', closeHelpPreview);
    document.getElementById('closeHelpPreviewButton').addEventListener('click', closeHelpPreview);
    helpPreviewOverlay.addEventListener('click', event => {
      if (event.target === helpPreviewOverlay) closeHelpPreview();
    });
    helpDrawerOverlay.addEventListener('click', event => {
      if (event.target === helpDrawerOverlay) {
        closeHelpDrawer();
        resetHelpForm();
      }
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && helpPreviewOverlay.classList.contains('active')) {
        closeHelpPreview();
        return;
      }
      if (event.key === 'Escape' && helpDrawerOverlay.classList.contains('active')) {
        closeHelpDrawer();
        resetHelpForm();
      }
    });

    helpTable.addEventListener('click', async (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      const row = button.closest('tr[data-resource]');
      let resource;
      try {
        resource = JSON.parse(row.dataset.resource);
      } catch (error) {
        console.error('Unable to read help resource', error);
        ModalController.showNotification('Unable to read this help resource.', 'error');
        return;
      }

      if (button.dataset.action === 'preview') {
        await showHelpPreview(resource);
        return;
      }

      if (button.dataset.action === 'edit') {
        document.getElementById('resourceId').value = resource.resource_id;
        document.getElementById('resourceType').value = resource.resource_type;
        document.getElementById('resourceTitle').value = resource.title;
        document.getElementById('resourceUrl').value = resource.url;
        document.getElementById('resourceOrder').value = resource.sort_order;
        document.getElementById('resourcePublished').checked = Boolean(resource.is_published);
        document.getElementById('resourceContent').value = resource.content;
        resourceFile.value = '';
        document.getElementById('saveHelpResource').textContent = 'Update Content';
        document.getElementById('helpFormTitle').textContent = `Edit ${resource.resource_type === 'tutorial' ? 'Tutorial Video' : resource.resource_type === 'documentation' ? 'Documentation' : 'FAQ'}`;
        updateHelpFormFields();
        showHelpDrawer();
        return;
      }

      if (button.dataset.action === 'delete' && window.confirm(`Delete "${resource.title}"?`)) {
        const payload = new FormData();
        payload.set('resource_id', resource.resource_id);
        await saveHelpResource('DELETE', payload);
      }
    });

    async function saveHelpResource(method, body) {
      try {
        body.set('_method', method);
        const response = await fetch('../api/help-resources', {
          method:'POST',
          credentials:'same-origin',
          headers:{'X-Requested-With':'XMLHttpRequest'},
          body
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Help content could not be saved');
        ModalController.showNotification(result.message || 'Help content saved', 'success');
        window.location.reload();
      } catch (error) {
        ModalController.showNotification(error.message || 'Help content could not be saved', 'error');
      }
    }

    helpForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = new FormData(helpForm);
      payload.set('is_published', document.getElementById('resourcePublished').checked ? '1' : '0');
      const resourceId = payload.get('resource_id');
      if (!resourceContent.value.trim() && !resourceUrl.value.trim() && !resourceFile.files.length) {
        resourceContent.setCustomValidity('Add a description, external link, or upload a file.');
        resourceContent.reportValidity();
        resourceContent.setCustomValidity('');
        return;
      }
      await saveHelpResource(resourceId ? 'PUT' : 'POST', payload);
    });
    updateHelpFormFields();

    const requestedType = new URLSearchParams(window.location.search).get('create');
    if (['documentation', 'tutorial', 'faq'].includes(requestedType)) {
      openHelpForm(requestedType);
    }
  </script>
  <?php endif; ?>
</body>
</html>
