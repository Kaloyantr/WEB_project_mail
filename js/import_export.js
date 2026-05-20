document.addEventListener("DOMContentLoaded", () => {
  const importForm = document.getElementById("import-form");
  const exportForm = document.getElementById("export-form");

  if (importForm) {
    importForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      clearNotice("#import-notice");
      document.getElementById("import-errors").innerHTML = "";

      try {
        const response = await fetch("../backend/api/import.php", {
          method: "POST",
          credentials: "include",
          body: new FormData(importForm)
        });
        const payload = await response.json();

        if (!response.ok) {
          if (payload.errors) {
            document.getElementById("import-errors").innerHTML = `
              <ul>${payload.errors.map((error) => `<li>Line ${escapeHtml(error.line)}: ${escapeHtml(error.message)}</li>`).join("")}</ul>
            `;
          }

          throw new Error(payload.message || "Import failed.");
        }

        showNotice("#import-notice", `Imported ${payload.imported} rows.`);
        importForm.reset();
      } catch (error) {
        showNotice("#import-notice", error.message, "error");
      }
    });
  }

  if (exportForm) {
    exportForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const type = new FormData(exportForm).get("type");
      window.location.href = `../backend/api/export.php?type=${encodeURIComponent(type)}`;
    });
  }

  requireAuth();
});
