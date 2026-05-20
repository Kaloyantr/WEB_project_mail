document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("audit-table");
  const form = document.getElementById("audit-filter-form");

  if (!table || !form) {
    return;
  }

  async function loadLogs() {
    const params = new URLSearchParams(new FormData(form));
    const response = await apiGet(`audit_logs.php?${params.toString()}`);
    const items = response.items || [];
    table.innerHTML = items.map((log) => `
      <tr>
        <td>${escapeHtml(log.id)}</td>
        <td>${escapeHtml(log.user_name || "")} <span class="muted">${escapeHtml(log.user_email || "")}</span></td>
        <td><span class="badge">${escapeHtml(log.action)}</span></td>
        <td>${escapeHtml(log.details || "")}</td>
        <td>${escapeHtml(formatDate(log.created_at))}</td>
      </tr>
    `).join("");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    try {
      await loadLogs();
    } catch (error) {
      showNotice("#audit-notice", error.message, "error");
    }
  });

  requireAuth().then(loadLogs).catch((error) => showNotice("#audit-notice", error.message, "error"));
});
