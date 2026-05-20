function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function setText(selector, value) {
  const element = document.querySelector(selector);

  if (element) {
    element.textContent = value ?? "";
  }
}

function showNotice(selector, message, type = "success") {
  const element = document.querySelector(selector);

  if (!element) {
    return;
  }

  element.hidden = false;
  element.className = `form-message ${type}`;
  element.textContent = message;
}

function clearNotice(selector) {
  const element = document.querySelector(selector);

  if (!element) {
    return;
  }

  element.hidden = true;
  element.textContent = "";
  element.className = "form-message";
}

function formDataToObject(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function splitIds(value) {
  return String(value || "")
    .split(",")
    .map((item) => Number(item.trim()))
    .filter((item) => Number.isInteger(item) && item > 0);
}

function optionList(items, labelKey = "name") {
  return items
    .map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item[labelKey])}</option>`)
    .join("");
}

function formatDate(value) {
  if (!value) {
    return "";
  }

  return new Date(value.replace(" ", "T")).toLocaleString("bg-BG");
}
