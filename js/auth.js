document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("login-form");
  const logoutButton = document.getElementById("logout-button");
  const dashboardRoot = document.getElementById("dashboard-page");

  if (loginForm) {
    setupLoginForm(loginForm);
  }

  if (dashboardRoot) {
    setupDashboard();
  }

  if (logoutButton) {
    logoutButton.addEventListener("click", async () => {
      await logout();
    });
  }
});

function showMessage(element, message, type = "error") {
  if (!element) {
    return;
  }

  element.hidden = false;
  element.textContent = message;
  element.className = `form-message ${type}`;
}

function clearMessage(element) {
  if (!element) {
    return;
  }

  element.hidden = true;
  element.textContent = "";
  element.className = "form-message";
}

function getFriendlyErrorMessage(error) {
  if (!error) {
    return "Възникна неочаквана грешка.";
  }

  switch (error.status) {
    case 400:
    case 401:
    case 403:
    case 404:
    case 500:
      return error.message;
    default:
      return error.message || "Възникна проблем при връзката със сървъра.";
  }
}

function setupLoginForm(form) {
  const messageBox = document.getElementById("login-message");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage(messageBox);

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;

    submitButton.disabled = true;
    submitButton.textContent = "Влизане...";

    try {
      const response = await apiPost("login.php", {
        email: form.email.value.trim(),
        password: form.password.value
      });

      apiState.currentUser = response.user || null;
      showMessage(messageBox, "Успешен вход. Пренасочване...", "success");
      window.location.href = "dashboard.html";
    } catch (error) {
      showMessage(messageBox, getFriendlyErrorMessage(error));
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    }
  });
}

async function setupDashboard() {
  const authMessage = document.getElementById("dashboard-message");
  const userName = document.getElementById("current-user-name");
  const userRole = document.getElementById("current-user-role");

  try {
    const user = await requireAuth();

    if (!user) {
      return;
    }

    if (userName) {
      userName.textContent = user.name;
    }

    if (userRole) {
      userRole.textContent = user.role;
    }

    clearMessage(authMessage);
  } catch (error) {
    showMessage(authMessage, getFriendlyErrorMessage(error));
  }
}
